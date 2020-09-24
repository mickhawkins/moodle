<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The definition of an item which can be exported.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace core\content\exportable_items;

use context;
use core\content\exportable_item;
use core\content\controllers\export_controller;
use stdClass;
use stored_file;

/**
 * An object used to represent content which can be served.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exportable_textarea extends exportable_item {

    /** @var string The destination path of the text content */
    protected $filepath;

    /** @var string The name of the table that ha the textarea within it */
    protected $tablename;

    /** @var int The id in the table */
    protected $id;

    /** @var string The name of the text field within the table */
    protected $textfield;

    /** @var null|string The name of the format field relating to the text field */
    protected $textformatfield;

    /** @var null|string The name of a file area for this content */
    protected $filearea;

    /** @var null|int The itemid for files in this text field */
    protected $itemid;

    /** @var null|int The itemid used for constructing pluginfiles */
    protected $pluginfileitemid;

    /**
     * Create a new exportable_item instance.
     *
     * If no filearea or itemid  is specified the no attempt will be made to export files.
     *
     * @param   context $context The context that this content belongs to
     * @param   string $uservisiblename The name displayed to the user when filtering
     * @param   string $tablename The name of the table that this textarea is in
     * @param   string $textfield The field within the tbale
     * @param   int $id The id in the database
     * @param   null|string $textformatfield The field in the database relating to the format field if one is present
     * @param   null|string $filearea The name of the file area for files associated with this text area
     * @param   null|int $itemid The itemid for files associated with this text area
     * @param   null|int $pluginfileitemid The itemid to use when constructing the pluginfile URL
     *          Some fileareas do not use any itemid in the URL and should therefore provide a `null` value here.
     */
    public function __construct(
        context $context,
        string $component,
        string $uservisiblename,
        string $filepath,
        string $tablename,
        string $textfield,
        int $id,
        ?string $textformatfield = null,
        ?string $filearea = null,
        ?int $itemid = null,
        ?int $pluginfileitemid = null
    ) {
        parent::__construct($context, $component, $uservisiblename);

        $this->filepath = $filepath;
        $this->tablename = $tablename;
        $this->textfield = $textfield;
        $this->textformatfield = $textformatfield;
        $this->id = $id;
        $this->filearea = $filearea;
        $this->itemid = $itemid;
        $this->pluginfileitemid = $pluginfileitemid;
    }

    /**
     * Add the content to the archive.
     *
     * @param   export_controller $controller The export controller associated with this export
     */
    public function add_to_archive(export_controller $controller): void {
        global $DB;

        // Fetch the field.
        $fields = [$this->textfield];
        if (!empty($this->textformatfield)) {
            $fields[] = $this->textformatfield;
        }
        $record = $DB->get_record($this->tablename, ['id' => $this->id], implode(', ', $fields));

        if (empty($record)) {
            return;
        }

        // Export all of the files for this text area.
        $content = $this->export_files($controller, $record->{$this->textfield});

        $content = $this->rewrite_other_pluginfile_urls($content);

        // Export the content to [contextpath]/[filepath]
        $controller->get_archive()->add_file_from_html_string(
            $this->get_context(),
            $this->filepath,
            $content
        );
    }

    /**
     * Rewrite any pluginfile URLs in the content.
     *
     * @param   string $content
     * @return  string
     */
    protected function rewrite_other_pluginfile_urls(string $content): string {
        // The pluginfile URLs should have been rewritten when the files were exported, but if any file was too large it
        // may not have been included.
        // In that situation use a tokenpluginfile URL.

        if (strpos($content, '@@PLUGINFILE@@/') !== false) {
            // Some files could not be rewritten.
            // Use a tokenurl pluginfile for those.
            $content = file_rewrite_pluginfile_urls(
                $content,
                'pluginfile.php',
                $this->context->id,
                $this->component,
                $this->filearea,
                $this->pluginfileitemid,
                [
                    'includetoken' => true,
                ]
            );
        }

        return $content;
    }

    /**
     * Export files releating to this text area.
     *
     * @param   string $content
     * @return  string
     */
    protected function export_files(export_controller $controller, string $content): string {
        if ($this->filearea === null) {
            return $content;
        }

        if ($this->itemid === null) {
            return $content;
        }

        // Export all of the files for this text area.
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, $this->component, $this->filearea, $this->itemid);

        $filelist = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filepathinzip = $this->get_filepath_for_file($file, false);
            $controller->get_archive()->add_file_from_stored_file(
                $this->get_context(),
                $filepathinzip,
                $file
            );

            if ($controller->get_archive()->is_file_in_archive($this->get_context(), $filepathinzip)) {
                // Attempt to rewrite any @@PLUGINFILE@@ URLs for this file in the content.
                $searchpath = "@@PLUGINFILE@@" . $file->get_filepath() . rawurlencode($file->get_filename());
                $content = str_replace($searchpath, $this->get_filepath_for_file($file, true), $content);
            }
        }

        return $content;
    }

    /**
     * Get the filepath for the specified stored_file.
     *
     * @param   stored_file $file
     * @param   bool $escape
     * @return  string
     */
    protected function get_filepath_for_file(stored_file $file, bool $escape): string {
        $path = [];

        $textareafilepath = dirname($this->filepath);
        if ($textareafilepath !== '.') {
            $path[] = $textareafilepath;
        }

        $filepath = sprintf(
            '%s%s%s',
            $file->get_filearea(),
            $file->get_filepath(),
            $file->get_filename()
        );

        if ($escape) {
            foreach (explode('/', $filepath) as $dirname) {
                $path[] = rawurlencode($dirname);
            }
            $filepath = implode('/', $path);
        }

        return ltrim(preg_replace('#/+#', '/', $filepath), '/');
    }

    /**
     * Serialize the exportable item.
     *
     * @return  array
     */
    public function __serialize(): array {
        return array_merge(
            [
                'filepath' => $this->filepath,
                'tablename' => $this->tablename,
                'fieldid' => $this->id,
                'textfield' => $this->textfield,
                'textformatfield' => $this->textformatfield,
                'filearea' => $this->filearea,
                'itemid' => $this->itemid,
                'pluginfileitemid' => $this->pluginfileitemid,
            ],
            parent::__serialize()
        );
    }

    /**
     * Unserialize the exportable item.
     *
     * @param   array $data
     */
    public function __unserialize(array $data): void {
        $this->filepath = $data['filepath'];
        $this->tablename = $data['tablename'];
        $this->id = $data['fieldid'];
        $this->textfield = $data['textfield'];
        $this->textformatfield = $data['textformatfield'];
        $this->filearea = $data['filearea'];
        $this->itemid = $data['itemid'];
        $this->pluginfileitemid = $data['pluginfileitemid'];

        parent::__unserialize($data);
    }
}

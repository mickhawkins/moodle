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

declare(strict_types=1);

/**
 * The definition of an item which can be exported.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\content\exportable_items;

use context;
use core\content\exportable_item;
use core\content\export\exported_item;
use core\content\export\helper;
use core\content\zipwriter;
use stdClass;
use stored_file;

/**
 * An object used to represent content which can be served.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exportable_stored_file extends exportable_item {

    /** @var string The destination path of the text content */
    protected $folderpath;

    /** @var stored_file The file to be exported */
    protected $file;

    /** @var int The itemid to use in the pluginfile URL */
    protected $pluginfileitemid;

    /**
     * Create a new exportable_item instance.
     *
     * If no filearea or itemid  is specified the no attempt will be made to export files.
     *
     * @param   context $context The context that this content belongs to
     * @param   string $component
     * @param   string $uservisiblename The name displayed to the user when filtering
     * @param   stored_file $file
     * @param   string $folderpath Any sub-directory to place files in
     */
    public function __construct(
        context $context,
        string $component,
        string $uservisiblename,
        stored_file $file,
        ?int $pluginfileitemid = null,
        string $folderpath = ''
    ) {
        parent::__construct($context, $component, $uservisiblename);

        $this->file = $file;
        $this->folderpath = $folderpath;
        $this->pluginfileitemid = $pluginfileitemid;
    }

    /**
     * Create a set of exportable_items from a set of area paramaters as passed to get_areas_files().
     *
     * If no filearea or itemid  is specified the no attempt will be made to export files.
     *
     * @param   context $context The context that this content belongs to
     * @param   string $component
     * @param   string $filearea
     * @param   null|int $itemid
     * @param   string $folderpath Any sub-directory to place files in
     * @return  array
     */
    public static function create_from_area_params(
        context $context,
        string $component,
        string $filearea,
        int $itemid,
        ?int $pluginfileitemid = null,
        string $folderpath = ''
    ): array {
        $fs = get_file_storage();
        if ($itemid === null) {
            $itemid = false;
        }

        $exportables = [];
        foreach ($fs->get_area_files($context->id, $component, $filearea, $itemid) as $file) {
            if ($file->is_directory()) {
                // Do not export directories.
                // If they contain file contents the directory structure will be created in the zip file.
                continue;
            }
            $filepath = $file->get_filepath() . $file->get_filename();
            $exportables[] = new self($context, $component, $filepath, $file, $pluginfileitemid, $folderpath);
        }

        return $exportables;
    }

    /**
     * Add the content to the archive.
     *
     * @param   zipwriter $archive
     */
    public function add_to_archive(zipwriter $archive): ?exported_item {
        // Export the content to [contextpath]/[filepath]
        $relativefilepath = $this->get_filepath_for_file();

        $archive->add_file_from_stored_file(
            $this->get_context(),
            $relativefilepath,
            $this->file
        );

        $exporteditem = new exported_item();
        $exporteditem->set_title($this->get_user_visible_name());

        if ($archive->is_file_in_archive($this->context, $relativefilepath)) {
            $exporteditem->add_file($relativefilepath, false);
        } else {
            $exporteditem->add_file($relativefilepath, false, helper::get_pluginfile_url_for_stored_file($this->file, $this->pluginfileitemid));
        }


        return $exporteditem;
    }

    /**
     * Get the filepath for the specified stored_file.
     *
     * @return  string
     */
    protected function get_filepath_for_file(): string {
        $folderpath = rtrim($this->folderpath);

        if (!empty($folderpath)) {
            $folderpath .= '/';
        }
        return sprintf(
            '%s%s%s%s',
            $folderpath,
            $this->file->get_filearea(),
            $this->file->get_filepath(),
            $this->file->get_filename()
        );
    }

    /**
     * Serialize the exportable item.
     *
     * @return  array
     */
    public function __serialize(): array {
        return array_merge(
            [
                'fileid' => $this->file->get_id(),
                'folderpath' => $this->folderpath,
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
        $fs = get_file_storage();

        $this->folderpath = $data['folderpath'];
        $this->file = $fs->get_file_by_id($data['fileid']);

        parent::__unserialize($data);
    }
}

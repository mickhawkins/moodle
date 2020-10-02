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
 * Helper.
 *
 * TODO Merge with the zipwriter.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\content\export;

use context;
use core\content\zipwriter;
use moodle_url;
use stored_file;

/**
 * Helper.
 *
 * TODO Merge with the zipwriter.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Add the content to the archive.
     *
     * @param   export_controller $controller The export controller associated with this export
     * @return  exported_item
     */
    public static function add_text_with_pluginfiles_to_archive(
        zipwriter $archive,
        context $context,
        string $filepath,
        string $content,
        string $component,
        string $filearea,
        int $fileitemid,
        ?int $pluginfileitemid
    ): exported_item {

        $subdir = dirname($filepath);
        // Export all of the files for this text area.
        $result = self::export_files_for_content($archive, $context, $subdir, $content, $component, $filearea, $fileitemid, $pluginfileitemid);

        // Export the content to [contextpath]/[filepath]
        $archive->add_file_from_html_string(
            $context,
            $filepath,
            $content
        );

        $result->add_file($filepath);

        return $result;
    }

    /**
     * Rewrite any pluginfile URLs in the content.
     *
     * @param   string $content
     * @return  string
     */
    protected static function rewrite_other_pluginfile_urls(context $context, string $content, string $component, string $filearea, ?int $pluginfileitemid): string {
        // The pluginfile URLs should have been rewritten when the files were exported, but if any file was too large it
        // may not have been included.
        // In that situation use a tokenpluginfile URL.

        if (strpos($content, '@@PLUGINFILE@@/') !== false) {
            // Some files could not be rewritten.
            // Use a tokenurl pluginfile for those.
            $content = file_rewrite_pluginfile_urls(
                $content,
                'pluginfile.php',
                $context->id,
                $component,
                $filearea,
                $pluginfileitemid,
                [
                    'includetoken' => true,
                ]
            );
        }

        return $content;
    }

    public static function get_pluginfile_url_for_stored_file(stored_file $file, ?int $pluginfileitemid): string {
        $link = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $pluginfileitemid,
            $file->get_filepath(),
            $file->get_filename(),
            true,
            true
        );

        return $link->out(false);
    }

    /**
     * Export files releating to this text area.
     *
     * @param   string $content
     * @return  exported_item
     */
    public static function export_files_for_content(
        zipwriter $archive,
        context $context,
        string $subdir,
        string $content,
        string $component,
        string $filearea,
        int $fileitemid,
        ?int $pluginfileitemid
    ): exported_item {
        // Export all of the files for this text area.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, $component, $filearea, $fileitemid);

        $filelist = [];

        $result = new exported_item();

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filepathinzip = self::get_filepath_for_file($file, $subdir, false);
            $archive->add_file_from_stored_file(
                $context,
                $filepathinzip,
                $file
            );

            if ($archive->is_file_in_archive($context, $filepathinzip)) {
                // Attempt to rewrite any @@PLUGINFILE@@ URLs for this file in the content.
                $searchpath = "@@PLUGINFILE@@" . $file->get_filepath() . rawurlencode($file->get_filename());
                if (strpos($content, $searchpath) !== false) {
                    $content = str_replace($searchpath, self::get_filepath_for_file($file, $subdir, true), $content);
                    $result->add_file($filepathinzip, true);
                } else {
                    $result->add_file($filepathinzip, false);
                }
            }

        }

        $content = self::rewrite_other_pluginfile_urls($context, $content, $component, $filearea, $pluginfileitemid);

        $result->set_content($content);

        return $result;
    }

    /**
     * Get the filepath for the specified stored_file.
     *
     * @param   stored_file $file
     * @param   bool $escape
     * @return  string
     */
    protected static function get_filepath_for_file(stored_file $file, string $parentdir, bool $escape): string {
        $path = [];

        $filepath = sprintf(
            '%s/%s/%s/%s',
            $parentdir,
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
}

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
 * Course content exporter implementation for mod_folder.
 *
 * @package mod_folder\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_folder\coursecontentexport;

use context;
use core_course\coursecontentexport\contentitem;
use core_course\coursecontentexport\exporter as basecontentexporter;
use core_course\coursecontentexport\zipwriter;
use stdClass;
use stored_file;

/**
 * Course content exporter implementation for mod_folder.
 *
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends basecontentexporter {

    /**
     * Get the content items for user.
     *
     * @param \context $context The context to find items in - usually an context_module
     * @param stdClass $user The user object.
     * @return array
     */
    public static function get_contentitems_for_user(\context $context, \stdClass $user): array {
        $contentitems = [];
        $fs = get_file_storage();

        $items = $fs->get_area_files(
            $context->id,
            'mod_folder',
            'content',
            0,
            'sortorder DESC, id ASC',
            true
        );

        foreach ($items as $item) {
            if ($item->get_filename() === '.') {
                continue;
            }
            $contentitems[] = new contentitem(
                $context,
                'mod_folder',
                $item->get_filename(),
                $item->is_directory() ? 'directory' : 'file',
                $item->get_id()
            );
        }

        return $contentitems;
    }

    /**
     * @param array $contentitems Array of content items.
     * @param stdClass $user The user object.
     * @param zipwriter $archive The zipstream object.
     * @return array
     */
    public static function export_contentitems_for_user(array $contentitems, stdClass $user, zipwriter $archive): array {
        $fs = get_file_storage();

        $pathnames = [];
        foreach ($contentitems as $item) {
            $file = $fs->get_file_by_id($item->get_id());
            $filepathnames = self::export_folder_content($item->get_context(), $file, $archive);
            $pathnames = array_merge($pathnames, $filepathnames);
        }

        return $pathnames;
    }

    /**
     * Export the folder content.
     *
     * @param context $context The context object.
     * @param stored_file $file The file to be exported.
     * @param zipwriter $archive The zipwriter object.
     * @return array
     */
    protected static function export_folder_content(context $context, stored_file $file, zipwriter $archive): array {
        $fs = get_file_storage();

        $pathnames = [];

        if ($file->get_filename() === '.') {
            return $pathnames;
        }

        if ($file->is_directory()) {
            $files = $fs->get_directory_files(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath()
            );

            foreach ($files as $dirfile) {
                $pathnames[] = self::export_folder_content($file, $archive);
            }
        } else {
            $pathnames[] = $archive->add_file_from_stored_file(
                $context,
                $file->get_filepath() . "/" . $file->get_filename(),
                $file,
                $file->get_contextid(),
                'mod_folder',
                'content',
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
        }

        return $pathnames;
    }
}

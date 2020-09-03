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
 * Manager.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\coursecontentexport;

use context_course;

/**
 * Wrapper class to loop through each activity export its course content.
 *
 * @package core_course\coursecontentexport
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Exports all course content.
     *
     * @param context_course $context
     * @param zipstream $archive
     */
    public static function export_all_content_for_course(context_course $context, zipwriter $archive) {
        global $USER;

        if (!self::can_export_content($context)) {
            return;
        }

        $user = $USER;
        $modinfo = get_fast_modinfo($context->instanceid, $user->id);

        $exportedfiles = [];
        foreach ($modinfo->instances as $modname => $modnames) {
           foreach ($modnames as $instanceid => $instance) {
                $classname = "\\mod_{$modname}\\coursecontentexport\\exporter";

                if (!class_exists($classname)) {
                    error_log("{$classname} does not exist");
                    continue;
                }

                $exportedfiles += $classname::export_contentitems_for_user(
                    $classname::get_approved_contentitems_for_user(\context_module::instance($instance->id), $user),
                    $user,
                    $archive
                );
            }
        }

        $archive->finish();
    }

    /**
     * Prepares an array of which modules within a can be exported.
     *
     * @param context_course $context
     * @return array The names of modules supporting course content export.
     */
    public static function get_supported_modules(context_course $context): array {
        global $CFG, $USER;

        $supportedmodules = [];

        if (self::can_export_content($context)) {
            $modinfo = get_fast_modinfo($context->instanceid, $USER->id);
            $modnames = array_keys($modinfo->instances);

            foreach ($modnames as $modname) {
                $classname = "\\mod_{$modname}\\coursecontentexport\\exporter";

                if (class_exists($classname)) {
                    $supportedmodules[] = $modname;
                }
            }
        }

        return $supportedmodules;
    }

    /**
     * Determine whether course content export is enabled, and if so, whether the current user has permission to do so.
     *
     * @param context_course $context The course context being checked.
     * @return bool
     */
    public static function can_export_content(context_course $context): bool {
        global $CFG;

        // Treat course download as disabled unless explicitly enabled.
        // (MDL-69561 will introduce the functionality to allow course download to be enabled at the course context level).
        return (!empty($CFG->enablecoursedownload) && $CFG->enablecoursedownload == COURSEDOWNLOADENABLED &&
                has_capability('moodle/course:downloadcontentexport', $context));
    }
}

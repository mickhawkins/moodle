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
 * Content API File Area definition.
 *
 * @package     core_files
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core;

use coding_exception;
use context;
use core\content\controllers\export\controller as export_controller;
use core\content\controllers\export\component_controller as component_export_controller;
use core\content\controllers\export\plugintype_controller as plugintype_export_controller;
use core\content\servable_item;
use core\content\zipwriter;
use core_component;
use moodle_url;
use stdClass;
use stored_file;

/**
 * The Content API allows all parts of Moodle to determine details about content within a component, or plugintype.
 *
 * This includes the description of files.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {

    /**
     * Check whether the specified user can export content for the specified context.
     *
     * @param   context $currentcontext
     * @param   stdClass $user
     * @return  bool
     */
    public static function can_export_content_for_context(context $currentcontext, stdClass $user): bool {
        return true;
    }

    /**
     * Export content for the specified context.
     *
     * @param   context $requestedcontext The context to be exported
     * @param   stdClass $user The user being exported
     * @param   zipwriter $archive The Zip Archive to export to
     */
    public static function export_content_for_context(context $requestedcontext, stdClass $user, zipwriter $archive): void {
        global $USER;

        if ($requestedcontext->contextlevel != CONTEXT_COURSE) {
            throw new coding_exception('The Content Export API currently only supports the export of courses');
        }

        if ($USER->id != $user->id) {
            throw new coding_exception('The Content Export API currently only supports export of the current user');
        }

        // Ensure that the zipwriter is aware of the requested context.
        $archive->set_root_context($requestedcontext);

        // Fetch all child contexts, indexed by path.
        $contextlist = [
            $requestedcontext->path => $requestedcontext,
        ];
        foreach ($requestedcontext->get_child_contexts() as $context) {
            $contextlist[$context->path] = $context;
        }

        // Reverse the order by key - this ensures that child contexts are processed before their parent.
        krsort($contextlist);

        // Get the course modinfo.
        $modinfo = get_fast_modinfo($requestedcontext->instanceid);

        // Filter out any context which cannot be exported.
        $contextlist = array_filter($contextlist, function($context) use ($user, $modinfo): bool {
            if ($context->contextlevel == CONTEXT_COURSE) {
                return self::can_export_content_for_context($context, $user);
            }

            if ($context->contextlevel == CONTEXT_MODULE) {
                if (empty($modinfo->cms[$context->instanceid])) {
                    // Unknown coursemodule in the course.
                    return false;
                }

                $cm = $modinfo->cms[$context->instanceid];

                if (!$cm->uservisible) {
                    // This user cannot view the activity.
                    return false;
                }

                // Defer to setting checks.
                return self::can_export_content_for_context($context, $user);
            }

            // Only course and activities are supported at this time.
            return false;
        });

        // Export each context.
        foreach ($contextlist as $context) {
            if ($context->contextlevel === CONTEXT_MODULE) {
                $cm = $modinfo->cms[$context->instanceid];
                $component = "mod_{$cm->modname}";

                // Check for a specific implementation for this module.
                // This will export any content specific to this activity.
                // For example, in mod_folder it will export the list of folders.
                $classname = component_export_controller::get_classname_for_component($component);
                $exportables = [];
                if (class_exists($classname) && is_a($classname, component_export_controller::class, true)) {
                    $controller = new $classname($component, $user, $context, $archive);
                    $exportables = $controller->get_exportable_items_for_user($context);
                }

                // Export any shared content for this activity.
                $activitycontroller = new \core\content\controllers\export\plugintypes\mod_core_controller($component, $user, $context, $archive);
                $activitycontroller->export_exportables($context, $exportables);
            } else if ($context->contextlevel === CONTEXT_COURSE) {
                // Export the course content.
                $controller = new \core_course\content\export_controller($context, $archive);
                $controller->export_items_for_user($contextlist, $user);
            }
        }

        $archive->finish();
    }
}

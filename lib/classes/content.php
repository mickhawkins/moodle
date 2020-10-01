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
        // Fetch all child contexts.
        $contextlist = array_merge(
            [$requestedcontext->id => $requestedcontext],
            $requestedcontext->get_child_contexts()
        );

        $contextlist = array_filter($contextlist, function($context) use ($user): bool {
            return self::can_export_content_for_context($context, $user);
        });

        $exportcontrollers = self::get_export_controller_instances($user, $requestedcontext, $archive);
        foreach ($exportcontrollers as $controller) {
            // Loop over all child contexts.
            foreach ($contextlist as $exportedcontext) {
                $controller->export_items_for_user($controller->get_exportable_items_for_user($exportedcontext));
            }
        }

        $archive->finish();
    }

    /**
     * Get the list of components, including all subsystems.
     *
     * @return  array
     */
    protected static function get_component_list(): array {
        $components = array_keys(array_reduce(\core_component::get_component_list(), function($carry, $item) {
            return array_merge($carry, $item);
        }, []));
        $components[] = 'core';

        return $components;
    }

    /**
     * Get a set of export controller instances for all components.
     *
     * @param   stdClass $user The user being exported
     * @param   context $context The context requested for export
     * @param   zipwriter $archive The instance of the zipwriter to be used for export
     * @return  export_controller[]
     */
    protected static function get_export_controller_instances(stdClass $user, context $context, zipwriter $archive): array {
        $instances = [];
        foreach (self::get_component_list() as $component) {
            $classname = component_export_controller::get_classname_for_component($component);
            if (class_exists($classname) && is_a($classname, component_export_controller::class, true)) {
                $instances[] = new $classname($component, $user, $context, $archive);
            }

            $classname = plugintype_export_controller::get_classname_for_component($component);
            if (class_exists($classname) && is_a($classname, plugintype_export_controller::class, true)) {
                $instances[] = new $classname($component, $user, $context, $archive);
            }

        }

        return $instances;
    }
}

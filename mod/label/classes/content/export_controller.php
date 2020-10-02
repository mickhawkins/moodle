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
 * Content export definition.
 *
 * @package     core
 * @copyright   2020 Simey Lameze <simey@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_label\content;

use context;
use core\content\exportable_items\exportable_stored_file;
use core\content\controllers\component_export_controller;

/**
 * A class which assists a component to export content.
 *
 * @copyright   2020 Simey Lameze <simey@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_controller extends component_export_controller {

    /**
     * Get the exportable items for the user in the specified context.
     *
     * Note: This context must be a child of the root context defined in the instance.
     *
     * @param   context $currentcontext The current context being exported
     * @return  exportable_item[]
     */
    public function get_exportable_items_for_user(context $currentcontext): array {
        if ($currentcontext->contextlevel != CONTEXT_MODULE) {
            return [];
        }

        $cm = self::get_cm_from_context($currentcontext);

        if ($this->component !== "mod_{$cm->modname}") {
            // Incorrect module.
            return [];
        }

        return exportable_stored_file::create_from_area_params($currentcontext, $this->component, 'content', 0);
    }
}

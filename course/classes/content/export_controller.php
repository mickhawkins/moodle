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
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\content;

use context;
use core\content\exportable_items\exportable_textarea;
use core\content\exportable_items\exportable_stored_file;
use core\content\controllers\export\component_controller;
use stdClass;

/**
 * A class which assists a component to export content.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_controller extends component_controller {

    /**
     * Get the exportable items for the user in the specified context.
     *
     * Note: This context must be a child of the root context defined in the instance.
     *
     * @param   context $currentcontext The current context being exported
     * @return  exportable_item[]
     */
    public function get_exportable_items_for_user(context $currentcontext): array {
        if ($currentcontext->contextlevel != CONTEXT_COURSE) {
            return [];
        }

        $course = get_course($currentcontext->instanceid);
        if (empty($course)) {
            return [];
        }

        $modinfo = get_fast_modinfo($course);

        $contentitems = [];

        // Display the summary.
        $contentitems[] = new exportable_textarea(
            $currentcontext,
            $this->get_component(),
            //get_string('summary', 'course'),
            'Summary',
            'index.html',
            'course',
            'summary',
            $course->id,
            'summaryformat',
            'summary',
            0,
            // The pluginfile URL does not include any itemid.
            null
        );

        // Add the overview files.
        $contentitems = array_merge(
            $contentitems,
            exportable_stored_file::create_from_area_params($currentcontext, $this->component, 'overviewfiles', 0)
        );

        // Loop over all sections.
        foreach ($modinfo->get_section_info_all() as $number => $section) {
            $contentitems[] = new exportable_textarea(
                $currentcontext,
                $this->get_component(),
                //get_string('section', 'course'),
                "Section {$number}",
                "section-{$number}.html",
                'course_sections',
                'summary',
                $section->id,
                'summaryformat',
                // Files are stored in section/sectionid.
                'section',
                $section->id,
                // The pluginfile URL uses section->id as an itemid.
                $section->id
            );
        }
        return $contentitems;
    }
}

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
 * This file contains the renderer for the admin security overview report
 *
 * @copyright 2019 Michael Hawkins <michaelh@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_security\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;

require_once($CFG->dirroot.'/report/security/locallib.php');

/**
 * The renderer for the admin security overview report
 *
 * @copyright 2019 Michael Hawkins <michaelh@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * todo: Write the docblock
     * @return type
     */
    public function prepare_sections() {
        $content = '';
        $data = ['reportsection' => []];

        $sections = report_security_get_section_mapping();

        //todo: Should probably move this into the report_security_get_section_mapping method so it doesn't need extra processing here
        //todo: can probably make the get_section_mapping method 2 methods, one for the section types, and one for the content of them, since otherwise
        //      this is unnecessarily fetching  more info than it needs. Can then make the section deets not return all
        foreach ($sections as $sectionid => $sectiondata) {
            $data['reportsection'][] = [
                'sectionid' => $sectionid,
                'title' => get_string("section:{$sectiondata['name']}", 'report_security'),
                'description' => get_string("section:{$sectiondata['name']}description", 'report_security'),
            ];
        }

        $template = 'report_security/security_report_section';
        $content .= $this->render_from_template($template, $data);

        return $content;
    }
}

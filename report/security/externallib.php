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
 * Library functions for security report
 *
 * @package    report_security
 * @copyright  2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class report_security_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function prepare_report_section_parameters() {
        return new external_function_parameters(
            [
                'section' => new external_single_structure(
                    [
                        'sectionid' => new external_value(PARAM_INT, 'ID of report section to prepare'),
                    ]
                )
            ]
        );
    }

    /**
     * Returns description of the return parameters
     * @return external_multiple_structure
     */
    public static function prepare_report_section_returns() {
        return new external_single_structure(
            [
                'position'         => new external_value(PARAM_NUMBER, 'Position of the issue within the section'),
                'issue'         => new external_value(PARAM_TEXT, 'Name of the issue being checked'),
                'linkparam'     => new external_value(PARAM_ALPHANUMEXT, 'Issue name for the further info link'),
                'level'         => new external_value(PARAM_ALPHANUM, 'Risk level of the issue'),
                'description'   => new external_value(PARAM_TEXT, 'Decsription of the result'),
            ]
        );
    }

    /**
     * Prepares all data for one section of the security report.
     *
     * @param array $section Contains the section of the report to be prepared.
     * @return array Information to populate report rows for a section.
     */
    public static function prepare_report_section($section) {
        $sectionid = $section['sectionid'];

        //TODO: Do stuff for the section.

        $result = [
            'position'      =>  1,
            'issue'         => 'Test issue, section ID ' . $sectionid,
            'linkparam'     => 'report_security_check_passwordpolicy',
            'level'         => 3,
            'description'   => 'This is the description of the test issue.',
        ];

        //TODO - the thing

        return $result;
    }
}

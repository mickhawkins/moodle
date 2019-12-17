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
 * @copyright  2019 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/externallib.php");
require_once("{$CFG->dirroot}/report/security/locallib.php");

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
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'ID of the report section'),
            'issues' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'issue'         => new external_value(PARAM_TEXT, 'Name of the check being performed'),
                        'linkparam'     => new external_value(PARAM_ALPHANUMEXT, 'Name for the further info link'),
                        'status'         => new external_value(PARAM_ALPHANUM, 'Risk status of the check'),
                        'description'   => new external_value(PARAM_TEXT, 'Decsription of the result'),
                    ]
                ), 'Checks which may require action in this section', VALUE_OPTIONAL
            ),
            'passedcount' => new external_value(PARAM_INT, 'The number of checks that passed in this section'),
            'passed' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'issue'         => new external_value(PARAM_TEXT, 'Name of the check being performed'),
                        'linkparam'     => new external_value(PARAM_ALPHANUMEXT, 'Name for the further info link'),
                        'status'         => new external_value(PARAM_ALPHANUM, 'Risk status of the check'),
                        'description'   => new external_value(PARAM_TEXT, 'Decsription of the result'),
                    ]
                ), 'Checks which have passed in this section', VALUE_OPTIONAL
            )
        ]);
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

        //todo: is name needed by the mapping method?
//todo: cehck these REPORT_SECURITY_BLAH statuses are available, may need to require locallib.php

        $sectioninfo = report_security_get_section_mapping($sectionid);
        $results = [
            REPORT_SECURITY_OK => [],
            REPORT_SECURITY_INFO => [],
            REPORT_SECURITY_SERIOUS => [],
            REPORT_SECURITY_CRITICAL => [],
        ];

        foreach ($sectioninfo['checks'] as $check) {
            $checkresult = call_user_func($check);

            $results[$checkresult->status][] = [
                'issue' => $checkresult->name,
                'linkparam' => $checkresult->issue,
                'status' => $checkresult->status,
                'description' => $checkresult->info,
            ];
        }

        $details = [
            'sectionid' => $sectionid,
            // Append in this order so results so most critical are listed first.
            'issues' => $results[REPORT_SECURITY_CRITICAL] + $results[REPORT_SECURITY_SERIOUS] + $results[REPORT_SECURITY_INFO],
            'passedcount' => count($results[REPORT_SECURITY_OK]),
            'passed' => $results[REPORT_SECURITY_OK],
        ];

        return $details;
    }
}

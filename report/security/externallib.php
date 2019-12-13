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
require_once("{$CFG->libdir}/report/security/locallib.php");

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
            'issues'      => new external_multiple_structure(
                new external_single_structure(
                    [
                        'position'      => new external_value(PARAM_NUMBER, 'Position of the issue within the section'),
                        'issue'         => new external_value(PARAM_TEXT, 'Name of the issue being checked'),
                        'linkparam'     => new external_value(PARAM_ALPHANUMEXT, 'Issue name for the further info link'),
                        'level'         => new external_value(PARAM_ALPHANUM, 'Risk level of the issue'),
                        'description'   => new external_value(PARAM_TEXT, 'Decsription of the result'),
                    ]
                )
            ),
            'passed'      => new external_multiple_structure(
                new external_single_structure(
                    [
                        'position'      => new external_value(PARAM_NUMBER, 'Position of the issue within the section'),
                        'issue'         => new external_value(PARAM_TEXT, 'Name of the issue being checked'),
                        'linkparam'     => new external_value(PARAM_ALPHANUMEXT, 'Issue name for the further info link'),
                        'level'         => new external_value(PARAM_ALPHANUM, 'Risk level of the issue'),
                        'description'   => new external_value(PARAM_TEXT, 'Decsription of the result'),
                    ]
                )
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
                'position' => 'TODO - remove?',
                'issue' => $checkresult->name,
                'linkparam' => $checkresult->issue,
                'level' => $checkresult->status,
                'description' => $checkresult->details,
            ];
            
            //TODO: replace link with linkparam probably, that is available in $checkresult
        }

        $details = [
            'sectionid' =>$sectionid,
            // Append so results are ordered by most critical first.
            'issues' => $results[REPORT_SECURITY_CRITICAL] + $results[REPORT_SECURITY_SERIOUS] + $results[REPORT_SECURITY_INFO],
            'passed' => $results[REPORT_SECURITY_OK],
        ];


        return $details;

//        $result = [
//            'sectionid' => $sectionid,
//            'issues' => [
//                [
//                    'position'    =>  1,
//                    'issue'       => 'Test issue 1, section ID ' . $sectionid,
//                    'linkparam'   => 'report_security_check_passwordpolicy',
//                    'level'       => 3,
//                    'description' => 'This is the description of the test issue.',
//                ],
//                [
//                    'position'    =>  2,
//                    'issue'       => 'Test issue 2, section ID ' . $sectionid,
//                    'linkparam'   => 'report_security_check_passwordpolicy',
//                    'level'       => 2,
//                    'description' => 'This is the other description.',
//                ],
//            ],
//            'passed' => [
//                [
//                    'position'    =>  1,
//                    'issue'       => 'Hello, section ID ' . $sectionid,
//                    'linkparam'   => 'report_security_check_passwordpolicy',
//                    'level'       => 1,
//                    'description' => 'This is the description of the test issue.',
//                ],
//                [
//                    'position'    =>  2,
//                    'issue'       => 'This is fine, section ID ' . $sectionid,
//                    'linkparam'   => 'report_security_check_unsecuredataroot',
//                    'level'       => 1,
//                    'description' => 'Dataroot directory must not be accessible via the web.',
//                ],
//            ],
//        ];

    }
}

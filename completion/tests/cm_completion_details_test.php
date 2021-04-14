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
 * Contains unit tests for core_completion/cm_completion_details.
 *
 * @package   core_completion
 * @copyright 2021 Jun Pataleta <jun@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types = 1);

namespace core_completion;

use advanced_testcase;
use cm_info;
use completion_info;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for unit testing core_completion/cm_completion_details.
 *
 * @package   core_completion
 * @copyright 2021 Jun Pataleta <jun@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_completion_details_test extends advanced_testcase {

    /** @var completion_info A completion object. */
    protected $completioninfo = null;

    /**
     * Fetches a mocked cm_completion_details instance.
     *
     * @param int|null $completion The completion tracking mode for the module.
     * @param array $completionoptions Completion options (e.g. completionview, completionusegrade, etc.)
     * @param string $modname The modname to set in the cm if a specific one is required.
     * @return cm_completion_details
     */
    protected function setup_data(?int $completion, array $completionoptions = [],
            $modname = 'somenonexistentmod'): cm_completion_details {

        if (is_null($completion)) {
            $completion = COMPLETION_TRACKING_AUTOMATIC;
        }

        // Mock a completion_info instance so we can simply mock the returns of completion_info::get_data() later.
        $this->completioninfo = $this->getMockBuilder(completion_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock return of completion_info's is_enabled() method to match the expected completion tracking for the module.
        $this->completioninfo->expects($this->any())
            ->method('is_enabled')
            ->willReturn($completion);

        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        // Mock the return of the magic getter method when fetching the cm_info object's customdata and instance values.
        $mockcminfo->expects($this->any())
            ->method('__get')
            ->will($this->returnValueMap([
                ['completion', $completion],
                ['instance', 1],
                ['modname', $modname],
                ['completionview', $completionoptions['completionview'] ?? COMPLETION_VIEW_NOT_REQUIRED],
                ['completiongradeitemnumber', $completionoptions['completionusegrade'] ?? null],
                ['customcompletion', $completionoptions['customcompletion'] ?? null]
            ]));

        return new cm_completion_details($this->completioninfo, $mockcminfo, 2);
    }

    /**
     * Provides data for test_has_completion().
     *
     * @return array[]
     */
    public function has_completion_provider(): array {
        return [
            'Automatic' => [
                COMPLETION_TRACKING_AUTOMATIC, true
            ],
            'Manual' => [
                COMPLETION_TRACKING_MANUAL, true
            ],
            'None' => [
                COMPLETION_TRACKING_NONE, false
            ],
        ];
    }

    /**
     * Test for has_completion().
     *
     * @dataProvider has_completion_provider
     * @param int $completion The completion tracking mode.
     * @param bool $expectedresult Expected result.
     */
    public function test_has_completion(int $completion, bool $expectedresult) {
        $cmcompletion = $this->setup_data($completion);

        $this->assertEquals($expectedresult, $cmcompletion->has_completion());
    }

    /**
     * Provides data for test_is_automatic().
     *
     * @return array[]
     */
    public function is_automatic_provider(): array {
        return [
            'Automatic' => [
                COMPLETION_TRACKING_AUTOMATIC, true
            ],
            'Manual' => [
                COMPLETION_TRACKING_MANUAL, false
            ],
            'None' => [
                COMPLETION_TRACKING_NONE, false
            ],
        ];
    }

    /**
     * Test for is_available().
     *
     * @dataProvider is_automatic_provider
     * @param int $completion The completion tracking mode.
     * @param bool $expectedresult Expected result.
     */
    public function test_is_automatic(int $completion, bool $expectedresult) {
        $cmcompletion = $this->setup_data($completion);

        $this->assertEquals($expectedresult, $cmcompletion->is_automatic());
    }

    /**
     * Data provider for test_get_overall_completion().
     * @return array[]
     */
    public function overall_completion_provider(): array {
        return [
            'Complete' => [COMPLETION_COMPLETE],
            'Incomplete' => [COMPLETION_INCOMPLETE],
        ];
    }

    /**
     * Test for get_overall_completion().
     *
     * @dataProvider overall_completion_provider
     * @param int $state
     */
    public function test_get_overall_completion(int $state) {
        $cmcompletion = $this->setup_data(COMPLETION_TRACKING_AUTOMATIC);

        $this->completioninfo->expects($this->once())
            ->method('get_data')
            ->willReturn((object)['completionstate' => $state]);

        $this->assertEquals($state, $cmcompletion->get_overall_completion());
    }

    /**
     * Data provider for test_get_details().
     * @return array[]
     */
    public function get_details_provider() {
        return [
            'No completion tracking' => [
                COMPLETION_TRACKING_NONE, null, null, []
            ],
            'Manual completion tracking' => [
                COMPLETION_TRACKING_MANUAL, null, null, []
            ],
            'Automatic, require view, not viewed' => [
                COMPLETION_TRACKING_AUTOMATIC, COMPLETION_INCOMPLETE, null, [
                    'completionview' => (object)[
                        'status' => COMPLETION_INCOMPLETE,
                        'description' => get_string('detail_desc:view', 'completion'),
                    ]
                ]
            ],
            'Automatic, require view, viewed' => [
                COMPLETION_TRACKING_AUTOMATIC, COMPLETION_COMPLETE, null, [
                    'completionview' => (object)[
                        'status' => COMPLETION_COMPLETE,
                        'description' => get_string('detail_desc:view', 'completion'),
                    ]
                ]
            ],
            'Automatic, require grade, incomplete' => [
                COMPLETION_TRACKING_AUTOMATIC, null, COMPLETION_INCOMPLETE, [
                    'completionusegrade' => (object)[
                        'status' => COMPLETION_INCOMPLETE,
                        'description' => get_string('detail_desc:receivegrade', 'completion'),
                    ]
                ]
            ],
            'Automatic, require grade, complete' => [
                COMPLETION_TRACKING_AUTOMATIC, null, COMPLETION_COMPLETE, [
                    'completionusegrade' => (object)[
                        'status' => COMPLETION_COMPLETE,
                        'description' => get_string('detail_desc:receivegrade', 'completion'),
                    ]
                ]
            ],
            'Automatic, require view (complete) and grade (incomplete)' => [
                COMPLETION_TRACKING_AUTOMATIC, COMPLETION_COMPLETE, COMPLETION_INCOMPLETE, [
                    'completionview' => (object)[
                        'status' => COMPLETION_COMPLETE,
                        'description' => get_string('detail_desc:view', 'completion'),
                    ],
                    'completionusegrade' => (object)[
                        'status' => COMPLETION_INCOMPLETE,
                        'description' => get_string('detail_desc:receivegrade', 'completion'),
                    ]
                ]
            ],
            'Automatic, require view (incomplete) and grade (complete)' => [
                COMPLETION_TRACKING_AUTOMATIC, COMPLETION_INCOMPLETE, COMPLETION_COMPLETE, [
                    'completionview' => (object)[
                        'status' => COMPLETION_INCOMPLETE,
                        'description' => get_string('detail_desc:view', 'completion'),
                    ],
                    'completionusegrade' => (object)[
                        'status' => COMPLETION_COMPLETE,
                        'description' => get_string('detail_desc:receivegrade', 'completion'),
                    ]
                ]
            ],
        ];
    }

    /**
     * Test for \core_completion\cm_completion_details::get_details().
     *
     * @dataProvider get_details_provider
     * @param int $completion The completion tracking mode.
     * @param int|null $completionview Completion status of the "view" completion condition.
     * @param int|null $completiongrade Completion status of the "must receive grade" completion condition.
     * @param array $expecteddetails Expected completion details returned by get_details().
     */
    public function test_get_details(int $completion, ?int $completionview, ?int $completiongrade, array $expecteddetails) {
        $options = [];
        $getdatareturn = (object)[
            'viewed' => $completionview,
            'completiongrade' => $completiongrade,
        ];

        if (!is_null($completionview)) {
            $options['completionview'] = true;
        }
        if (!is_null($completiongrade)) {
            $options['completionusegrade'] = true;
        }

        $cmcompletion = $this->setup_data($completion, $options);

        $this->completioninfo->expects($this->any())
            ->method('get_data')
            ->willReturn($getdatareturn);

        $this->assertEquals($expecteddetails, $cmcompletion->get_details());
    }

    /**
     * Data provider for test_get_details().
     * @return array[]
     */
    public function get_details_custom_order_provider() {
        return [
        /*    'Custom and view/grade standard conditions, view first and grade last' => [
                true,
                true,
                ['completionview', 'completionsubmit', 'completiongrade'],
                ['completionview', 'completionsubmit', 'completiongrade'],
            ],*/
            'Custom and view/grade standard conditions, view first and grade not last' => [
                true,
                true,
                ['completionview', 'completiongrade', 'completionsubmit'],
                ['completionview', 'completiongrade', 'completionsubmit'],
            ],/*
            'Custom and grade standard conditions only, no view condition' => [
                false,
                true,
                ['completionview', 'completionsubmit', 'completiongrade'],
                ['completionsubmit', 'completiongrade'],
            ],
            'Custom and view standard conditions only, no grade condition' => [
                false,
                true,
                ['completionview', 'completionsubmit', 'completiongrade'],
                ['completionview','completionsubmit'],
            ],
            'Incomplete sort order provided' => [
                true,
                true,
                ['completionview', 'completionsubmit'],
                [],
            ],*/
        ];
    }

    /**
     * Test custom sort order is functioning in \core_completion\cm_completion_details::get_details().
     *
     * @dataProvider get_details_custom_order_provider
     * //TODO
     * @param bool $completionview Completion status of the "view" completion condition.
     * @param bool $completiongrade Completion status of the "must receive grade" completion condition.
     * @param array $sortorder Custom sort order configured by the module.
     * @param array $expectedorder The expected keys output, in order.
     */
    public function test_get_details_custom_order(bool $completionview, bool $completiongrade, array $sortorder, array $expectedorder) {
        $options['customcompletion'] = [
            'somecustomcondition' => true,
        ];

        if ($completionview) {
            $options['completionview'] = true;
        }
        if ($completiongrade) {
            $options['completionusegrade'] = true;
        }

        $cmcompletion = $this->setup_data(COMPLETION_TRACKING_AUTOMATIC, $options, 'assign');

        $getdatareturn = (object)[
            'viewed' => $completionview ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE,
            'completiongrade' => $completiongrade ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE,
            'customcompletion' => [
                'completionsubmit' => COMPLETION_COMPLETE,
            ],
        ];

        // Expect a coding exception if we don't provide sort order for all conditions.
        if (empty($expectedorder)) {
            $this->expectException(coding_exception::class);
            $exceptiontext = "\mod_assign\completion\custom_completion::get_sort_order() is missing one" .
            " or more completion conditions. All custom and standard conditions that apply to this activity must be listed.";
            $this->expectExceptionMessage($exceptiontext);
        }

        $mockcompletion = $this->getMockBuilder(\mod_assign\completion\custom_completion::class)
            ->onlyMethods(['get_sort_order'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockcompletion->expects($this->once())
            ->method('get_sort_order')
            ->willReturn($sortorder);

        $this->completioninfo->expects($this->any())
            ->method('get_data')
            ->willReturn($getdatareturn);

        $fetcheddetails = $cmcompletion->get_details();

        // Check the expected number of items are returned, and sorted in the correct order.
        if (!empty($expectedorder)) {
            $this->assertCount(count($expectedorder), $fetcheddetails);
            $this->assertTrue((array_keys($fetcheddetails) === $expectedorder));
        }
    }
}

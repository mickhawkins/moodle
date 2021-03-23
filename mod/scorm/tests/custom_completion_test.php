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

declare(strict_types = 1);

namespace mod_scorm;

use advanced_testcase;
use cm_info;
use coding_exception;
use mod_scorm\completion\custom_completion;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for unit testing mod_scorm/custom_completion.
 *
 * @package   mod_scorm
 * @copyright 2021 Michael Hawkins <michaelh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion_test extends advanced_testcase {

    /**
     * Data provider for get_state().
     *
     * @return array[]
     */
    public function get_state_provider(): array {

        // Prepare various reusable user scorm track data used to mock various completion states/requirements.
        $completionincomplete = (object) [
            'id' => 1,
            'scoid' => 1,
            'element' => 'cmi.completion_status',
            'value' => 'incomplete',
        ];

        $completionpassed = (object) [
            'id' => 1,
            'scoid' => 1,
            'element' => 'cmi.completion_status',
            'value' => 'passed',
        ];

        $completioncompleted = (object) [
            'id' => 1,
            'scoid' => 2,
            'element' => 'cmi.success_status',
            'value' => 'completed',
        ];

        $completionscorefail = (object) [
            'id' => 1,
            'scoid' => 1,
            'element' => 'cmi.score.raw',
            'value' => '20',
        ];

        $completionscorepass = (object) [
            'id' => 1,
            'scoid' => 1,
            'element' => 'cmi.score.raw',
            'value' => '100',
        ];

        return [
            'Undefined completion requirement' => [
                'somenonexistentrule', COMPLETION_ENABLED, [$completionincomplete], null, coding_exception::class
            ],
            'Completion status requirement not available' => [
                'completionstatusrequired', COMPLETION_DISABLED, [$completionincomplete], null, moodle_exception::class
            ],
            'Completion status Passed required, user has no completion status recorded' => [
                'completionstatusrequired', 2, [], COMPLETION_INCOMPLETE, null
            ],
            'Completion status Passed required, user has not passed' => [
                'completionstatusrequired', 2, [$completionincomplete], COMPLETION_INCOMPLETE, null
            ],
            'Completion status Passed required, user has passed' => [
                'completionstatusrequired', 2, [$completionpassed], COMPLETION_COMPLETE, null
            ],
            'Completion status Completed required, user has not completed' => [
                'completionstatusrequired', 4, [$completionincomplete], COMPLETION_INCOMPLETE, null
            ],
            'Completion status Completed required, user has completed' => [
                'completionstatusrequired', 4, [$completioncompleted], COMPLETION_COMPLETE, null
            ],
            'Completion status Passed and Completed required, user has only completed' => [
                'completionstatusrequired', 6, [$completioncompleted], COMPLETION_INCOMPLETE, null
            ],
            'Completion status Passed and Completed required, user has completed and passed' => [
                'completionstatusrequired', 6, [$completionpassed, $completioncompleted], COMPLETION_COMPLETE, null
            ],
            'Completion score required, user has no score' => [
                'completionscorerequired', 80, [], COMPLETION_INCOMPLETE, null
            ],
            'Completion score required, user score does not meet requirement' => [
                'completionscorerequired', 80, [$completionscorefail], COMPLETION_INCOMPLETE, null
            ],
            'Completion score required, user score meets requirement' => [
                'completionscorerequired', 80, [$completionscorepass], COMPLETION_COMPLETE, null
            ],
            'Completion of all scos required, user has not completed' => [
                'completionstatusallscos', 1, [$completionincomplete, $completioncompleted], COMPLETION_INCOMPLETE, null
            ],
            'Completion of all scos required, user has completed' => [
                'completionstatusallscos', 1, [$completionpassed, $completioncompleted], COMPLETION_COMPLETE, null
            ],
        ];
    }

    /**
     * Test for get_state().
     *
     * @dataProvider get_state_provider
     * @param string $rule The custom completion condition.
     * @param int $rulevalue The custom completion rule value.
     * @param array $uservalue The relevant record database mock data recorded against the user for the rule.
     * @param int|null $status Expected completion status for the rule.
     * @param string|null $exception Expected exception.
     */
    public function test_get_state(string $rule, int $rulevalue, array $uservalue, ?int $status, ?string $exception) {
        global $DB;

        if (!is_null($exception)) {
            $this->expectException($exception);
        }

        // Custom completion rule data for cm_info::customdata.
        $customdataval = [
            'customcompletionrules' => [
                $rule => $rulevalue
            ]
        ];

        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        // Mock the return of the magic getter method when fetching the cm_info object's
        // customdata and instance values.
       $mockcminfo->expects($this->any())
            ->method('__get')
            ->will($this->returnValueMap([
                ['customdata', $customdataval],
                ['instance', 1],
            ]));

        // Mock the DB call fetching user's SCORM track data.
        $DB = $this->createMock(get_class($DB));
        $DB->expects($this->atMost(1))
            ->method('get_records_sql')
            ->willReturn($uservalue);

        // For completed all scos tests, mock the DB call that fetches the sco IDs.
        if ($rule === 'completionstatusallscos') {
            $returnscos = [];

            foreach ($uservalue as $data) {
                $returnscos[$data->scoid] = (object) ['id' => $data->scoid];
            }

            $DB->expects($this->atMost(1))
            ->method('get_records')
            ->willReturn($returnscos);
        }

        $customcompletion = new custom_completion($mockcminfo, 2);

        $this->assertEquals($status, $customcompletion->get_state($rule));
    }

    /**
     * Test for get_defined_custom_rules().
     */
    public function test_get_defined_custom_rules() {
        $expectedrules = [
            'completionstatusrequired',
            'completionscorerequired',
            'completionstatusallscos',
        ];

        $definedrules = custom_completion::get_defined_custom_rules();
        $this->assertCount(3, $definedrules);

        foreach ($definedrules as $definedrule) {
            $this->assertContains($definedrule, $expectedrules);
        }
    }

    /**
     * Test for get_defined_custom_rule_descriptions().
     */
    public function test_get_custom_rule_descriptions() {
        // Get defined custom rules.
        $rules = custom_completion::get_defined_custom_rules();

        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        // Instantiate a custom_completion object using the mocked cm_info.
        $customcompletion = new custom_completion($mockcminfo, 1);

        // Get custom rule descriptions.
        $ruledescriptions = $customcompletion->get_custom_rule_descriptions();

        // Confirm that defined rules and rule descriptions are consistent with each other.
        $this->assertEquals(count($rules), count($ruledescriptions));
        foreach ($rules as $rule) {
            $this->assertArrayHasKey($rule, $ruledescriptions);
        }
    }

    /**
     * Test for is_defined().
     */
    public function test_is_defined() {
        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $customcompletion = new custom_completion($mockcminfo, 1);

        // All rules are defined.
        $this->assertTrue($customcompletion->is_defined('completionstatusrequired'));
        $this->assertTrue($customcompletion->is_defined('completionscorerequired'));
        $this->assertTrue($customcompletion->is_defined('completionstatusallscos'));

        // Undefined rule is not found.
        $this->assertFalse($customcompletion->is_defined('somerandomrule'));
    }

    /**
     * Data provider for test_get_available_custom_rules().
     *
     * @return array[]
     */
    public function get_available_custom_rules_provider(): array {
        return [
            'Completion status enabled only' => [
                [
                    'completionstatusrequired' => 4,
                    'completionscorerequired' => COMPLETION_DISABLED,
                    'completionstatusallscos' => COMPLETION_DISABLED,
                ],
                ['completionstatusrequired'],
            ],
            'Completion score enabled only' => [
                [
                    'completionstatusrequired' => COMPLETION_DISABLED,
                    'completionscorerequired' => 80,
                    'completionstatusallscos' => COMPLETION_DISABLED,
                ],
                ['completionscorerequired'],
            ],
            'Completion status and all scos completed both enabled' => [
                [
                    'completionstatusrequired' => 2,
                    'completionscorerequired' => COMPLETION_DISABLED,
                    'completionstatusallscos' => COMPLETION_ENABLED,
                ],
                ['completionstatusrequired', 'completionstatusallscos'],
            ],
            'Completion status and score both enabled' => [
                [
                    'completionstatusrequired' => COMPLETION_ENABLED,
                    'completionscorerequired' => 80,
                    'completionstatusallscos' => COMPLETION_DISABLED,
                ],
                ['completionstatusrequired', 'completionscorerequired'],
            ],
            'All custom completion conditions enabled' => [
                [
                    'completionstatusrequired' => 6,
                    'completionscorerequired' => 80,
                    'completionstatusallscos' => COMPLETION_ENABLED,
                ],
                ['completionstatusrequired', 'completionscorerequired', 'completionstatusallscos'],
            ],
        ];
    }

    /**
     * Test for get_available_custom_rules().
     *
     * @dataProvider get_available_custom_rules_provider
     * @param array $completionrulesvalues
     * @param array $expected
     */
    public function test_get_available_custom_rules(array $completionrulesvalues, array $expected) {
        $customcompletionrules = [
            'customcompletionrules' => $completionrulesvalues,
        ];

        // Build a mock cm_info instance.
        $mockcminfo = $this->getMockBuilder(cm_info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        // Mock the return of magic getter for the customdata attribute.
        $mockcminfo->expects($this->any())
            ->method('__get')
            ->with('customdata')
            ->willReturn($customcompletionrules);

        $customcompletion = new custom_completion($mockcminfo, 1);
        $this->assertEquals($expected, $customcompletion->get_available_custom_rules());
    }
}

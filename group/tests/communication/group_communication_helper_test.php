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

namespace core_group\communication;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../communication/tests/communication_test_helper_trait.php');

use core_communication\communication_test_helper_trait;
use core_group\communication\communication_helper as group_communication_helper;
use core_communication\processor as communication_processor;

/**
 * Test communication helper and related methods for groups.
 *
 * @package    core_group
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_group\communication\communication_helper
 */
class group_communication_helper_test extends \advanced_testcase {

    use communication_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_communication_configs();
    }

    /**
     * Test load_for_group_id.
     *
     * @covers ::load_for_group_id
     */
    public function test_load_for_group_id(): void {
        // As communication is created by default.
        $course = $this->getDataGenerator()->create_course(['groupmode' => 1]);
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        $groupcommunication = group_communication_helper::load_for_group_id($group->id);
        $this->assertInstanceOf(
            communication_processor::class,
            $groupcommunication->get_processor(),
        );
    }

    /**
     * Test update_group_communication.
     *
     * @covers ::update_group_communication
     */
    public function test_update_group_communication(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => 1]);
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        group_communication_helper::update_group_communication($course, $group);

        $groupcommunication = group_communication_helper::load_for_group_id($group->id);
        $this->assertInstanceOf(
            communication_processor::class,
            $groupcommunication->get_processor(),
        );

        $this->assertEquals(
            $group->id,
            $groupcommunication->get_processor()->get_instance_id(),
        );
    }
}

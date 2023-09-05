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

namespace core_user\communication;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../communication/tests/communication_test_helper_trait.php');

use core_communication\communication_test_helper_trait;
use core_course\communication\communication_helper as course_communication_helper;
use core_user\communication\communication_helper as user_communication_helper;

/**
 * Test communication helper for users and its related functions.
 *
 * @package    core_user
 * @category   test
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_user\communication\communication_helper
 */
class user_communication_helper_test extends \advanced_testcase {

    use communication_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_communication_configs();
    }

    /**
     * Test update of room membership for the user updates.
     *
     * @covers ::update_user_room_memberships
     */
    public function test_update_user_room_memberships(): void{
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $coursecontext = \context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        role_assign($teacherrole->id, $user->id, $coursecontext->id);

        $coursecommunication = course_communication_helper::load_for_course_id($course->id);
        $courseusers = $coursecommunication->get_processor()->get_all_userids_for_instance();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);

        $user->suspended = 1;
        user_communication_helper::update_user_room_memberships($user);

        $coursecommunication->reload();
        $courseusers = $coursecommunication->get_processor()->get_all_delete_flagged_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);
    }
}

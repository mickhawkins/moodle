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

namespace core_course\communication;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../communication/tests/communication_test_helper_trait.php');

use core_communication\communication_test_helper_trait;
use core_course\communication\communication_helper as course_communication_helper;
use core_group\communication\communication_helper as group_communication_helper;
use core_communication\processor as communication_processor;

/**
 * Test communication helper and its related functions.
 *
 * @package    core_course
 * @category   test
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_course\communication\communication_helper
 */
class course_communication_helper_test extends \advanced_testcase {

    use communication_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_communication_configs();
    }

    /**
     * Test load_for_course_id.
     *
     * @covers ::load_for_course_id
     */
    public function test_load_for_course_id(): void {
        // As communication is created by default.
        $course = $this->getDataGenerator()->create_course();

        $coursecommunication = course_communication_helper::load_for_course_id($course->id);
        $this->assertInstanceOf(
            communication_processor::class,
            $coursecommunication->get_processor()
        );
    }

    /**
     * Test update_course_communication.
     *
     * @covers ::update_course_communication
     */
    public function test_update_course_communication(): void {
        global $DB;

        // Set up the data with course, group, user etc.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $coursecontext = \context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        role_assign($teacherrole->id, $user->id, $coursecontext->id);
        groups_add_member($group->id, $user->id);

        // Now test that there is communication instances for the course and the user added for that instance.
        $coursecommunication = course_communication_helper::load_for_course_id($course->id);
        $this->assertInstanceOf(
            communication_processor::class,
            $coursecommunication->get_processor()
        );
        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_all_userids_for_instance();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);
        // Group should not have any instance yet.
        $groupcommunication = group_communication_helper::load_for_group_id($group->id);
        $this->assertNull($groupcommunication->get_processor());

        // Now update the course.
        $course->groupmode = 1;
        update_course($course);

        // Now there should be a group communication instance.
        $groupcommunication->reload();
        $this->assertInstanceOf(
            communication_processor::class,
            $groupcommunication->get_processor()
        );

        // All the course instance users must be marked as deleted.
        $courseusers = $coursecommunication->get_processor()->get_all_delete_flagged_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);
        // Group instance should have the user.
        $groupusers = $groupcommunication->get_processor()->get_all_userids_for_instance();
        $groupusers = reset($groupusers);
        $this->assertEquals($user->id, $groupusers);
    }

    /**
     * Test get_access_to_all_group_cap_users.
     *
     * @covers ::get_access_to_all_group_cap_users
     */
    public function test_get_access_to_all_group_cap_users(): void {
        global $DB;
        // Set up the data with course, group, user etc.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $coursecontext = \context_course::instance($course->id);
        // Enrol user1 as teacher.
        $teacherrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        role_assign($teacherrole->id, $user1->id, $coursecontext->id);
        // Enrol user2 as student.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        role_assign($studentrole->id, $user2->id, $coursecontext->id);

        $allgroupaccessusers = course_communication_helper::get_access_to_all_group_cap_users(
            [$user1->id, $user2->id],
            $course->id
        );
        $this->assertContains($user1->id, $allgroupaccessusers);
        $this->assertNotContains($user2->id, $allgroupaccessusers);
    }

    /**
     * Test get_access_to_all_group_cap_users.
     *
     * @covers ::update_course_communication_instance_by_provider
     */
    public function test_update_course_communication_instance_by_provider(): void {
        global $DB;

        // Set up the data with course, group, user etc.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $coursecontext = \context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        role_assign($teacherrole->id, $user->id, $coursecontext->id);

        $coursecommunication = course_communication_helper::load_for_course_id($course->id);

        // Now update the communication instance with none. Setting none will make the instance inactive and mark users as deleted.
        course_communication_helper::update_course_communication_instance_by_provider(
            provider: 'none',
            communication: $coursecommunication,
            instance: $course,
            communicationroomname: 'Simple room',
            users: [$user->id],
        );

        // Check that the instance is inactive.
        $coursecommunication->reload();
        $processor = $coursecommunication->get_processor();
        $this->assertEquals('none', $processor->get_provider());

        $courseusers = $processor->get_all_delete_flagged_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);

        // Now re-enable the provider.
        course_communication_helper::update_course_communication_instance_by_provider(
            provider: 'communication_matrix',
            communication: $coursecommunication,
            instance: $course,
            communicationroomname: 'Simple room',
            users: [$user->id],
        );

        // Check the instance is active and all users are active.
        $coursecommunication->reload();
        $processor = $coursecommunication->get_processor();
        $this->assertEquals('communication_matrix', $processor->get_provider());

        $courseusers = $processor->get_instance_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);
    }

    /**
     * Test update_communication_room_membership.
     *
     * @covers ::update_communication_room_membership
     * @covers ::get_communication_membership_actions
     */
    public function test_update_communication_room_membership(): void {
        global $DB;

        // Set up the data with course, group, user etc.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $coursecontext = \context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        role_assign($teacherrole->id, $user->id, $coursecontext->id);

        // Now remove members from room.
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            communicationmemberaction: 'remove_members_from_room',
        );

        // Now test that there is communication instances for the course and the user removed from that instance.
        $coursecommunication = course_communication_helper::load_for_course_id($course->id);
        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_all_delete_flagged_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);

        // Now add members to room.
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            communicationmemberaction: 'add_members_to_room',
        );

        $coursecommunication->reload();
        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_instance_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);

        // Now update membership.
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            communicationmemberaction: 'update_room_membership',
        );

        $coursecommunication->reload();
        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_instance_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals($user->id, $courseusers);

        // Now try using invalid action.
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Invalid action provided.');
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            communicationmemberaction: 'a_funny_action',
        );
    }

    /**
     * Test create_course_communication_instance.
     *
     * @covers ::create_course_communication_instance
     */
    public function test_create_course_communication_instance(): void {
        $course = $this->get_course();
        $coursecommunication = course_communication_helper::load_for_course_id($course->id);

        $processor = $coursecommunication->get_processor();
        $this->assertEquals('communication_matrix', $processor->get_provider());
        $this->assertEquals('Sampleroom', $processor->get_room_name());
    }
}

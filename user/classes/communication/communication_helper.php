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

use stdClass;
use core_communication\api;
use core_course\communication\communication_helper as course_communication_helper;

/**
 * Class communication helper to help with communication related tasks for the users.
 *
 * This class mainly handles the communication actions for different changes for users as well as helps to reduce duplication.
 *
 * @package    core_user
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_helper {

    /**
     * Update the room membership for the user updates.
     *
     * @param stdClass $user The user object.
     */
    public static function update_user_room_memberships(stdClass $user): void {
        if (!api::is_available()) {
            return;
        }
        global $DB;

        // Get the user courses.
        $usercourses = enrol_get_users_courses($user->id);
        // Get the current record for compare the changes before triggering any action.
        $currentrecord = $DB->get_record('user', ['id' => $user->id]);

        if (!empty($currentrecord) && isset($user->suspended) && $currentrecord->suspended !== $user->suspended) {

            // Decide the action for the communication api for the user.
            $communicationmemberaction = ($user->suspended === 0) ? 'add_members_to_room' : 'remove_members_from_room';

            foreach ($usercourses as $usercourse) {
                course_communication_helper::update_communication_room_membership(
                    $usercourse,
                    [$user->id],
                    $communicationmemberaction
                );
            }
        }
    }

    /**
     * Delete the room membership for the user.
     *
     * @param stdClass $user The user object.
     */
    public static function delete_user_room_membership(stdClass $user): void {
        if (!api::is_available()) {
            return;
        }

        foreach (enrol_get_users_courses($user->id) as $course) {
            $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;
            if ((int)$groupmode === NOGROUPS) {
                $communication = \core_course\communication\communication_helper::load_for_course_id($course->id);
                $communication->get_room_user_provider()->remove_members_from_room([$user->id]);
                $communication->get_processor()->delete_instance_user_mapping([$user->id]);
            } else {
                // If group mode is set then handle the group communication rooms.
                $coursegroups = groups_get_all_groups($course->id);
                foreach ($coursegroups as $coursegroup) {
                    $communication = \core_group\communication\communication_helper::load_for_group_id($coursegroup->id);
                    $communication->get_room_user_provider()->remove_members_from_room([$user->id]);
                    $communication->get_processor()->delete_instance_user_mapping([$user->id]);
                }
            }
        }
    }

}

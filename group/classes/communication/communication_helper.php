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

use core_communication\api;

/**
 * Class communication helper to help with communication related tasks for groups.
 *
 * This class mainly handles the communication actions for different setup in groups as well as helps to reduce duplication.
 *
 * @package    core_group
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_helper {

    /**
     * Load the communication instance for group id.
     *
     * @param int $groupid The group id.
     * @return api The communication instance.
     */
    public static function load_for_group_id(int $groupid): api {
        return \core_communication\api::load_by_instance(
            component: 'core_group',
            instancetype: 'groupcommunication',
            instanceid: $groupid,
        );
    }

    /**
     * Communication api call to create room for a group if course has group mode enabled.
     *
     * @param \stdClass $course The course object.
     * @param \stdclass $group The group object.
     */
    public static function update_group_communication(\stdClass $course, \stdclass $group): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;

        if ((int)$groupmode === NOGROUPS) {
            return;
        }

        $communication = self::load_for_group_id($group->id);

        // Get the course communication instance to set the provider.
        $coursecommunication = \core_course\communication\communication_helper::load_for_course_id($course->id);
        $coursecommunication->set_data($course);
        $communication->update_room($coursecommunication->get_provider(), $group->name, null, $course);

        // As it's a new group, we need to add the users with all access group role to the room.
        $courseusers = enrol_get_course_users($course->id);
        $enrolledusers = [];
        foreach ($courseusers as $courseuser) {
            $enrolledusers[] = $courseuser->id;
        }
        $userstoadd = \core_course\communication\communication_helper::get_access_to_all_group_cap_users(
            userids: $enrolledusers,
            courseid: $course->id,
        );
        $communication->add_members_to_room($userstoadd);
    }

    /**
     * Get the user ids from user objects.
     *
     * @param array $users The user objects
     * @return array The user ids.
     */
    public static function get_usersids_from_users(array $users): array {
        $userids = [];
        foreach ($users as $user) {
            $userids[] = $user->id;
        }
        return $userids;
    }
}

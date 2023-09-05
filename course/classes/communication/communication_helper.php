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

use core_communication\api;
use core_communication\processor;
use core_group\communication\communication_helper as groupcommunication_helper;
use core_tests\event\static_info_viewing;
use stdClass;
use stored_file;

/**
 * Class communication helper to help with communication related tasks for course.
 *
 * This class mainly handles the communication actions for different setup in course as well as helps to reduce duplication.
 *
 * @package    core
 * @subpackage course
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_helper {

    /** @var string COURSE_COMMUNICATION_INSTANCETYPE The course communication instance type. */
    public const COURSE_COMMUNICATION_INSTANCETYPE = 'coursecommunication';

    /** @var string COURSE_COMMUNICATION_COMPONENT The course communication component. */
    public const COURSE_COMMUNICATION_COMPONENT = 'core_course';

    /**
     * Load the communication instance for course id.
     *
     * @param int $courseid The course id.
     * @return api The communication instance.
     */
    public static function load_for_course_id(int $courseid): api {
        return \core_communication\api::load_by_instance(
            component: self::COURSE_COMMUNICATION_COMPONENT,
            instancetype: self::COURSE_COMMUNICATION_INSTANCETYPE,
            instanceid: $courseid,
        );
    }

    /**
     * Update course communication according to course data.
     * Course can have course or group rooms. Group mode enabling will create rooms for groups.
     *
     * @param stdClass $course The course data
     * @param stdClass $oldcourse The old course data before the update
     * @param bool $changesincoursecat Whether the course moved to a different category
     */
    public static function update_course_communication(
        stdClass $course,
        stdClass $oldcourse,
        bool $changesincoursecat
    ): void {
        global $CFG;

        // Check if provider is selected.
        $provider = $course->selectedcommunication ?? null;
        // If the course moved to hidden category, set provider to none.
        if ($changesincoursecat && empty($course->visible)) {
            $provider = processor::PROVIDER_NONE;
        }

        // Set data.
        $coursecommunication = self::load_for_course_id($course->id);

        // Attempt to get the communication provider if it wasn't provided in the data.
        if (empty($provider) && api::is_available()) {
            $provider = $coursecommunication->get_provider();
        }

        if (!empty($provider) && api::is_available()) {

            // Get the course image.
            $courseimage = course_get_courseimage($course);

            // This nasty logic is here because of hide course doesn't pass anything in the data object.
            if (!empty($course->communicationroomname)) {
                $coursecommunicationroomname = $course->communicationroomname;
            } else {
                $coursecommunicationroomname = $course->fullname ?? $oldcourse->fullname;
            }

            // List of enrolled users for course communication.
            require_once($CFG->libdir . '/enrollib.php');
            $courseusers = enrol_get_course_users($course->id);
            $enrolledusers = [];
            foreach ($courseusers as $courseuser) {
                $enrolledusers[] = $courseuser->id;
            }

            // Check for group mode, we will have to get the course data again as the group info is not always in the object.
            $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;

            // If group mode is disabled, get the communication information for creating room for a course.
            if ((int)$groupmode === NOGROUPS) {

                // Remove all the members from group rooms if there is any.
                $coursegroups = groups_get_all_groups($course->id);
                foreach ($coursegroups as $coursegroup) {
                    $communication = groupcommunication_helper::load_for_group_id($coursegroup->id);
                    // Remove the members from the group room.
                    $communication->remove_all_members_from_room();
                    // Now update the room.
                    $communication->update_room(
                        selectedprovider: $provider,
                        communicationroomname: $coursegroup->name,
                        avatar: null,
                        instance: $course,
                    );
                }

                // Now create/update the course room.
                $communication = self::load_for_course_id($course->id);

                self::update_course_communication_instance_by_provider(
                    provider: $provider,
                    communication: $communication,
                    instance: $course,
                    communicationroomname: $coursecommunicationroomname,
                    users: $enrolledusers,
                    instanceimage: $courseimage,
                );
            } else {
                // Remove the members for the course room if instance available.
                $communication = self::load_for_course_id($course->id);
                // Remove the members from the course room.
                $communication->remove_all_members_from_room();
                // Now update the room data, but do not create a course room.
                $communication->update_room(
                    selectedprovider: $provider,
                    communicationroomname: $coursecommunicationroomname,
                    avatar: $courseimage,
                    instance: $course,
                );

                $coursegroups = groups_get_all_groups($course->id);
                foreach ($coursegroups as $coursegroup) {
                    $groupuserstoadd = [];

                    $groupusers = groups_get_members($coursegroup->id);
                    foreach ($groupusers as $groupuser) {
                        $groupuserstoadd[] = $groupuser->id;
                    }

                    $allaccessgroupusers = self::get_access_to_all_group_cap_users($enrolledusers, $course->id);
                    foreach ($allaccessgroupusers as $allaccessgroupuser) {
                        if (!in_array($allaccessgroupuser, $groupuserstoadd, true)) {
                            $groupuserstoadd[] = $allaccessgroupuser;
                        }
                    }

                    // Now create/update the group room.
                    $communication = groupcommunication_helper::load_for_group_id($coursegroup->id);

                    self::update_course_communication_instance_by_provider(
                        provider: $provider,
                        communication: $communication,
                        instance: $course,
                        communicationroomname: $coursegroup->name,
                        users: $groupuserstoadd,
                    );
                }
            }
        }
    }

    /**
     * Get the users with capability of access to all groups to add them in all the groups by default.
     *
     * @param array $userids user ids to check the permission
     * @param int $courseid course id
     * @return array of userids
     */
    public static function get_access_to_all_group_cap_users(array $userids, int $courseid): array {
        $accesstoallgrpupusers = [];
        $context = \context_course::instance($courseid);

        foreach ($userids as $userid) {
            if (has_capability('moodle/site:accessallgroups', $context, $userid)) {
                $accesstoallgrpupusers[] = $userid;
            }
        }

        return $accesstoallgrpupusers;
    }

    /**
     * Update the communication instance and its membership according the provider updated for the course.
     *
     * @param string $provider The provider to use.
     * @param api $communication The communication instance.
     * @param stdClass $instance The instance data.
     * @param string $communicationroomname The communication room name.
     * @param array $users The users to add to the room.
     * @param stored_file|null $instanceimage The instance image.
     */
    public static function update_course_communication_instance_by_provider(
        string $provider,
        api $communication,
        stdClass $instance,
        string $communicationroomname,
        array $users,
        ?\stored_file $instanceimage = null,
    ): void {

        // If provider set to none, remove all the members.
        if ($provider === processor::PROVIDER_NONE) {
            $communication->remove_all_members_from_room();
            $communication->update_room(
                selectedprovider: $provider,
                communicationroomname: $communicationroomname,
                avatar: $instanceimage,
                instance: $instance,
            );
            return;
        }

        $queue = true;
        if (
            // If previous provider was not none and current provider is not none, but a different provider, remove members.
            $communication->get_provider() !== '' &&
            $communication->get_provider() !== processor::PROVIDER_NONE &&
            $provider !== $communication->get_provider()
        ) {
            $communication->remove_all_members_from_room();
            $queue = false;
        } else if (
            // If previous provider was none and current provider is not none, but a different provider, remove members.
            ($communication->get_provider() === '' || $communication->get_provider() === processor::PROVIDER_NONE) &&
            $provider !== $communication->get_provider()
        ) {
            $queue = false;
        }

        $communication->update_room(
            selectedprovider: $provider,
            communicationroomname: $communicationroomname,
            avatar: $instanceimage,
            instance: $instance,
        );
        $communication->add_members_to_room(
            userids: $users,
            queue: $queue,
        );
    }

    /**
     * Helper to update room membership according to action passed.
     * This method will help reduce a large amount of duplications of code in different places in core.
     *
     * @param \stdClass $course The course object.
     * @param array $userids The user ids to add to the communication room.
     * @param string $communicationmemberaction The action to perform on the communication room.
     */
    public static function update_communication_room_membership (
        \stdClass $course,
        array $userids,
        string $communicationmemberaction = 'add_members_to_room'
    ): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        if (!in_array($communicationmemberaction, self::get_communication_membership_actions(), true)) {
            throw new \coding_exception('Invalid action provided.');
        }

        // Get the group mode for this course.
        $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;

        // If group mode is not set then just handle the course communication for these users.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_for_course_id($course->id);
            $communication->$communicationmemberaction($userids);
        } else {
            // If group mode is set then handle the group communication rooms for these users.
            $coursegroups = groups_get_all_groups($course->id);

            $userhandled = [];

            foreach ($coursegroups as $coursegroup) {
                // Get the group user who need to be handled and also a member of the group.
                $groupuserstohandle = array_intersect(
                    array_map(
                        static fn($user) => $user->id,
                        groups_get_members($coursegroup->id),
                    ),
                    $userids,
                );

                // Add the users not in group but have the cap and was in the group room initially.
                $allaccessgroupusers = self::get_access_to_all_group_cap_users($userids, $course->id);
                foreach ($allaccessgroupusers as $allaccessgroupuser) {
                    if (!in_array($allaccessgroupuser, $groupuserstohandle, true)) {
                        $groupuserstohandle[] = $allaccessgroupuser;
                    }
                }

                $userhandled = array_merge($userhandled, $groupuserstohandle);

                $communication = groupcommunication_helper::load_for_group_id($coursegroup->id);
                $communication->$communicationmemberaction($groupuserstohandle);
            }

            // If the user was not in any group but an update/remove action requested for the user.
            // Then the user had a role with access all groups cap, but made a regular user, so we need to handle the user.
            $usersnothandled = array_diff($userids, $userhandled);
            // These users are not handled and not in any group, so logically these users lost their permission to stay in a room.
            // So we need to remove them from the room.
            foreach ($coursegroups as $coursegroup) {
                $communication = groupcommunication_helper::load_for_group_id($coursegroup->id);
                $communication->remove_members_from_room($usersnothandled);
            }
        }
    }

    /**
     * Get the communication membership actions.
     *
     * @return string[] The list of actions which can be performed on communication membership.
     */
    public static function get_communication_membership_actions(): array {
        return [
            'add_members_to_room',
            'remove_members_from_room',
            'update_room_membership',
        ];
    }

    /**
     * Get the course communication status notification for course.
     *
     * @param \stdClass $course The course object.
     */
    public static function get_course_communication_status_notification(\stdClass $course): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Get the group mode for this course.
        $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;

        // If group mode is not set then just handle the course communication for these users.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_for_course_id($course->id);
            $communication->show_communication_room_status_notification();
        } else {
            // If group mode is set then handle the group communication rooms for these users.
            $coursegroups = groups_get_all_groups($course->id);
            $numberofgroups = count($coursegroups);

            // If no groups available, nothing to show.
            if ($numberofgroups === 0) {
                return;
            }

            $numberofreadygroups = 0;

            foreach ($coursegroups as $coursegroup) {
                $communication = groupcommunication_helper::load_for_group_id($coursegroup->id);
                $roomstatus = $communication->get_communication_room_url() ? 'ready' : 'pending';
                switch ($roomstatus) {
                    case 'ready':
                        $numberofreadygroups ++;
                        break;
                    case 'pending':
                        $pendincommunicationobject = $communication;
                        break;
                }
            }

            if ($numberofgroups === $numberofreadygroups) {
                $communication->show_communication_room_status_notification();
            } else {
                $pendincommunicationobject->show_communication_room_status_notification();
            }
        }
    }

    /**
     * Delete course communication data and remove members.
     * Course can have communication data if it is a group or a course.
     * This action is important to perform even if the experimental feature is disabled.
     *
     * @param stdclass $course The course object.
     */
    public static function delete_course_communication(stdclass $course): void {
        $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;

        // If group mode is not set then just handle the course communication room.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_for_course_id($course->id);
            $communication->delete_room();
        } else {
            // If group mode is set then handle the group communication rooms.
            $coursegroups = groups_get_all_groups($course->id);
            foreach ($coursegroups as $coursegroup) {
                $communication = \core_group\communication\communication_helper::load_for_group_id($coursegroup->id);
                $communication->delete_room();
            }
        }
    }

    /**
     * Create course communication instance.
     *
     * @param stdClass $course The course object.
     */
    public static function create_course_communication_instance(stdClass $course): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Check for default provider config setting.
        $defaultprovider = get_config('moodlecourse', 'coursecommunicationprovider');
        $provider = (isset($course->selectedcommunication)) ? $course->selectedcommunication : $defaultprovider;

        if (!empty($provider)) {
            // Prepare the communication api data.
            $courseimage = course_get_courseimage($course);
            $communicationroomname = !empty($course->communicationroomname) ? $course->communicationroomname : $course->fullname;

            // Communication api call.
            $communication = \core_communication\api::load_by_instance(
                'core_course',
                'coursecommunication',
                $course->id,
            );
            $communication->create_and_configure_room(
                $provider,
                $communicationroomname,
                $courseimage ?: null,
                $course,
            );
        }
    }

    /**
     * Check if the communication instance for the course should get updated.
     *
     * @param stdClass $course The course object.
     * @param stdClass $oldcourse The old course object.
     * @param bool $changesincoursecat True if the course category has changed.
     * @return bool True if the communication instance update is required.
     */
    public static function is_communication_instance_update_required(
        stdClass $course,
        stdClass $oldcourse,
        bool $changesincoursecat
    ): bool {
        // Something changed? return true as we should update.
        $groupmode = $course->groupmode ?? get_course($course->id)->groupmode;
        if ($changesincoursecat || $groupmode !== $oldcourse->groupmode) {
            return true;
        }
        return false;
    }

}

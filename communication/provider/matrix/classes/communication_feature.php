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

namespace communication_matrix;

use core_group\communication\communication_helper as groupcommunication_helper;
use communication_matrix\local\spec\features\matrix\{
    create_room_v3 as create_room_feature,
    get_room_members_v3 as get_room_members_feature,
    remove_member_from_room_v3 as remove_member_from_room_feature,
    update_room_avatar_v3 as update_room_avatar_feature,
    update_room_name_v3 as update_room_name_feature,
    update_room_topic_v3 as update_room_topic_feature,
    upload_content_v3 as upload_content_feature,
    media_create_v1 as media_create_feature,
};
use communication_matrix\local\spec\features\synapse\{
    create_user_v2 as create_user_feature,
    get_room_info_v1 as get_room_info_feature,
    get_user_info_v2 as get_user_info_feature,
    invite_member_to_room_v1 as invite_member_to_room_feature,
};
use core_communication\processor;
use stdClass;
use GuzzleHttp\Psr7\Response;

/**
 * class communication_feature to handle matrix specific actions.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_feature implements
    \core_communication\communication_provider,
    \core_communication\user_provider,
    \core_communication\room_chat_provider,
    \core_communication\room_user_provider,
    \core_communication\form_provider {

    /** @var ?matrix_rooms $room The matrix room object to update room information */
    private ?matrix_rooms $room = null;

    /** @var string|null The URI of the home server */
    protected ?string $homeserverurl = null;

    /** @var string The URI of the Matrix web client */
    protected string $webclienturl;

    /** @var \communication_matrix\local\spec\v1p1|null The Matrix API processor */
    protected ?matrix_client $matrixapi;

    /**
     * Load the communication provider for the communication api.
     *
     * @param processor $communication The communication processor object
     * @return communication_feature The communication provider object
     */
    public static function load_for_instance(processor $communication): self {
        return new self($communication);
    }

    /**
     * Reload the room information.
     * This may be necessary after a room has been created or updated via the adhoc task.
     * This is primarily intended for use in unit testing, but may have real world cases too.
     */
    public function reload(): void {
        $this->room = null;
        $this->processor = processor::load_by_id($this->processor->get_id());
    }

    /**
     * Constructor for communication provider to initialize necessary objects for api cals etc..
     *
     * @param processor $processor The communication processor object
     */
    private function __construct(
        private \core_communication\processor $processor,
    ) {
        $this->homeserverurl = get_config('communication_matrix', 'matrixhomeserverurl');
        $this->webclienturl = get_config('communication_matrix', 'matrixelementurl');

        if ($this->homeserverurl) {
            // Generate the API instance.
            $this->matrixapi = matrix_client::instance(
                serverurl: $this->homeserverurl,
                accesstoken: get_config('communication_matrix', 'matrixaccesstoken'),
            );
        }
    }

    /**
     * Check whether the room configuration has been created yet.
     *
     * @return bool
     */
    protected function room_exists(): bool {
        return (bool) $this->get_room_configuration();
    }

    /**
     * Whether the room exists on the remote server.
     * This does not involve a remote call, but checks whether Moodle is aware of the room id.
     * @return bool
     */
    protected function remote_room_exists(): bool {
        $room = $this->get_room_configuration();

        return $room && ($room->get_room_id() !== null);
    }

    /**
     * Get the stored room configuration.
     *
     * @return null|matrix_room|matrix_space
     */
    public function get_room_configuration(): null|matrix_room|matrix_space {
        // If the space configuration is required, we need to load the space object, otherwise the room object.
        if ($this->is_space_configuration_required() && $this->processor->get_component() === 'core_course') {
            $this->room = matrix_space::load_by_processor_id($this->processor->get_id());
        } else {
            $this->room = matrix_room::load_by_processor_id($this->processor->get_id());
        }

        return $this->room;
    }

    /**
     * Get the room configuration for room membership.
     *
     * This is a special case where we need to load the room object for membership.
     * The method get_room_configuration() will load the room object according to the course setup for creating room/space.
     * While managing membership, we need to load the room object according to the processor id, we dont need to worry about space.
     * Space implementation is done using its own method with all setup done there for a safer and cleaner implementation.
     *
     * @return matrix_room|null The room object
     */
    public function get_room_configuration_for_membership(): null|matrix_room {
        $this->room = matrix_room::load_by_processor_id($this->processor->get_id());
        return $this->room;
    }

    /**
     * Return the current room id.
     *
     * There are two cases while getting the room id. For updating chat rooms, spaces etc. we need to consider space.
     * For all other cases like membership, power level, we don't need to consider space as space does not have members.
     * Only rooms have members and adding members to space is disabled from request.
     *
     * @return string|null
     */
    public function get_room_id(): ?string {
        return $this->get_room_configuration()?->get_room_id();
    }

    /**
     * Create the room record according to the configuration.
     * It will create a record for room or space according to the configuration.
     *
     * This standard method will check the current config for the instance and create the room/space record accordingly.
     *
     * @param string|null $matrixroomtopic The topic for the room or space
     * @return null|matrix_room|matrix_space
     */
    protected function create_room_record(?string $matrixroomtopic): null|matrix_room|matrix_space {
        // If space config is required, we need to create space object, otherwise room object.
        if ($this->is_space_configuration_required() && $this->processor->get_component() === 'core_course') {
            $room = matrix_space::class;
        } else {
            $room = matrix_room::class;
        }

        return $room::create_room_record(
            processorid: $this->processor->get_id(),
            topic: $matrixroomtopic,
        );
    }

    /**
     * Add/invite room members to the space.
     *
     * This nasty hack is required as matrix space works in a weird way that space need to have all the members added.
     * Without the membership, users wont be able to see the space in their client.
     *
     * We should add a user to the space only after the user is added to a room.
     * This is done to get away with complex logics to consider when adding members to space considering the component.
     *
     * @param array $userids The Moodle user ids to add
     */
    private function add_members_to_space(array $userids): void {
        if ($userids === []) {
            return;
        }

        if ($this->processor->get_component() !== 'core_group') {
            return;
        }

        // Get the course communication instance.
        $instancedata = groups_get_group($this->processor->get_instance_id());
        if (!$instancedata) {
            return;
        }

        $processor = processor::load_by_instance(
            component: 'core_course',
            instancetype: 'coursecommunication',
            instanceid: $instancedata->courseid,
        );

        $room = matrix_space::load_by_processor_id($processor->get_id());

        foreach ($userids as $userid) {
            $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($userid);

            $response = $this->matrixapi->get_room_members($room->get_room_id());
            $body = self::get_body($response);

            // Check user id is in the returned room member ids.
            $roommembership = isset($body->joined) && array_key_exists($matrixuserid, (array) $body->joined);

            if ($matrixuserid && $this->check_user_exists($matrixuserid) && !$roommembership) {
                $this->matrixapi->invite_member_to_room(
                    $room->get_room_id(),
                    $matrixuserid,
                );
            }
        }
    }

    /**
     * Check the userids for space membership requirement.
     * Some members might be a member of multiple groups and removal from one group doesn't mean removal from space.
     * Even if the user is removed from one group room, their membership in other rooms might also be required.
     *
     * @param array $userids The Moodle user ids to search for active membership
     * @param processor|null $processor The processor object
     * @return array The user ids to remove from space
     */
    private function verify_users_to_remove_from_space(array $userids, ?processor $processor): array {
        if ($userids === [] || $processor === null) {
            return [];
        }

        global $DB;
        $coursegroups = groups_get_all_groups($processor->get_instance_id());
        $communicationinstances = [];
        foreach ($coursegroups as $coursegroup) {
            $communicationinstances[] = groupcommunication_helper::load_for_group_id($coursegroup->id)->get_processor()->get_id();
        }
        // Now check if the user is active in any other group communication instance.
        if ($communicationinstances === []) {
            return [];
        }

        $userstoremove = [];

        // Check the user against the group rooms and check if the user has a mapping which is not deleted or active.
        // This nasty logic is required as we need to check if the user is active in any other room before removed from space.
        foreach ($userids as $userid) {
            $usermappings = $DB->get_records_select(
                'communication_user',
                'userid = ? AND deleted = ? AND commid IN (' . implode(',', $communicationinstances) . ')',
                [$userid, 0],
                'id',
            );

            // If an active mapping found for the user, we need to keep the user in space, otherwise remove.
            if (count($usermappings) === 0) {
                $userstoremove[] = $userid;
            }
        }
        return $userstoremove;
    }

    /**
     * Remove the users from space when removed from the room.
     *
     * @param array $userids The Moodle user ids to remove
     */
    private function remove_members_from_space(array $userids): void {
        if ($userids === []) {
            return;
        }

        if ($this->processor->get_component() !== 'core_group') {
            return;
        }

        // Get the course communication instance.
        $instancedata = groups_get_group($this->processor->get_instance_id());
        if (!$instancedata) {
            return;
        }

        $processor = processor::load_by_instance(
            component: 'core_course',
            instancetype: 'coursecommunication',
            instanceid: $instancedata->courseid,
        );

        // Check and verify which users need to be removed from space.
        $userstoremove = $this->verify_users_to_remove_from_space($userids, $processor);

        if ($userstoremove === []) {
            return;
        }

        $room = matrix_space::load_by_processor_id($processor->get_id());
        foreach ($userids as $userid) {
            // Check user is a member of space first.
            $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($userid);

            // Check if user is the room admin and halt removal of this user.
            $response = $this->matrixapi->get_room_info($room->get_room_id());
            $matrixroomdata = self::get_body($response);
            $roomadmin = $matrixroomdata->creator;
            $isadmin = $matrixuserid === $roomadmin;

            $response = $this->matrixapi->get_room_members($room->get_room_id());
            $body = self::get_body($response);

            // Check user id is in the returned room member ids.
            $roommembership = isset($body->joined) && array_key_exists($matrixuserid, (array) $body->joined);

            if (!$isadmin && $matrixuserid && $this->check_user_exists($matrixuserid) && $roommembership) {
                $this->matrixapi->remove_member_from_room(
                    $room->get_room_id(),
                    $matrixuserid,
                );
            }
        }
    }

    /**
     * Create members.
     *
     * @param array $userids The Moodle user ids to create
     */
    public function create_members(array $userids): void {
        $addedmembers = [];

        // This API requiures the create_user feature.
        $this->matrixapi->require_feature(create_user_feature::class);

        foreach ($userids as $userid) {
            $user = \core_user::get_user($userid);
            $userfullname = fullname($user);

            // Proceed if we have a user's full name and email to work with.
            if (!empty($user->email) && !empty($userfullname)) {
                $qualifiedmuid = matrix_user_manager::get_formatted_matrix_userid($user->username);

                // First create user in matrix.
                $response = $this->matrixapi->create_user(
                    userid: $qualifiedmuid,
                    displayname: $userfullname,
                    threepids: [(object)[
                        'medium' => 'email',
                        'address' => $user->email
                    ]],
                    externalids: [],
                );
                $body = json_decode($response->getBody());

                if (!empty($matrixuserid = $body->name)) {
                    // Then create matrix user id in moodle.
                    matrix_user_manager::set_matrix_userid_in_moodle($userid, $qualifiedmuid);
                    if ($this->add_registered_matrix_user_to_room($matrixuserid)) {
                        $addedmembers[] = $userid;
                    }
                }
            }
        }

        // Set the power level of the users.
        if (!empty($addedmembers) && $this->is_power_levels_update_required($addedmembers)) {
            $this->set_matrix_power_levels();
        }

        // Now determine the group config and add users to the space.
        $this->add_members_to_space($addedmembers);

        // Mark then users as synced for the added members.
        $this->processor->mark_users_as_synced($addedmembers);
    }

    public function update_room_membership(array $userids): void {
        // Before updating the membership, check if the user is added. If not, add first then sync the power level.
        foreach ($userids as $userid) {
            $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($userid);

            if ($matrixuserid && $this->check_user_exists($matrixuserid)) {
                $this->add_registered_matrix_user_to_room($matrixuserid);
            }
        }

        $this->set_matrix_power_levels();
        // Mark then users as synced for the updated members.
        $this->processor->mark_users_as_synced($userids);
    }

    /**
     * Add members to a room.
     *
     * @param array $userids The user ids to add
     */
    public function add_members_to_room(array $userids): void {
        $unregisteredmembers = [];
        $addedmembers = [];

        foreach ($userids as $userid) {
            $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($userid);

            if ($matrixuserid && $this->check_user_exists($matrixuserid)) {
                if ($this->add_registered_matrix_user_to_room($matrixuserid)) {
                    $addedmembers[] = $userid;
                }
            } else {
                $unregisteredmembers[] = $userid;
            }
        }

        // Set the power level of the users.
        if (!empty($addedmembers) && $this->is_power_levels_update_required($addedmembers)) {
            $this->set_matrix_power_levels();
        }

        // Now determine the group config and add users to the space.
        $this->add_members_to_space($addedmembers);

        // Mark then users as synced for the added members.
        $this->processor->mark_users_as_synced($addedmembers);

        // Create Matrix users.
        if (count($unregisteredmembers) > 0) {
            $this->create_members($unregisteredmembers);
        }
    }

    /**
     * Adds the registered matrix user id to room.
     *
     * @param string $matrixuserid Registered matrix user id
     */
    private function add_registered_matrix_user_to_room(string $matrixuserid): bool {
        // Require the invite_member_to_room API feature.
        $this->matrixapi->require_feature(invite_member_to_room_feature::class);

        if (!$this->check_room_membership($matrixuserid)) {
            $response = $this->matrixapi->invite_member_to_room(
                $this->get_room_configuration_for_membership()->get_room_id(),
                $matrixuserid,
            );

            $body = self::get_body($response);
            if (empty($body->room_id)) {
                return false;
            }

            if ($body->room_id !== $this->get_room_configuration_for_membership()->get_room_id()) {
                return false;
            }

            return true;
        }
        return false;
    }

    /**
     * Remove members from a room.
     *
     * @param array $userids The Moodle user ids to remove
     */
    public function remove_members_from_room(array $userids): void {
        // This API requiures the remove_members_from_room feature.
        $this->matrixapi->require_feature(remove_member_from_room_feature::class);

        if ($this->get_room_id() === null) {
            return;
        }

        // Remove the power level for the user first.
        $this->set_matrix_power_levels($userids);

        $membersremoved = [];

        foreach ($userids as $userid) {
            // Check user is member of room first.
            $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($userid);

            // Check if user is the room admin and halt removal of this user.
            $response = $this->matrixapi->get_room_info($this->get_room_configuration_for_membership()->get_room_id());
            $matrixroomdata = self::get_body($response);
            $roomadmin = $matrixroomdata->creator;
            $isadmin = $matrixuserid === $roomadmin;

            if (
                !$isadmin && $matrixuserid && $this->check_user_exists($matrixuserid) &&
                $this->check_room_membership($matrixuserid)
            ) {
                $this->matrixapi->remove_member_from_room(
                    $this->get_room_configuration_for_membership()->get_room_id(),
                    $matrixuserid,
                );

                $membersremoved[] = $userid;
            }
        }

        // Now determine the group config and remove users to the space.
        $this->remove_members_from_space($membersremoved);

        $this->processor->delete_instance_user_mapping($membersremoved);
    }

    /**
     * Check if a user exists in Matrix.
     * Use if user existence is needed before doing something else.
     *
     * @param string $matrixuserid The Matrix user id to check
     * @return bool
     */
    public function check_user_exists(string $matrixuserid): bool {
        // This API requires the get_user_info feature.
        $this->matrixapi->require_feature(get_user_info_feature::class);

        $response = $this->matrixapi->get_user_info($matrixuserid);
        $body = self::get_body($response);

        return isset($body->name);
    }

    /**
     * Check if a user is a member of a room.
     * Use if membership confirmation is needed before doing something else.
     *
     * @param string $matrixuserid The Matrix user id to check
     * @return bool
     */
    public function check_room_membership(string $matrixuserid): bool {
        // This API requires the get_room_members feature.
        $this->matrixapi->require_feature(get_room_members_feature::class);

        $response = $this->matrixapi->get_room_members($this->get_room_configuration_for_membership()->get_room_id());
        $body = self::get_body($response);

        // Check user id is in the returned room member ids.
        return isset($body->joined) && array_key_exists($matrixuserid, (array) $body->joined);
    }

    /**
     * Create a room based on the data in the communication instance.
     *
     * @return bool
     */
    public function create_chat_room(): bool {
        if ($this->remote_room_exists()) {
            // A room already exists. Update it instead.
            return $this->update_chat_room();
        }

        // This method requires the create_room API feature.
        $this->matrixapi->require_feature(create_room_feature::class);

        $room = $this->get_room_configuration();

        $response = $this->matrixapi->create_room(
            name: $this->processor->get_room_name(),
            visibility: 'private',
            preset: 'private_chat',
            initialstate: [],
            options: [
                'topic' => $room->get_topic(),
                'creation_content' => $room->get_creation_content(),
            ],
        );

        $response = self::get_body($response);

        if (empty($response->room_id)) {
            throw new \moodle_exception(
                'Unable to determine ID of matrix room',
            );
        }

        // Update our record of the matrix room_id.
        $room->update_room_record(
            roomid: $response->room_id,
        );

        // Update the room avatar.
        $this->update_room_avatar();
        // Update the room space if needed.
        $this->update_room_space();
        return true;
    }

    /**
     * This method is to set the space to the rooms after a space is created.
     *
     * While this will seem not necessary is some cases, its quite important to make sure there is no data loss.
     * All the tasks are happening using ad-hoc task and there is no guarantee that the space will be created before the room.
     * That means, we can not say the space id will be available when the room is created.
     * That is why this extra layer to check after creating the space, do we need to add any room to that space.
     * And also update the records accordingly.
     */
    protected function update_space_for_all_rooms(): void {
        // If the room config is not a space or not from course component, then we don't need to do anything.
        if ($this->get_room_configuration() instanceof matrix_room || $this->processor->get_component() !== 'core_course') {
            return;
        }

        // Get all the groups for the course.
        $coursegroups = groups_get_all_groups($this->processor->get_instance_id());
        // Now go through the group communication instances and check if any of them are not in the space.
        foreach ($coursegroups as $coursegroup) {
            $processor = processor::load_by_instance(
                component: 'core_group',
                instancetype: 'groupcommunication',
                instanceid: $coursegroup->id,
            );
            // No processor found, skip.
            if (!$processor) {
                continue;
            }

            $room = matrix_room::load_by_processor_id($processor->get_id());
            // No room found, skip.
            if (!$room || $room->get_room_id() === null) {
                continue;
            }

            // Load the space.
            $space = matrix_space::load_by_processor_id($this->processor->get_id());

            // Api call to update matrix end.
            $this->matrixapi->update_room_parent_space(
                roomid: $space->get_room_id(),
                parentid: $room->get_room_id(),
            );

            // Update the room record with the associated space record.
            $room->set_space_id($space->get_id());
            $room->update_room_record();
        }
    }

    /**
     * Update the room space based on the parent context of the communication instance.
     *
     * After creating or updating a room, we need to check for the space and update the space if needed.
     * This is necessary in case of group communication instances where those rooms will live in the space of the course.
     */
    protected function update_room_space(): void {
        // If the room is a space and the component is not from group, go through the instances and update the spaces.
        if ($this->get_room_configuration() instanceof matrix_space || $this->processor->get_component() !== 'core_group') {
            $this->update_space_for_all_rooms();
            return;
        }

        // Get the course communication instance.
        $instancedata = groups_get_group($this->processor->get_instance_id());
        $processor = processor::load_by_instance(
            component: 'core_course',
            instancetype: 'coursecommunication',
            instanceid: $instancedata->courseid,
        );
        // No processor found, skip.
        if (!$processor) {
            return;
        }

        $space = matrix_space::load_by_processor_id($processor->get_id());
        // No space found, skip.
        if (!$space || $space->get_room_id() === null) {
            return;
        }

        // Load the room.
        $room = matrix_room::load_by_processor_id($this->processor->get_id());

        // Update the matrix end and add the room to the space.
        $this->matrixapi->update_room_parent_space(
            roomid: $space->get_room_id(),
            parentid: $room->get_room_id(),
        );

        // Update the room record with the associated space record.
        $room->set_space_id($space->get_id());
        $room->update_room_record();
    }

    public function update_chat_room(): bool {
        if (!$this->remote_room_exists()) {
            // No room exists. Create it instead.
            return $this->create_chat_room();
        }

        $this->matrixapi->require_features([
            get_room_info_feature::class,
            update_room_name_feature::class,
            update_room_topic_feature::class,
        ]);

        // Get room data.
        $response = $this->matrixapi->get_room_info($this->get_room_id());
        $remoteroomdata = self::get_body($response);

        // Update the room name when it's updated from the form.
        if ($remoteroomdata->name !== $this->processor->get_room_name()) {
            $this->matrixapi->update_room_name($this->get_room_id(), $this->processor->get_room_name());
        }

        // Update the room topic if set.
        $localroomdata = $this->get_room_configuration();
        if ($remoteroomdata->topic !== $localroomdata->get_topic()) {
            $this->matrixapi->update_room_topic(
                roomid: $localroomdata->get_room_id(),
                topic: $localroomdata->get_topic(),
            );
        }

        // Update room avatar.
        $this->update_room_avatar();
        // Update the room space.
        $this->update_room_space();

        return true;
    }

    public function delete_chat_room(): bool {
        $roomconfig =  $this->get_room_configuration();
        $roomconfig?->delete_room_record();
        $this->room = null;

        return true;
    }

    /**
     * Update the room avatar when an instance image is added or updated.
     */
    public function update_room_avatar(): void {
        // Both of the following features of the remote API are required.
        $this->matrixapi->require_features([
            upload_content_feature::class,
            update_room_avatar_feature::class,
        ]);

        // Check if we have an avatar that needs to be synced.
        if ($this->processor->is_avatar_synced()) {
            return;
        }

        $instanceimage = $this->processor->get_avatar();
        $contenturi = null;

        if ($this->matrixapi->implements_feature(media_create_feature::class)) {
            // From version 1.7 we can fetch a mxc URI and use it before uploading the content.
            if ($instanceimage) {
                $response = $this->matrixapi->media_create();
                $contenturi = self::get_body($response)->content_uri;

                // Now update the room avatar.
                $response = $this->matrixapi->update_room_avatar($this->get_room_id(), $contenturi);

                // And finally upload the content.
                $this->matrixapi->upload_content($instanceimage);
            } else {
                $response = $this->matrixapi->update_room_avatar($this->get_room_id(), null);
            }
        } else {
            // Prior to v1.7 the only way to upload content was to upload the content, which returns a mxc URI to use.

            if ($instanceimage) {
                // First upload the content.
                $response = $this->matrixapi->upload_content($instanceimage);
                $body = self::get_body($response);
                $contenturi = $body->content_uri;
            }

            // Now update the room avatar.
            $response = $this->matrixapi->update_room_avatar($this->get_room_id(), $contenturi);
        }

        // Indicate the avatar has been synced if it was successfully set with Matrix.
        if ($response->getReasonPhrase() === 'OK') {
            $this->processor->set_avatar_synced_flag(true);
        }
    }

    public function get_chat_room_url(): ?string {
        if (!$this->get_room_id()) {
            // We don't have a room id for this record.
            return null;
        }

        return sprintf(
            "%s#/room/%s",
            $this->webclienturl,
            $this->get_room_id(),
        );
    }

    public function save_form_data(\stdClass $instance): void {
        $matrixroomtopic = $instance->matrixroomtopic ?? null;
        $room = $this->get_room_configuration();

        if ($room) {
            $room->update_room_record(
                topic: $matrixroomtopic,
            );
        } else {
            $this->room = $this->create_room_record($matrixroomtopic);
        }
    }

    public function set_form_data(\stdClass $instance): void {
        if (!empty($instance->id) && !empty($this->processor->get_id())) {
            if ($this->room_exists()) {
                $instance->matrixroomtopic = $this->get_room_configuration()->get_topic();
            }
        }
    }

    public static function set_form_definition(\MoodleQuickForm $mform): void {
        // Room description for the communication provider.
        $mform->insertElementBefore($mform->createElement('text', 'matrixroomtopic',
            get_string('matrixroomtopic', 'communication_matrix'),
            'maxlength="255" size="20"'), 'addcommunicationoptionshere');
        $mform->addHelpButton('matrixroomtopic', 'matrixroomtopic', 'communication_matrix');
        $mform->setType('matrixroomtopic', PARAM_TEXT);
    }

    /**
     * Get the body of a response as a stdClass.
     *
     * @param Response $response
     * @return stdClass
     */
    public static function get_body(Response $response): stdClass {
        $body = $response->getBody();

        return json_decode($body, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Set the matrix power level with the room.
     *
     * @param array $resetusers The list of users to override and reset their power level to 0
     */
    private function set_matrix_power_levels(array $resetusers = []): void {
        // Get all the current users for the room.
        $existingusers = $this->processor->get_all_userids_for_instance();

        $userpowerlevels = [];
        foreach ($existingusers as $existinguser) {
            $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($existinguser);

            if (!$matrixuserid) {
                continue;
            }

            if (!empty($resetusers) && in_array($existinguser, $resetusers, true)) {
                $userpowerlevels[$matrixuserid] = matrix_constants::POWER_LEVEL_DEFAULT;
            } else {
                $existinguserpowerlevel = $this->get_user_allowed_power_level($existinguser);
                // We don't need to include the default power level users in request, as matrix will make then default anyways.
                if ($existinguserpowerlevel > matrix_constants::POWER_LEVEL_DEFAULT) {
                    $userpowerlevels[$matrixuserid] = $existinguserpowerlevel;
                }
            }
        }

        // Now add the token user permission to retain the permission in the room.
        $response = $this->matrixapi->get_room_info($this->get_room_configuration_for_membership()->get_room_id());
        $matrixroomdata = self::get_body($response);
        $roomadmin = $matrixroomdata->creator;
        $userpowerlevels[$roomadmin] = matrix_constants::POWER_LEVEL_MAXIMUM;

        $this->matrixapi->update_room_power_levels($this->get_room_configuration_for_membership()->get_room_id(), $userpowerlevels);
    }

    /**
     * Determine if a power level update is required.
     * Matrix will always set a user to the default power level of 0 when a power level update is made.
     * That is, unless we specify another level. As long as one person's level is greater than the default,
     * we will need to set the power levels of all users greater than the default.
     *
     * @param array $userids The users to evaluate
     * @return boolean Returns true if an update is required
     */
    private function is_power_levels_update_required(array $userids): bool {
        // Is the user's power level greater than the default?
        foreach ($userids as $userid) {
            if ($this->get_user_allowed_power_level($userid) > matrix_constants::POWER_LEVEL_DEFAULT) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the allowed power level for the user id according to perms/site admin or default.
     *
     * @param int $userid
     * @return int
     */
    public function get_user_allowed_power_level(int $userid): int {
        if ($this->processor->get_instance_type() === 'coursecommunication') {
            $instanceid = $this->processor->get_instance_id();
        } else if ($this->processor->get_instance_type() === 'groupcommunication') {
            $instanceid = groups_get_group($this->processor->get_instance_id())->courseid;
        }
        $context = \context_course::instance($instanceid);
        $powerlevel = matrix_constants::POWER_LEVEL_DEFAULT;

        if (has_capability('communication/matrix:moderator', $context, $userid)) {
            $powerlevel = matrix_constants::POWER_LEVEL_MODERATOR;
        }

        // If site admin, override all caps.
        if (is_siteadmin($userid)) {
            $powerlevel = matrix_constants::POWER_LEVEL_MOODLE_SITE_ADMIN;
        }

        return $powerlevel;
    }

    /**
     * Check if we need create a space instead of matrix room for this instance.
     *
     * @return bool Returns true if a space is required
     */
    protected function is_space_configuration_required(): bool {
        global $DB;

        // If group mode disabled, we don't need worry about spaces.
        if ($this->processor->get_component() === 'core_group') {
            $instance = groups_get_group($this->processor->get_instance_id());
            if ($instance) {
                $instanceid = $instance->courseid;
            } else {
                return false;
            }
        } else {
            $instanceid = $this->processor->get_instance_id();
        }
        $course = $DB->get_record('course', array('id' => $instanceid), '*', MUST_EXIST);
        $groupmode = $course->groupmode;
        if ((int) $groupmode === NOGROUPS) {
            return false;
        }
        return true;
    }
}

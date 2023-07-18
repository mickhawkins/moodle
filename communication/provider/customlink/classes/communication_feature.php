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

namespace communication_customlink;

use core_communication\processor;

/**
 * class communication_feature to handle custom link specific actions.
 *
 * @package    communication_customlink
 * @copyright  2023 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_feature implements
    \core_communication\communication_provider,
    \core_communication\user_provider,
    \core_communication\room_chat_provider,
    \core_communication\room_user_provider,
    \core_communication\form_provider {

    /**
     * Load the communication provider for the communication API.
     *
     * @param processor $communication The communication processor object.
     * @return communication_feature The communication provider object.
     */
    public static function load_for_instance(processor $communication): self {
        return new self($communication);
    }

    /**
     * Constructor for communication provider.
     *
     * @param processor $communication The communication processor object.
     */
    private function __construct(
        private \core_communication\processor $communication,
    ) {
        // No specific initialisation required.
    }

    /**
     * Create members - member management not required for custom links.
     *
     * @param array $userids The Moodle user ids to create
     */
    public function create_members(array $userids): void {

//TODO - TBC- are these sync calls required in relevant methods?

        // Mark then users as synced for the added members.
        $this->communication->mark_users_as_synced($userids);
    }

    /**
     * Add members to a room  - member management not required for custom links.
     *
     * @param array $userids The user ids to add
     */
    public function add_members_to_room(array $userids): void {

        // Mark then users as synced for the added members.
        $this->communication->mark_users_as_synced($userids);
    }

    /**
     * Remove members from a room  - member management not required for custom links.
     *
     * @param array $userids The Moodle user ids to remove
     */
    public function remove_members_from_room(array $userids): void {
//TODO - call required?
        $this->communication->delete_instance_user_mapping($userids);
    }

    /**
     * Check if a user exists - always returns true for custom links.
     *
     * @param string $matrixuserid The Matrix user id to check
     * @return bool
     */
    public function check_user_exists(string $matrixuserid): bool {
//TODO - requyired?
        return true;
    }

    /**
     * Check if a user is a member of a room - member management not required for custom links.
     *
     * @param string $matrixuserid The Matrix user id to check
     * @return bool
     */
    public function check_room_membership(string $matrixuserid): bool {
        return true;
    }

    /**
     * Create room - room existence managed externally, always return true.
     *
     * @return boolean
     */
    public function create_chat_room(): bool {
        return true;
    }

    /**
     * Update room - room existence managed externally, always return true.
     *
     * @return boolean
     */
    public function update_chat_room(): bool {
        return true;
    }

    /**
     * Delete room - room existence managed externally, always return true.
     *
     * @return boolean
     */
    public function delete_chat_room(): bool {
        return true;
    }

    /**
     * Fetch the URL for this custom link provider.
     *
     * @return string The custom URL
     */
    public function get_chat_room_url(): ?string {

        //TODO - fetch the custom field for this provider
        //Can look at how this->matrixrooms is set up
        return 'TODO';
    }

    public function save_form_data(\stdClass $instance): void {
//TODO
        $matrixroomtopic = $instance->matrixroomtopic ?? null;
        if ($this->matrixrooms->room_record_exists()) {
            $this->matrixrooms->update_matrix_room_record($this->matrixrooms->get_matrix_room_id(), $matrixroomtopic);
        } else {
            // Create the record with empty room id as we don't have it yet.
            $this->matrixrooms->create_matrix_room_record(
                $this->communication->get_id(),
                $this->matrixrooms->get_matrix_room_id(),
                $matrixroomtopic,
            );
        }
    }

    public function set_form_data(\stdClass $instance): void {
//TODO
        if (!empty($instance->id) && !empty($this->communication->get_id())) {
            $instance->url = $this->matrixrooms->get_customlink_url();
        }
    }

    public static function set_form_definition(\MoodleQuickForm $mform): void {
        // Custom link description for the communication provider.
        $mform->insertElementBefore($mform->createElement('text', 'customlink',
            get_string('customlink', 'communication_customlink'),
            'maxlength="255" size="20"'), 'addcommunicationoptionshere');
        $mform->addHelpButton('customlink', 'customlink', 'communication_customlink');
        $mform->setType('customlink', PARAM_TEXT);
    }
}

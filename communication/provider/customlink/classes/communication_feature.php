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
    \core_communication\room_chat_provider,
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
        global $DB;

//TODO: cache / look at abstracting out fetching etc like matrix_rooms does
        $url = $DB->get_field(
            'communication_customlink',
            'url',
            ['commid' => $this->communication->get_id()]
        );

        $url = $url ?? null;

        return $url;
    }

    public function save_form_data(\stdClass $instance): void {
        global $DB;

        $tablename = 'communication_customlink';
        $commid = $this->communication->get_id();

        $rowid = $DB->get_field(
            $tablename,
            'id',
            ['commid' => $commid]
        );

        if ($rowid !== false) {
            // Update record.
            $dbrecord = new \stdClass();
            $dbrecord->id = $rowid;
            $dbrecord->url = $instance->customlink;
            $DB->update_record($tablename, $dbrecord);
        } else {
            // Create the record.
            $dbrecord = new \stdClass();
            $dbrecord->commid = $commid;
//$dbrecord->roomid = $this->matrixrooms->get_matrix_room_id(),
            $dbrecord->url = $instance->customlink ?? null; //TODO - Null probably currently not accepted
            $dbrecord = $DB->insert_record($tablename, $dbrecord);

        }
    }

    public function set_form_data(\stdClass $instance): void {
//TODO
        // if (!empty($instance->id) && !empty($this->communication->get_id())) {
        //     $instance->url = $this->matrixrooms->get_customlink_url();
        // }
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

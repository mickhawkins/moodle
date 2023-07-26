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
 * @package   communication_customlink
 * @copyright 2023 Michael Hawkins <michaelh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_feature implements
    \core_communication\communication_provider,
    \core_communication\room_chat_provider,
    \core_communication\form_provider {

    /** @var \cache_application $cache The application cache for this provider. */
    private $cache;

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
        $this->cache = \cache::make('communication_customlink', 'customlink');
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
     * @return string|null The custom URL, or null if not found.
     */
    public function get_chat_room_url(): ?string {
        global $DB;

        $commid = $this->communication->get_id();

        if (!empty($commid)) {
            $cachekey = "link_url_{$commid}";

            // Attempt to fetch the room URL from the cache.
            if ($url = $this->cache->get($cachekey)) {
                return $url;
            }

            // If not found in the cache, fetch the URL from the database.
            $url = $DB->get_field(
                'communication_customlink',
                'url',
                ['commid' => $commid],
            );

            $url = $url ?? null;

            // Cache the URL.
            $this->cache->set($cachekey, $url);
        } else {
            $url = null;
        }

        return $url;
    }

    public function save_form_data(\stdClass $instance): void {
        global $DB;

        $tablename = 'communication_customlink';
        $commid = $this->communication->get_id();

        $dbrecord = new \stdClass();
        $dbrecord->url = $instance->customlink ?? null; //TODO - is null ever relevant /accepted here or in DB?

        $rowid = $DB->get_field(
            $tablename,
            'id',
            ['commid' => $commid]
        );

        if ($rowid !== false) {
            // Update record.
            $dbrecord->id = $rowid;
            $DB->update_record($tablename, $dbrecord);
        } else {
            // Create the record.
            $dbrecord->commid = $commid;
            $dbrecord = $DB->insert_record($tablename, $dbrecord);
        }

        // Cache the URL.
        $this->cache->set("link_url_{$commid}", $dbrecord->url);
    }

    public function set_form_data(\stdClass $instance): void {
        if (!empty($instance->id) && !empty($this->communication->get_id())) {
            $instance->customlink = $this->get_chat_room_url();
        }
    }

    public static function set_form_definition(\MoodleQuickForm $mform): void {
        // Custom link description for the communication provider.
        $mform->insertElementBefore($mform->createElement('text', 'customlink',
            get_string('customlink', 'communication_customlink'),
            'maxlength="255" size="20"'), 'addcommunicationoptionshere');
        $mform->addHelpButton('customlink', 'customlink', 'communication_customlink');
        $mform->setType('customlink', PARAM_TEXT);
        $mform->addRule('customlink', get_string('required'), 'required', null, 'client');
        $mform->addRule('customlink', get_string('required'), 'required', null, 'server');
        $mform->addRule('customlink', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('customlink', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');
    }
}

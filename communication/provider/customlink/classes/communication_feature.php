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
    \core_communication\form_provider,
    \core_communication\form_validation_provider,
    \core_communication\room_chat_provider {
    /** @var string The database table storing custom link specific data */
    protected const CUSTOMLINK_TABLE = 'communication_customlink';

    /** @var \cache_application $cache The application cache for this provider. */
    protected \cache_application $cache;

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
        $cachekey = "link_url_{$commid}";

        // Attempt to fetch the room URL from the cache.
        if ($url = $this->cache->get($cachekey)) {
            return $url;
        }

        // If not found in the cache, fetch the URL from the database.
        $url = $DB->get_field(
            self::CUSTOMLINK_TABLE,
            'url',
            ['commid' => $commid],
        );

        // Cache the URL.
        $this->cache->set($cachekey, $url);

        return $url;
    }

    public function save_form_data(\stdClass $instance): void {
        global $DB;

        $commid = $this->communication->get_id();
        $cachekey = "link_url_{$commid}";

        $newrecord = new \stdClass();
        $newrecord->url = $instance->customlinkurl ?? null;

        $existingrecord = $DB->get_record(
            self::CUSTOMLINK_TABLE,
            ['commid' => $commid],
            'id, url'
        );

        if (!$existingrecord) {
            // Create the record if it does not exist.
            $newrecord->commid = $commid;
            $DB->insert_record(self::CUSTOMLINK_TABLE, $newrecord);
        } else if ($newrecord->url !== $existingrecord->url) {
            // Update record if the URL has changed.
            $newrecord->id = $existingrecord->id;
            $DB->update_record(self::CUSTOMLINK_TABLE, $newrecord);
        } else {
            // No change made.
            return;
        }

        // Cache the new URL.
        $this->cache->set($cachekey, $newrecord->url);
    }

    public function set_form_data(\stdClass $instance): void {
        if (!empty($instance->id) && !empty($this->communication->get_id())) {
            $instance->customlinkurl = $this->get_chat_room_url();
        }
    }

    public static function set_form_definition(\MoodleQuickForm $mform): void {
        // Custom link description for the communication provider.
        $mform->insertElementBefore($mform->createElement(
            'text',
            'customlinkurl',
            get_string('customlinkurl', 'communication_customlink'),
            'maxlength="255" size="40"'
        ), 'addcommunicationoptionshere');
        $mform->addHelpButton('customlinkurl', 'customlinkurl', 'communication_customlink');
        // The URL needs custom validation to support Matrix URLs, so the form element needs to be raw.
        $mform->setType('customlinkurl', PARAM_RAW);
        $mform->addRule('customlinkurl', get_string('required'), 'required', null, 'client');
        $mform->addRule('customlinkurl', get_string('maximumchars', '', 255), 'maxlength', 255);
        $mform->insertElementBefore($mform->createElement(
            'static',
            'customlinkurlinfo',
            '',
            get_string('customlinkurlinfo', 'communication_customlink'),
            'addcommunicationoptionshere'
        ), 'addcommunicationoptionshere');
    }

    public static function perform_custom_form_validation(array $data): array {

error_log("HIT CUSTOM VALIDATION");

        try {
            validate_param($data['customlinkurl'], PARAM_URL);
        } catch (\core\exception\invalid_parameter_exception $e) {
            // Failed URL validation - check if it's a Matrix URL (they fail URL spec validation due to using /#/).
            // Matrix URLs follow the format baseurl/#/!roomid, baseurl/#/#roomalias, or baseurl/#/@user.
            $testurl = preg_replace('/\/#\/[!#@]/', '/', $data['customlinkurl'], 1);
//TODO: Check which permutations were actually failing, might be able to just do one instead of the 3 possible !#@ (could be just /#/# that it doesn't like.
// If that's the case, sites pre 4.5 would simply need to use the full room URL instead of the alias (ie check if /#/!blah works fine).
            if ($testurl === $data['customlinkurl']) {
                // Was not a potential Matrix URL, which means it simply fails URL validation.
                error_log("ERROR 1");
                return [
                    'customlinkurl' => get_string('invalidurl', 'error'),
                ];
            }

            // Potentially a Matrix URL. Re-validate with Matrix specific portion (eg /#/#) removed.
            // (If it passes, treat it as a valid Matrix URL).
            try {
                error_log("MIGHT BE MATRIX");
                validate_param($testurl, PARAM_URL);
            } catch (\core\exception\invalid_parameter_exception $e) {
                error_log("ERROR 2");
                return [
                    'customlinkurl' => get_string('invalidurl', 'error'),
                ];
            }
        }
error_log("VALID URL");
        // No validation errors identified.
        return [];
    }

    public static function is_configured(): bool {
        return true;
    }
}

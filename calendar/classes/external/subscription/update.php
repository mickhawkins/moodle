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

/**
 * Calendar external API for updating the subscription.
 *
 * @package core_calendar
 * @category external
 * @copyright 2021 Huong Nguyen <huongnv13@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_calendar\external\subscription;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;

/**
 * Calendar external API for updating the subscription.
 *
 * @package core_calendar
 * @category external
 * @copyright 2021 Huong Nguyen <huongnv13@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update extends external_api {

    /**
     * Describes the parameters for updating the subscription.
     *
     * @return external_function_parameters
     * @since Moodle 4.0
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'subscriptionid' => new external_value(PARAM_INT, 'The id of the subscription', VALUE_REQUIRED),
            'pollinterval' => new external_value(PARAM_INT, 'The poll interval of the subscription', VALUE_REQUIRED)
        ]);
    }

    /**
     * External function to update the calendar subscription.
     *
     * @param int $subscriptionid Subscription id.
     * @param int $pollinterval Poll interval.
     * @return array
     */
    public static function execute(int $subscriptionid, int $pollinterval): array {
        [
            'subscriptionid' => $subscriptionid,
            'pollinterval' => $pollinterval
        ] = self::validate_parameters(self::execute_parameters(), [
            'subscriptionid' => $subscriptionid,
            'pollinterval' => $pollinterval
        ]);
        $status = false;
        $warnings = [];
        $results = [];
        if (calendar_can_edit_subscription($subscriptionid)) {
            // Fetch the subscription from the database making sure it exists.
            $sub = calendar_get_subscription($subscriptionid);
            // Skip updating file subscriptions.
            if (empty($sub->url)) {
                return [
                    'status' => true,
                    'warnings' => $warnings
                ];
            }
            $sub->pollinterval = $pollinterval;
            calendar_update_subscription($sub);
            $status = true;
            $results = calendar_update_subscription_events($subscriptionid);
        } else {
            $warnings[] = [
                'item' => $subscriptionid,
                'warningcode' => 'errordeletingsubscription',
                'message' => get_string('nopermissions', 'error', get_string('managesubscriptions', 'calendar'))
            ];
        }
        return [
            'status' => $status,
            'results' => $results,
            'warnings' => $warnings
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     * @since Moodle 4.0
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'status: true if success'),
            'results' => new external_single_structure([
                'eventsimported' => new external_value(PARAM_INT, 'Total number of imported events'),
                'eventsskipped' => new external_value(PARAM_INT, 'Total number of skipped events'),
                'eventsupdated' => new external_value(PARAM_INT, 'Total number of updated events'),
                'eventsdeleted' => new external_value(PARAM_INT, 'Total number of deleted events'),
                'haserror' => new external_value(PARAM_BOOL, 'Has error or not'),
                'errors' => new external_multiple_structure(new external_value(PARAM_TEXT))
            ]),
            'warnings' => new external_warnings()
        ]);
    }
}

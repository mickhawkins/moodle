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
 * User steps definition.
 *
 * @package    core_user
 * @category   test
 * @copyright  2017 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException;

/**
 * Steps definitions for users.
 *
 * @package    core_user
 * @category   test
 * @copyright  2017 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_user extends behat_base {

    /**
     * Choose from the bulk action menu.
     *
     * @Given /^I choose "(?P<nodetext_string>(?:[^"]|\\")*)" from the participants page bulk action menu$/
     * @param string $nodetext The menu item to select.
     */
    public function i_choose_from_the_participants_page_bulk_action_menu($nodetext) {
        $this->execute("behat_forms::i_set_the_field_to", [
            "With selected users...",
            $this->escape($nodetext)
        ]);
    }

    /**
     * The input field should have autocomplete set to this value.
     *
     * @Then /^the field "(?P<field_string>(?:[^"]|\\")*)" should have purpose "(?P<purpose_string>(?:[^"]|\\")*)"$/
     * @param string $field The field to select.
     * @param string $purpose The expected purpose.
     */
    public function the_field_should_have_purpose($field, $purpose) {
        $fld = behat_field_manager::get_form_field_from_label($field, $this);

        $value = $fld->get_attribute('autocomplete');
        if ($value != $purpose) {
            $reason = 'The "' . $field . '" field does not have purpose "' . $purpose . '"';
            throw new ExpectationException($reason, $this->getSession());
        }
    }

    /**
     * The input field should not have autocomplete set to this value.
     *
     * @Then /^the field "(?P<field_string>(?:[^"]|\\")*)" should not have purpose "(?P<purpose_string>(?:[^"]|\\")*)"$/
     * @param string $field The field to select.
     * @param string $purpose The expected purpose we do not want.
     */
    public function the_field_should_not_have_purpose($field, $purpose) {
        $fld = behat_field_manager::get_form_field_from_label($field, $this);

        $value = $fld->get_attribute('autocomplete');
        if ($value == $purpose) {
            throw new ExpectationException('The "' . $field . '" field does have purpose "' . $purpose . '"', $this->getSession());
        }
    }

    /**
     * Creates user last access data within given courses.
     *
     * @Given /^I set last access times for the following:$/
     * @param TableNode $tabledata The user last access data to be created or updated.
     */
    public function i_set_last_access_times_for_the_following(TableNode $tabledata) {
        global $DB;

        $courseids = [];
        $userdata = [];

        // Add access times to the relevant courses/users.
        foreach ($tabledata->getHash() as $tablerow) {
            // Fetch course ID if we haven't already.
            if (!array_key_exists($tablerow['course'], $courseids)) {
                $courseids[$tablerow['course']] = $DB->get_field('course', 'id', ['shortname' => $tablerow['course']]);
            }

            // Fetch user table data if we haven't already.
            if (!array_key_exists($tablerow['user'], $userdata)) {
                $userfields = 'id, firstaccess, lastaccess, lastlogin, currentlogin';
                $userdata[$tablerow['user']] = [];
                $userdata[$tablerow['user']]['old'] = $DB->get_record('user', ['username' => $tablerow['user']], $userfields);
                $userdata[$tablerow['user']]['new']['firstaccess'] = $userdata[$tablerow['user']]['old']->firstaccess;
                $userdata[$tablerow['user']]['new']['lastaccess'] = $userdata[$tablerow['user']]['old']->lastaccess;
                $userdata[$tablerow['user']]['new']['lastlogin'] = $userdata[$tablerow['user']]['old']->lastlogin;
                $userdata[$tablerow['user']]['new']['currentlogin'] = $userdata[$tablerow['user']]['old']->currentlogin;
            }

            // Check for lastaccess data for this course.
            $lastaccessdata = [
                'userid' => $userdata[$tablerow['user']]['old']->id,
                'courseid' => $courseids[$tablerow['course']],
            ];

            $lastaccessid = $DB->get_field('user_lastaccess', 'id', $lastaccessdata);

            $dbdata = (object) $lastaccessdata;
            $dbdata->timeaccess = $tablerow['lastaccess'];

            // Set the course last access time.
            if ($lastaccessid) {
                $dbdata->id = $lastaccessid;
                $DB->update_record('user_lastaccess', $dbdata);
            } else {
                $DB->insert_record('user_lastaccess', $dbdata);
            }

            // Store changes to other user access times as needed.

            // Update first access if this is the user's first login, or this access is earlier than their current first access.
            if (empty($userdata[$tablerow['user']]['new']['firstaccess']) ||
                    $userdata[$tablerow['user']]['new']['firstaccess'] > $tablerow['lastaccess']) {
                $userdata[$tablerow['user']]['new']['firstaccess'] = $tablerow['lastaccess'];
            }

            // Update last access if it is the user's most recent access.
            if (empty($userdata[$tablerow['user']]['new']['lastaccess']) ||
                    $userdata[$tablerow['user']]['new']['lastaccess'] < $tablerow['lastaccess']) {
                $userdata[$tablerow['user']]['new']['lastaccess'] = $tablerow['lastaccess'];
            }

            // Update last and current login if it is the user's most recent access.
            if (empty($userdata[$tablerow['user']]['new']['lastlogin']) ||
                    $userdata[$tablerow['user']]['new']['lastlogin'] < $tablerow['lastaccess']) {
                $userdata[$tablerow['user']]['new']['wlastlogin'] = $tablerow['lastaccess'];
                $userdata[$tablerow['user']]['new']['currentlogin'] = $tablerow['lastaccess'];
            }
        }

        // Update user access times once per modified user.
        foreach ($userdata as $data) {
            $updatedata = [];

            if ($data['new']['firstaccess'] != $data['old']->firstaccess) {
                $updatedata['firstaccess'] = $data['new']['firstaccess'];
            }

            if ($data['new']['lastaccess'] != $data['old']->lastaccess) {
                $updatedata['lastaccess'] = $data['new']['lastaccess'];
            }

            if ($data['new']['lastlogin'] != $data['old']->lastlogin) {
                $updatedata['lastlogin'] = $data['new']['lastlogin'];
            }

            if ($data['new']['currentlogin'] != $data['old']->currentlogin) {
                $updatedata['currentlogin'] = $data['new']['currentlogin'];
            }

            // Only update user access data if there have been any changes.
            if (!empty($updatedata)) {
                $updatedata['id'] = $data['old']->id;
                $updatedata = (object) $updatedata;
                $DB->update_record('user', $updatedata);
            }
        }
    }
}

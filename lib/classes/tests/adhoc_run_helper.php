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
 * This file defines a trait to assist with testing of adhoc tasks.
 *
 * @package    core
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/cronlib.php');

/**
 * This trait includes functions to assist with adhoc tasks in tests.
 *
 * @package    core
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait adhoc_run_helper {

    /**
     * Run adhoc tasks, optionally matching the specified classname.
     *
     * @param   string  $matchclass The name of the class to match on.
     *                              This is tested using the php is_a function so parent tasks, interfaces, and traits
     *                              will also match.
     * @param   int     $matchuserid The userid to match.
     */
    protected function run_adhoc_tasks($matchclass = '', $matchuserid = null) {
        global $DB;

        $params = [];
        if (!empty($matchclass)) {
            if (strpos($matchclass, '\\') !== 0) {
                $matchclass = '\\' . $matchclass;
            }
            $params['classname'] = $matchclass;
        }

        if (!empty($matchuserid)) {
            $params['userid'] = $matchuserid;
        }

        $lock = $this->createMock(\core\lock\lock::class);
        $cronlock = $this->createMock(\core\lock\lock::class);

        $tasks = $DB->get_recordset('task_adhoc', $params);
        foreach ($tasks as $record) {
            // Note: This is for cron only.
            // We do not lock the tasks.
            $task = \core\task\manager::adhoc_task_from_record($record);

            $user = null;
            if ($userid = $task->get_userid()) {
                // This task has a userid specified.
                $user = \core_user::get_user($userid);

                // User found. Check that they are suitable.
                \core_user::require_active_user($user, true, true);
            }

            $task->set_lock($lock);
            if (!$task->is_blocking()) {
                $cronlock->release();
            } else {
                $task->set_cron_lock($cronlock);
            }

            cron_prepare_core_renderer();
            $this->setUser($user);

            $task->execute();
            \core\task\manager::adhoc_task_complete($task);

            unset($task);
        }
        $tasks->close();
    }
}

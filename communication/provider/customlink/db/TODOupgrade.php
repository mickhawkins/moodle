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
 * Install steps for communication_customlink.
 *
 * @package    communication_customlink
 * @copyright  2023 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade procedures for the custom link communication plugin.
 *
 * @return bool
 */
function xmldb_communication_customlink_upgrade($oldversion) {
    global $DB;
//TODO - generate properly / correct version
//Also seems like the table would need to be created as well
    $dbman = $DB->get_manager();
    if ($oldversion < 2023071800) {
        $table = new xmldb_table('communication_customlink');
        $field = new xmldb_field('url', XMLDB_TYPE_CHAR, '255', null, false, false, null, 'roomid'); //TODO roomid doesn't exist

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Plugin savepoint reached.
        upgrade_plugin_savepoint(true, 2023071800, 'communication', 'customlink');
    }

    return true;

}

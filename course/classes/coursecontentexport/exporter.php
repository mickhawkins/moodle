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
 * Class exporter.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\coursecontentexport;

use context;
use stdClass;

/**
 * Class exporter.
 *
 * Base class that an activity must implement if it is to support the coursecontentexport API.
 *
 * @package core_course\coursecontentexport
 */
abstract class exporter {

    /**
     * Get the content items for user.
     *
     * @param context $context The context to find items in - usually an context_module
     * @param stdClass $user The user object.
     * @return array
     */
    abstract public static function get_contentitems_for_user(\context $context, \stdClass $user): array;

    /**
     * Get the approved content items for user.
     *
     * @param context $context The context to find items in - usually an context_module
     * @param stdClass $user The user object.
     * @return array
     */
    public static function get_approved_contentitems_for_user(\context $context, \stdClass $user): array {
        return array_map(function($contentitem) {
            return requested_contentitem::approve($contentitem);
        }, static::get_contentitems_for_user($context, $user));
    }

    /**
     * @param array $contentitems Array of content items.
     * @param stdClass $user The user object.
     * @param zipstream $archive The zipstream file resource.
     * @return array
     */
    abstract public static function export_contentitems_for_user(array $contentitems, stdClass $user, zipwriter $archive): array;
}

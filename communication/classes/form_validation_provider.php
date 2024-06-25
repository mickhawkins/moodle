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

namespace core_communication;

/**
 * Interface form_validation_provider to define custom validation required by a provider when validating the provider form.

 * Every plugin that requires custom validation on any of the provider form fields must implement/extend this class in the plugin.
 *
 * @package    core_communication
 * @copyright  2024 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface form_validation_provider {
    /**
     * Custom mform validation to be performed when submitting the provider form data.
     *
     * @param array $data
     * @return array Any errors identified, in the format 'fieldname' => 'error string'. Returns empty array if no errors found.
     */
    public static function perform_custom_form_validation(array $data): array;
}

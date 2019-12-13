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
 * Web services for the security report
 *
 * @package    report_security
 * @copyright  2019 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

//TODO: remove this $services section.
/*$services = [
    // Web service name.
    'securityreportservice' => [
        // Web service functions of this service.
        'functions' => ['report_security_prepare_report_section'],
        'requiredcapability' => 'report/security:view',
        'restrictedusers' => 0,
        // Enabled by default.
        'enabled' => 1,
    ]
];*/

$functions = [
    'report_security_prepare_report_section' => [
        'classname'     => 'report_security_external',
        'methodname'    => 'prepare_report_section',
        'classpath'     => 'report/security/externallib.php',
        'description'   => 'Prepare information for a section of the security report',
        'type'          => 'read',
        'capabilities'  => 'report/security:view',
        'ajax'          => true,
    ]
];


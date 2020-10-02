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
 * Course format export controller for individual course formats to extend.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\content\controllers\export\plugintypes;

use context;
use core\content\controllers\indirect_export_controller;

/**
 * The definition of an export_controller for a course format.
 *
 * Note: This controller will not be called directly by the content API, but by other controllers.
 * In this instance it is expected to be called by the course.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_controller extends indirect_export_controller {
    /** @var string The component name */
    protected $component;

    public function __construct(string $component) {
        $this->component = $component;
    }

    /**
     * Get the contentarea classname for the component.
     *
     * @param   string $component
     * @return  string The classname
     */
    protected static function get_classname_for_component(string $component): string {
        $component = core_component::normalize_componentname($component);

        return "\\{$component}}\\content\\controllers\\export_controller";
    }

    public static function get_controller_instance_for_component(string $component): self {
        $classname = self::get_classname_for_component($component);
        if (class_exists($classname) && is_a($classname, self)) {
            return new $classname($component);
        }

        return new self($component);
    }
}

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
 * Activity module exporter for the content API.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\content\export\exporters;

use context;
use core\content\controllers\export\component_controller;

/**
 * Activity module exporter for the content API.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mod_instance_exporter extends component_exporter {

    /** @var cm_info The activity information for this course module */
    protected $cm;

    /**
     * Constructor for the general activity exporter.
     *
     * @param   context $context The context to export
     * @param   string $component The component that this instance relates to
     * @param   stdClass $user The user to be exported
     * @param   zipwriter $archive
     */
    public function __construct() {
        parent::__construct(...func_get_args());

        $coursecontext = $this->context->get_course_context();
        $modinfo = get_fast_modinfo($coursecontext->instanceid);
        $this->cm = $modinfo->get_cm($this->context->instanceid);
    }

    /**
     * Get the modname for the activity.
     *
     * @return  string
     */
    protected function get_modname(): string {
        return $this->cm->modname;
    }
}

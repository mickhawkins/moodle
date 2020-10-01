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
 * Content API Export definition.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\content\controllers;

use coding_exception;
use context;
use core\content\zipwriter;
use core_component;
use course_modinfo;
use cm_info;
use stdClass;

/**
 * A class to help define, describe, and export content in a specific context.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class export_controller extends abstract_controller {

    /** @var string The component that this instance belongs to */
    protected $component = null;

    /** @var stdClass The user being exported */
    protected $user;

    /** @var ziparchive A reference to the zip archive */
    protected $archive;

    /** @var context The file path requested for export by the user */
    protected $rootcontext;

    /**
     * Get the exportable items for the user in the specified context.
     *
     * Note: This context must be a child of the root context defined in the instance.
     *
     * @param   context $currentcontext The current context being exported
     * @return  exportable_item[]
     */
    abstract public function get_exportable_items_for_user(context $currentcontext): array;

    /**
     * Constructor for a new export controller.
     *
     * @param   string $component The component that this instance relates to
     * @param   stdClass $user The user to be exported
     * @param   context $rootcontext The root context that is being exported
     *          This is primarily used to ensure that file paths are relative
     * @param   zipwriter $archive
     */
    public function __construct(string $component, stdClass $user, context $rootcontext, zipwriter $archive) {
        $this->component = $component;
        $this->user = $user;
        $this->rootcontext = $rootcontext;
        $this->archive = $archive;
    }

    /**
     * Get the component name.
     *
     * @return  string
     */
    public function get_component(): string {
        [$type, $component] = core_component::normalize_component($this->component);
        if ($type === 'core') {
            return $component;
        }

        return core_component::normalize_componentname($this->component);
    }

    /**
     * Get the archive used for export.
     *
     * @return  ziparchive
     */
    public function get_archive(): zipwriter {
        if ($this->archive === null) {
            throw new coding_exception("Archive has not been set up yet");
        }

        return $this->archive;
    }

    /**
     * Get the context that was requested for export.
     *
     * @return  context
     */
    public function get_root_context(): context {
        return $this->rootcontext;
    }

    /**
     * Export the specified items for the user.
     *
     * @param   array $exportableitems Array of exportable items.
     */
    public function export_items_for_user(array $exportableitems): void {
        $pathnames = [];
        foreach ($exportableitems as $item) {
            $item->add_to_archive($this);
        }
    }

    /**
     * Get the course_modinfo from a context.
     *
     * @param   context $modcontext
     * @return  course_modinfo
     */
    protected static function get_course_modinfo_from_context(context $modcontext): course_modinfo {
        $coursecontext = $modcontext->get_course_context();


        return get_fast_modinfo($coursecontext->instanceid);
    }

    /**
     * Get the cm_info from a context.
     *
     * @param   context $modcontext
     * @return  cm_info
     */
    protected static function get_cm_from_context(context $modcontext): cm_info {
        $modinfo = self::get_course_modinfo_from_context($modcontext);

        return $modinfo->get_cm($modcontext->instanceid);
    }

    protected function get_modname(context $currentcontext) {
        $cm = self::get_cm_from_context($currentcontext);

        return $cm->modname;
    }
}

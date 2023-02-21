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

namespace core\moodlenet;

use cm_info;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to store information about a single activity which is being shared to become a MoodleNet resource.
 *
 * @package    core\moodlenet
 * @copyright  2023 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_resource {

    /** @var int Course ID of the course the activity resides in. */
    protected $courseid;

    /** @var cm_info Course module of the activity. */
    protected $cm;

    /** @var string Name of the activity. */
    protected $activityname;

    /** @var string Activity description. */
    protected $activitydescription;

   /**
    * Constructor.
    *
    * @param int $courseid Course ID where the activity/resource is located.
    * @param int $cmid Course module ID of the activity to be shared.
    * @return void
    */
    public function __construct(int $courseid, int $cmid) {
        GLOBAL $DB;

        $this->courseid = $courseid;
        $this->cm = get_fast_modinfo($courseid)->get_cm($cmid);
        $this->activityname = $this->cm->name;
        $this->activitydescription = $DB->get_field($this->cm->modname, 'intro', ['id' => $this->cm->instance]);
    }

    /**
     * Course ID accessor.
     *
     * @return int
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * Activity course module info accessor.
     *
     * @return cm_info
     */
    public function get_cm(): cm_info {
        return $this->cm;
    }

    /**
     * Activity name accessor.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->activityname;
    }

    /**
     * Activity description accessor.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->activitydescription;
    }
}

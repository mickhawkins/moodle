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
 * File containing the class activity information renderable.
 *
 * @package    core_course
 * @copyright  2021 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The activity information renderable class.
 *
 * @package    core_course
 * @copyright  2021 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_information implements renderable, templatable {

    /** @var int The course module ID. */
    protected $cmid = null;

    /** @var string The activity name. */
    protected $activityname = null;

    /**
     * @var array The action link object for the prev link.
     */
    protected $activitydates = null;

    /**
     * @var stdClass The action link object for the next link.
     */
    protected $completiondata = null;

    /**
     * Constructor.
     *
     * @param int $cmid The course module ID.
     * @param string $activityname The activity name.
     * @param stdClass $completiondata The completion data.
     * @param array $activitydates The activity dates.
     */
    public function __construct(int $cmid, string $activityname, stdClass $completiondata, array $activitydates = []) {
        $this->cmid = $cmid;
        $this->activityname = $activityname;
        $this->completiondata = $completiondata;
        $this->activitydates = $activitydates;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->cmid = $this->cmid;
        $data->activityname = $this->activityname;
        $data->activitydates = $this->activitydates;
        $data->hascompletion = $this->completiondata->hascompletion;
        $data->isautomatic = $this->completiondata->isautomatic;
        $data->overrideby = $this->completiondata->overrideby;
        // We'll show only the completion conditions and not the completion status if we're not tracking completion for this user
        // (e.g. a teacher, admin).
        $data->istrackeduser = $this->completiondata->istrackeduser;

        // Automatic completion details.
        $details = [];
        foreach ($this->completiondata->details as $key => $detail) {
            // Set additional attributes for the template.
            $detail->key = $key;
            $detail->statuscomplete = in_array($detail->status, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]);
            $detail->statuscompletefail = $detail->status == COMPLETION_COMPLETE_FAIL;
            $detail->statusincomplete = $detail->status == COMPLETION_INCOMPLETE;

            // Add an accessible description to be used for title and aria-label attributes for overridden completion details.
            if ($data->overrideby) {
                $setbydata = (object)[
                    'condition' => $detail->description,
                    'setby' => $data->overrideby,
                ];
                $detail->accessibledescription = get_string('completion_setby:auto', 'course', $setbydata);
            }

            // We don't need the status in the template.
            unset($detail->status);

            $details[] = $detail;
        }
        $data->completiondetails = $details;

        // Overall completion states.
        $data->overallcomplete = $this->completiondata->overallstatus == COMPLETION_COMPLETE;
        $data->overallincomplete = $this->completiondata->overallstatus == COMPLETION_INCOMPLETE;

        // Set an accessible description for manual completions with overridden completion state.
        if (!$data->isautomatic && $data->overrideby) {
            $setbydata = (object)[
                'activityname' => $data->activityname,
                'setby' => $data->overrideby,
            ];
            $setbylangkey = $data->overallcomplete ? 'completion_setby:manual:done' : 'completion_setby:manual:markdone';
            $data->accessibledescription = get_string($setbylangkey, 'course', $setbydata);
        }

        return $data;
    }
}

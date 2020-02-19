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
 * Class for rendering user filters on the course participants page.
 *
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_user\output;

use context;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for rendering user filters on the course participants page.
 *
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_filter implements renderable, templatable {

    /** @var moodle_url|string $baseurl The url with params used to call this page. */
    protected $baseurl;

    /** @var context $context The context where the filters are being rendered. */
    protected $context;

    /** @var array $filtertypes The filter types to be displayed. */
    protected $filtertypes = [];

    /** @var array $filteroptions The options available for enumerated filter types. */
    protected $filteroptions = [];

    /**
     * User filter constructor.
     *
     * @param context $context The context where the filters are being rendered.
     * @param string|moodle_url $baseurl The url with params needed to call up this page.
     */
    public function __construct(context $context, string $baseurl = null) {
        $this->context = $context;

        if (!empty($baseurl)) {
            $this->baseurl = new moodle_url($baseurl);
        }

        $this->prepare_filters();
    }

    protected function prepare_filters() {
        $canreviewenrol = has_capability('moodle/course:enrolreview', $context);

        // Include status filter if user has access.
        if ($canreviewenrol) {
            $statuslabel = get_string('status');
            $statusname = strtolower($statuslabel);

            $this->filtertypes[$statusname] = $statuslabel;

            $this->filteroptions[$statusname] = [
                ENROL_USER_ACTIVE => get_string('active'),
                ['value' => ENROL_USER_SUSPENDED, 'label' => get_string('inactive')],
            ];
        }

    }

    /**
     * Export the renderer data in a mustache template friendly format.
     *
     * @param renderer_base $output Unused.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->filtertypes = [];
        $data->filteroptions = [];

        foreach ($this->filtertypes as $filtername => $filterlabel) {
            $data->filtertypes[] = (object) [
                'name' => $filtername,
                'label' => $filterlabel,
            ];

            // If filter has options, set them.
            if (!empty($this->filteroptions[$filtername])) {
                foreach ($this->filteroptions[$filtername] as $optionvalue => $optionlabel) {
                    $data->filteroptions[$filtername] = (object) [
                        'value' => $optionvalue,
                        'label' => $optionlabel,
                    ];
                }
            }
        }

        return $data;
    }
}
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
use core_user\participants_table;
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

    /** @var array $matchtypes The match types available within each filter. */
    protected $matchtypes = [];

    /** @var int $matchdefault The value to display on the match type dropdown by default */
    protected $matchtypesdefault;

    /** @var moodle_url|string $baseurl The url with params used to call this page. */
    protected $baseurl;

    /** @var context $context The context where the filters are being rendered. */
    protected $context;

    /** @var array $filtertypes The filter types to be displayed. */
    protected $filtertypes = [];

    /** @var int $filtertypesdefault The value to display on the filter types dropdown by default */
    protected $filtertypesdefault;

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

        $this->prepare_filter_match_types();
        $this->prepare_filters();
    }

    /**
     * Fetch relevant strings and prepare match types for use within each filter condition.
     */
    protected function prepare_filter_match_types() {
        $this->matchtypes = [
            participants_table::MATCH_ALL => get_string('all'),
            participants_table::MATCH_ANY => get_string('any'),
            participants_table::MATCH_NONE => get_string('none'),
        ];

        $this->matchtypesdefault = $this->matchtypes[participants_table::MATCH_ALL];
    }

    /**
     * Prepare filter options available to this user, as well as any values for enumerated filter types.
     */
    protected function prepare_filters() {
        $this->filtertypesdefault = get_string('selectfilter');

        // Status filter.
        $canreviewenrol = has_capability('moodle/course:enrolreview', $this->context);

        // Include status filter if user has access.
        if ($canreviewenrol) {
            $statuslabel = get_string('status');
            $statusname = strtolower($statuslabel);

            $this->filtertypes[$statusname] = [
                'inputtype' => 'enhanceddropdown',
                'label' => $statuslabel,
            ];

            $this->filteroptions[$statusname] = [
                ENROL_USER_ACTIVE => get_string('active'),
                ENROL_USER_SUSPENDED => get_string('inactive'),
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
        $data->matchtypes = [];
        $data->filtertypes = [];
        $data->filteroptions = [];

        $data->matchtypesdefault = $this->matchtypesdefault;
        $data->filtertypesdefault = $this->filtertypesdefault;

        foreach ($this->matchtypes as $matchvalue => $matchlabel) {
            $data->matchtypes[] = (object) [
                'value' => $matchvalue,
                'label' => $matchlabel,
            ];
        }

        foreach ($this->filtertypes as $filtername => $filterinfo) {
            $inputtype = $filterinfo['inputtype'];
            $data->filtertypes[] = (object) [
                'inputtype' => $inputtype,
                'value' => $filtername,
                'label' => $filterinfo['label'],
            ];

            // If filter has options, set them.
            if (!empty($this->filteroptions[$filtername])) {
                $dataoptions = [
                    'filtername' => $filtername,
                    'options' => [],
                ];

                foreach ($this->filteroptions[$filtername] as $optionvalue => $optionlabel) {
                    $dataoptions['options'][] = [
                        'value' => $optionvalue,
                        'label' => $optionlabel,
                    ];
                }

                // Insert the options into the correct input type in the template.
                $data->$inputtype[] = (object) $dataoptions;
            }
        }

        return $data;
    }
}
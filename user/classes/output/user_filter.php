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

    /** @var array $jointypes The join types available within each filter. */
    protected $jointypes = [];

    /** @var int $jointypesdefault The value to display on the join type dropdown by default */
    protected $jointypesdefault;

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
     * @param string|moodle_url (optional) $baseurl The url with params needed to call up this page.
     */
    public function __construct(context $context, string $baseurl = null) {
        $this->context = $context;

        if (!empty($baseurl)) {
            $this->baseurl = new moodle_url($baseurl);
        }

        $this->prepare_filter_join_types();
        $this->prepare_filters();
    }

    /**
     * Fetch relevant strings and prepare join types for use within each filter condition.
     */
    protected function prepare_filter_join_types(): void {
        $this->jointypes = [
            participants_table::JOIN_ALL => get_string('all'),
            participants_table::JOIN_ANY => get_string('any'),
            participants_table::JOIN_NONE => get_string('none'),
        ];

        $this->jointypesdefault = [
            'value' => participants_table::JOIN_ALL,
            'label' => $this->jointypes[participants_table::JOIN_ALL],
        ];
    }

    /**
     * Prepare filter options available to this user, as well as any values for enumerated filter types.
     */
    protected function prepare_filters(): void {
        $this->filtertypesdefault = [
            'value' => '',
            'label' => get_string('selectfilter'),
        ];

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
     * @return stdClass Data in a format compatible with a mustache template.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->jointypes = [];
        $data->filtertypes = [];
        $data->filteroptions = [];

        $data->jointypesdefaultlabel = $this->jointypesdefault['label'];
        $data->jointypesdefaultvalue = $this->jointypesdefault['value'];
        $data->filtertypesdefaultlabel = $this->filtertypesdefault['label'];
        $data->filtertypesdefaultvalue = $this->filtertypesdefault['value'];

        foreach ($this->jointypes as $joinvalue => $joinlabel) {
            $data->jointypes[] = (object) [
                'value' => $joinvalue,
                'label' => $joinlabel,
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
                $data->{$inputtype}[] = (object) $dataoptions;
            }
        }

        return $data;
    }
}
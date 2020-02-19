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

    /** @var array $filtertypes The filter types available. */
    protected $filtertypes = [];

    /** @var array $filteroptions The options available for enumerated filter types. */
    protected $filteroptions = [];

    /** @var moodle_url|string $baseurl The url with params used to call this page. */
    protected $baseurl;

    /**
     * User filter constructor.
     *
     * @param context $context The context object.
     * @param array $filtertypes The types of filters available.
     * @param array $filteroptions The options available for each enumerated filter type.
     * @param string|moodle_url $baseurl The url with params needed to call up this page.
     */
    public function __construct(context $context, array $filtertypes, array $filteroptions, string $baseurl = null) {
        $this->filtertypes = $filtertypes;
        $this->filteroptions = $filteroptions;

        //TODO: Probably remove filteroptions from being passed in, can be determined based on filtertypes.

        $this->prepare_filter_options();

        if (!empty($baseurl)) {
            $this->baseurl = new moodle_url($baseurl);
        }
    }

    /**
     * Prepares the options available for the filter types.
     */
    protected function prepare_filter_options() {
        foreach ($this->filtertypes as $filter) {

        }
    }

    /**
     * Export the renderer data in a mustache template friendly format.
     *
     * @param renderer_base $output Unused.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $data = new stdClass();

        foreach ($this->selectedoptions as $option) {
            if (!isset($this->filteroptions[$option])) {
                $this->filteroptions[$option] = $option;
            }
        }

        $data->filteroptions = [];
        $originalfilteroptions = [];
        foreach ($this->filteroptions as $value => $label) {
            $selected = in_array($value, $this->selectedoptions);
            $filteroption = (object)[
                'value' => $value,
                'label' => $label
            ];
            $originalfilteroptions[] = $filteroption;
            $filteroption->selected = $selected;
            $data->filteroptions[] = $filteroption;
        }
        $data->originaloptionsjson = json_encode($originalfilteroptions);
        return $data;
    }
}
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
 * Download course content confirmation and execution.
 *
 * @package    core
 * @subpackage course
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');

use core_course\coursecontentexport\manager;
use core_course\coursecontentexport\zipwriter;

$contextid = required_param('contextid', PARAM_INT);
$isdownload = optional_param('download', 0, PARAM_BOOL);
$coursecontext = context::instance_by_id($contextid);

// Currently only support course content downloads on a per-course basis.
if (!($coursecontext instanceof context_course)) {
    redirect(new moodle_url('/'));
}

$PAGE->set_url('/course/downloadcontent.php', ['contextid' => $contextid]);

$courseid = $coursecontext->instanceid;
require_login($courseid);

$courseinfo = get_fast_modinfo($courseid)->get_course();
$filename = str_replace('/', '', str_replace(' ', '_', $courseinfo->shortname)) . '_' . time() . '.zip';

// If download confirmed, prepare and start the zipstream of the course download content.
if ($isdownload) {
    confirm_sesskey();

    $exportoptions = null;

    if (!empty($CFG->maxsizepercoursedownloadfile)) {
        $exportoptions = new stdClass();
        $exportoptions->maxfilesize = $CFG->maxsizepercoursedownloadfile;
    }

    $streamwriter = zipwriter::get_stream_writer($filename, $exportoptions);

    manager::export_all_content_for_course($coursecontext, $streamwriter);

    redirect(new moodle_url("/course/view.php?id={$courseid}"));
} else {
    $PAGE->set_title(get_string('downloadcoursecontent', 'course'));
    $PAGE->set_heading(format_string($courseinfo->fullname));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('downloadcoursecontent', 'course'));

    // Prepare download confirmation information and display it.
    $modulenames = manager::get_supported_modules($coursecontext);
    $confirmationvalues = [
        'modules' => '<strong>' . join(', ', $modulenames) . '</strong>',
        'maxfilesize' => display_size($CFG->maxsizepercoursedownloadfile),
    ];

    echo $OUTPUT->confirm(get_string('coursedownloadconfirmation', 'course', $confirmationvalues),
        "/course/downloadcontent.php?contextid={$contextid}&download=1",
        "/course/view.php?id={$courseid}");
}

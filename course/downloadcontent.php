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

$courseid = required_param('courseid', PARAM_INT);
$isdownload = optional_param('download', 0, PARAM_BOOL);

$PAGE->set_url('/course/downloadcontent.php', array('courseid'=>$courseid));
require_login($courseid);

$coursecontext = context_course::instance($courseid);
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

    redirect("/course/view.php?id={$courseid}");
} else {
    // Prepare download confirmation information and display it.
    $modulenames = manager::get_supported_modules($coursecontext);

    //TODO: lang string(s)
    $text = "You are about to download a zip file of course content ({$filename}), which may include the following activities:";
    $text .= '<br><ul>';

    foreach ($modulenames as $modname) {
        $text .= '<li>' . ucfirst($modname) . '</li><br>';
    }

    echo $OUTPUT->header('sdfsdf'); // format_string($courseinfo->fullname)
    echo $OUTPUT->heading(get_string('downloadcoursecontent', 'course'));

    echo $OUTPUT->confirm($text,
        "/course/downloadcontent.php?courseid={$courseid}&download=1",
        "/course/view.php?id={$courseid}");

}

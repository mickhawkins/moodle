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
 * This script displays the forum summary report for the given parameters, within a user's capabilities.
 *
 * @package   forumreport_summary
 * @copyright 2019 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../config.php");

if (isguestuser()) {
    print_error('noguest');
}

$courseid = required_param('courseid', PARAM_INT);
$forumid = optional_param('forumid', 0, PARAM_INT);
$perpage = optional_param('perpage', \forumreport_summary\summary_table::DEFAULT_PER_PAGE, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$filters = [];
$pageurlparams = [
    'courseid' => $courseid,
    'perpage' => $perpage,
];

// Establish filter values.
$filters['groups'] = optional_param_array('filtergroups', [], PARAM_INT);
$filters['datefrom'] = optional_param_array('datefrom', ['enabled' => 0], PARAM_INT);
$filters['dateto'] = optional_param_array('dateto', ['enabled' => 0], PARAM_INT);

$modinfo = get_fast_modinfo($courseid);
$course = $modinfo->get_course();
$courseforums = $modinfo->instances['forum'];
$cms = [];

if ($forumid) {
    $filters['forums'] = [$forumid];

    if (!isset($courseforums[$forumid])) {
        throw new \moodle_exception("A valid forum ID is required to generate a summary report.");
    }

    $foruminfo = $courseforums[$forumid];
    $title = $foruminfo->name;
    $forumcm = $foruminfo->get_course_module_record();
    $cms[] = $forumcm;

    require_login($courseid, false, $forumcm);
    $context = \context_module::instance($forumcm->id);
    $canexport = !$download && has_capability('mod/forum:exportforum', $context);
    $redirecturl = new moodle_url("/mod/forum/view.php");
    $redirecturl->param('id', $forumid);
    $pageurlparams['forumid'] = $forumid;
    $accessallforums = false;
} else {
    // Course level report
    require_login($courseid, false);

    // Fetch the forum cms for the course.
    foreach ($courseforums as $courseforum) {
        $cms[] = $courseforum->get_course_module_record();
    }

    $context = \context_course::instance($courseid);
    $title = $course->fullname;
    // Export currently only supports single forum exports.
    $canexport = false;
    $redirecturl = new moodle_url("/course/view.php");
    $redirecturl->param('id', $courseid);

    // Determine which forums the user has access to in the course, and whether that is all forums in the course.
    $modinfo = get_fast_modinfo($courseid);
    $foruminstances = $modinfo->instances['forum'];
    $allforumsincourse = array_keys($foruminstances);
    $forumsvisibletouser = array_filter($foruminstances, function($foruminstance) {
        return $foruminstance->uservisible;
    });
    $forumsvisiblekeys = array_keys($forumsvisibletouser);

    $filters['forums'] = $forumsvisiblekeys;

    $accessallforums = empty(array_diff($allforumsincourse, $forumsvisiblekeys));
}

$pageurl = new moodle_url("/mod/forum/report/summary/index.php", $pageurlparams);

$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('report');
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('nodetitle', "forumreport_summary"));

$allowbulkoperations = !$download && !empty($CFG->messaging) && has_capability('moodle/course:bulkmessaging', $context);
$canseeprivatereplies = false;
$privatereplycapcount = 0;
$canview = false;

foreach ($filters['forums'] as $forumidfilter) {
    $forumcontext = \context_module::instance($forumidfilter);

    // This capability is required in at least one of the given contexts to view any version of the report.
    if (has_capability("forumreport/summary:view", $forumcontext)) {
        $canview = true;
    }

    if (has_capability('mod/forum:readprivatereplies', $forumcontext)) {
        $privatereplycapcount++;
    }
}

if (!$canview) {
    redirect($redirecturl);
}

// Only use private replies if user has that cap in every forum context in the report.
if (count($filters['forums']) === $privatereplycapcount) {
    $canseeprivatereplies = true;
}

echo "Can view: " . var_export($canview, true) . '<br>';
echo "Private replies: " . var_export($canseeprivatereplies, true);exit;

// Prepare and display the report.
$table = new \forumreport_summary\summary_table($courseid, $filters, $allowbulkoperations,
        $canseeprivatereplies, $perpage, $canexport, $accessallforums);
$table->baseurl = $pageurl;

$eventparams = [
    'context' => $context,
    'other' => [
        'forumid' => $forumid,
        'hasviewall' => has_capability('forumreport/summary:viewall', $context), //todo - per forum
    ],
];

if ($download) {
    \forumreport_summary\event\report_downloaded::create($eventparams)->trigger();
    $table->download($download);
} else {
    \forumreport_summary\event\report_viewed::create($eventparams)->trigger();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('summarytitle', 'forumreport_summary', $title), 2, 'p-b-2');

    if (!empty($filters['groups'])) {
        \core\notification::info(get_string('viewsdisclaimer', 'forumreport_summary'));
    }

    // Render the report filters form.
    $renderer = $PAGE->get_renderer('forumreport_summary');

    unset($filters['forums']);
    echo $renderer->render_filters_form($cms, $pageurl, $filters);
    $table->show_download_buttons_at(array(TABLE_P_BOTTOM));
    echo $renderer->render_summary_table($table);
    echo $OUTPUT->footer();
}

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
 * This file is the entry point to the assign module. All pages are rendered from here
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, true, $cm);

/////////////TEST/////////////

/** Test the MN activity sender */
$courseid = $course->id;
$cmid = 569; //large file $cm->id; //
$userid = 2;
$httpclient = new \core\http_client();
$issuerid = 14;
$issuer = \core\oauth2\api::get_issuer($issuerid);
$additionalscopes = '';
//$oauthclient = \core\oauth2\api::get_system_oauth_client($issuer);
$oauthclient =  \core\oauth2\api::get_user_oauth_client($issuer, new moodle_url($CFG->wwwroot), $additionalscopes);
$shareformat = \core\moodlenet\activity_sender::SHARE_FORMAT_BACKUP;
$result = \core\moodlenet\activity_sender::share_activity($courseid, $cmid, $userid, $httpclient, $oauthclient, $shareformat);

echo "<pre>" . var_export($result, true) . "</pre>";

exit();

/** Test file packaging stuff only */
//  $resourceinfo = new \core\moodlenet\activity_resource($course->id, $cm->id);

//  $packager = new \core\moodlenet\activity_packager($resourceinfo);
//  $filedata = $packager->get_package();

//echo "<pre>" . var_export($file, true) . "</pre>";exit;

 // Use this to test what is being downloaded.
 header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
 header("Content-Type: application/zip");
 header("Content-Transfer-Encoding: Binary");
 header("Content-Length:". $filedata['storedfile']->get_filesize());
 header("Content-Disposition: attachment; filename=" . $filedata['storedfile']->get_filename());
 echo $filedata['filecontents'];
 die();

/////////////END TEST/////////////

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

$assign = new assign($context, $cm, $course);
$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_ALPHA),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('useridlistid', $assign->get_useridlist_key_id(), PARAM_ALPHANUM));

$url = new moodle_url('/mod/assign/view.php', $urlparams);
$PAGE->set_url($url);

// Update module completion status.
$assign->set_module_viewed();

// Apply overrides.
$assign->update_effective_access($USER->id);

// Get the assign class to
// render the page.
echo $assign->view(optional_param('action', '', PARAM_ALPHA));

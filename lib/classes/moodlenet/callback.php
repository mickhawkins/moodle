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
 * MoodleNet callback.
 *
 * @package    core\moodlenet
 * @copyright  2023 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\moodlenet;

use core\oauth2\api;
use core_php_time_limit;
use moodle_url;

require_once(__DIR__ . '/../../../config.php');
require_login();

// Parameters.
$issuerid = required_param('issuerid', PARAM_INT);

// Headers to make it not cacheable.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Wait as long as it takes for this script to finish.
core_php_time_limit::raise();

$issuer = api::get_issuer($issuerid);
$returnurl = new moodle_url('/lib/classes/moodlenet/callback.php');
$returnurl->param('issuerid', $issuerid);
$returnurl->param('callback', 'yes');
$returnurl->param('sesskey', sesskey());
$oauthclient = api::get_user_oauth_client($issuer, $returnurl, activity_sender::API_SCOPE_CREATE, true);
$oauthclient->is_logged_in(); // Will upgrade the auth code to a token.

$strhttpsbug = json_encode(get_string('moodlenet:share_fail_title', 'moodle'));

// Javascript code to call the MoodleNet authorization again in the opener window.
$js = <<<EOD
<html>
<head>
    <script type="text/javascript">
    try {
        if (window.opener) {
            window.opener.moodleNetAuthorize();
            setTimeout(() => {
                window.close();
            }, 300);
        } else {
            throw new Error({$strhttpsbug});
        }
    } catch (e) {
        throw new Error({$strhttpsbug});
    }
    </script>
</head>
<body>
</body>
</html>
EOD;

die($js);

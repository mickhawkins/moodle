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

namespace core\moodlenet;

use Exception;
use core\event\moodlenet_resource_exported;
use core\http_client;
use core\oauth2\client;
use core\oauth2\issuer;

defined('MOODLE_INTERNAL') || die();

/**
 * API for sharing Moodle LMS activities to MoodleNet instances.
 *
 * @package    core\moodlenet
 * @copyright  2023 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class activity_sender {

    /**
     * @var int Backup share format - the content is being shared as a Moodle backup file.
     */
    public const SHARE_FORMAT_BACKUP = 0;

    /**
     * @var string MoodleNet resource creation endpoint URI.
     */
    protected const API_CREATE_URI = '/.pkg/@moodlenet/ed-resource/basic/v1/create';

    /**
     * @var int Maximum upload file size (1.07 GB).
     */
    protected const MAX_FILESIZE = 1070000000;

    /**
     * Share an activity/resource to MoodleNet.
     *
     * @param int $courseid The course ID where the activity is located.
     * @param int $cmid The course module ID of the activity being shared.
     * @param int $userid The user ID who is sharing the activity.
     * @param \core\http_client $httpclient the httpclient object being used to perform the share.
     * @param \core\oauth2\client $oauthclient The OAuth 2 client for the MoodleNet instance.
     * @param int $shareformat The data format to share in. Defaults to a Moodle backup (SHARE_FORMAT_BACKUP).
     * @return array The HTTP response code from MoodleNet and the MoodleNet draft resource URL (URL empty string on fail).
     *               Format: ['responsecode' => 201, 'drafturl' => 'draft.url/here']
     */
    public static function share_activity(int $courseid, int $cmid, int $userid,
            http_client $httpclient, client $oauthclient, int $shareformat = self::SHARE_FORMAT_BACKUP): array {
        global $CFG;

        $accesstoken = '';
        $isfileshare = false;
        $issuer = $oauthclient->get_issuer();
        $resourceurl = '';
        $responsecode = 0;

        // Check user can share to the requested MoodleNet instance.
        $coursecontext = \context_course::instance($courseid);
        require_capability('moodle/moodlenet:sendactivity', $coursecontext, $userid);

//TODO - temporarily bypassing the actual issuer checks here
        if (1==1) {
//        if ($CFG->enablesharingtomoodlenet && self::is_valid_instance($issuer) && $oauthclient->is_logged_in()) {
            $accesstoken = 't0k3n'; //$oauthclient->get_accesstoken();
        } else {
            $responsecode = 404;
        }

        // Only attempt to prepare and send the resource if validation has passed and we have an OAuth 2 token.
        if (!$responsecode && $accesstoken) {
            $resourceinfo = new activity_resource($courseid, $cmid, $userid);
            $requestdata = [];

            // Prepare file in requested format.
            $moodleneturl = $issuer->get('baseurl');
            $filedata = self::prepare_share_contents($resourceinfo, $shareformat);
            $isfileshare = !empty($filedata['file']);
            $apiurl = rtrim($moodleneturl, '/') . self::API_CREATE_URI;

            // Multipart API request to MoodleNet if a file is being sent (eg a .mbz).
            if ($isfileshare) {

                // Avoid sending a file larger than the defined limit.
                if ($filedata['file']->get_filesize() > self::MAX_FILESIZE) {
                    // "Payload too large" HTTP code.
                    $responsecode = 413;
                    self::log_event($coursecontext, $cmid, $resourceurl, $responsecode);
                    $filedata['file']->delete();

                    return [
                        'responsecode' => $responsecode,
                        'drafturl' => '',
                    ];
                }

                $filecontents = '';
                $fh = $filedata['file']->get_content_file_handle();
                while($fileline = fgets($fh)) {
                    $filecontents .= $fileline;
                }
                fclose($fh);

                $requestdata = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accesstoken,
                    ],
                    'multipart' => [
                        [
                            'name' => 'metadata',
                            'contents' => json_encode([
                                'name' => $resourceinfo->get_name(),
                                'description' => $resourceinfo->get_description(),
                            ]),
                            'headers' => [
                                'Content-Disposition' => 'form-data; name="."',
                            ],
                        ],
                        [
                            'name' => 'filecontents',
                            'contents' => $filecontents,
                            'headers' => [
                                'Content-Disposition' => 'form-data; name=".resource"; filename="'. $filedata['file']->get_filename() . '"',
                                'Content-Type' => $filedata['file']->get_mimetype(),
                                'Content-Transfer-Encoding' => 'binary',
                            ],
                        ],
                    ],
                ];
            }

            try {
                $response = $httpclient->request('POST', $apiurl, $requestdata);
                $responsecode = $response->getStatusCode();
            } catch(Exception $e) {
                // Something went wrong - set a known fail response.
                $responsecode = 401;
            };

            if ($responsecode == 201) {
                $responsebody = json_decode($response->getBody());
                $resourceurl = $responsebody->homepage;

                // TODO: Store consumable information about completed share - to be completed in MDL-77296.
            }
        }

        // Log attempt to share (and whether or not it was successful).
        self::log_event($coursecontext, $cmid, $resourceurl, $responsecode);

        if ($isfileshare && $shareformat === self::SHARE_FORMAT_BACKUP) {
            // If shared as a file, delete the file now it is no longer required.
            // Note: This is only valid behaviour when sharing is performed synchronously and no retries are performed on failure.
            $filedata['file']->delete();
        }

        return [
            'responsecode' => $responsecode,
            'drafturl' => $resourceurl,
        ];
    }

    /**
     * Check whether the specified issuer is configured as a MoodleNet instance that can be shared to.
     *
     * @return bool true if the issuer is enabled and available to share to.
     */
    protected static function is_valid_instance(issuer $issuer): bool {
        $issuerid = $issuer->get('id');
//TODO: This is wrong:
        $allowedissuer = get_config('core', 'moodlenet/oauthservice');

        return ($issuerid == $allowedissuer && $issuer->get('enabled') && $issuer->get('servicetype') == 'moodlenet');
    }

    /**
     * Prepare the data for sharing, in the format specified.
     *
     * @param activity_resource $resourceinfo Information about the resource being shared.
     * @param int $shareformat The share format to prepare (eg SHARE_FORMAT_BACKUP).
     * @return array Array of metadata about the file, as well as the contents itself. TODO - outline content format
     */
    protected static function prepare_share_contents(activity_resource $resourceinfo, int $shareformat): array {

        switch ($shareformat) {
            case self::SHARE_FORMAT_BACKUP:
                // If sharing the activity as a backup, prepare the packaged backup.
                $packager = new activity_packager($resourceinfo);
                $filedata = $packager->get_package();
                break;
            default:
                $filedata = [];
                break;
        };

        return $filedata;
    }

    /**
     * Log an event to the admin logs for an outbound share attempt.
     *
     * @param \context $coursecontext
     * @param integer $cmid
     * @param string $resourceurl
     * @param integer $responsecode
     * @return void
     */
    protected static function log_event(\context $coursecontext, int $cmid, string $resourceurl, int $responsecode): void {
        $event = moodlenet_resource_exported::create([
            'context' => $coursecontext,
            'other' => [
                'cmids' => [$cmid],
                'resourceurl' => $resourceurl,
                'success' => ($responsecode == 201),
            ],
        ]);
        $event->trigger();
    }

//TODO: This probably isn't required    /**
    //  * Delete a resource when it is no longer required (eg it has been sent to MoodleNet).
    //  *
    //  * @param \context $coursecontext The course context where the file exists.
    //  * @param int $shareformat The format the file was shared in.
    //  * @param int $fileitemid The file item ID to identify which file to delete.
    //  * @return void
    //  */
    // protected static function delete_resource_file(\context $coursecontext, int $shareformat, string $fileitemid): void {
    //     // If a file was created for sharing, delete it.
    //     if (!empty($todofileid) && in_array($shareformat, [self::SHARE_FORMAT_BACKUP])) {
    //         $fs = get_file_storage();
    //         $fs->delete_area_files($coursecontext->id, 'core', 'MoodleNet', $fileitemid);
    //         //^TODO: Check this is right - context ID, seems risky potentially deleing for "core'", need to make sure MN is the name used for the file are(or update this)
    //     }
    // }
}

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
 * Manager for completed data requests that have recently expired.
 *
 * @package    tool_dataprivacy
 * @copyright  2018 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_dataprivacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager for completed data requests that have recently expired.
 *
 * @copyright  2018 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expired_data_requests {
    /** @var array The ID and user ID of expired data requests.*/
    protected $expiredrequests = [];

    /**
     * Updates expired completed data requests and removes the associated files.
     *
     * @return int The number of requests that have been marked expired.
     */
    public function delete_expired_requests() {
        $expiredcount = 0;
        $this->expiredrequests = $this->get_expired_completed_requests();

        if ($this->update_expired_request_statuses()) {
            $this->remove_data_request_files();
            $expiredcount = count($this->expiredrequests);
        }

        return $expiredcount;
    }

    /**
     * Fetch completed data requests which are due to expire.
     *
     * @return array Details of completed requests which are due to expire.
     */
    protected function get_expired_completed_requests() {
        global $DB;

        $expiryseconds = get_config('tool_dataprivacy', 'privacyrequestexpiry');
        $expirytime = strtotime("-{$expiryseconds} second");
        $table = data_request::TABLE;
        $sqlwhere = 'type = :export_type AND status = :completestatus AND timemodified <= :expirytime';
        $params = array(
            'export_type' => api::DATAREQUEST_TYPE_EXPORT,
            'completestatus' => api::DATAREQUEST_STATUS_DOWNLOAD_READY,
            'expirytime' => $expirytime,
        );
        $sort = 'id';
        $fields = 'id, userid';

        return $DB->get_records_select_menu($table, $sqlwhere, $params, $sort, $fields, 0, 2000);
    }

    /**
     * Update status on expired completed data requests.
     *
     * @return bool True if requests were updated.
     */
    protected function update_expired_request_statuses() {
        global $DB;

        $ids = array_keys($this->expiredrequests);
        $return = false;

        if (count($ids) > 0) {
            list($insql, $inparams) = $DB->get_in_or_equal($ids);
            $initialparams = array(api::DATAREQUEST_STATUS_EXPIRED, time());
            $params = array_merge($initialparams, $inparams);

            $update = "UPDATE {" . data_request::TABLE . "}
                          SET status = ?, timemodified = ?
                        WHERE id $insql";

            $return = $DB->execute($update, $params);
        }

        return $return;
    }

    /**
     * Remove files created by specified data requests.
     *
     * @return void
     */
    protected function remove_data_request_files() {
        $fs = get_file_storage();

        foreach ($this->expiredrequests as $id => $userid) {
            $usercontext = \context_user::instance($userid);
            $fs->delete_area_files($usercontext->id, 'tool_dataprivacy', 'export', $id);
        }
    }
}

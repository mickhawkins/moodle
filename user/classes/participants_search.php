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
 * Class used to fetch participants based on a filterset.
 *
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace core_user;

use context_course;
use context_helper;
use core_table\local\filter\filterset;
use core_user;
use moodle_recordset;
use user_picture;

defined('MOODLE_INTERNAL') || die;

/**
 * Class used to fetch participants based on a filterset.
 *
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants_search {

    /**
     * @var filterset $filterset The filterset describing which participants to include in the search.
     */
    protected $filterset;

    /**
     * @var int $courseid The course ID being searched.
     */
    protected $courseid;

    /**
     * @var context_course $context The course context being searched.
     */
    protected $context;

    /**
     * @var string[] $userfields Names of any extra user fields to be shown when listing users.
     */
    protected $userfields;

    /**
     * Class constructor.
     *
     * @param filterset $filterset The filterset used to filter the participants in a course.
     */
    public function __construct(filterset $filterset) {
        $this->filterset = $filterset;
        $this->courseid = $this->filterset->get_filter('courseid')->current();
        $this->context = context_course::instance($this->courseid, MUST_EXIST);
        $this->userfields = get_extra_user_fields($this->context);
    }

    /**
     * Fetch participants matching the filterset.
     *
     * @param string $additionalwhere Any additional SQL to add to where.
     * @param array $additionalparams The additional params used by $additionalwhere.
     * @param string $sort Optional SQL sort.
     * @param int $limitfrom Return a subset of records, starting at this point (optional).
     * @param int $limitnum Return a subset comprising this many records (optional, required if $limitfrom is set).
     * @return moodle_recordset
     */
    public function get_participants(string $additionalwhere = '', array $additionalparams = [], string $sort = '',
            int $limitfrom = 0, int $limitnum = 0): moodle_recordset {
        global $DB;

        [
            'select' => $select,
            'from' => $from,
            'where' => $where,
            'params' => $params,
        ] = $this->get_participants_sql($additionalwhere, $additionalparams);

        return $DB->get_recordset_sql("{$select} {$from} {$where} {$sort}", $params, $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of participants for a given course.
     *
     * @param string $additionalwhere Any additional SQL to add to where.
     * @param array $additionalparams The additional params used by $additionalwhere.
     * @return int
     */
    public function get_total_participants_count(string $additionalwhere = '', array $additionalparams = []): int {
        global $DB;

        [
            'from' => $from,
            'where' => $where,
            'params' => $params,
        ] = $this->get_participants_sql($additionalwhere, $additionalparams);

        return $DB->count_records_sql("SELECT COUNT(u.id) {$from} {$where}", $params);
    }

    /**
     * Generate the SQL used to fetch filtered data for the participants table.
     *
     * @param string $additionalwhere Any additional SQL to add to where
     * @param array $additionalparams The additional params
     * @return array
     */
    protected function get_participants_sql(string $additionalwhere, array $additionalparams): array {
        $isfrontpage = ($this->courseid == SITEID);
        $accesssince = $this->filterset->has_filter('accesssince') ? $this->filterset->get_filter('accesssince')->current() : 0;

        [
            'sql' => $esql,
            'params' => $params,
        ] = $this->get_enrolled_sql();

        $joins = ['FROM {user} u'];
        $wheres = [];

        $userfieldssql = user_picture::fields('u', $this->userfields);

        if ($isfrontpage) {
            $select = "SELECT $userfieldssql, u.lastaccess";
            $joins[] = "JOIN ($esql) e ON e.id = u.id"; // Everybody on the frontpage usually.
            if ($accesssince) {
                $wheres[] = user_get_user_lastaccess_sql($accesssince);
            }
        } else {
            $select = "SELECT $userfieldssql, COALESCE(ul.timeaccess, 0) AS lastaccess";
            $joins[] = "JOIN ($esql) e ON e.id = u.id"; // Course enrolled users only.
            // Not everybody has accessed the course yet.
            $joins[] = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)';
            $params['courseid'] = $this->courseid;
            if ($accesssince) {
                $wheres[] = user_get_course_lastaccess_sql($accesssince);
            }
        }

        // Performance hacks - we preload user contexts together with accounts.
        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = 'LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)';
        $params['contextlevel'] = CONTEXT_USER;
        $select .= $ccselect;
        $joins[] = $ccjoin;

        // Apply any role filtering.
        if ($this->filterset->has_filter('roles')) {
            [
                'where' => $roleswhere,
                'params' => $rolesparams,
            ] = $this->get_roles_sql();

            if (!empty($roleswhere)) {
                $wheres[] = "({$roleswhere})";
            }

            if (!empty($rolesparams)) {
                $params = array_merge($params, $rolesparams);
            }
        }

        // Apply any keyword text searches.
        if ($this->filterset->has_filter('keywords')) {
            [
                'wheres' => $keywordswheres,
                'params' => $keywordsparams,
            ] = $this->get_keywords_search_sql();

            if (!empty($keywordswheres)) {
                $wheres = array_merge($wheres, $keywordswheres);
            }

            if (!empty($keywordsparams)) {
                $params = array_merge($params, $keywordsparams);
            }
        }

        // Add any supplied additional WHERE clauses.
        if (!empty($additionalwhere)) {
            $wheres[] = $additionalwhere;
            $params = array_merge($params, $additionalparams);
        }

        // Prepare final values.
        $from = implode("\n", $joins);
        if ($wheres) {
            $where = 'WHERE ' . implode(' AND ', $wheres);
        } else {
            $where = '';
        }

        return [
            'select' => $select,
            'from' => $from,
            'where' => $where,
            'params' => $params,
        ];
    }

    /**
     * Prepare SQL and associated parameters for users enrolled in the course.
     *
     * @return array SQL query data in the format ['sql' => '', 'params' => []].
     */
    protected function get_enrolled_sql() {
        // Default status filter settings. We only show active by default, especially if the user has no capability to review enrolments.
        $onlyactive = true;
        $onlysuspended = false;

        $enrolids = $this->filterset->has_filter('enrolments') ? $this->filterset->get_filter('enrolments')->get_filter_values() : [];
        $groupids = $this->filterset->has_filter('groups') ? $this->filterset->get_filter('groups')->get_filter_values() : [];

        if (has_capability('moodle/course:enrolreview', $this->context) &&
                (has_capability('moodle/course:viewsuspendedusers', $this->context))) {

            $statusids = $this->filterset->has_filter('status') ? $this->filterset->get_filter('status')->get_filter_values() : [-1];

            // If both status IDs are selected, treat it as not filtering by status.
            // TODO - This will only work in the 'Any' case. 'All' and 'Not' cases require the related methods to be refactored.
            if (count($statusids) > 1) {
                $statusid = -1;
            } else {
                $statusid = $statusids[0];
            }

            switch ($statusid) {
                case ENROL_USER_ACTIVE:
                    // Nothing to do here.
                    break;
                case ENROL_USER_SUSPENDED:
                    $onlyactive = false;
                    $onlysuspended = true;
                    break;
                default:
                    // If the user has capability to review user enrolments, but statusid is set to -1, set $onlyactive to false.
                    $onlyactive = false;
                    break;
            }
        }

        $prefix = 'eu_';
        $capjoin = get_enrolled_with_capabilities_join(
                $this->context, $prefix, null, $groupids, $onlyactive, $onlysuspended, $enrolids);

        $sql = "SELECT DISTINCT {$prefix}u.id
                  FROM {user} {$prefix}u
                       {$capjoin->joins}
                 WHERE {$capjoin->wheres}";

        return [
            'sql' => $sql,
            'params' => $capjoin->params,
        ];
    }

    /**
     * Prepare SQL where clause and associated parameters for any roles filtering being performed.
     *
     * @return array SQL query data in the format ['where' => '', 'params' => []].
     */
    protected function get_roles_sql() {
        global $DB;

        $where = '';
        $params = [];

        // Limit list to users with some role only.
        if ($this->filterset->has_filter('roles')) {
            $roleids = $this->filterset->get_filter('roles')->get_filter_values();

            // We want to query both the current context and parent contexts.
            $rolecontextids = $this->context->get_parent_context_ids(true);

            // Get users without any role, if needed.
            if (($withoutkey = array_search(-1, $roleids)) !== false) {
                list($relatedctxsql1, $relatedctxparams1) = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx1');

                $where .= "(u.id NOT IN (SELECT userid FROM {role_assignments} WHERE contextid {$relatedctxsql1}))";
                $params = array_merge($params, $relatedctxparams1);
                unset($roleids[$withoutkey]);

                if (!empty($roleids)) {
                    // Currently only handle 'Any' (logical OR) case within filters.
                    // This needs to be extended to support 'All'/'None' later.
                    $where .= ' OR ';
                }
            }

            // Get users with specified roles, if needed.
            if (!empty($roleids)) {
                list($relatedctxsql2, $relatedctxparams2) = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx2');
                list($roleidssql, $roleidsparams) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

                $where .= "(u.id IN (
                                  SELECT userid
                                    FROM {role_assignments}
                                   WHERE roleid {$roleidssql}
                                     AND contextid {$relatedctxsql2})
                                )";
                $params = array_merge($params, $roleidsparams, $relatedctxparams2);
            }
        }

        return [
            'where' => $where,
            'params' => $params,
        ];
    }

    /**
     * Prepare SQL where clauses and associated parameters for any keyword searches being performed.
     *
     * @return array SQL query data in the format ['wheres' => [], 'params' => []].
     */
    protected function get_keywords_search_sql(): array {
        global $CFG, $DB, $USER;

        $keywords = [];
        $wheres = [];
        $params = [];

        if ($this->filterset->has_filter('keywords')) {
            $keywords = $this->filterset->get_filter('keywords')->get_filter_values();
        }

        foreach ($keywords as $index => $keyword) {
            $searchkey1 = 'search' . $index . '1';
            $searchkey2 = 'search' . $index . '2';
            $searchkey3 = 'search' . $index . '3';
            $searchkey4 = 'search' . $index . '4';
            $searchkey5 = 'search' . $index . '5';
            $searchkey6 = 'search' . $index . '6';
            $searchkey7 = 'search' . $index . '7';

            $conditions = [];
            // Search by fullname.
            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $conditions[] = $DB->sql_like($fullname, ':' . $searchkey1, false, false);

            // Search by email.
            $email = $DB->sql_like('email', ':' . $searchkey2, false, false);
            if (!in_array('email', $this->userfields)) {
                $maildisplay = 'maildisplay' . $index;
                $userid1 = 'userid' . $index . '1';
                // Prevent users who hide their email address from being found by others
                // who aren't allowed to see hidden email addresses.
                $email = "(". $email ." AND (" .
                        "u.maildisplay <> :$maildisplay " .
                        "OR u.id = :$userid1". // User can always find himself.
                        "))";
                $params[$maildisplay] = core_user::MAILDISPLAY_HIDE;
                $params[$userid1] = $USER->id;
            }
            $conditions[] = $email;

            // Search by idnumber.
            $idnumber = $DB->sql_like('idnumber', ':' . $searchkey3, false, false);
            if (!in_array('idnumber', $this->userfields)) {
                $userid2 = 'userid' . $index . '2';
                // Users who aren't allowed to see idnumbers should at most find themselves
                // when searching for an idnumber.
                $idnumber = "(". $idnumber . " AND u.id = :$userid2)";
                $params[$userid2] = $USER->id;
            }
            $conditions[] = $idnumber;

            if (!empty($CFG->showuseridentity)) {
                // Search all user identify fields.
                $extrasearchfields = explode(',', $CFG->showuseridentity);
                foreach ($extrasearchfields as $extrasearchfield) {
                    if (in_array($extrasearchfield, ['email', 'idnumber', 'country'])) {
                        // Already covered above. Search by country not supported.
                        continue;
                    }
                    $param = $searchkey3 . $extrasearchfield;
                    $condition = $DB->sql_like($extrasearchfield, ':' . $param, false, false);
                    $params[$param] = "%$keyword%";
                    if (!in_array($extrasearchfield, $this->userfields)) {
                        // User cannot see this field, but allow match if their own account.
                        $userid3 = 'userid' . $index . '3' . $extrasearchfield;
                        $condition = "(". $condition . " AND u.id = :$userid3)";
                        $params[$userid3] = $USER->id;
                    }
                    $conditions[] = $condition;
                }
            }

            // Search by middlename.
            $middlename = $DB->sql_like('middlename', ':' . $searchkey4, false, false);
            $conditions[] = $middlename;

            // Search by alternatename.
            $alternatename = $DB->sql_like('alternatename', ':' . $searchkey5, false, false);
            $conditions[] = $alternatename;

            // Search by firstnamephonetic.
            $firstnamephonetic = $DB->sql_like('firstnamephonetic', ':' . $searchkey6, false, false);
            $conditions[] = $firstnamephonetic;

            // Search by lastnamephonetic.
            $lastnamephonetic = $DB->sql_like('lastnamephonetic', ':' . $searchkey7, false, false);
            $conditions[] = $lastnamephonetic;

            $wheres[] = "(". implode(" OR ", $conditions) .") ";
            $params[$searchkey1] = "%$keyword%";
            $params[$searchkey2] = "%$keyword%";
            $params[$searchkey3] = "%$keyword%";
            $params[$searchkey4] = "%$keyword%";
            $params[$searchkey5] = "%$keyword%";
            $params[$searchkey6] = "%$keyword%";
            $params[$searchkey7] = "%$keyword%";
        }

        return [
            'wheres' => $wheres,
            'params' => $params,
        ];
    }
}

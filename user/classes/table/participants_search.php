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

namespace core_user\table;

use context;
use context_helper;
use core_table\local\filter\filterset;
use core_user;
use moodle_recordset;
use stdClass;
use user_picture;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/lib.php');

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
     * @var stdClass $course The course being searched.
     */
    protected $course;

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
     * @param stdClass $course The course being searched.
     * @param context $context The context of the search.
     * @param filterset $filterset The filterset used to filter the participants in a course.
     */
    public function __construct(stdClass $course, context $context, filterset $filterset) {
        $this->course = $course;
        $this->context = $context;
        $this->filterset = $filterset;

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
        //TODO - does frontpage need to be handled? If not, can remove some stuff, if so, need to add for some enrolment filtering stuff.
        $isfrontpage = ($this->course->id == SITEID);
        $accesssince = 0;
        // Whether to match on users who HAVE accessed since the given time (ie false is 'inactive for more than x').
        $matchaccesssince = false;

        if ($this->filterset->has_filter('accesssince')) {
            $accesssince = $this->filterset->get_filter('accesssince')->current();

            // Last access filtering only supports matching or not matching, not any/all/none.
            $jointypenone = $this->filterset->get_filter('accesssince')::JOINTYPE_NONE;
            if ($this->filterset->get_filter('accesssince')->get_join_type() === $jointypenone) {
                $matchaccesssince = true;
            }
        }

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
                $wheres[] = user_get_user_lastaccess_sql($accesssince, 'u', $matchaccesssince);
            }
        } else {
            $select = "SELECT $userfieldssql, COALESCE(ul.timeaccess, 0) AS lastaccess";
            $joins[] = "JOIN ($esql) e ON e.id = u.id"; // Course enrolled users only.
            // Not everybody has accessed the course yet.
            $joins[] = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)';
            $params['courseid'] = $this->course->id;
            if ($accesssince) {
                $wheres[] = user_get_course_lastaccess_sql($accesssince, 'ul', $matchaccesssince);
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
                'where' => $keywordswhere,
                'params' => $keywordsparams,
            ] = $this->get_keywords_search_sql();

            if (!empty($keywordswhere)) {
                $wheres[] = $keywordswhere;
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
            switch ($this->filterset->get_join_type()) {
                case $this->filterset::JOINTYPE_ALL:
                    $wheresjoin = ' AND ';
                    break;
                case $this->filterset::JOINTYPE_NONE:
                    $wheresjoin = ' AND NOT ';
                    break;
                default:
                    // Default to 'Any' jointype.
                    $wheresjoin = ' OR ';
                    break;
            }

            $where = 'WHERE ' . implode($wheresjoin, $wheres);
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
    protected function get_enrolled_sql(): array {
        $prefix = 'eu_';
        $uid = "{$prefix}u.id";
        $joins = [];
        $wheres = [];
        $params = [];

        // Prepare any enrolment method filtering.
        [
            'joins' => $methodjoins,
            'where' => $wheres[],
            'params' => $methodparams,
        ] = $this->get_enrol_method_sql($uid);

        // Prepare any status filtering.
        [
            'joins' => $statusjoins,
            'where' => $wheres[],
            'params' => $statusparams,
        ] = $this->get_status_sql($uid);

        $joins = array_merge($joins, $methodjoins, $statusjoins);

        // If no enrolment method / status filtering is taking place, still limit the participants to those enrolled in the course.
        if (empty($joins)) {
            $enrolprefix = 'epre_';
            $joins[] = "JOIN {user_enrolments} {$enrolprefix}ue ON {$enrolprefix}ue.userid = {$uid}";
            $joins[] = "JOIN {enrol} {$enrolprefix}e ON {$enrolprefix}e.id = {$enrolprefix}ue.enrolid
                                                     AND {$enrolprefix}e.courseid = :{$enrolprefix}courseid";
            $params = [
                "{$enrolprefix}courseid" => $this->course->id,
            ];
        }

        // Prepare any groups filtering.
        $groupids = [];
        $groupsparams = [];

        if ($this->filterset->has_filter('groups')) {
            $groupids = $this->filterset->get_filter('groups')->get_filter_values();
        }

        if ($groupids) {
            $groupjoin = groups_get_members_join($groupids, $uid, $this->context, $this->get_groups_jointype());
            $joins[] = $groupjoin->joins;
            $groupsparams = $groupjoin->params;
            if (!empty($groupjoin->wheres)) {
                $wheres[] = $groupjoin->wheres;
            }
        }

        // Combine the relevant filters and prepare the query.
        $joinsql = implode("\n", $joins);
        $params = array_merge($params, $methodparams, $statusparams, $groupsparams);
        $wheres = array_filter($wheres);

        if ($this->filterset->get_join_type() === $this->filterset::JOINTYPE_NONE) {
            $wheresql = 'NOT ' . implode(") AND (", $wheres) . ')';
        } else if ($this->filterset->get_join_type() === $this->filterset::JOINTYPE_ALL) {
            $wheresql = implode(" AND ", $wheres);
        } else {
            $wheresql = implode(" OR ", $wheres);
        }

        $sql = "SELECT DISTINCT {$prefix}u.id
                  FROM {user} {$prefix}u
                       {$joinsql}
                 WHERE {$prefix}u.deleted = 0";

        if (!empty($wheresql)) {
            $sql .= " AND ({$wheresql})";
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * Fetch the groups filter's grouplib jointype, based on its filterset jointype.
     * This mapping is to ensure compatibility between the two, should their values ever differ.
     *
     * @return int
     */
    protected function get_groups_jointype(): int {
        $groupsfilter = $this->filterset->get_filter('groups');

        switch ($groupsfilter->get_join_type()) {
            case $groupsfilter::JOINTYPE_NONE:
                $groupsjoin = GROUPSJOINNONE;
                break;
            case $groupsfilter::JOINTYPE_ALL:
                $groupsjoin = GROUPSJOINALL;
                break;
            default:
                // Default to ANY jointype.
                $groupsjoin = GROUPSJOINANY;
                break;
        }

        return $groupsjoin;
    }

    /**
     * Prepare SQL where clause and associated parameters for any roles filtering being performed.
     *
     * @return array SQL query data in the format ['where' => '', 'params' => []].
     */
    protected function get_roles_sql(): array {
        global $DB;

        $where = '';
        $params = [];

        // Limit list to users with some role only.
        if ($this->filterset->has_filter('roles')) {
            $rolesfilter = $this->filterset->get_filter('roles');

            $roleids = $rolesfilter->get_filter_values();
            $jointype = $rolesfilter->get_join_type();

            // Determine how to match values in the query.
            $matchinsql = 'IN';
            switch ($jointype) {
                case $rolesfilter::JOINTYPE_ALL:
                    $wherejoin = ' AND ';
                    break;
                case $rolesfilter::JOINTYPE_NONE:
                    $wherejoin = ' AND NOT ';
                    $matchinsql = 'NOT IN';
                    break;
                default:
                    // Default to 'Any' jointype.
                    $wherejoin = ' OR ';
                    break;
            }

            // We want to query both the current context and parent contexts.
            $rolecontextids = $this->context->get_parent_context_ids(true);

            // Get users without any role, if needed.
            if (($withoutkey = array_search(-1, $roleids)) !== false) {
                list($relatedctxsql1, $norolectxparams) = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx');

                if ($jointype === $rolesfilter::JOINTYPE_NONE) {
                    $where .= "(u.id IN (SELECT userid FROM {role_assignments} WHERE contextid {$relatedctxsql1}))";
                } else {
                    $where .= "(u.id NOT IN (SELECT userid FROM {role_assignments} WHERE contextid {$relatedctxsql1}))";
                }

                $params = array_merge($params, $norolectxparams);

                if ($withoutkey !== false) {
                    unset($roleids[$withoutkey]);
                }

                // Join if any roles will be included.
                if (!empty($roleids)) {
                    // The NOT case is replaced with AND to prevent a double negative.
                    $where .= $jointype === $rolesfilter::JOINTYPE_NONE ? ' AND ' : $wherejoin;
                }
            }

            // Get users with specified roles, if needed.
            if (!empty($roleids)) {
                // All case - need one WHERE per filtered role.
                if ($rolesfilter::JOINTYPE_ALL === $jointype) {
                    $numroles = count($roleids);
                    $rolecount = 1;

                    foreach ($roleids as $roleid) {
                        list($relatedctxsql, $relctxparams) = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx');
                        list($roleidssql, $roleidparams) = $DB->get_in_or_equal($roleid, SQL_PARAMS_NAMED, 'roleids');

                        $where .= "(u.id IN (
                                     SELECT userid
                                       FROM {role_assignments}
                                      WHERE roleid {$roleidssql}
                                        AND contextid {$relatedctxsql})
                                   )";

                        if ($rolecount < $numroles) {
                            $where .= $wherejoin;
                            $rolecount++;
                        }

                        $params = array_merge($params, $roleidparams, $relctxparams);
                    }

                } else {
                    // Any / None cases - need one WHERE to cover all filtered roles.
                    list($relatedctxsql, $relctxparams) = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx');
                    list($roleidssql, $roleidsparams) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'roleids');

                    $where .= "(u.id {$matchinsql} (
                                 SELECT userid
                                   FROM {role_assignments}
                                  WHERE roleid {$roleidssql}
                                    AND contextid {$relatedctxsql})
                               )";

                    $params = array_merge($params, $roleidsparams, $relctxparams);
                }
            }
        }

        return [
            'where' => $where,
            'params' => $params,
        ];
    }

    /**
     * Prepare SQL where clause and associated parameters for any keyword searches being performed.
     *
     * @return array SQL query data in the format ['where' => '', 'params' => []].
     */
    protected function get_keywords_search_sql(): array {
        global $CFG, $DB, $USER;

        $keywords = [];
        $where = '';
        $params = [];
        $keywordsfilter = $this->filterset->get_filter('keywords');
        $jointype = $keywordsfilter->get_join_type();
        $notjoin = false;

        // Determine how to match values in the query.
        switch ($jointype) {
            case $keywordsfilter::JOINTYPE_ALL:
                $wherejoin = ' AND ';
                break;
            case $keywordsfilter::JOINTYPE_NONE:
                $wherejoin = ' AND NOT ';
                $notjoin = true;
                break;
            default:
                // Default to 'Any' jointype.
                $wherejoin = ' OR ';
                break;
        }

        if ($this->filterset->has_filter('keywords')) {
            $keywords = $keywordsfilter->get_filter_values();
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

            if ($notjoin) {
                $email = "(email IS NOT NULL AND {$email})";
            }

            if (!in_array('email', $this->userfields)) {
                $maildisplay = 'maildisplay' . $index;
                $userid1 = 'userid' . $index . '1';
                // Prevent users who hide their email address from being found by others
                // who aren't allowed to see hidden email addresses.
                $email = "(". $email ." AND (" .
                        "u.maildisplay <> :$maildisplay " .
                        "OR u.id = :$userid1". // Users can always find themselves.
                        "))";
                $params[$maildisplay] = core_user::MAILDISPLAY_HIDE;
                $params[$userid1] = $USER->id;
            }

            $conditions[] = $email;

            // Search by idnumber.
            $idnumber = $DB->sql_like('idnumber', ':' . $searchkey3, false, false);

            if ($notjoin) {
                $idnumber = "(idnumber IS NOT NULL AND  {$idnumber})";
            }

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

                    if ($notjoin) {
                        $condition = "($extrasearchfield IS NOT NULL AND {$condition})";
                    }

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

            if ($notjoin) {
                $middlename = "(middlename IS NOT NULL AND {$middlename})";
            }

            $conditions[] = $middlename;

            // Search by alternatename.
            $alternatename = $DB->sql_like('alternatename', ':' . $searchkey5, false, false);

            if ($notjoin) {
                $alternatename = "(middlename IS NOT NULL AND {$alternatename})";
            }

            $conditions[] = $alternatename;

            // Search by firstnamephonetic.
            $firstnamephonetic = $DB->sql_like('firstnamephonetic', ':' . $searchkey6, false, false);

            if ($notjoin) {
                $firstnamephonetic = "(middlename IS NOT NULL AND {$firstnamephonetic})";
            }

            $conditions[] = $firstnamephonetic;

            // Search by lastnamephonetic.
            $lastnamephonetic = $DB->sql_like('lastnamephonetic', ':' . $searchkey7, false, false);

            if ($notjoin) {
                $lastnamephonetic = "(middlename IS NOT NULL AND {$lastnamephonetic})";
            }

            $conditions[] = $lastnamephonetic;

            if (!empty($where)) {
                $where .= $wherejoin;
            } else if ($jointype === $keywordsfilter::JOINTYPE_NONE) {
                // Join type 'None' requires the WHERE to begin with NOT.
                $where .= ' NOT ';
            }

            $where .= "(". implode(" OR ", $conditions) .") ";
            $params[$searchkey1] = "%$keyword%";
            $params[$searchkey2] = "%$keyword%";
            $params[$searchkey3] = "%$keyword%";
            $params[$searchkey4] = "%$keyword%";
            $params[$searchkey5] = "%$keyword%";
            $params[$searchkey6] = "%$keyword%";
            $params[$searchkey7] = "%$keyword%";
        }

        return [
            'where' => $where,
            'params' => $params,
        ];
    }

    /**
     * Prepare the enrolment methods filter SQL content.
     *
     * @param string $useridcolumn User ID column used in the calling query, e.g. u.id
     * @return array SQL query data in the format ['joins' => [], 'where' => '', 'params' => []].
     */
    protected function get_enrol_method_sql($useridcolumn): array {
        global $DB;

        $prefix = 'ejm_';
        $joins  = [];
        $where = '';
        $params = [];
        $enrolids = [];

        if ($this->filterset->has_filter('enrolments')) {
            $enrolids = $this->filterset->get_filter('enrolments')->get_filter_values();
        }

        $baseenrolconditions = "{$prefix}e.id = {$prefix}ue.enrolid
                      AND {$prefix}e.courseid = :{$prefix}courseid";

        if (!empty($enrolids)) {
            switch ($this->filterset->get_filter('enrolments')->get_join_type()) {
                // Handle 'All' join type.
                case $this->filterset->get_filter('enrolments')::JOINTYPE_ALL:
                    foreach ($enrolids as $i => $enrolid) {
                        $thisprefix = "{$prefix}{$i}";
                        $baseenrolconditions = "{$thisprefix}e.id = {$thisprefix}ue.enrolid
                                      AND {$thisprefix}e.courseid = :{$thisprefix}courseid";

                        list($enrolidsql, $enrolidparam) = $DB->get_in_or_equal($enrolid, SQL_PARAMS_NAMED, $thisprefix);
                        $idconditions = "{$baseenrolconditions} AND {$thisprefix}e.id {$enrolidsql}";

                        $joins[] = "JOIN {user_enrolments} {$thisprefix}ue ON {$thisprefix}ue.userid = {$useridcolumn}";
                        $joins[] = "JOIN {enrol} {$thisprefix}e ON ({$idconditions})";

                        $params["{$thisprefix}courseid"] = $this->context->instanceid;
                        $params = array_merge($params, $enrolidparam);
                    }
                    break;

                case $this->filterset->get_filter('enrolments')::JOINTYPE_NONE:
                    // Handle 'None' join type.

                    // We need to join the enrol table twice, so require a second prefix.
                    $prefix2 = "{$prefix}2";

                    // Need to match users who are in the course, but do not match any of the filtered enrolment methods.
                    list($enrolidssql, $enrolidsparams) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, $prefix2, false);

                    $joins[] = "JOIN {user_enrolments} {$prefix}ue ON {$prefix}ue.userid = {$useridcolumn}";
                    $joins[] = "JOIN {enrol} {$prefix}e ON ({$baseenrolconditions})";
                    $joins[] = "LEFT JOIN {enrol} {$prefix2}e
                                       ON ({$prefix2}e.id = {$prefix}ue.enrolid
                                          AND {$prefix2}e.courseid = :{$prefix2}courseid
                                          AND {$prefix2}e.id {$enrolidssql})";

                    $where = "{$prefix2}e.id IS NULL";
                    $params["{$prefix}courseid"] = $this->context->instanceid;
                    $params = array_merge($params, $enrolidsparams);

                    break;

                default:
                    // Handle the 'Any' join type.
                    list($enrolidssql, $enrolidsparams) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, $prefix);
                    $enrolconditions = "{$baseenrolconditions} AND {$prefix}e.id {$enrolidssql}";

                    $joins[] = "JOIN {user_enrolments} {$prefix}ue ON {$prefix}ue.userid = {$useridcolumn}";
                    $joins[] = "JOIN {enrol} {$prefix}e ON ({$enrolconditions})";

                    $params["{$prefix}courseid"] = $this->context->instanceid;
                    $params = array_merge($params, $enrolidsparams);
                    break;
            }
        }

        return [
            'joins' => $joins,
            'where' => $where,
            'params' => $params,
        ];
    }

    /**
     * Prepare the status filter SQL content.
     *
     * @param string $useridcolumn User ID column used in the calling query, e.g. u.id
     * @return array SQL query data in the format ['joins' => [], 'where' => '', 'params' => []].
     */
    protected function get_status_sql($useridcolumn): array {
        $prefix = 'ejs_';
        $joins  = [];
        $where = '';
        $params = [];

        // By default we filter to show users with active status only.
        $statusids = [ENROL_USER_ACTIVE];
        $statusjointype = $this->filterset::JOINTYPE_DEFAULT;

        // Set additional status filtering if the user has relevant capabilities.
        if (has_capability('moodle/course:enrolreview', $this->context) &&
                (has_capability('moodle/course:viewsuspendedusers', $this->context))) {
            // Default to no filtering if capabilities allow for it.
            $statusids = [];

            if ($this->filterset->has_filter('status')) {
                $statusjointype = $this->filterset->get_filter('status')->get_join_type();
                $statusfiltervalues = $this->filterset->get_filter('status')->get_filter_values();

                // If values are set for the status filter, use them.
                if (!empty($statusfiltervalues)) {
                    $statusids = $statusfiltervalues;
                }
            }
        }

        if (!empty($statusids)) {
            $enroljoin = 'LEFT JOIN {enrol} %1$se ON %1$se.id = %1$sue.enrolid
                                                  AND %1$se.courseid = :%1$scourseid';

            $whereactive = '(%1$sue.status = :%2$sactive
                          AND %1$se.status = :%2$senabled
                      AND %1$sue.timestart < :%2$snow1
                       AND (%1$sue.timeend = 0
                         OR %1$sue.timeend > :%2$snow2))';

            $wheresuspended = '(%1$sue.status = :%2$ssuspended
                             OR %1$se.status != :%2$senabled
                         OR %1$sue.timestart >= :%2$snow1
                           OR (%1$sue.timeend > 0
                          AND %1$sue.timeend <= :%2$snow2))';

            // Round 'now' time to help DB caching.
            $now = round(time(), -2);

            switch ($statusjointype) {
                case $this->filterset::JOINTYPE_ALL:
                    $joinwheres = [];

                    foreach ($statusids as $i => $statusid) {
                        $joinprefix = "{$prefix}{$i}";
                        $joins[] = "JOIN {user_enrolments} {$joinprefix}ue ON {$joinprefix}ue.userid = {$useridcolumn}";

                        if ($statusid === ENROL_USER_ACTIVE) {
                            // Conditions to be met if user filtering by active.
                            $joinwheres[] = sprintf($whereactive, $joinprefix, $joinprefix);

                            $activeparams = [
                                "{$joinprefix}active" => ENROL_USER_ACTIVE,
                                "{$joinprefix}enabled" => ENROL_INSTANCE_ENABLED,
                                "{$joinprefix}now1"   => $now,
                                "{$joinprefix}now2"   => $now,
                                "{$joinprefix}courseid"   => $this->course->id,
                            ];

                            $params = array_merge($params, $activeparams);
                        } else {
                            // Conditions to be met if filtering by suspended (currently the only other status).
                            $joinwheres[] = sprintf($wheresuspended, $joinprefix, $joinprefix);

                            $suspendedparams = [
                                "{$joinprefix}suspended" => ENROL_USER_SUSPENDED,
                                "{$joinprefix}enabled" => ENROL_INSTANCE_ENABLED,
                                "{$joinprefix}now1"   => $now,
                                "{$joinprefix}now2"   => $now,
                                "{$joinprefix}courseid"   => $this->course->id,
                            ];

                            $params = array_merge($params, $suspendedparams);
                        }

                        $joins[] = sprintf($enroljoin, $joinprefix);
                    }

                    $where = '(' . implode(' AND ', $joinwheres) . ')';
                    break;

                case $this->filterset::JOINTYPE_NONE:
                    // Should always be enrolled, just not in any of the filtered statuses.
                    $joins[] = "JOIN {user_enrolments} {$prefix}ue ON {$prefix}ue.userid = {$useridcolumn}";
                    $joins[] = sprintf($enroljoin, $prefix);
                    $joinwheres = [];
                    $params["{$prefix}courseid"] = $this->course->id;

                    foreach ($statusids as $i => $statusid) {
                        $paramprefix = "{$prefix}{$i}";

                        if ($statusid === ENROL_USER_ACTIVE) {
                            // Conditions to be met if user filtering by active.
                            $joinwheres[] = sprintf("NOT {$whereactive}", $prefix, $paramprefix);

                            $activeparams = [
                                "{$paramprefix}active" => ENROL_USER_ACTIVE,
                                "{$paramprefix}enabled" => ENROL_INSTANCE_ENABLED,
                                "{$paramprefix}now1"   => $now,
                                "{$paramprefix}now2"   => $now,
                            ];

                            $params = array_merge($params, $activeparams);
                        } else {
                            // Conditions to be met if filtering by suspended (currently the only other status).
                            $joinwheres[] = sprintf("NOT {$wheresuspended}", $prefix, $paramprefix);

                            $suspendedparams = [
                                "{$paramprefix}suspended" => ENROL_USER_SUSPENDED,
                                "{$paramprefix}enabled" => ENROL_INSTANCE_ENABLED,
                                "{$paramprefix}now1"   => $now,
                                "{$paramprefix}now2"   => $now,
                            ];

                            $params = array_merge($params, $suspendedparams);
                        }
                    }

                    $where = '(' . implode(' AND ', $joinwheres) . ')';
                    break;

                default:
                    // Handle the 'Any' join type.

                    $joins[] = "JOIN {user_enrolments} {$prefix}ue ON {$prefix}ue.userid = {$useridcolumn}";
                    $joins[] = sprintf($enroljoin, $prefix);
                    $joinwheres = [];
                    $params["{$prefix}courseid"] = $this->course->id;

                    foreach ($statusids as $i => $statusid) {
                        $paramprefix = "{$prefix}{$i}";

                        if ($statusid === ENROL_USER_ACTIVE) {
                            // Conditions to be met if user filtering by active.
                            $joinwheres[] = sprintf($whereactive, $prefix, $paramprefix);

                            $activeparams = [
                                "{$paramprefix}active" => ENROL_USER_ACTIVE,
                                "{$paramprefix}enabled" => ENROL_INSTANCE_ENABLED,
                                "{$paramprefix}now1"   => $now,
                                "{$paramprefix}now2"   => $now,
                            ];

                            $params = array_merge($params, $activeparams);
                        } else {
                            // Conditions to be met if filtering by suspended (currently the only other status).
                            $joinwheres[] = sprintf($wheresuspended, $prefix, $paramprefix);

                            $suspendedparams = [
                                "{$paramprefix}suspended" => ENROL_USER_SUSPENDED,
                                "{$paramprefix}enabled" => ENROL_INSTANCE_ENABLED,
                                "{$paramprefix}now1"   => $now,
                                "{$paramprefix}now2"   => $now,
                            ];

                            $params = array_merge($params, $suspendedparams);
                        }
                    }

                    $where = '(' . implode(' OR ', $joinwheres) . ')';
                    break;
            }
        }

        return [
            'joins' => $joins,
            'where' => $where,
            'params' => $params,
        ];
    }
}

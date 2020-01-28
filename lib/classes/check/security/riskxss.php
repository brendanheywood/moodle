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
 * Lists all users with XSS risk
 *
 * It would be great to combine this with risk trusts in user table,
 * unfortunately nobody implemented user trust UI yet :-(
 *
 * @package    core
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2008 petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\check\security;

defined('MOODLE_INTERNAL') || die();

use core\check\check;

/**
 * Lists all users with XSS risk
 *
 * It would be great to combine this with risk trusts in user table,
 * unfortunately nobody implemented user trust UI yet :-(
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2008 petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class riskxss extends security_check {

    /**
     * Constructor
     */
    public function __construct() {

        global $DB;
        $this->id = 'riskxss';
        $this->name = get_string('check_riskxss_name', 'report_security');

        $this->params = array('capallow' => CAP_ALLOW);
        $this->sqlfrom = "FROM (SELECT DISTINCT rcx.contextid, rcx.roleid
                           FROM {role_capabilities} rcx
                           JOIN {capabilities} cap ON (cap.name = rcx.capability AND
                                " . $DB->sql_bitand('cap.riskbitmask', RISK_XSS) . " <> 0)
                           WHERE rcx.permission = :capallow) rc,
                     {context} c,
                     {context} sc,
            {role_assignments} ra,
                        {user} u
                         WHERE c.id = rc.contextid
                           AND (sc.path = c.path OR
                                sc.path LIKE " . $DB->sql_concat('c.path', "'/%'") . " OR
                                c.path LIKE " . $DB->sql_concat('sc.path', "'/%'") . ")
                           AND u.id = ra.userid AND u.deleted = 0
                           AND ra.contextid = sc.id
                           AND ra.roleid = rc.roleid";

        $count = $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $this->sqlfrom", $this->params);

        if ($count == 0) {
            $this->status = check::OK;
        } else {
            $this->status = check::WARNING;
        }

        $this->summary = get_string('check_riskxss_warning', 'report_security', $count);

    }

    /**
     * Showing the full list of user may be slow so defer it
     *
     * @return string
     */
    public function get_details() : string {

        global $CFG, $DB;

        $userfields = \user_picture::fields('u');
        $users = $DB->get_records_sql("SELECT DISTINCT $userfields $this->sqlfrom", $this->params);
        foreach ($users as $uid => $user) {
            $users[$uid] = fullname($user);
        }
        $users = implode(', ', $users);
        return get_string('check_riskxss_details', 'report_security', $users);

    }
}


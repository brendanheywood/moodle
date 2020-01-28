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
 * Verifies sanity of guest role
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
 * Verifies sanity of guest role
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2008 petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guestrole extends security_check {

    /**
     * Constructor
     */
    public function __construct() {

        global $DB, $CFG;
        $this->id = 'guestrole';
        $this->name = get_string('check_guestrole_name', 'report_security');
        $this->link = new \action_link(
            new \moodle_url('/admin/settings.php?section=userpolicies'),
            get_string('userpolicies', 'admin'));

        if (!$guestrole = $DB->get_record('role', ['id' => $CFG->guestroleid])) {
            $this->status  = check::WARNING;
            $this->summary = get_string('check_guestrole_notset', 'report_security');
            return;
        }

        // Risky caps - usually very dangerous.
        $sql = "SELECT COUNT(DISTINCT rc.contextid)
                  FROM {role_capabilities} rc
                  JOIN {capabilities} cap ON cap.name = rc.capability
                 WHERE " . $DB->sql_bitand('cap.riskbitmask', (RISK_XSS | RISK_CONFIG | RISK_DATALOSS)) . " <> 0
                   AND rc.permission = :capallow
                   AND rc.roleid = :roleid";

        $riskycount = $DB->count_records_sql($sql, [
            'capallow' => CAP_ALLOW,
            'roleid' => $guestrole->id,
        ]);

        // It may have either no or 'guest' archetype - nothing else, or else it would break during upgrades badly.
        if ($guestrole->archetype === '' or $guestrole->archetype === 'guest') {
            $legacyok = true;
        } else {
            $legacyok = false;
        }

        if ($riskycount or !$legacyok) {
            $this->status  = check::CRITICAL;
            $this->summary = get_string('check_guestrole_error', 'report_security', format_string($guestrole->name));

        } else {
            $this->status  = check::OK;
            $this->summary = get_string('check_guestrole_ok', 'report_security');
        }

        $this->details = get_string('check_guestrole_details', 'report_security');
    }
}


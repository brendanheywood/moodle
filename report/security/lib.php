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
 * Report security
 *
 * @package    report_security
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Handle security related routes inside /.well-known/
 *
 * @param string $route
 */
function report_security_route_wellknown(string $route) {

    global $CFG;

    // See https://w3c.github.io/webappsec-change-password-url/
    if (strpos($route, '/.well-known/change-password') === 0) {
        redirect(new moodle_url('/login/change_password.php'));
    }

    // Generates a /.well-known/security.txt file
    // See also https://securitytxt.org/
    if (strpos($route, '/.well-known/security.txt') === 0) {

        http_response_code(200);
        header("Content-Type: text/plain");

        $expires = gmdate('D, d M Y H:i:s', time() + WEEKSECS * 10 ) .' +0000';
        $canonical = $CFG->wwwroot . '/.well-known/security.txt';
        $contact = '';
        if (!empty($CFG->supportemail)) {
            $contact .= "Contact:   mailto:$CFG->supportemail\n";
        }
        if (!empty($CFG->supportpage)) {
            $contact .= "Contact:   $CFG->supportpage\n";
        }

        // If you wish to override this you can simply place a normal file into
        // /.well-known/security.txt which should take precedence. Note this text
        // conforms to a draft specification and so should not be translated, see:
        // https://tools.ietf.org/html/draft-foudil-securitytxt-10 for more info.
        echo <<<EOT
# This site is built using Moodle an open source LMS.
#
# For security issues specific to this site please contact the local Moodle administrator
$contact
# For security issues in Moodle itself please refer to:
Contact:   mailto:security@moodle.org
Policy:    https://docs.moodle.org/dev/Moodle_security_procedures

Canonical: $canonical
Expires:   $expires
EOT;
        die;

    }
}

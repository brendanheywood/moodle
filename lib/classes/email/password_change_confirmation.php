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

namespace core\email;

defined('MOODLE_INTERNAL') || die();

/**
 * A password change confirmation emails
 *
 * @package core
 * @category email
 * @copyright 2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class password_change_confirmation extends email_base {

    function render($output) {

        global $SITE;

        $out = $output->salutation($this->to);
        $out .= $output->markdown(get_string('emailresetintro', '', [
            'username' => s($this->to->username),
            'sitename' => s($SITE->fullname),
        ]));

        $url = new \moodle_url('/login/forgot_password.php', ['token' => $this->data['token']]);
        $out .= $output->single_button($url, get_string('emailresetyourpassword'));

        $out .= $output->markdown(get_string('emailresethelp', '', ['resetminutes' => $this->data['resetmins']]));
        $out .= $output->signature_support($this->to);
        return $out;

    }

}


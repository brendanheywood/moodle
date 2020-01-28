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
 * Verifies fatal misconfiguration of dataroot
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
 * Verifies fatal misconfiguration of dataroot
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2008 petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unsecuredataroot extends security_check {

    /**
     * Constructor
     */
    public function __construct() {

        global $CFG;

        $this->id = 'unsecuredataroot';
        $this->name = get_string('check_unsecuredataroot_name', 'report_security');
        $this->details = get_string('check_unsecuredataroot_details', 'report_security');

        $insecuredataroot = is_dataroot_insecure(true);

        if ($insecuredataroot == INSECURE_DATAROOT_WARNING) {
            $this->status = check::ERROR;
            $this->summary = get_string('check_unsecuredataroot_warning', 'report_security', $CFG->dataroot);

        } else if ($insecuredataroot == INSECURE_DATAROOT_ERROR) {
            $this->status = check::CRITICAL;
            $this->summary = get_string('check_unsecuredataroot_error', 'report_security', $CFG->dataroot);

        } else {
            $this->status = check::OK;
            $this->summary = get_string('check_unsecuredataroot_ok', 'report_security');
        }
    }

}


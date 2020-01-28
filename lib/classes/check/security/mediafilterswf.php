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
 * Verifies sloppy swf embedding - this should have been removed long ago!!
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
 * Verifies sloppy swf embedding - this should have been removed long ago!!
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2008 petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediafilterswf extends security_check {

    /**
     * Constructor
     */
    public function __construct() {

        global $CFG;

        $this->id = 'mediafilterswf';
        $this->name = get_string('check_mediafilterswf_name', 'report_security');
        $this->details = get_string('check_mediafilterswf_details', 'report_security');
        $this->link = new \action_link(
            new \moodle_url('/admin/settings.php?section=managemediaplayers'),
            get_string('managemediaplayers', 'media'));

        $activefilters = filter_get_globally_enabled();

        $enabledmediaplayers = \core\plugininfo\media::get_enabled_plugins();
        if (array_search('mediaplugin', $activefilters) !== false and array_key_exists('swf', $enabledmediaplayers)) {
            $this->status = check::CRITICAL;
            $this->summary = get_string('check_mediafilterswf_error', 'report_security');
        } else {
            $this->status = check::OK;
            $this->summary = get_string('check_mediafilterswf_ok', 'report_security');
        }
    }
}


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
 * A collection of utils to help manage the various file paths
 *
 * @package    core
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\files;

/**
 * A collection of utils to help manage the various file paths
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_utils {

    /**
     * Return all file paths
     *
     * The order is important, these are listed in heirarchical
     * nested order, shallowest first and deepest last.
     */
    public static function get_all_file_configs() {
        return [
            'dirroot',
            'dataroot',
            'localcachedir',    // Default inside dataroot.
            'cachedir',         // Default inside dataroot.
            'langotherroot',    // Default inside dataroot.
            'langlocalroot',    // Default inside dataroot.
            'tempdir',          // Default inside dataroot.
            'backuptempdir',    // Default inside tempdir.
            'themedir',         // No default.
            'localrequestdir',  // Default to system tmp.
        ];
    }

    /**
     * Given a file path return which $CFG manages it
     *
     * @param string $path
     * @param bool $deepest Should we return the deepst path or shallowest if there is an overlap?
     * @param bool $showother Should we return 'other' directories if no match?
     * @return string Config name
     */
    public static function get_config_from_path(string $path, bool $deepest = false, bool $showother = false): string {
        global $CFG;
        $cfgnames = self::get_all_file_configs();

        if ($deepest) {
            $cfgnames = array_reverse($cfgnames);
        }

        foreach ($cfgnames as $cfg) {
            if (property_exists($CFG, $cfg) && strpos($path, $CFG->$cfg) === 0) {
                return $cfg;
            }
        }
        if ($showother) {
            return dirname($path);
        }
        return '';
    }

    /**
     * Redact sensitive paths from messages
     *
     * @param string $message possibly containing paths we want to hide
     * @return string a redacted messages
     */
    public static function redact_paths(string $message): string {

        global $CFG;
        $searches = [];
        $replaces = [];

        // We search from most specific to least specific.
        $cfgnames = array_reverse(self::get_all_file_configs());
        foreach ($cfgnames as $cfgname) {
            if (property_exists($CFG, $cfgname)) {
                $searches[] = $CFG->$cfgname;
                $replaces[] = "[$cfgname]";
            }
        }
        return str_replace($searches, $replaces, $message);
    }
}

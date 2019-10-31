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
 * A namespace contains license specific functions
 *
 * @since      Moodle 2.0
 * @package    core
 * @subpackage lib
 * @copyright  2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class license_manager {

    /**
     * License is a core license and can not be updated or deleted.
     */
    const CORE_LICENSE = 0;

    /**
     * License is a custom license and can be updated and/or deleted.
     */
    const CUSTOM_LICENSE = 1;

    /**
     * Integer representation of boolean for a license that is enabled.
     */
    const LICENSE_ENABLED = 1;

    /**
     * Integer representation of boolean for a license that is disabled.
     */
    const LICENSE_DISABLED = 0;

    /**
     * Adding a new license type
     * @param object $license {
     *            shortname => string a shortname of license, will be refered by files table[required]
     *            fullname  => string the fullname of the license [required]
     *            source => string the homepage of the license type[required]
     *            enabled => int is it enabled?
     *            version  => int a version number used by moodle [required]
     *            custom => int is this a custom license?
     * }
     */
    static public function add($license) {
        global $DB;
        if ($record = $DB->get_record('license', array('shortname'=>$license->shortname))) {
            // record exists
            $license->enabled = $record->enabled;
            $license->id = $record->id;
            $DB->update_record('license', $license);
        } else {
            $DB->insert_record('license', $license);
        }
        // Add the new license to the end of priority order for licenses.
        $licensepriority = explode(',', get_config('', 'licensepriority'));
        $licensepriority[] = $license->shortname;
        set_config('licensepriority', implode(',', $licensepriority));

        return true;
    }

    /**
     * Get license records
     * @param mixed $param
     * @return array
     */
    static public function get_licenses($param = null) {
        global $DB;
        if (empty($param) || !is_array($param)) {
            $param = array();
        }
        // get licenses by conditions
        if ($records = $DB->get_records('license', $param)) {
            return $records;
        } else {
            return array();
        }
    }

    /**
     * Get all installed licenses in order of priority.
     *
     * @return array $result of license objects.
     */
    static public function get_licenses_in_priority_order() {
        $result = [];
        $licenses = self::get_licenses();

        $order = explode(',', get_config('', 'licensepriority'));

        foreach ($order as $licensename) {
            foreach ($licenses as $key => $license) {
                if ($licensename == $license->shortname) {
                    $result[$key] = $license;
                }
            }
        }

        // We shouldn't get here as priority is added on install and at license creation,
        // but just in case, check for any licenses not in the global licensepriority config,
        // add them to the results and update config to include them.
        $remaininglicensekeys = array_diff(array_keys($licenses), array_keys($result));
        if ($remaininglicensekeys) {

            $licensepriority = explode(',', get_config('', 'licensepriority'));

            foreach ($remaininglicensekeys as $key) {
                $result[$key] = $licenses[$key];
                $licensepriority[] = $licenses[$key]->shortname;
            }

            set_config('licensepriority', implode(',', $licensepriority));
        }

        return $result;
    }

    /**
     * Get license record by shortname
     * @param mixed $param the shortname of license, or an array
     * @return object
     */
    static public function get_license_by_shortname($name) {
        global $DB;
        if ($record = $DB->get_record('license', array('shortname'=>$name))) {
            return $record;
        } else {
            return null;
        }
    }

    /**
     * Enable a license
     * @param string $license the shortname of license
     * @return boolean
     */
    static public function enable($license) {
        global $DB;
        if ($license = self::get_license_by_shortname($license)) {
            $license->enabled = self::LICENSE_ENABLED;
            $DB->update_record('license', $license);
        }
        self::set_active_licenses();
        return true;
    }

    /**
     * Disable a license
     * @param string $license the shortname of license
     * @return boolean
     */
    static public function disable($license) {
        global $DB, $CFG;
        // Site default license cannot be disabled!
        if ($license == $CFG->sitedefaultlicense) {
            print_error('error');
        }
        if ($license = self::get_license_by_shortname($license)) {
            $license->enabled = self::LICENSE_DISABLED;
            $DB->update_record('license', $license);
        }
        self::set_active_licenses();
        return true;
    }

    /**
     * Delete a custom license.
     *
     * @param string $licenseshortname the shortname of license.
     */
    static public function delete($licenseshortname) {
        global $DB;

        if ($license = self::get_license_by_shortname($licenseshortname)) {
            if ($license->custom == self::CUSTOM_LICENSE) {
                $DB->delete_records('license', ['id' => $license->id]);
            } else {
                print_error('licensecantdeletecore', 'error');
            }
        } else {
            print_error('licensenotfoundshortname', 'error', '', $licenseshortname);
        }
    }

    /**
     * Store active licenses in global $CFG.
     */
    static private function set_active_licenses() {
        // set to global $CFG
        $licenses = self::get_licenses(array('enabled'=>1));
        $result = array();
        foreach ($licenses as $l) {
            $result[] = $l->shortname;
        }
        set_config('licenses', implode(',', $result));
    }

    /**
     * Get the globally configured active licenses.
     *
     * @return array of license objects.
     * @throws \coding_exception
     */
    static public function get_active_licenses() {
        global $CFG;

        $result = [];

        if (!empty($CFG->licenses)) {
            $activelicenses = explode(',', $CFG->licenses);
            $licenses = self::get_licenses();
            foreach ($licenses as $license) {
                if (in_array($license->shortname, $activelicenses)) {
                    // Interpret core license strings for internationalisation.
                    if ($license->custom == self::CORE_LICENSE) {
                        $license->fullname = get_string($license->shortname, 'license');
                    }
                    $result[] = $license;
                }
            }
        }

        return $result;
    }

    /**
     * Get the globally configured active licenses as an array.
     *
     * @return array $licenses an associative array of licenses shaped as ['shortname' => 'fullname']
     */
    static public function get_active_licenses_as_array() {
        $activelicense = self::get_active_licenses();

        $licenses = [];
        foreach ($activelicense as $license) {
            $licenses[$license->shortname] = $license->fullname;
        }

        return $licenses;
    }

    /**
     * Install moodle build-in licenses
     */
    static public function install_licenses() {
        $activelicenses = array();

        $license = new stdClass();

        $license->shortname = 'unknown';
        $license->fullname = 'Unknown license';
        $license->source = '';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'allrightsreserved';
        $license->fullname = 'All rights reserved';
        $license->source = 'https://en.wikipedia.org/wiki/All_rights_reserved';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'public';
        $license->fullname = 'Public Domain';
        $license->source = 'https://en.wikipedia.org/wiki/Public_domain';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'cc';
        $license->fullname = 'Creative Commons';
        $license->source = 'https://creativecommons.org/licenses/by/3.0/';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'cc-nd';
        $license->fullname = 'Creative Commons - NoDerivs';
        $license->source = 'https://creativecommons.org/licenses/by-nd/3.0/';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'cc-nc-nd';
        $license->fullname = 'Creative Commons - No Commercial NoDerivs';
        $license->source = 'https://creativecommons.org/licenses/by-nc-nd/3.0/';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'cc-nc';
        $license->fullname = 'Creative Commons - No Commercial';
        $license->source = 'https://creativecommons.org/licenses/by-nc/3.0/';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'cc-nc-sa';
        $license->fullname = 'Creative Commons - No Commercial ShareAlike';
        $license->source = 'https://creativecommons.org/licenses/by-nc-sa/3.0/';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        $license->shortname = 'cc-sa';
        $license->fullname = 'Creative Commons - ShareAlike';
        $license->source = 'https://creativecommons.org/licenses/by-sa/3.0/';
        $license->enabled = self::LICENSE_ENABLED;
        $license->version = '2010033100';
        $license->custom = self::CORE_LICENSE;
        $activelicenses[] = $license->shortname;
        self::add($license);

        set_config('licenses', implode(',', $activelicenses));
        set_config('licensepriority', implode(',', $activelicenses));
        set_config('sitedefaultlicense', reset($activelicenses));
    }
}

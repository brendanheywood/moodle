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
 * Check API manager
 *
 * @package    core
 * @category   check
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\check;

defined('MOODLE_INTERNAL') || die();

/**
 * Check API manager
 *
 * @package    core
 * @category   check
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Return all security checks
     *
     * @return array of check objects
     */
    static public function get_security_checks() : array {
        $checks = [
            new security\unsecuredataroot(),
            new security\displayerrors(),
            new security\vendordir(),
            new security\nodemodules(),
            new security\configrw(),
            new security\preventexecpath(),
            new security\noauth(),
            new security\mediafilterswf(),
            new security\embed(),
            new security\openprofiles(),
            new security\crawlers(),
            new security\passwordpolicy(),
            new security\emailchangeconfirmation(),
            new security\webcron(),
            new security\cookiesecure(),
            new security\riskxss(),
            new security\riskadmin(),
            new security\riskbackup(),
            new security\defaultuserrole(),
            new security\guestrole(),
            new security\frontpagerole(),
        ];

        // Any plugin can add security checks to this report by implementing a callback
        // <component>_security_checks() which returns a check object.
        // See MDL-67776 for more details.
        $morechecks = get_plugins_with_function('security_checks', 'lib.php');
        foreach ($morechecks as $plugintype => $plugins) {
            foreach ($plugins as $pluginfunction) {
                $result = $pluginfunction();
                $checks = array_merge($checks, $result);
            }
        }

        return $checks;
    }

}


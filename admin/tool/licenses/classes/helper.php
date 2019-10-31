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
 * License manager helper class.
 *
 * @package    tool_licenses
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_licenses;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * License manager helper class.
 *
 * @package    tool_licenses
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Moodle relative path to the licenses manager.
     */
    const MANAGER_PATH = '/admin/tool/licenses/manager.php';

    /**
     * Get the URL for viewing the license manager interface.
     *
     * @return \moodle_url
     */
    public static function get_view_license_manager_url() {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_VIEW_LICENSE_MANAGER]);
        return $url;
    }

    /**
     * Get the URL for endpoint enabling a license.
     *
     * @param string $licenseshortname the shortname of license to enable.
     *
     * @return \moodle_url
     */
    public static function get_enable_license_url(string $licenseshortname) {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_ENABLE, 'license' => $licenseshortname]);

        return $url;
    }

    /**
     * Get the URL for endpoint disabling a license.
     *
     * @param string $licenseshortname the shortname of license to disable.
     *
     * @return \moodle_url
     */
    public static function get_disable_license_url(string $licenseshortname) {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_DISABLE, 'license' => $licenseshortname]);

        return $url;
    }

    /**
     * Get the URL endpoint to create a new license.
     *
     * @return \moodle_url
     */
    public static function get_create_license_url() {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_CREATE]);

        return $url;
    }

    /**
     * Get the URL endpoint to update an existing license.
     *
     * @param string $licenseshortname the shortname of license to update.
     *
     * @return \moodle_url
     */
    public static function get_update_license_url(string $licenseshortname) {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_UPDATE, 'license' => $licenseshortname]);

        return $url;
    }

    /**
     * Get the URL endpoint to delete an existing license.
     *
     * @param string $licenseshortname the shortname of license to delete.
     *
     * @return \moodle_url
     */
    public static function get_delete_license_url(string $licenseshortname) {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_DELETE, 'license' => $licenseshortname]);

        return $url;
    }

    /**
     * Get the URL endpoint to move a license priority up order.
     *
     * @param string $licenseshortname the shortname of license to move up.
     *
     * @return \moodle_url
     */
    public static function get_moveup_license_url(string $licenseshortname) {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_MOVE_UP, 'license' => $licenseshortname]);

        return $url;
    }

    /**
     * Get the URL endpoint to move a license priority down order.
     *
     * @param string $licenseshortname the shortname of license to move down.
     *
     * @return \moodle_url
     */
    public static function get_movedown_license_url(string $licenseshortname) {
        $url = new moodle_url(self::MANAGER_PATH,
            ['action' => manager::ACTION_MOVE_DOWN, 'license' => $licenseshortname]);

        return $url;
    }

}
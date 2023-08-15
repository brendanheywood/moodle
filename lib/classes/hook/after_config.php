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
 * after config hook
 *
 * @package    core
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2023 onwards Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\hook;

/**
 * after config hook
 */
class after_config implements \core\hook\described_hook, deprecated_callback_replacement {

    /**
     * Hook description
     */
    public static function get_hook_description(): string {
        return 'Hook dispatched just after config is loaded.';
    }

    /**
     * Replaces the _after_config legacy hook
     */
    public static function get_deprecated_plugin_callbacks(): array {
        return ['after_config'];
    }
}


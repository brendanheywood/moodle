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
 * A Handler to process bounce messages
 *
 * @package    tool_messageinbound
 * @category   message
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_messageinbound\message\inbound;

defined('MOODLE_INTERNAL') || die();

/**
 * A Handler to process bounce messages and increment a users count
 *
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bounce_handler extends \core\message\inbound\handler {

    /**
     * Magic getter to fetch the specified key.
     *
     * The purpose of this is so we can store the bounce config as 'core'
     * $CFG variables so it can be force set in config.php and searched in the
     * admin GUI. It also eliminates the potentially confusing idea of enabling
     * bounce detection at both the system level as well as the handler level.
     *
     * @param string $key The name of the key to retrieve
     */
    public function __get($key) {
        global $CFG;

        if ($key == 'enabled') {
            return !empty($CFG->messageinbound_handlebounces);
        }
        return parent::__get($key);
    }

    /**
     * Do not allow changes to the address validation setting.
     */
    public function can_change_validateaddress() {
        return false;
    }

    /**
     * Enabling is done on the plugin settings page.
     */
    public function can_change_enabled() {
        return false;
    }

    /**
     * Return a description for the current handler.
     *
     * @return string
     */
    public function get_description() {
        return get_string('bouncehandler', 'tool_messageinbound');
    }

    /**
     * Return a name for the current handler.
     * This appears in the admin pages as a human-readable name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('bouncehandlername', 'tool_messageinbound');
    }

    /**
     * Generate a checksum for an email address
     *
     * @param string $email an email address
     * @return integer
     */
    static public function email_checksum($email) {
        return crc32($email);
    }


    /**
     * Process a bounce message received
     *
     * @param \stdClass $record The Inbound Message record
     * @param \stdClass $data The message data packet.
     * @return bool Whether the message was successfully processed.
     * @throws \core\message\inbound\processing_failed_exception when the message can not be found.
     */
    public function process_message(\stdClass $record, \stdClass $data) {

        preg_match("/Return-Path:\s*(<(.*)>)/", $data->headers, $matches);

        // If there is a Return-Path this is a normal email to the custom
        // noreply email address for this user. This should be very rare and
        // we will just ignore it instead of bouncing it back.
        if ($matches[1] != '<>') {
            mtrace("=== Ignoring normal message to personal noreply address. ===");
            return true;
        }

        $user = $data->data->user;
        $newchecksum = self::email_checksum($user->email);
        $oldchecksum = $record->data->datavalue;

        // If the checksums do not match then the user has changed email address
        // between when the original email was sent and when the bounce
        // notification arrived. Their new email may be correct, but we don't
        // know yet so we won't penalise them just in case.
        if ($newchecksum != $oldchecksum) {
            mtrace("=== Bounce message ignored because users email has changed since sent. ===");
            return true;
        }

        $bounces = set_bounce_count($user);
        mtrace("=== Increment bounce count to {$bounces} for {$user->email} ===");

        return true;
    }
}

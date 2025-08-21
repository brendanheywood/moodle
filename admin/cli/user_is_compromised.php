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
 * This script allows you to mark a user as having been compromised.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2025 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'username' => '',
], [
    'h' => 'help',
    'u' => 'username',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help = "
This marks a user as having been compromised and resets everything.

Options:
-h, --help                    Print out this help
-u, --username=username       Specify username to reset

Example:
\$sudo -u www-data /usr/bin/php admin/cli/user_is_compromised.php --username=rosaura
";

if ($options['help']) {
    echo $help;
    die;
}

$username = $options['username'];
if (empty($username)) {
    echo $help;
    die;
}

if (!$user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
    cli_error("Can not find user '$username'");
}

set_user_preference('auth_forcepasswordchange', true, $user->id);
\core\session\manager::destroy_user_sessions($user->id);
exit(0); // 0 means success.

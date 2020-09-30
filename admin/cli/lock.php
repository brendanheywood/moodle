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
 * Lock CLI script
 *
 * @package    core
 * @subpackage cli
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/clilib.php");

list($options, $unrecognized) = cli_get_params(
    [
        'help'      => false,
        'resource'  => '',
        'component' => '',
        'execute'   => '',
        'wait'      => 0,
        'timeout'   => MINSECS * 10,
    ], [
        'h' => 'help',
        'c' => 'component',
        'r' => 'resource',
        'w' => 'wait',
        't' => 'timeout',
        'e' => 'execute',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] or empty($options['resource']) or empty($options['component'])) {
    $help = <<<EOT
A Lock API utility script for gaining and holding any resource lock.

Options:
 -h, --help         Print out this help
 -r, --resource     The lock's resource name - required
 -c, --component    The lock's component, eg tool_foobar - required
 -e, --execute      This runs an arbitrary shell command while holding the lock.
                    After the command has run it will exit ignoring --timeout.
 -w, --wait=N       How many seconds we should wait to gain the lock
                    Defaults to 10 seconds. If we do not gain the lock it will
                    exit with a code of 2.
 -t  --timeout=N    Maximum time in seconds we should hold the lock.
                    Defaults to 10 minutes.
                    A value of 0 seconds which means hold the lock indefintely.
                    If it times out it will exit with a code of 3.

Examples:

1) Ensure that a task isn't running at the same time:

php admin/cli/lock.php -c=cron -r='\\tool_foobar\\task\\my_task' --execute="/bin/command"


EOT;

    echo $help;
    die;
}

if (CLI_MAINTENANCE) {
    echo "CLI maintenance mode active, lock api suspended.\n";
    exit(1);
}

if (moodle_needs_upgrading()) {
    echo "Moodle upgrade pending, lock api suspended.\n";
    exit(1);
}

$resource   = $options['resource'];
$component  = $options['component'];
$execute    = $options['execute'];
$wait       = (int)$options['wait'];
$timeout    = (int)$options['timeout'];

$humantimenow = date('r', time());

$lockfactory = \core\lock\lock_config::get_lock_factory($component);

$lockname = "$component:$resource";

\core\local\cli\shutdown::script_supports_graceful_exit();


if ($lock = $lockfactory->get_lock($resource, $wait)) {

    mtrace("Lock '$lockname' gained at: {$humantimenow}");

    if (!empty($execute)) {

        mtrace("Executing command: `$execute`");
        exec($execute, $output, $status);
        mtrace("output = $status");
        mtrace(var_dump($output));
        mtrace('');
        $humantimenow = date('r', time());
        mtrace("Lock '$lockname' released at: {$humantimenow}");
        $lock->release();
        exit;
    }

    $start = time();
    if ($timeout > 0) {
        mtrace("Holding lock for maximum of " . format_time($timeout));
    } else {
        mtrace("Holding lock for until SIGINT / Ctrl ^C");
    }

    while(!\core\local\cli\shutdown::should_gracefully_exit() &&
        ($timeout == 0 or (time() - $start) < $timeout) ) {
        mtrace(".", '');
        sleep(1);
    }

    $humantimenow = date('r', time());
    mtrace('');
    mtrace("Lock '$lockname' released at: {$humantimenow}");
    $lock->release();

    // If we reach the timeout exit with error 2.
    if (!\core\local\cli\shutdown::should_gracefully_exit()) {
        exit(2);
    }

} else {

    $humantimenow = date('r', time());
    mtrace("Lock '$lockname' timed out at: {$humantimenow}");
    exit(3);
}


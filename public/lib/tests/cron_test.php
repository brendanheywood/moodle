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

namespace core;

use core\task\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Testable subclass of cron used to verify the keepalive scheduling behaviour.
 *
 * It overrides run_scheduled_tasks(), run_adhoc_tasks(), sleep(), and
 * get_next_scheduled_task_time() so that:
 *   - The two task-runner methods record their invocations rather than executing real tasks.
 *   - sleep() advances the injected fake clock instead of blocking the process.
 *   - get_next_scheduled_task_time() returns controlled values without hitting the database.
 *
 * For these overrides to be reachable from run_main_process() the production code must
 * call all four via static:: (late static binding) rather than self:: or bare built-ins.
 *
 * @package core
 */
class testable_keepalive_cron extends cron {
    /** @var array Ordered log of every method invocation. */
    public static array $methodcalls = [];

    /**
     * Queue of values to return from get_next_scheduled_task_time(), one per call.
     * If the queue is empty, PHP_INT_MAX is returned (no future scheduled tasks).
     *
     * @var int[]
     */
    public static array $nextscheduledtimes = [];

    /**
     * Reset all recorded invocations and the next-scheduled-times queue.
     */
    public static function reset_tracking(): void {
        self::$methodcalls = [];
        self::$nextscheduledtimes = [];
    }

    /**
     * Record the call rather than running real scheduled tasks.
     *
     * @param int $startruntime
     * @param int|null $startprocesstime
     */
    public static function run_scheduled_tasks(
        int $startruntime,
        ?int $startprocesstime = null,
    ): void {
        self::$methodcalls[] = ['method' => 'run_scheduled_tasks', 'time' => $startruntime];
    }

    /**
     * Record the call rather than running real adhoc tasks.
     *
     * @param int $startruntime
     * @param int $keepalive
     * @param bool $checklimits
     * @param int|null $startprocesstime
     * @param int|null $maxtasks
     * @param string|null $classname
     */
    public static function run_adhoc_tasks(
        int $startruntime,
        $keepalive = 0,
        $checklimits = true,
        ?int $startprocesstime = null,
        ?int $maxtasks = null,
        ?string $classname = null,
    ): void {
        self::$methodcalls[] = ['method' => 'run_adhoc_tasks', 'time' => $startruntime];
    }

    /**
     * Advance the injected clock instead of blocking the process.
     *
     * @param int $seconds
     */
    protected static function sleep(int $seconds): void {
        \core\di::get(\core\clock::class)->bump($seconds);
    }

    /**
     * Return the next scheduled time from the pre-configured queue rather than
     * querying the database. Returns 5 minutes ahead when the queue is exhausted.
     *
     * @param int $after
     * @return int|null
     */
    protected static function get_next_scheduled_task_time(int $after): ?int {
        if (!empty(self::$nextscheduledtimes)) {
            return array_shift(self::$nextscheduledtimes);
        }
        return $after + (5 * MINSECS);
    }
}

/**
 * Tests for core\cron.
 *
 * @package     core
 * @copyright   2023 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core\cron
 */
final class cron_test extends \advanced_testcase {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/task_fixtures.php');
    }

    /**
     * Reset relevant caches between tests.
     */
    public function setUp(): void {
        parent::setUp();
        cron::reset_user_cache();
        testable_keepalive_cron::reset_tracking();
    }

    /**
     * Test the setup_user function.
     */
    public function test_setup_user(): void {
        // This function uses the $GLOBALS super global. Disable the VariableNameLowerCase sniff for this function.
        // phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        global $PAGE, $USER, $SESSION, $SITE, $CFG;
        $this->resetAfterTest();

        $admin = get_admin();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        cron::setup_user();
        $this->assertSame($admin->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($SITE->id));
        $this->assertSame($CFG->timezone, $USER->timezone);
        $this->assertSame('', $USER->lang);
        $this->assertSame('', $USER->theme);
        $SESSION->test1 = true;
        $adminsession = $SESSION;
        $adminuser = $USER;
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user(null, $course);
        $this->assertSame($admin->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($course->id));
        $this->assertSame($adminsession, $SESSION);
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user($user1);
        $this->assertSame($user1->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($SITE->id));
        $this->assertNotSame($adminsession, $SESSION);
        $this->assertObjectNotHasProperty('test1', $SESSION);
        $this->assertEmpty((array)$SESSION);
        $usersession1 = $SESSION;
        $SESSION->test2 = true;
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user($user1);
        $this->assertSame($user1->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($SITE->id));
        $this->assertNotSame($adminsession, $SESSION);
        $this->assertSame($usersession1, $SESSION);
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user($user2);
        $this->assertSame($user2->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($SITE->id));
        $this->assertNotSame($adminsession, $SESSION);
        $this->assertNotSame($usersession1, $SESSION);
        $this->assertEmpty((array)$SESSION);
        $usersession2 = $SESSION;
        $usersession2->test3 = true;
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user($user2, $course);
        $this->assertSame($user2->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($course->id));
        $this->assertNotSame($adminsession, $SESSION);
        $this->assertNotSame($usersession1, $SESSION);
        $this->assertSame($usersession2, $SESSION);
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user($user1);
        $this->assertSame($user1->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($SITE->id));
        $this->assertNotSame($adminsession, $SESSION);
        $this->assertNotSame($usersession1, $SESSION);
        $this->assertEmpty((array)$SESSION);
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user();
        $this->assertSame($admin->id, $USER->id);
        $this->assertSame($PAGE->context, \context_course::instance($SITE->id));
        $this->assertSame($adminsession, $SESSION);
        $this->assertSame($adminuser, $USER);
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::reset_user_cache();
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        cron::setup_user();
        $this->assertNotSame($adminsession, $SESSION);
        $this->assertNotSame($adminuser, $USER);
        $this->assertSame($GLOBALS['SESSION'], $_SESSION['SESSION']);
        $this->assertSame($GLOBALS['SESSION'], $SESSION);
        $this->assertSame($GLOBALS['USER'], $_SESSION['USER']);
        $this->assertSame($GLOBALS['USER'], $USER);

        // phpcs:enable
    }

    /**
     * Test that run_inner_adhoc_task() routes to adhoc_task_delayed() when the task
     * calls set_soft_retry_delay() from within execute(), and that the task remains
     * in the DB (not deleted), with fail_delay reset to 0, attemptsavailable decremented,
     * and nextruntime advanced by the requested delay.
     *
     * @covers \core\cron::run_inner_adhoc_task
     * @covers \core\task\manager::adhoc_task_delayed
     * @dataProvider run_inner_adhoc_task_delayed_provider
     * @param int|null $softretrydelay Soft retry delay, or null for exponential backoff.
     * @param int $now Frozen clock value.
     * @param int $expectednextruntime Expected nextruntime stored in the DB after the delay.
     */
    public function test_run_inner_adhoc_task_routes_to_delayed_when_soft_retry_set(
        ?int $softretrydelay,
        int $now,
        int $expectednextruntime,
    ): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        cron::reset_user_cache();

        $CFG->task_logtostdout = true;

        // Freeze the clock.
        $clock = $this->mock_clock_with_frozen($now);

        // Queue a task that will call set_soft_retry_delay() during execute().
        $task = new \core\task\soft_retry_adhoc_test_task();
        if ($softretrydelay !== null) {
            $task->set_custom_data(['delay' => $softretrydelay]);
        }
        $taskid = manager::queue_adhoc_task($task);

        // Retrieve the task as the cron runner would.
        $task = manager::get_next_adhoc_task($clock->time());
        $this->assertNotNull($task, 'Task should be retrievable from the queue.');

        $initialattempts = $task->get_attempts_available();

        // Run it through the full cron runner path.
        ob_start();
        cron::run_inner_adhoc_task($task);
        $output = ob_get_clean();

        // The task must NOT have been deleted. It is delayed, not complete.
        $record = $DB->get_record('task_adhoc', ['id' => $taskid]);
        $this->assertNotFalse($record);

        // Nextruntime must be the expected future time.
        $this->assertEquals($expectednextruntime, (int) $record->nextruntime);

        // Fail_delay must be 0, a soft retry is not a failure.
        $this->assertEquals(0, (int) $record->faildelay);

        // Attemptsavailable must have been decremented by one.
        $this->assertEquals($initialattempts - 1, (int) $record->attemptsavailable);

        // The cron log should contain the "delayed" message, not the "complete" message.
        $this->assertStringContainsString('Adhoc task delayed:', $output);
        $this->assertStringNotContainsString('Adhoc task complete:', $output);

        // Metadata (timestarted, hostname, pid) must be cleared.
        $this->assertEmpty($record->timestarted);
        $this->assertEmpty($record->hostname);
        $this->assertEmpty($record->pid);
    }

    /**
     * Data provider for test_run_inner_adhoc_task_routes_to_delayed_when_soft_retry_set.
     *
     * @return array
     */
    public static function run_inner_adhoc_task_delayed_provider(): array {
        return [
            // Explicit delay: nextruntime = now(1000) + 120 = 1120.
            'explicit_delay' => [
                'softretrydelay'      => 120,
                'now'                 => 1000,
                'expectednextruntime' => 1120,
            ],
            // Exponential backoff: retrycount = max(0, 12 - attemptsavailable(12)) = 0,
            // delay = min(86400, 60 * pow(2, 0)) = 60, nextruntime = 1000 + 60 = 1060.
            'exponential_backoff' => [
                'softretrydelay'      => null,
                'now'                 => 1000,
                'expectednextruntime' => 1060,
            ],
        ];
    }

    /**
     * Test that run_inner_adhoc_task() routes to adhoc_task_complete() when the task
     * executes successfully WITHOUT calling set_soft_retry_delay(), and that the task
     * is deleted from the DB (not kept for retry).
     *
     * @covers \core\cron::run_inner_adhoc_task
     * @covers \core\task\manager::adhoc_task_complete
     */
    public function test_run_inner_adhoc_task_routes_to_complete_when_no_soft_retry(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        // See test_run_inner_adhoc_task_routes_to_delayed_when_soft_retry_set for explanation.
        $this->preventResetByRollback();
        cron::reset_user_cache();

        $CFG->task_logtostdout = true;

        $clock = $this->mock_clock_with_frozen(1000);

        // A plain task whose execute() does nothing, no set_soft_retry_delay() call.
        $task = new \core\task\adhoc_test_task();
        $taskid = manager::queue_adhoc_task($task);

        $task = manager::get_next_adhoc_task($clock->time());
        $this->assertNotNull($task);

        ob_start();
        cron::run_inner_adhoc_task($task);
        $output = ob_get_clean();

        // Task must have been deleted after successful completion.
        $this->assertFalse($DB->record_exists('task_adhoc', ['id' => $taskid]));

        // The cron log must contain "complete" and NOT "delayed".
        $this->assertStringContainsString('Adhoc task complete:', $output);
        $this->assertStringNotContainsString('Adhoc task delayed:', $output);
    }

    /**
     * Scheduled tasks should run exactly once on the first iteration, then be skipped
     * for the remainder of the keepalive window because get_next_scheduled_task_time()
     * returns a time well beyond the keepalive finish time.
     *
     * Adhoc tasks must still be polled on every iteration.
     */
    public function test_keepalive_skips_scheduled_when_next_task_is_far_away(): void {
        $this->resetAfterTest();

        $startofminute = (int)(time() / MINSECS) * MINSECS;
        $clock = $this->mock_clock_with_incrementing($startofminute + 30);

        // No future scheduled tasks within the keepalive window.
        testable_keepalive_cron::$nextscheduledtimes = [$startofminute + 30 + HOURSECS];

        ob_start();
        testable_keepalive_cron::run_main_process(10);
        ob_end_clean();

        $calls = testable_keepalive_cron::$methodcalls;

        $scheduledcalls = array_values(array_filter(
            $calls,
            fn($c) => $c['method'] === 'run_scheduled_tasks',
        ));
        $adhoccalls = array_values(array_filter(
            $calls,
            fn($c) => $c['method'] === 'run_adhoc_tasks',
        ));

        // Scheduled tasks must run exactly once — on the very first iteration.
        $this->assertCount(
            1,
            $scheduledcalls,
            'Scheduled tasks should run once then be skipped until the next due time arrives',
        );

        // Adhoc tasks must be polled on every iteration.
        $this->assertGreaterThan(
            1,
            count($adhoccalls),
            'Adhoc tasks should be polled on every keepalive loop iteration while time remains',
        );
    }

    /**
     * When get_next_scheduled_task_time() returns a time that falls within the keepalive
     * window, scheduled tasks should run a second time once the clock reaches that time,
     * then be skipped again for the remainder of the window.
     *
     * Adhoc tasks must be polled on every iteration regardless.
     */
    public function test_keepalive_runs_scheduled_again_when_next_task_time_arrives(): void {
        $this->resetAfterTest();

        $startofminute = (int)(time() / MINSECS) * MINSECS;
        $clock = $this->mock_clock_with_incrementing($startofminute + 30);

        // The incrementing clock advances by ~3 seconds per loop iteration
        // (one time() call at the top + one at the bottom + one bump from sleep).
        // Set the next scheduled time to 5 seconds ahead so it falls within iteration 3
        // (at ~T+7), then return a far-future time so no further runs occur.
        $nexttasktime = $startofminute + 30 + 5;
        testable_keepalive_cron::$nextscheduledtimes = [$nexttasktime, $startofminute + 30 + HOURSECS];

        ob_start();
        testable_keepalive_cron::run_main_process(10);
        ob_end_clean();

        $calls = testable_keepalive_cron::$methodcalls;

        $scheduledcalls = array_values(array_filter(
            $calls,
            fn($c) => $c['method'] === 'run_scheduled_tasks',
        ));
        $adhoccalls = array_values(array_filter(
            $calls,
            fn($c) => $c['method'] === 'run_adhoc_tasks',
        ));

        // Scheduled tasks must run exactly twice: once immediately, then again when the
        // next-task time is reached.
        $this->assertCount(
            2,
            $scheduledcalls,
            'Scheduled tasks should fire again when the next scheduled task time is reached',
        );

        // The second run must be at or after the time returned by get_next_scheduled_task_time().
        $this->assertGreaterThanOrEqual(
            $nexttasktime,
            $scheduledcalls[1]['time'],
            'The second scheduled run should not happen before the next-task time',
        );

        // Adhoc tasks are polled every iteration regardless.
        $this->assertGreaterThan(
            1,
            count($adhoccalls),
            'Adhoc tasks should be polled on every keepalive loop iteration',
        );
    }
}

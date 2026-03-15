<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core\task;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../fixtures/task_fixtures.php');

/**
 * Test class for scheduled adhoc tasks.
 *
 * @package    core
 * @category   test
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright  Catalyst IT, 2025
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\core\task\manager::class)]
final class scheduled_adhoc_task_test extends \advanced_testcase {
    /**
     * Assert that the specified tasks are equal.
     *
     * @param   adhoc_task_scheduled $task
     * @param   adhoc_task_scheduled $comparisontask
     */
    private function assert_task_equals(adhoc_task_scheduled $task, adhoc_task_scheduled $comparisontask): void {
        // Convert both to an object.
        $task = manager::record_from_scheduled_adhoc_task($task);
        $comparisontask = manager::record_from_scheduled_adhoc_task($comparisontask);

        // Reset the nextruntime and nextstoptime fields as they are intentionally dynamic.
        $task->nextruntime = null;
        $comparisontask->nextruntime = null;
        $task->nextstoptime = null;
        $comparisontask->nextstoptime = null;

        $args = array_merge(
            [
                $task,
                $comparisontask,
            ],
            array_slice(func_get_args(), 2)
        );

        call_user_func_array([$this, 'assertEquals'], $args);
    }

    /**
     * Assert that the specified tasks are not equal.
     *
     * @param   adhoc_task_scheduled $task
     * @param   adhoc_task_scheduled $comparisontask
     */
    private function assert_task_not_equals(adhoc_task_scheduled $task, adhoc_task_scheduled $comparisontask): void {
        // Convert both to an object.
        $task = manager::record_from_scheduled_adhoc_task($task);
        $comparisontask = manager::record_from_scheduled_adhoc_task($comparisontask);

        // Reset the nextruntime field as it is intentionally dynamic.
        $task->nextruntime = null;
        $comparisontask->nextruntime = null;

        $args = array_merge(
            [
                $task,
                $comparisontask,
            ],
            array_slice(func_get_args(), 2)
        );

        call_user_func_array([$this, 'assertNotEquals'], $args);
    }

    /**
     * Data provider for {@see test_get_max_next_scheduled_time}.
     *
     * Each entry is [nextruntime, cronspec, expected] where cronspec is "minute hour day month dayofweek".
     * Note: all date strings are in the default Australia/Perth server timezone.
     *
     * @return array[]
     */
    public static function get_max_next_scheduled_time_provider(): array {
        return [
            // Every minute — no meaningful window end, returns 0.
            'all wildcards'                => ['2023-11-01 10:00', '* * * * *',       0],

            // Minute range — window ends at the start of the minute after the top of the range.
            'minute range, start of range' => ['2023-11-01 10:30', '30-50 * * * *',   '2023-11-01 10:51'],
            'minute range, middle of range'=> ['2023-11-01 10:40', '30-50 * * * *',   '2023-11-01 10:51'],

            // Single minute value — window ends at the start of the next minute.
            'single minute value'          => ['2023-11-01 10:30', '30 * * * *',      '2023-11-01 10:31'],

            // Comma-separated minutes — window ends at the start of the next minute after the slot.
            'minute list'                  => ['2023-11-01 10:10', '10,20,30 * * * *','2023-11-01 10:11'],

            // Step minutes — same as discrete list.
            'minute step'                  => ['2023-11-01 10:10', '*/10 * * * *',    '2023-11-01 10:11'],

            // Hour range with specific minute — window ends at the start of the next minute.
            'hour range, specific minute'  => ['2023-11-01 10:30', '30 10-14 * * *',  '2023-11-01 14:31'],

            // Both minute and hour ranges — window ends at start of minute after last-hour:last-minute.
            'minute and hour range'        => ['2023-11-01 10:30', '30-50 10-14 * * *','2023-11-01 14:51'],

            // Wildcard minutes with an hour range — window ends at the start of the next hour.
            'wildcard minutes, hour range' => ['2023-11-01 10:00', '* 10-14 * * *',   '2023-11-01 15:00'],

            // Single hour value with a specific minute — ends at the start of the next minute.
            'single hour, single minute'   => ['2023-11-01 10:30', '30 10 * * *',     '2023-11-01 10:31'],

            // Day range — window ends at the start of the next minute on the last valid day.
            'day range'                    => ['2023-11-01 10:30', '30 10 1-5 * *',   '2023-11-05 10:31'],

            // Month range — window ends at the start of the next minute in the last valid month.
            'month range, specific fields' => ['2023-06-01 10:30', '30 10 1 6-8 *',   '2023-08-01 10:31'],

            // Single-value month, all other fields wildcards — window spans the whole month,
            // ending at midnight on the 1st of the following month.
            'whole month wildcard fields'  => ['2023-11-01 00:00', '* * * 11 *',      '2023-12-01 00:00'],

            // Month range, all other fields wildcards — window spans through the last whole month.
            'month range wildcard fields'  => ['2023-06-01 00:00', '* * * 6-8 *',     '2023-09-01 00:00'],

            // Specific minute with all other fields wildcards.
            'specific minute, rest wildcard'=> ['2023-11-01 10:05', '5 * * * *',      '2023-11-01 10:06'],
        ];
    }

    /**
     * Tests that get_max_next_scheduled_time returns the end of the run window for a variety of cron specs.
     *
     * @param string $nextruntime The task's next run time (strtotime format, Australia/Perth tz).
     * @param string $cronspec Cron spec string "minute hour day month dayofweek".
     * @param string|int $expected Expected stop time (strtotime format, or 0 for no limit).
     */
    #[DataProvider('get_max_next_scheduled_time_provider')]
    public function test_get_max_next_scheduled_time(
        string $nextruntime,
        string $cronspec,
        string|int $expected,
    ): void {
        [$minute, $hour, $day, $month, $dayofweek] = explode(' ', $cronspec);

        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute($minute);
        $task->set_hour($hour);
        $task->set_day($day);
        $task->set_day_of_week($dayofweek);
        $task->set_month($month);

        $nextruntimets = strtotime($nextruntime);
        $expectedts = is_int($expected) ? $expected : strtotime($expected);

        $actual = $task->get_max_next_scheduled_time($nextruntimets);
        $this->assertEquals(
            $expectedts,
            $actual,
            sprintf('cronspec "%s": expected %s, got %s', $cronspec, $expected, date('Y-m-d H:i:s', $actual))
        );
    }

    /**
     * reset_scheduled_adhoc_tasks_for_component never modifies existing records.
     */
    public function test_reset_scheduled_tasks_for_component_changed_in_source_for_scheduled_adhoc_task(): void {
        $this->resetAfterTest(true);

        $task = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);

        // Change the schedule.
        $task->set_minute('1');
        $task->set_hour('2');
        $task->set_day('3');
        $task->set_month('4');
        $task->set_day_of_week('5');
        manager::configure_scheduled_adhoc_task($task);

        $taskafterchange = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);
        $this->assert_task_not_equals($task, manager::get_scheduled_adhoc_task(asynchronous_backup_task::class) ?: $task);

        // Reset must not touch existing records.
        manager::reset_scheduled_adhoc_tasks_for_component('moodle');

        $taskafterreset = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);
        $this->assert_task_equals($taskafterchange, $taskafterreset,
            'reset_scheduled_adhoc_tasks_for_component must not modify existing records');
    }

    /**
     * Ensure that the method reset_scheduled_tasks_for_scheduled inserting the new task in the database if it was deleted.
     */
    public function test_reset_scheduled_tasks_for_component_deleted_for_scheduled_adhoc_task(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Load default scheduled adhoc tasks.
        $defaulttasks = manager::load_scheduled_adhoc_tasks_for_component('moodle');

        // Get scheduled adhoc task.
        $task = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);

        // Delete the scheduled adhoc task.
        $DB->delete_records('task_scheduled_adhoc', ['classname' => '\\' . trim($task->get_classname(), '\\')]);

        $this->assertFalse(manager::get_scheduled_adhoc_task(asynchronous_backup_task::class));

        // Reset the scheduled adhoc tasks.
        manager::reset_scheduled_adhoc_tasks_for_component('moodle');

        // Assert that the second task was added back.
        $taskafterreset = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);
        $this->assertNotFalse($taskafterreset);
        $this->assert_task_equals($task, $taskafterreset);
        $this->assertCount(count($defaulttasks), manager::load_scheduled_adhoc_tasks_for_component('moodle'));
    }

    /**
     * Ensure that the queue_adhoc_task function is working when the scheduled adhoc task is enabled or disabled.
     */
    public function test_queue_adhoc_task_for_scheduled_adhoc_task(): void {
        global $DB;
        $this->resetAfterTest(true);

        $clock = $this->mock_clock_with_frozen();

        // --- Enabled schedule: task can be queued and runs normally ---
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('0');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        $result = manager::queue_adhoc_task(new adhoc_test_task(), true);
        $this->assertNotFalse($result, 'Enabled task should be queueable');
        $this->assertCount(1, manager::get_adhoc_tasks(adhoc_test_task::class));

        $returned = manager::get_next_adhoc_task($clock->time() + 1, false, adhoc_test_task::class);
        $this->assertNotEmpty($returned);
        $this->assertInstanceOf(adhoc_test_task::class, $returned);
        $returned->execute();
        manager::adhoc_task_complete($returned);

        // --- Disabled schedule: task must not be queued and must not run ---
        $task = new adhoc_task_scheduled(new adhoc_test2_task());
        $task->set_minute('0');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(true);

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        $result = manager::queue_adhoc_task(new adhoc_test2_task(), true);
        $this->assertFalse($result, 'Disabled task must not be queueable');
        $this->assertCount(0, manager::get_adhoc_tasks(adhoc_test2_task::class));

        $returned = manager::get_next_adhoc_task($clock->time() + 1, false, adhoc_test2_task::class);
        $this->assertNull($returned, 'Disabled task must not be returned from the queue');
    }

    /**
     * reschedule_or_queue_adhoc_task queues the task the first time, and does not
     * create a duplicate on subsequent calls.
     */
    public function test_reschedule_or_queue_adhoc_task_match_update_runtime_for_scheduled_adhoc_task(): void {
        global $DB;
        $this->resetAfterTest(true);

        $clock = $this->mock_clock_with_frozen();

        // Register an enabled schedule.
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('5');
        $task->set_hour('*');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        // First call: nothing queued yet — one instance should be created.
        manager::reschedule_or_queue_adhoc_task(new adhoc_test_task());
        $this->assertCount(1, manager::get_adhoc_tasks(adhoc_test_task::class));

        // Second call: already queued — must not create a duplicate.
        manager::reschedule_or_queue_adhoc_task(new adhoc_test_task());
        $this->assertCount(1, manager::get_adhoc_tasks(adhoc_test_task::class));
    }

    /**
     * Changing a scheduled adhoc task's schedule does not alter the nextruntime of
     * already-queued adhoc task instances.
     */
    public function test_reschedule_queued_scheduled_adhoc_tasks_update_all_scheduled_adhoc_tasks(): void {
        global $DB;
        $this->resetAfterTest(true);

        $clock = $this->mock_clock_with_frozen();

        // Insert an enabled schedule.
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('10-50');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);

        $record = manager::record_from_scheduled_adhoc_task($task);
        $schedid = $DB->insert_record('task_scheduled_adhoc', $record);

        // Queue two tasks — their nextruntime is set to ASAP (clock->time() - 1).
        $adhoctask1 = new adhoc_test_task();
        $adhoctask1->set_custom_data('scheduled_task_1');
        manager::queue_adhoc_task($adhoctask1);
        $adhoctask2 = new adhoc_test_task();
        $adhoctask2->set_custom_data('scheduled_task_2');
        manager::queue_adhoc_task($adhoctask2);

        $expectedNextRuntime = $clock->time() - 1;

        // Update the schedule (e.g. after cron reconfigures it) — queued tasks must not be affected.
        $DB->set_field('task_scheduled_adhoc', 'nextruntime', $clock->time() + 3600, ['id' => $schedid]);
        $DB->set_field('task_scheduled_adhoc', 'nextstoptime', $clock->time() + 3660, ['id' => $schedid]);

        // The queued task nextruntime must be unchanged.
        foreach (manager::get_adhoc_tasks(adhoc_test_task::class) as $t) {
            $this->assertEquals($expectedNextRuntime, $t->get_next_run_time(),
                'Schedule change must not alter queued task nextruntime');
        }

        // Disabling the schedule must not alter queued task nextruntime either.
        $DB->set_field('task_scheduled_adhoc', 'disabled', 1, ['id' => $schedid]);

        foreach (manager::get_adhoc_tasks(adhoc_test_task::class) as $t) {
            $this->assertEquals($expectedNextRuntime, $t->get_next_run_time(),
                'Disabling schedule must not alter queued task nextruntime');
        }
    }

    /**
     * A newly constructed task should be enabled by default.
     */
    public function test_scheduled_adhoc_task_enabled_by_default(): void {
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $this->assertFalse($task->get_disabled());
        $this->assertTrue($task->is_enabled());
    }

    /**
     * A disabled scheduled adhoc task should not be queueable.
     */
    public function test_disabled_task_cannot_be_queued(): void {
        global $DB;
        $this->resetAfterTest();

        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('*');
        $task->set_hour('*');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(true);

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        // Queuing should be blocked.
        $result = manager::queue_adhoc_task(new adhoc_test_task());
        $this->assertFalse($result);
        $this->assertCount(0, manager::get_adhoc_tasks(adhoc_test_task::class));
    }

    /**
     * An enabled scheduled adhoc task can be queued normally.
     */
    public function test_enabled_task_can_be_queued(): void {
        global $DB;
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen();

        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('*');
        $task->set_hour('*');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        $result = manager::queue_adhoc_task(new adhoc_test_task());
        $this->assertNotFalse($result);
        $this->assertCount(1, manager::get_adhoc_tasks(adhoc_test_task::class));
    }

    /**
     * A disabled task already in the queue should not be returned by get_next_adhoc_task.
     */
    public function test_disabled_task_not_returned_from_queue(): void {
        global $DB;
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen(1000000);

        // Manually insert an adhoc task record (bypassing queue_adhoc_task's disabled check).
        $adhoc = new adhoc_test_task();
        $record = manager::record_from_adhoc_task($adhoc);
        $record->nextruntime = $clock->time() - 1;
        $DB->insert_record('task_adhoc', $record);

        // Register the scheduled config as disabled.
        $scheduled = new adhoc_task_scheduled($adhoc);
        $scheduled->set_minute('*');
        $scheduled->set_hour('*');
        $scheduled->set_day('*');
        $scheduled->set_month('*');
        $scheduled->set_day_of_week('*');
        $scheduled->set_component('moodle');
        $scheduled->set_disabled(true);
        $schedrecord = manager::record_from_scheduled_adhoc_task($scheduled);
        $DB->insert_record('task_scheduled_adhoc', $schedrecord);

        // The task should not be returned.
        $result = manager::get_next_adhoc_task($clock->time(), false);
        $this->assertNull($result);
    }

    /**
     * An enabled task in the queue within its schedule window should be returned.
     */
    public function test_enabled_task_returned_from_queue(): void {
        global $DB;
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen(1000000);

        $adhoc = new adhoc_test_task();

        // Register the scheduled config as enabled with all-wildcard schedule.
        $scheduled = new adhoc_task_scheduled($adhoc);
        $scheduled->set_minute('*');
        $scheduled->set_hour('*');
        $scheduled->set_day('*');
        $scheduled->set_month('*');
        $scheduled->set_day_of_week('*');
        $scheduled->set_component('moodle');
        $scheduled->set_disabled(false);
        $schedrecord = manager::record_from_scheduled_adhoc_task($scheduled);
        $schedrecord->nextruntime = 0;
        $schedrecord->nextstoptime = 0;
        $DB->insert_record('task_scheduled_adhoc', $schedrecord);

        // Manually insert an adhoc task.
        $record = manager::record_from_adhoc_task($adhoc);
        $record->nextruntime = $clock->time() - 1;
        $DB->insert_record('task_adhoc', $record);

        $result = manager::get_next_adhoc_task($clock->time(), false);
        $this->assertNotNull($result);
        $this->assertInstanceOf(adhoc_test_task::class, $result);
        manager::adhoc_task_complete($result);
    }

    /**
     * The schedule window constrains when queued tasks can run, but does not mutate
     * the queued task's own nextruntime. Removing the schedule restores normal behaviour.
     *
     * Steps:
     * 1. Queue a task whose nextruntime is in the past (due to run now).
     * 2. Save a schedule that only permits running tomorrow — queue should appear empty.
     * 3. Delete the schedule — the task's original nextruntime still applies, so it
     *    should be returned as the next task to run.
     */
    public function test_schedule_window_gates_without_mutating_nextruntime(): void {
        global $DB;
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen(2000000);

        $adhoc = new adhoc_test_task();

        // Insert a queued adhoc task that is due to run right now.
        $record = manager::record_from_adhoc_task($adhoc);
        $record->nextruntime = $clock->time() - 3600; // 1 hour ago.
        $taskid = $DB->insert_record('task_adhoc', $record);

        // Register a schedule whose window starts tomorrow.
        $tomorrow = $clock->time() + 86400;
        $scheduled = new adhoc_task_scheduled($adhoc);
        $scheduled->set_minute('0');
        $scheduled->set_hour('9');
        $scheduled->set_day('*');
        $scheduled->set_month('*');
        $scheduled->set_day_of_week('*');
        $scheduled->set_component('moodle');
        $scheduled->set_disabled(false);
        $schedrecord = manager::record_from_scheduled_adhoc_task($scheduled);
        $schedrecord->nextruntime  = $tomorrow;
        $schedrecord->nextstoptime = $tomorrow + 60;
        $schedid = $DB->insert_record('task_scheduled_adhoc', $schedrecord);

        // With the schedule active (window is tomorrow), the queue should be empty.
        $result = manager::get_next_adhoc_task($clock->time(), false);
        $this->assertNull($result, 'Task should not run when schedule window is in the future');

        // The queued task's nextruntime must be unchanged.
        $row = $DB->get_record('task_adhoc', ['id' => $taskid]);
        $this->assertEquals(
            $clock->time() - 3600,
            (int) $row->nextruntime,
            'Schedule must not mutate the queued task nextruntime'
        );

        // Remove the schedule entirely.
        $DB->delete_records('task_scheduled_adhoc', ['id' => $schedid]);

        // Now the task's own nextruntime governs — it should be returned.
        $result = manager::get_next_adhoc_task($clock->time(), false);
        $this->assertNotNull($result, 'Task should run once schedule is removed');
        $this->assertInstanceOf(adhoc_test_task::class, $result);
        manager::adhoc_task_complete($result);
    }

    /**
     * get_all_scheduled_adhoc_tasks advances expired windows in-place and returns tasks
     * with updated nextruntime/nextstoptime, without requiring a separate DB call.
     */
    public function test_get_all_scheduled_adhoc_tasks_advances_expired_windows(): void {
        global $DB;
        $this->resetAfterTest();

        // 30 seconds into the 10:00 window — the 09:00 window has already closed.
        $clock = $this->mock_clock_with_frozen(strtotime('2025-01-15 10:00:30 UTC'));

        $adhoc = new adhoc_test_task();

        // Insert a schedule with the previous window (09:00–09:01) already expired.
        $scheduled = new adhoc_task_scheduled($adhoc);
        $scheduled->set_minute('0');
        $scheduled->set_hour('*');
        $scheduled->set_day('*');
        $scheduled->set_month('*');
        $scheduled->set_day_of_week('*');
        $scheduled->set_component('moodle');
        $scheduled->set_disabled(false);
        $schedrecord = manager::record_from_scheduled_adhoc_task($scheduled);
        $schedrecord->nextruntime  = strtotime('2025-01-15 09:00:00 UTC');
        $schedrecord->nextstoptime = strtotime('2025-01-15 09:01:00 UTC');
        $DB->insert_record('task_scheduled_adhoc', $schedrecord);

        $tasks = manager::get_all_scheduled_adhoc_tasks();

        $task = null;
        foreach ($tasks as $t) {
            if ($t->get_classname() === adhoc_test_task::class) {
                $task = $t;
                break;
            }
        }
        $this->assertNotNull($task, 'Task must be present in get_all_scheduled_adhoc_tasks result');

        // The returned task object must reflect the advanced window (10:00).
        $this->assertEquals(
            strtotime('2025-01-15 10:00:00 UTC'),
            $task->get_next_run_time(),
            'Returned task must have the advanced nextruntime'
        );
        $this->assertEquals(
            strtotime('2025-01-15 10:01:00 UTC'),
            $task->get_next_stop_time(),
            'Returned task must have the advanced nextstoptime'
        );

        // The DB record must also be updated.
        $canonicalclassname = manager::get_canonical_class_name(adhoc_test_task::class);
        $row = $DB->get_record('task_scheduled_adhoc', ['classname' => $canonicalclassname]);
        $this->assertEquals(strtotime('2025-01-15 10:00:00 UTC'), (int) $row->nextruntime);
        $this->assertEquals(strtotime('2025-01-15 10:01:00 UTC'), (int) $row->nextstoptime);
    }
}

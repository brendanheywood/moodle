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
     * Ensure that the method reset_scheduled_tasks_for_scheduled resets time field values for scheduled adhoc task.
     */
    public function test_reset_scheduled_tasks_for_component_changed_in_source_for_scheduled_adhoc_task(): void {
        $this->resetAfterTest(true);

        // Get asynchronous_backup_task scheduled adhoc task.
        $task = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);

        // Get a copy of the task before main changes for later comparison.
        $taskbeforechange = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);

        // Edit a task to simulate a change in its definition (as if it was not customised).
        $task->set_minute('1');
        $task->set_hour('2');
        $task->set_day('3');
        $task->set_month('4');
        $task->set_day_of_week('5');
        manager::configure_scheduled_adhoc_task($task);

        // Fetch the task out for comparison.
        $taskafterchange = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);

        // The after and before tasks shouldn't be the same.
        $this->assert_task_not_equals($taskbeforechange, $taskafterchange);

        // Reset the scheduled tasks.
        manager::reset_scheduled_tasks_for_component('moodle');

        // Get the task.
        $taskafterreset = manager::get_scheduled_adhoc_task(asynchronous_backup_task::class);

        // The after and before tasks should be the same.
        $this->assert_task_equals($taskbeforechange, $taskafterreset);
    }

    /**
     * Ensure that the method reset_scheduled_tasks_for_scheduled do not override the scheduled adhoc task if its
     * customized.
     */
    public function test_reset_scheduled_tasks_for_component_customised_for_scheduled_adhoc_task(): void {
        $this->resetAfterTest(true);

        // Load default scheduled adhoc tasks.
        $defaulttasks = manager::load_scheduled_adhoc_tasks_for_component('moodle');

        // Customise a task.
        $task = reset($defaulttasks);
        $task->set_minute('1');
        $task->set_hour('2');
        $task->set_day('3');
        $task->set_month('4');
        $task->set_day_of_week('5');
        $task->set_customised('1');
        manager::configure_scheduled_adhoc_task($task);

        // Check task before reset.
        $taskbeforereset = manager::get_scheduled_adhoc_task($task->get_classname());
        $this->assert_task_equals($task, $taskbeforereset);

        // Reset the scheduled tasks.
        manager::reset_scheduled_tasks_for_component('moodle');

        // Get the task.
        $taskafterreset = manager::get_scheduled_adhoc_task($task->get_classname());

        // The task should still be the same as the customised.
        $this->assert_task_equals($task, $taskafterreset);
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

        // Reset the scheduled tasks.
        manager::reset_scheduled_tasks_for_component('moodle');

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

        // Schedule an adhoc task and ensure that it's running.
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('0');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);
        $nextruntime = $task->get_next_scheduled_time($clock->time());

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        manager::queue_adhoc_task(new adhoc_test_task(), true);

        $task = manager::get_next_adhoc_task($nextruntime + 1, true, adhoc_test_task::class);

        // Task should not be null.
        $this->assertNotEmpty($task);
        // The adhoc task next run time should not be ASAP.
        $this->assertNotEquals($clock->time() - 1, $task->get_next_run_time());
        // The scheduled adhoc tasks next run time should be equal to the actual adhoc task next run time.
        $this->assertEquals($nextruntime, $task->get_next_run_time());
        // There should be only one adhoc task.
        $this->assertEquals(1, count(manager::get_adhoc_tasks(adhoc_test_task::class)));
        // Check the adhoc class instance.
        $this->assertInstanceOf(adhoc_test_task::class, $task);
        $task->execute();
        manager::adhoc_task_complete($task);

        // Disable the scheduling for adhoc task and run as normal adhoc task.
        $task = new adhoc_task_scheduled(new adhoc_test2_task());
        $task->set_minute('0');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(true);
        $nextruntime = $task->get_next_scheduled_time($clock->time());

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        manager::queue_adhoc_task(new adhoc_test2_task(), true);
        $task = manager::get_next_adhoc_task($clock->time() + 1, true, adhoc_test2_task::class);

        // The adhoc task should not be null.
        $this->assertNotEmpty($task);
        // The next run time should be ASAP.
        $this->assertEquals($clock->time() - 1, $task->get_next_run_time());
        // The scheduled adhoc tasks next run time shouldn't be equal to the actual adhoc task next run time.
        $this->assertNotEquals($nextruntime, $task->get_next_run_time());
        // There should be only one adhoc task.
        $this->assertEquals(1, count(manager::get_adhoc_tasks(adhoc_test2_task::class)));
        // Check the adhoc class instance.
        $this->assertInstanceOf(adhoc_test2_task::class, $task);
        $task->execute();
        manager::adhoc_task_complete($task);
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will update the run time for scheduled adhoc task
     * if there are planned changes.
     */
    public function test_reschedule_or_queue_adhoc_task_match_update_runtime_for_scheduled_adhoc_task(): void {
        global $DB;
        $this->resetAfterTest(true);

        $clock = $this->mock_clock_with_frozen();

        // Schedule the task.
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('5');
        $task->set_hour('*');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);
        $initialruntime = $task->get_next_scheduled_time($clock->time());

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        manager::reschedule_or_queue_adhoc_task(new adhoc_test_task());

        // Bump time and re-schedule the task.
        $clock->set_to($initialruntime);
        $newruntime = $task->get_next_scheduled_time($clock->time());
        // The initial run time should not be equal to new run time.
        $this->assertNotEquals($initialruntime, $newruntime);

        manager::reschedule_or_queue_adhoc_task(new adhoc_test_task());

        // Get all adhoc tasks.
        $tasks = manager::get_adhoc_tasks(adhoc_test_task::class);
        // We're expecting only one updated scheduled task.
        $this->assertCount(1, $tasks);

        // Check if the new run time is equal to queued task next run time.
        $task = reset($tasks);
        $this->assertEquals($newruntime, $task->get_next_run_time());
    }

    /**
     * Ensure that reschedule_queued_scheduled_adhoc_tasks function reschedules all tasks by class.
     */
    public function test_reschedule_queued_scheduled_adhoc_tasks_update_all_scheduled_adhoc_tasks(): void {
        global $DB;
        $this->resetAfterTest(true);

        $clock = $this->mock_clock_with_frozen();

        // Schedule is enabled.
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('10-50');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(false);
        $initialruntime = $task->get_next_scheduled_time($clock->time());

        $record = manager::record_from_scheduled_adhoc_task($task);
        $DB->insert_record('task_scheduled_adhoc', $record);

        // Queue adhoc task.
        $adhoctask1 = new adhoc_test_task();
        $adhoctask1->set_custom_data('scheduled_task_1');
        manager::queue_adhoc_task($adhoctask1);
        $adhoctask2 = new adhoc_test_task();
        $adhoctask2->set_custom_data('scheduled_task_2');
        manager::queue_adhoc_task($adhoctask2);

        // Bump time and re-schedule the task.
        $clock->set_to($initialruntime);
        $newruntime = $task->get_next_scheduled_time($clock->time());

        $this->assertNotEquals($initialruntime, $newruntime);

        manager::reschedule_queued_adhoc_tasks_by_class($task->get_classname());

        // Get all adhoc tasks.
        $tasks = manager::get_adhoc_tasks(adhoc_test_task::class);
        $this->assertCount(2, $tasks);
        // Check all adhoc tasks next run time.
        foreach ($tasks as $task) {
            $this->assertEquals($newruntime, $task->get_next_run_time());
        }

        // Schedule is disabled.
        $task = new adhoc_task_scheduled(new adhoc_test_task());
        $task->set_minute('10-50');
        $task->set_hour('1-6');
        $task->set_day('*');
        $task->set_month('*');
        $task->set_day_of_week('*');
        $task->set_component('moodle');
        $task->set_disabled(true);

        manager::reschedule_queued_adhoc_tasks_by_class($task->get_classname());

        // Get all adhoc tasks.
        $tasks = manager::get_adhoc_tasks(adhoc_test_task::class);
        $this->assertCount(2, $tasks);
        // Check all adhoc tasks next run time.
        foreach ($tasks as $task) {
            $this->assertEquals($newruntime, $task->get_next_run_time());
        }
    }
}

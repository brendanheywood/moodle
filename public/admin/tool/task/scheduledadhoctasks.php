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

/**
 * Scheduled adhoc task list.
 *
 * @package    tool_task
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright  Catalyst IT, 2025
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('scheduledadhoctasks');

$action = optional_param('action', '', PARAM_ALPHAEXT);
$taskname = optional_param('task', null, PARAM_RAW);
$lastchanged = optional_param('lastchanged', '', PARAM_RAW);

$renderer = $PAGE->get_renderer('tool_task');

$task = null;
$mform = null;

if ($taskname) {
    $task = \core\task\manager::get_scheduled_adhoc_task($taskname);
    if (!$task) {
        throw new \moodle_exception('invaliddata');
    }
}

$PAGE->navbar->add(get_string('scheduledadhoctasks', 'tool_task'), $PAGE->url);

if ($action == 'edit') {
    $PAGE->navbar->add(get_string('editstaskscheduledadhoc', 'tool_task', $task->get_name()));
}

if ($task) {
    $mform = new \tool_task\form\edit_scheduled_adhoc_task_form(null, $task);
    $nexturl = new \moodle_url($PAGE->url, ['lastchanged' => $taskname]);
}

if ($mform && ($mform->is_cancelled() || !empty($CFG->preventscheduledtaskchanges) || $task->is_overridden())) {
    redirect($nexturl);
} else if ($action == 'edit' && empty($CFG->preventscheduledtaskchanges)) {
    if ($data = $mform->get_data()) {
        if ($data->resettodefaults) {
            $task->set_minute('*');
            $task->set_hour('*');
            $task->set_month('*');
            $task->set_day_of_week('*');
            $task->set_day('*');
            $task->set_disabled('*');
            $task->set_customised(false);
        } else {
            $task->set_minute($data->minute);
            $task->set_hour($data->hour);
            $task->set_month($data->month);
            $task->set_day_of_week($data->dayofweek);
            $task->set_day($data->day);
            $task->set_disabled($data->disabled);
            $task->set_customised(true);
        }

        try {
            // Save the task.
            \core\task\manager::configure_scheduled_adhoc_task($task);
            redirect($nexturl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Exception $e) {
            redirect($nexturl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editstaskscheduledadhoc', 'tool_task', $task->get_name()));
        echo html_writer::div('\\' . $task->get_classname(), 'task-class text-ltr');
        echo html_writer::div(get_string(
            'fromcomponent',
            'tool_task',
            $renderer->component_name($task->get_component())
        ));
        $mform->display();
        echo $OUTPUT->footer();
    }
} else {
    echo $OUTPUT->header();

    if (!get_config('core', 'cron_enabled')) {
        echo $renderer->cron_disabled();
    }

    $tasks = core\task\manager::get_all_scheduled_adhoc_tasks();
    echo $renderer->scheduled_adhoc_tasks_table($tasks, $lastchanged);
    echo $OUTPUT->footer();
}

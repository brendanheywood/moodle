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
 * Scheduled adhoc task edit form.
 *
 * @package    tool_task
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright  Catalyst IT, 2025
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_task\form;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

require_once($CFG->libdir . '/formslib.php');

/**
 * Scheduled adhoc task edit form.
 *
 * @package    tool_task
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright  Catalyst IT, 2025
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_scheduled_adhoc_task_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $task = $this->_customdata;

        $mform->addGroup([
            $mform->createElement('text', 'minute'),
            $mform->createElement(
                'static',
                'minutedefault',
                '',
                get_string('defaultx', 'tool_task', '*')
            ),
        ], 'minutegroup', get_string('taskscheduleminute', 'tool_task'), null, false);
        $mform->setType('minute', PARAM_RAW);
        $mform->addHelpButton('minutegroup', 'taskscheduleminute', 'tool_task');

        $mform->addGroup([
            $mform->createElement('text', 'hour'),
            $mform->createElement(
                'static',
                'hourdefault',
                '',
                get_string('defaultx', 'tool_task', '*')
            ),
        ], 'hourgroup', get_string('taskschedulehour', 'tool_task'), null, false);
        $mform->setType('hour', PARAM_RAW);
        $mform->addHelpButton('hourgroup', 'taskschedulehour', 'tool_task');

        $mform->addGroup([
            $mform->createElement('text', 'day'),
            $mform->createElement(
                'static',
                'daydefault',
                '',
                get_string('defaultx', 'tool_task', '*')
            ),
        ], 'daygroup', get_string('taskscheduleday', 'tool_task'), null, false);
        $mform->setType('day', PARAM_RAW);
        $mform->addHelpButton('daygroup', 'taskscheduleday', 'tool_task');

        $mform->addGroup([
            $mform->createElement('text', 'month'),
            $mform->createElement(
                'static',
                'monthdefault',
                '',
                get_string('defaultx', 'tool_task', '*')
            ),
        ], 'monthgroup', get_string('taskschedulemonth', 'tool_task'), null, false);
        $mform->setType('month', PARAM_RAW);
        $mform->addHelpButton('monthgroup', 'taskschedulemonth', 'tool_task');

        $mform->addGroup([
            $mform->createElement('text', 'dayofweek'),
            $mform->createElement(
                'static',
                'dayofweekdefault',
                '',
                get_string('defaultx', 'tool_task', '*')
            ),
        ], 'dayofweekgroup', get_string('taskscheduledayofweek', 'tool_task'), null, false);
        $mform->setType('dayofweek', PARAM_RAW);
        $mform->addHelpButton('dayofweekgroup', 'taskscheduledayofweek', 'tool_task');

        $mform->addElement('advcheckbox', 'disabled', get_string('scheduleddisabled', 'tool_task'));
        $mform->addHelpButton('disabled', 'scheduleddisabled', 'tool_task');

        $mform->addElement('advcheckbox', 'resettodefaults', get_string('resettasktodefaults', 'tool_task'));
        $mform->addHelpButton('resettodefaults', 'resettasktodefaults', 'tool_task');

        $mform->disabledIf('minute', 'resettodefaults', 'checked');
        $mform->disabledIf('hour', 'resettodefaults', 'checked');
        $mform->disabledIf('day', 'resettodefaults', 'checked');
        $mform->disabledIf('dayofweek', 'resettodefaults', 'checked');
        $mform->disabledIf('month', 'resettodefaults', 'checked');
        $mform->disabledIf('disabled', 'resettodefaults', 'checked');

        $mform->addElement('hidden', 'task', $task->get_classname());
        $mform->setType('task', PARAM_RAW);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHANUMEXT);
        $this->add_action_buttons(true, get_string('savechanges'));

        // Do not use defaults for existing values, the set_data() is the correct way.
        $this->set_data(\core\task\manager::record_from_scheduled_adhoc_task($task));
    }

    /**
     * Custom validations.
     *
     * @param array $data
     * @param array $files
     *
     * @return array
     */
    public function validation($data, $files) {
        $error = parent::validation($data, $files);
        // Use a checker class.
        $checker = new \tool_task\scheduled_checker_task();
        $checker->set_minute($data['minute']);
        $checker->set_hour($data['hour']);
        $checker->set_month($data['month']);
        $checker->set_day_of_week($data['dayofweek']);
        $checker->set_day($data['day']);
        $checker->set_disabled(false);

        if (!$checker->is_valid($checker::FIELD_MINUTE)) {
            $error['minutegroup'] = get_string('invaliddata', 'core_error');
        }
        if (!$checker->is_valid($checker::FIELD_HOUR)) {
            $error['hourgroup'] = get_string('invaliddata', 'core_error');
        }
        if (!$checker->is_valid($checker::FIELD_DAY)) {
            $error['daygroup'] = get_string('invaliddata', 'core_error');
        }
        if (!$checker->is_valid($checker::FIELD_MONTH)) {
            $error['monthgroup'] = get_string('invaliddata', 'core_error');
        }
        if (!$checker->is_valid($checker::FIELD_DAYOFWEEK)) {
            $error['dayofweekgroup'] = get_string('invaliddata', 'core_error');
        }

        return $error;
    }
}

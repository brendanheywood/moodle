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
 * Long running tasks check
 *
 * @package    tool_task
 * @author     Brendan Heywood (brendan@catalyst-au.net)
 * @copyright  2022 Catalyst IT Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_task\check;

defined('MOODLE_INTERNAL') || die();

use core\check\check;
use core\check\result;
use core\task\manager;

/**
 * Long running tasks check
 *
 * @package    tool_task
 * @author     Brendan Heywood (brendan@catalyst-au.net)
 * @copyright  2022 Catalyst IT Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class longrunningtasks extends check {

    /**
     * Constructor
     */
    public function __construct() {
        global $CFG;
        $this->id = 'longrunningtasks';
        $this->name = get_string('checklongrunningtasks', 'tool_task');
    }

    /**
     * Links to the running task list
     *
     * @return \action_link|null
     * @throws \coding_exception
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/tool/task/runningtasks.php');
        return new \action_link($url, get_string('runningtasks', 'tool_task'));
    }

    /**
     * Return result
     * @return result
     */
    public function get_result() : result {
        global $CFG;

        $status = result::OK;
        $slowtasks = 0;

        $tasks = \core\task\manager::get_running_tasks();
        foreach ($tasks as $record) {
            if (!in_array($record->type, ['adhoc', 'scheduled'])) {
                continue;
            }

            $managermethod = $record->type . "_task_from_record";
            $task = manager::$managermethod($record);
            $result = $task->get_runtime_status();
            $taskstatus = $result->get_status();

            if ($taskstatus == result::OK) {
                continue;
            }
            $slowtasks++;

            // The overall check status is the worst tasks status.
            $status = ($status !== result::ERROR) ? $taskstatus : $status;
        }

        $details = get_string('checklongrunningtaskcount', 'tool_task', $slowtasks);
        // $summary = get_string('longrunningtasks', 'tool_task');
        $summary= $details;
        return new result($status, $summary, $details);
    }
}

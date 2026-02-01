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
 * Script deletes an adhoc task.
 *
 * @package tool_task
 * @copyright Catalyst
 * @author Waleed ul hassan
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require('../../../config.php');

// Basic security checks.
require_admin();
require_sesskey();

// Get task and check the parameter is valid.
$taskid = required_param('taskid', PARAM_INT);
$classname = optional_param('classname', '', PARAM_TEXT);
$returnurl = new moodle_url('/admin/tool/task/adhoctasks.php', ['classname' => $classname]);

$task = null;
try {
    $task = \core\task\manager::get_adhoc_task($taskid);
    if (!$task) {
        redirect($returnurl, get_string('deleteadhoclocked', 'tool_task'), null, \core\output\notification::NOTIFY_WARNING);
    }
    \core\task\manager::delete_adhoc_task($taskid);
} catch (moodle_exception $e) {
    if ($e->errorcode !== 'invalidtaskid') {
        throw $e;
    }
    redirect($returnurl, get_string('deleteadhocnotfound', 'tool_task'), null, \core\output\notification::NOTIFY_ERROR);
} finally {
    // The get_adhoc_task() call hands the task back with a lock held. The
    // delete_adhoc_task() call does not know about the lock, so it is this
    // caller's responsibility to always release it, even if an exception is
    // thrown in between.
    $task?->get_lock()?->release();
}

redirect($returnurl, get_string('deleteadhocsuccess', 'tool_task'), null, \core\output\notification::NOTIFY_SUCCESS);

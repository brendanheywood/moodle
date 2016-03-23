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
 * Download the raw feedback results in any data format
 *
 * @copyright 2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_feedback
 */

define('NO_OUTPUT_BUFFERING', true);
require_once("../../config.php");
require_once("lib.php");
require_once($CFG->dirroot . '/lib/dataformatlib.php');
feedback_load_feedback_items();

$id           = required_param('id', PARAM_INT);
$dataformat   = required_param('dataformat', PARAM_ALPHA);
$coursefilter = optional_param('coursefilter', '0', PARAM_INT);
$url = new moodle_url('/mod/feedback/download.php', array('id' => $id));
if ($coursefilter !== '0') {
    $url->param('coursefilter', $coursefilter);
}
$PAGE->set_url($url);
if (!$cm = get_coursemodule_from_id('feedback', $id)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}
if (!$feedback = $DB->get_record("feedback", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_sesskey();
require_capability('mod/feedback:viewreports', $context);

$params = array('feedback' => $feedback->id, 'hasvalue' => 1);
if (!$items = $DB->get_records('feedback_item', $params, 'position')) {
    print_error('no_items_available_yet',
                'feedback',
                new moodle_url('/mod/feedback/view.php', array('id' => $id)) );
    exit;
}

list($columns, $rs, $anon) = feedback_download_get_data($items, $cm, $context);

$filename = $feedback->name;
$groupid = groups_get_activity_group($cm);
if ($groupid) {
    $filename .= ' (' . groups_get_group_name($groupid) . ')';
}

download_as_dataformat($filename, $dataformat, $columns, $rs, 'feedback_download_process_record', $anon);
$rs->close();


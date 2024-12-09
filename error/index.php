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
 * Moodle 404 Error page
 *
 * This is for 404 error pages served by the webserver and then passed
 * to Moodle to be rendered using the site theme.
 *
 * ErrorDocument 404 /error/index.php
 *
 * @package    core
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php'); // phpcs:ignore

// Until we have a more robust routing api in place this is a very simple
// and clean way to handle arbitrary urls without a php extension.
if ($ME === '/.well-known/change-password') {
    redirect(new moodle_url('/login/change_password.php'));
}

$context = context_system::instance();
$title = get_string('pagenotexisttitle', 'error');
$PAGE->set_url('/error/index.php');
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);

// This allows the webserver to dictate wether the http status should remain
// what it would have been, or force it to be a 404. Under other conditions
// it could most often be a 403, 405 or a 50x error.
$code = optional_param('code', 0, PARAM_INT);
if ($code == 404) {
    header("HTTP/1.0 404 Not Found");
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('pagenotexist', 'error', s($ME)), 'error');
echo $OUTPUT->supportemail(['class' => 'text-center d-block mb-3 fw-bold']);
echo $OUTPUT->continue_button($CFG->wwwroot);
echo $OUTPUT->footer();

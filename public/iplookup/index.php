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
 * Displays IP address on map.
 *
 * This script is compatible with IPv4 and IPv6.
 *
 * @package    core_iplookup
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once('lib.php');

require_login(0, false);
if (isguestuser()) {
    // Guest users cannot perform lookups.
    throw new require_login_exception('Guests are not allowed here.');
}

$ip = optional_param('ip', getremoteaddr(), PARAM_RAW);
$user = optional_param('user', 0, PARAM_INT);
$course = optional_param('course', 0, PARAM_INT);
$width = optional_param('width', 0, PARAM_INT);
$height = optional_param('height', 0, PARAM_INT);
$ispopup = optional_param('popup', 0, PARAM_INT);

$urlparams = [];
if (!empty($ip)) {
    $urlparams['ip'] = $ip;
}
if (!empty($user)) {
    $urlparams['user'] = $user;
}
if (!empty($course)) {
    $urlparams['course'] = $course;
}

// Params width and height are set, we assume to have a popup.
if ($width > 0 && $height > 0) {
    $urlparams['width'] = $width;
    $urlparams['height'] = $height;
    $ispopup = 1;
} else if ($ispopup === 1) {  // Param popup was set, then we know that we want a popup.
    $urlparams['ispopup'] = 1;
}
// Set the page layout accordingly.
if ($ispopup) {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_pagelayout('standard');
}

$PAGE->set_url('/iplookup/index.php', $urlparams);

$info = iplookup_find_location($ip);

if ($course) {
    // If given a course then user is required.
    $user = required_param('user', PARAM_INT);
    $course = $DB->get_record('course', ['id' => $course], '*', MUST_EXIST);
    require_login($course);
    $PAGE->set_course($course);
}

if ($user) {
    // If we are looking at the IP of a user we must provide the IP
    // rather than lookup our own IP.
    $ip = required_param('ip', PARAM_RAW);
    $user = $DB->get_record('user', ['id' => $user, 'deleted' => 0]);
    $context = context_user::instance($user->id);
}

if ($course) {
    $title = fullname($user) . ' | ' . get_string('iplookup', 'admin');
    $PAGE->set_context(context_course::instance($course->id));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_title($title);
    $PAGE->navbar->add(fullname($user), new moodle_url('/user/view.php', [
        'id' => $user->id,
        'course' => $course->id,
    ]));
    $PAGE->navbar->add(get_string('iplookup', 'admin'));

    echo $OUTPUT->header();
    echo $OUTPUT->context_header([
        'heading' => fullname($user),
        'user' => $user,
    ], 2);
    echo $OUTPUT->heading(get_string('iplookup', 'admin'), 3);
} else if ($user && has_capability('moodle/user:viewdetails', context_user::instance($user->id))) {
    $title = fullname($user) . ' | ' . get_string('iplookup', 'admin');
    $PAGE->navbar->add(get_string('iplookup', 'admin'), '');
    $PAGE->set_context(context_user::instance($user->id));
    $PAGE->navigation->extend_for_user($user);
    $PAGE->set_heading($title);
    $PAGE->set_title($title);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('iplookup', 'admin'), 3);
} else {
    $title = get_string('iplookup', 'admin') . ': ' . $ip;
    $PAGE->set_context(context_system::instance());
    $PAGE->set_heading($title);
    $PAGE->set_title($title);
    echo $OUTPUT->header();
}

if ($info['error']) {
    echo $OUTPUT->notification($info['error']);
}

$showmap = $info && !empty($info['longitude']) && !empty($info['latitude']);
$map = '';

// The map dimension is here as big as the popup/page is, so max width and at least 360px height.
if ($ispopup) {
    $mapdim = 'width: ' . (($width > 0) ? $width . 'px' : '100%')
        . '; height: ' . (($height > 0) ? $height . 'px; ' : '100%; min-height: 400px;');
} else {
    $mapdim = 'width: 100%; height: 100%; min-height: 400px';
}

if (empty($CFG->googlemapkey3)) {
    // If no Google API key is set then we use OpenStreetMap.
    // Have a fixed zoom factor to calculate corners of the map.
    $fkt = 10;
    $bboxl = $info['longitude'] - $fkt;
    $bboxr = $info['longitude'] + $fkt;
    $bboxb = $info['latitude'] - $fkt;
    $bboxt = $info['latitude'] + $fkt;

    $url = (new moodle_url('https://www.openstreetmap.org/export/embed.html', [
        'bbox' => "$bboxl,$bboxb,$bboxr,$bboxt",
        'layer' => "mapnik",
        'marker' => "{$info['latitude']},{$info['longitude']}",
    ]))->out();
    $map = "<div id='map' style='$mapdim'><object data='$url' style='$mapdim'></object></div>";
} else {
    // Google API key is set, then use Google Maps.
    $PAGE->requires->js(new moodle_url(
        'https://maps.googleapis.com/maps/api/js',
        [
            'key' => $CFG->googlemapkey3,
            'sensor' => 'false',
        ]
    ));
    $module = ['name' => 'core_iplookup', 'fullpath' => '/iplookup/module.js'];
    $PAGE->requires->js_init_call('M.core_iplookup.init3', [$info['latitude'], $info['longitude'], $ip], true, $module);
    $map = "<div id='map' style='$mapdim'></div>";
}

echo $OUTPUT->render_from_template('core/iplookup', [
    'ip' => $ip,
    'info' => $info,
    'showmap' => $showmap,
    'map' => $map,
]);

echo $OUTPUT->footer();

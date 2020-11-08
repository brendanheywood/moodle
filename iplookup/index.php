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
 * This script is not compatible with IPv6.
 *
 * @package    core
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

$ip   = optional_param('ip', getremoteaddr(), PARAM_RAW);
$user = optional_param('user', 0, PARAM_INT);

if (isset($CFG->iplookup)) {
    // Clean up of old settings.
    set_config('iplookup', null);
}

$PAGE->set_url('/iplookup/index.php', ['id' => $ip, 'user' => $user]);
$PAGE->set_pagelayout('report');
$PAGE->set_context(context_system::instance());

$info = array($ip);
$note = array();

if (cleanremoteaddr($ip) === false) {
    print_error('invalidipformat', 'error');
}

if (ip_is_public($ip)) {
    $info = iplookup_find_location($ip);
    array_unshift($info['title'], $ip);
    $title = implode(' - ', $info['title']);
} else {
    $info = [
        'error'  => get_string('iplookupprivate', 'error'),
        'note'  => '',
    ];
    $title  = "$ip - " . get_string('iplookupprivate', 'error');
}

if ($user) {
    if ($user = $DB->get_record('user', ['id' => $user, 'deleted' => 0])) {
        // Note: better not show full names to everybody.
        if (has_capability('moodle/user:viewdetails', context_user::instance($user->id))) {
            $title .= ' - ' . fullname($user);
        }
    }
}

$PAGE->set_title(get_string('iplookup', 'admin').': '.$title);
$PAGE->set_heading($title);
echo $OUTPUT->header();

if (!empty($info['error'])) {
    echo $OUTPUT->notification($info['error'],  \core\notification::ERROR);
}

if (isset($info['longitude']) && isset($info['latitude'])) {
    if (empty($CFG->googlemapkey3)) {
        $imgwidth  = 620;
        $imgheight = 310;
        $dotwidth  = 18;
        $dotheight = 30;

        $ml = -$dotwidth / 2;
        $mt = -$dotheight;

        $dx = sprintf('%.1f', (180 + $info['longitude']) / 360 * 100 );
        $dy = sprintf('%.1f',  (90 - $info['latitude'] ) / 180 * 100 );

        echo html_writer::start_tag('div', ['style' => "max-width:{$imgwidth}px; position: relative;"]);
        echo html_writer::img('earth.jpeg', '', [
            'width' => '100%',
            'height' => '100%',
            'alt' => '',
        ]);
        echo html_writer::img('marker.gif', $title, [
            'style' => "position: absolute; width:{$dotwidth}px; height:{$dotheight}px;"
                . " left: {$dx}%; top: {$dy}%; margin-left:{$ml}px; margin-top:{$mt}px;",
            'title' => $title,
        ]);
        echo html_writer::end_tag('div');

    } else {
        if (is_https()) {
            $PAGE->requires->js(new moodle_url('https://maps.googleapis.com/maps/api/js',
                ['key' => $CFG->googlemapkey3, 'sensor' => 'false']));
        } else {
            $PAGE->requires->js(new moodle_url('http://maps.googleapis.com/maps/api/js',
                ['key' => $CFG->googlemapkey3, 'sensor' => 'false']));
        }
        $module = ['name' => 'core_iplookup', 'fullpath' => '/iplookup/module.js'];
        $PAGE->requires->js_init_call('M.core_iplookup.init3', array($info['latitude'], $info['longitude'], $ip), true, $module);

        echo '<div id="map" style="width: 650px; height: 360px"></div>';
    }
}

echo html_writer::tag('div', $info['note'], ['id' => 'note']);

echo $OUTPUT->footer();

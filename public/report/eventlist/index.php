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
 * Event documentation.
 *
 * @package   report_eventlist
 * @copyright 2014 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('reporteventlists');

$component = optional_param('component', '', PARAM_COMPONENT);
$search    = optional_param('search', '', PARAM_TEXT);
$crud      = optional_param('crud', '', PARAM_ALPHA);
// Use PARAM_RAW and whitelist to avoid PARAM_INT coercing '' to 0 (which equals LEVEL_OTHER).
$edulevel  = optional_param('edulevel', '', PARAM_RAW);
$validedulevels = [
    '',
    (string)\core\event\base::LEVEL_TEACHING,
    (string)\core\event\base::LEVEL_PARTICIPATING,
    (string)\core\event\base::LEVEL_OTHER,
];
if (!in_array($edulevel, $validedulevels, true)) {
    $edulevel = '';
}
$observerfilter = optional_param('observerfilter', '', PARAM_ALPHA);
if (!in_array($observerfilter, ['', 'observed'], true)) {
    $observerfilter = '';
}

echo $OUTPUT->header();
$table = new \report_eventlist\table\event_list_table($component, $search, $crud, $edulevel, $observerfilter);
ob_start();
$table->out();
$tablehtml = ob_get_clean();

echo $OUTPUT->heading(get_string('pluginname', 'report_eventlist') . ' (' . $table->rowcount . ')');

echo html_writer::start_tag('ul', ['class' => 'nav nav-tabs mb-3']);
echo html_writer::tag(
    'li',
    html_writer::link(
        new moodle_url('/report/eventlist/index.php'),
        get_string('pluginname', 'report_eventlist'),
        ['class' => 'nav-link active', 'aria-current' => 'page']
    ),
    ['class' => 'nav-item']
);
echo html_writer::tag(
    'li',
    html_writer::link(
        new moodle_url('/admin/hooks.php'),
        get_string('hooksoverview', 'core_admin'),
        ['class' => 'nav-link']
    ),
    ['class' => 'nav-item']
);
echo html_writer::tag(
    'li',
    html_writer::link(
        new moodle_url('/admin/callbacks.php'),
        get_string('callbacksoverview', 'core_admin'),
        ['class' => 'nav-link']
    ),
    ['class' => 'nav-item']
);
echo html_writer::end_tag('ul');
$allcomponents = report_eventlist_list_generator::get_component_list();

// Render the filter form.
$filterurl = new moodle_url('/report/eventlist/index.php');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $filterurl,
    'class'  => 'mb-3 d-flex gap-2 align-items-end flex-wrap',
]);

echo html_writer::start_div('form-group d-flex flex-column', ['style' => 'max-width: 12em']);
echo html_writer::tag('label', get_string('eventsearch', 'report_eventlist'), ['for' => 'id_search', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'search',
    'id'          => 'id_search',
    'value'       => s($search),
    'placeholder' => get_string('eventsearchplaceholder', 'report_eventlist'),
    'class'       => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column', ['style' => 'max-width: 20em']);
echo html_writer::tag('label', get_string('eventcomponent', 'report_eventlist'), [
    'for'   => 'id_component',
    'class' => 'form-label',
]);
$options = ['' => get_string('all', 'report_eventlist')] + $allcomponents;
echo html_writer::select($options, 'component', $component, false, ['id' => 'id_component', 'class' => 'custom-select']);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column');
echo html_writer::tag('label', get_string('crud', 'report_eventlist'), ['for' => 'id_crud', 'class' => 'form-label']);
$crudoptions = [
    ''  => get_string('all', 'report_eventlist'),
    'c' => get_string('create', 'report_eventlist'),
    'r' => get_string('read', 'report_eventlist'),
    'u' => get_string('update', 'report_eventlist'),
    'd' => get_string('delete', 'report_eventlist'),
];
echo html_writer::select($crudoptions, 'crud', $crud, false, ['id' => 'id_crud', 'class' => 'custom-select w-auto']);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column');
echo html_writer::tag('label', get_string('edulevel', 'report_eventlist'), ['for' => 'id_edulevel', 'class' => 'form-label']);
$eduoptions = [
    ''  => get_string('all', 'report_eventlist'),
    (string)\core\event\base::LEVEL_TEACHING      => get_string('teaching', 'report_eventlist'),
    (string)\core\event\base::LEVEL_PARTICIPATING => get_string('participating', 'report_eventlist'),
    (string)\core\event\base::LEVEL_OTHER         => get_string('other', 'report_eventlist'),
];
echo html_writer::select($eduoptions, 'edulevel', $edulevel, false, ['id' => 'id_edulevel', 'class' => 'custom-select w-auto']);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column');
echo html_writer::tag('label', get_string('observers', 'report_eventlist'), [
    'for'   => 'id_observerfilter',
    'class' => 'form-label',
]);
$observeroptions = [
    ''         => get_string('all', 'report_eventlist'),
    'observed' => get_string('observerfilter_observed', 'report_eventlist'),
];
echo html_writer::select($observeroptions, 'observerfilter', $observerfilter, false, [
    'id'    => 'id_observerfilter',
    'class' => 'custom-select w-auto',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group align-self-end');
echo html_writer::tag('button', get_string('filter', 'report_eventlist'), ['type' => 'submit', 'class' => 'btn btn-primary']);
if ($component !== '' || $search !== '' || $crud !== '' || $edulevel !== '' || $observerfilter !== '') {
    echo ' ';
    echo html_writer::link($filterurl, get_string('clear', 'report_eventlist'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $tablehtml;

echo $OUTPUT->footer();

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
 * Legacy callbacks overview page.
 *
 * Lists all call-sites that use get_plugins_with_function() or
 * get_plugin_list_with_function() discovered via static analysis,
 * and shows whether each has been migrated to a hook.
 *
 * @package   core_admin
 * @copyright 2025 onwards
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('callbacksoverview');
require_capability('moodle/site:config', \core\context\system::instance());

$search    = optional_param('search', '', PARAM_TEXT);
$component = optional_param('component', '', PARAM_COMPONENT);
$status    = optional_param('status', '', PARAM_ALPHA);
$refresh   = optional_param('refresh', 0, PARAM_INT);

if ($status !== '' && !in_array($status, ['legacy', 'migrated'], true)) {
    $status = '';
}

// Force a fresh scan and redirect back so the page renders clean results.
if ($refresh) {
    \core_admin\local\callback_scanner::scan(true);
    redirect(new moodle_url('/admin/callbacks.php', [
        'search'    => $search,
        'component' => $component,
        'status'    => $status,
    ]));
}

$table = new \core_admin\table\callback_list_table($search, $component, $status);
ob_start();
$table->out();
$tablehtml = ob_get_clean();

$count = $table->rowcount;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('callbacksoverview', 'core_admin') . " ($count)");

// Navigation tabs shared with the Events overview and Hooks overview pages.
echo html_writer::start_tag('ul', ['class' => 'nav nav-tabs mb-3']);
echo html_writer::tag(
    'li',
    html_writer::link(
        new moodle_url('/report/eventlist/index.php'),
        get_string('pluginname', 'report_eventlist'),
        ['class' => 'nav-link']
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
        ['class' => 'nav-link active', 'aria-current' => 'page']
    ),
    ['class' => 'nav-item']
);
echo html_writer::end_tag('ul');

// Filter form.
$filterurl = new moodle_url('/admin/callbacks.php');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $filterurl,
    'class'  => 'mb-3 d-flex gap-2 align-items-end flex-wrap',
]);

echo html_writer::start_div('form-group d-flex flex-column', ['style' => 'max-width: 12em']);
echo html_writer::tag('label', get_string('callbacksearch', 'core_admin'), [
    'for'   => 'id_search',
    'class' => 'form-label',
]);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'search',
    'id'          => 'id_search',
    'value'       => s($search),
    'placeholder' => get_string('callbacksearchplaceholder', 'core_admin'),
    'class'       => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column', ['style' => 'max-width: 20em']);
echo html_writer::tag('label', get_string('callbackcomponent', 'core_admin'), [
    'for'   => 'id_component',
    'class' => 'form-label',
]);
$options = ['' => get_string('all', 'moodle')] + $table->get_component_list();
echo html_writer::select($options, 'component', $component, false, [
    'id'    => 'id_component',
    'class' => 'custom-select',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column');
echo html_writer::tag('label', get_string('callbackstatus', 'core_admin'), [
    'for'   => 'id_status',
    'class' => 'form-label',
]);
$statusoptions = [
    ''         => get_string('all', 'moodle'),
    'legacy'   => get_string('callbacklegacy', 'core_admin'),
    'migrated' => get_string('callbackmigrated', 'core_admin'),
];
echo html_writer::select($statusoptions, 'status', $status, false, [
    'id'    => 'id_status',
    'class' => 'custom-select w-auto',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group align-self-end');
echo html_writer::tag('button', get_string('filter', 'core_admin'), [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
]);
if ($search !== '' || $component !== '' || $status !== '') {
    echo ' ';
    echo html_writer::link($filterurl, get_string('clear', 'core_admin'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Refresh cache link (the scan is cached for 1 hour; use this to force a re-scan).
$refreshurl = new moodle_url('/admin/callbacks.php', [
    'refresh'   => 1,
    'search'    => $search,
    'component' => $component,
    'status'    => $status,
]);
echo html_writer::div(
    html_writer::link(
        $refreshurl,
        $OUTPUT->pix_icon('t/reload', get_string('callbackrefresh', 'core_admin'))
            . ' ' . get_string('callbackrefresh', 'core_admin'),
        ['class' => 'btn btn-sm btn-secondary mb-3']
    )
);

echo $tablehtml;

echo $OUTPUT->footer();

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
 * Hooks overview page.
 *
 * @package   core
 * @author    Petr Skoda
 * @copyright 2022 Open LMS
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('hooksoverview');
require_capability('moodle/site:config', \core\context\system::instance());

$search    = optional_param('search', '', PARAM_TEXT);
$component = optional_param('component', '', PARAM_COMPONENT);

$hookmanager = \core\di::get(\core\hook\manager::class);

$table = new \core_admin\table\hook_list_table($search, $component);
ob_start();
$table->out();
$tablehtml = ob_get_clean();

$hookcount = $table->rowcount;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hooksoverview', 'core_admin') . " ($hookcount)");

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
        ['class' => 'nav-link active', 'aria-current' => 'page']
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

$filterurl = new moodle_url('/admin/hooks.php');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $filterurl,
    'class'  => 'mb-3 d-flex gap-2 align-items-end flex-wrap',
]);

echo html_writer::start_div('form-group d-flex flex-column', ['style' => 'max-width: 12em']);
echo html_writer::tag('label', get_string('hooksearch', 'core_admin'), ['for' => 'id_search', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'search',
    'id'          => 'id_search',
    'value'       => s($search),
    'placeholder' => get_string('hooksearchplaceholder', 'core_admin'),
    'class'       => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group d-flex flex-column', ['style' => 'max-width: 20em']);
echo html_writer::tag('label', get_string('hookcomponent', 'core_admin'), ['for' => 'id_component', 'class' => 'form-label']);
$options = ['' => get_string('all', 'moodle')] + $table->get_component_list();
echo html_writer::select($options, 'component', $component, false, ['id' => 'id_component', 'class' => 'custom-select']);
echo html_writer::end_div();

echo html_writer::start_div('form-group align-self-end');
echo html_writer::tag('button', get_string('filter', 'core_admin'), ['type' => 'submit', 'class' => 'btn btn-primary']);
if ($search !== '' || $component !== '') {
    echo ' ';
    echo html_writer::link($filterurl, get_string('clear', 'core_admin'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $tablehtml;

echo $OUTPUT->footer();

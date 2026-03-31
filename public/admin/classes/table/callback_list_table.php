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

namespace core_admin\table;

use core_admin\local\callback_scanner;
use flexible_table;
use html_writer;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->libdir}/tablelib.php");

/**
 * Table listing legacy plugin callback call-sites discovered by static analysis.
 *
 * Supports filtering by free-text search, component, and migration status.
 *
 * @package   core_admin
 * @copyright 2025 onwards
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callback_list_table extends flexible_table {
    /** @var string Free-text search filter (matches callback name, component, or source file path). */
    protected string $search = '';

    /** @var string Component filter (exact match on the component string). */
    protected string $component = '';

    /** @var string Status filter: '' = all, 'legacy' = not migrated, 'migrated' = migrated. */
    protected string $status = '';

    /** @var int Number of rows rendered after all filters have been applied. */
    public int $rowcount = 0;

    /**
     * Constructor.
     *
     * @param string $search    Free-text filter.
     * @param string $component Component filter.
     * @param string $status    Status filter ('' | 'legacy' | 'migrated').
     */
    public function __construct(string $search = '', string $component = '', string $status = '') {
        global $CFG;

        $this->search    = $search;
        $this->component = $component;
        $this->status    = $status;

        $this->define_baseurl('/admin/callbacks.php');
        parent::__construct('core_admin-callback_list_table');

        $this->define_columns(['callbackname', 'component', 'status', 'implementors', 'sourcefn']);
        $this->define_headers([
            get_string('callbackname', 'core_admin'),
            get_string('component', 'core_admin'),
            get_string('callbackstatus', 'core_admin'),
            get_string('callbackimplementors', 'core_admin'),
            get_string('callbackfunction', 'core_admin'),
        ]);

        $this->setup();
    }

    /**
     * Render the table, applying all active filters.
     */
    public function out(): void {
        $hookmanager = \core\di::get(\core\hook\manager::class);
        $callbacks   = callback_scanner::scan();

        foreach ($callbacks as $record) {
            // Status filter.
            if ($this->status === 'legacy' && $record['migratedtohook']) {
                continue;
            }
            if ($this->status === 'migrated' && !$record['migratedtohook']) {
                continue;
            }

            // Component filter.
            if ($this->component !== '' && $record['component'] !== $this->component) {
                continue;
            }

            // Free-text search (callback name, component, or source file path).
            if ($this->search !== '') {
                $lower = strtolower($this->search);
                if (
                        strpos(strtolower($record['callbackname']), $lower) === false
                        && strpos(strtolower($record['component']), $lower) === false
                        && strpos(strtolower($record['file']), $lower) === false
                ) {
                    continue;
                }
            }

            // Cross-reference with hook manager to find the replacing hook class(es).
            $hookclasses = $hookmanager->get_hooks_deprecating_plugin_callback($record['callbackname']);
            $hookclass   = $hookclasses ? implode(', ', $hookclasses) : null;

            $this->rowcount++;
            $this->add_data_keyed($this->format_row((object) array_merge($record, ['hookclass' => $hookclass])));
        }

        $this->finish_output(false);
    }

    /**
     * Render the callbackname column, including the source file and line number beneath it.
     *
     * @param stdClass $row Table row data.
     * @return string
     */
    protected function col_callbackname(stdClass $row): string {
        $out = s($row->callbackname);
        if ($row->plugintype !== null) {
            $out .= ' ' . html_writer::tag(
                'small',
                html_writer::span(
                    get_string('callbackplugintype', 'core_admin', s($row->plugintype)),
                    'badge badge-secondary'
                )
            );
        }
        $filewithline = s($row->file);
        if (!empty($row->line)) {
            $filewithline .= ':' . (int)$row->line;
        }
        $out .= html_writer::tag(
            'div',
            html_writer::tag('code', $filewithline, ['class' => 'text-muted']),
            ['class' => 'small mt-1']
        );
        return $out;
    }

    /**
     * Render the component column.
     *
     * @param stdClass $row Table row data.
     * @return string
     */
    protected function col_component(stdClass $row): string {
        $manager   = get_string_manager();
        $component = $row->component;
        if ($component === 'core') {
            return get_string('core', 'core_admin');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $component) && $manager->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component)
                . html_writer::tag('small', ' (' . s($component) . ')');
        }
        return s($component);
    }

    /**
     * Render the sourcefn (function name) column.
     *
     * @param stdClass $row Table row data.
     * @return string
     */
    protected function col_sourcefn(stdClass $row): string {
        return html_writer::tag('code', s($row->sourcefn));
    }

    /**
     * Render the status column with a badge and, if migrated, the hook class name.
     *
     * @param stdClass $row Table row data.
     * @return string
     */
    protected function col_status(stdClass $row): string {
        if ($row->migratedtohook) {
            $badge = html_writer::span(
                get_string('callbackmigrated', 'core_admin'),
                'badge badge-success'
            );
            if ($row->hookclass) {
                $badge .= html_writer::tag('div', html_writer::tag('small', s($row->hookclass)), ['class' => 'mt-1']);
            }
            return $badge;
        }
        return html_writer::span(get_string('callbacklegacy', 'core_admin'), 'badge badge-warning');
    }

    /**
     * Render the implementors column — the plugins that define this callback.
     *
     * @param stdClass $row Table row data.
     * @return string
     */
    protected function col_implementors(stdClass $row): string {
        $implementors = $row->implementors ?? [];
        if (empty($implementors)) {
            return html_writer::tag('small', html_writer::span(get_string('none'), 'text-muted'));
        }
        $out = html_writer::start_tag('ol');
        foreach ($implementors as $impl) {
            $function   = $impl['function'] ?? '';
            $file       = $impl['file'] ?? '';
            $hookstatus = $impl['hookstatus'] ?? null;

            $item = s($function);

            if ($hookstatus === 'both') {
                $item .= ' ' . html_writer::span(
                    get_string('callbackhookadopted', 'core_admin'),
                    'badge badge-success'
                );
            } else if ($hookstatus === 'legacy_only') {
                $item .= ' ' . html_writer::span(
                    get_string('callbacklegacyonly', 'core_admin'),
                    'badge badge-warning'
                );
            }

            if ($file !== '') {
                $item .= html_writer::tag(
                    'div',
                    html_writer::tag('code', s($file), ['class' => 'text-muted']),
                    ['class' => 'small']
                );
            }
            $out .= html_writer::tag('li', $item);
        }
        $out .= html_writer::end_tag('ol');
        return $out;
    }

    /**
     * Return a sorted list of unique components found in the scan, for the filter select.
     *
     * @return array  component => display label
     */
    public function get_component_list(): array {
        return callback_scanner::get_component_list();
    }
}

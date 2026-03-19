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

namespace report_eventlist\table;

use core_component;
use flexible_table;
use html_writer;
use moodle_url;
use report_eventlist_list_generator;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/tablelib.php");

/**
 * Event list table for the event list report.
 *
 * @package    report_eventlist
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_list_table extends flexible_table {
    /** @var array All event observers keyed by event class name. */
    protected array $allobservers = [];

    /** @var int Number of rows matched after filtering. */
    public int $rowcount = 0;

    /**
     * Constructor.
     *
     * @param string $component Optional component filter (frankenstyle, e.g. 'mod_forum').
     * @param string $search Optional free-text search string.
     * @param string $crud Optional CRUD filter (c/u/r/d).
     * @param string $edulevel Optional edulevel filter (0/1/2).
     * @param string $observerfilter Optional observer filter ('observed' or 'unobserved').
     */
    public function __construct(
        /** @var string Optional component filter (frankenstyle, e.g. 'mod_forum'). */
        protected string $component = '',
        /** @var string Optional free-text search string. */
        protected string $search = '',
        /** @var string Optional CRUD filter (c/u/r/d). */
        protected string $crud = '',
        /** @var string Optional edulevel filter (0/1/2). */
        protected string $edulevel = '',
        /** @var string Optional observer filter ('observed' or 'unobserved'). */
        protected string $observerfilter = '',
    ) {
        global $CFG;

        $baseurl = new moodle_url('/report/eventlist/index.php');
        $this->define_baseurl($baseurl);
        parent::__construct('report_eventlist-event_list_table');

        $this->allobservers = report_eventlist_list_generator::get_observer_list();

        $this->setup_column_configuration();
        $this->setup();
        $this->apply_column_widths();
    }

    /**
     * Apply column widths after setup() has initialised the column_style arrays.
     */
    protected function apply_column_widths(): void {
        $widths = [
            'crud'        => '8%',
            'details'     => '21%',
            'observers'   => '41%',
            'edulevel'    => '10%',
            'since'       => '7%',
            'objecttable' => '13%',
        ];
        foreach ($widths as $col => $width) {
            $this->column_style($col, 'width', $width);
        }
    }

    /**
     * Set up column configuration for this table.
     */
    protected function setup_column_configuration(): void {
        $this->define_columns(['crud', 'details', 'observers', 'edulevel', 'since', 'objecttable']);
        $this->define_headers([
            get_string('crud', 'report_eventlist'),
            get_string('eventname', 'report_eventlist'),
            get_string('relatedobservers', 'report_eventlist'),
            get_string('edulevel', 'report_eventlist'),
            get_string('since', 'report_eventlist'),
            get_string('affectedtable', 'report_eventlist'),
        ]);
        $this->sortable(false);
        $this->collapsible(false);
        $this->column_class('crud', 'text-end');
        $this->set_attribute('style', 'table-layout: fixed; width: 100%;');

        $columns = ['crud', 'details', 'observers', 'edulevel', 'since', 'objecttable'];
        $columnhelp = array_map(function (string $column): ?\renderable {
            if ($column === 'observers') {
                return new \help_icon('relatedobservers', 'report_eventlist');
            }
            return null;
        }, $columns);
        $this->define_help_for_headers($columnhelp);
    }

    /**
     * Output the table.
     */
    public function out(): void {
        // Suppress debug messages while reflecting over deprecated events.
        global $CFG;
        $debuglevel     = $CFG->debug;
        $debugdisplay   = $CFG->debugdisplay;
        $debugdeveloper = $CFG->debugdeveloper;
        $CFG->debug          = 0;
        $CFG->debugdisplay   = false;
        $CFG->debugdeveloper = false;

        $eventsignore = [\core\event\unknown_logged::class];

        $events = core_component::get_component_classes_in_namespace(null, 'event');

        // Sort: core events first, then alphabetically by class name.
        ksort($events);
        $coreevents = [];
        foreach ($events as $classname => $path) {
            if (str_starts_with($classname, 'core\\') || str_starts_with($classname, 'core_')) {
                $coreevents[$classname] = $path;
                unset($events[$classname]);
            }
        }
        $events = array_merge($coreevents, $events);

        foreach (array_keys($events) as $classname) {
            if (!is_a($classname, \core\event\base::class, true)) {
                continue;
            }
            $ref = new \ReflectionClass($classname);
            if ($ref->isAbstract()) {
                continue;
            }
            if (in_array($classname, $eventsignore)) {
                continue;
            }

            $info = $classname::get_static_info();
            $eventcomponent = $info['component'];

            // Apply component filter.
            if ($this->component !== '' && $eventcomponent !== $this->component) {
                continue;
            }

            // Apply CRUD filter.
            if ($this->crud !== '' && ($info['crud'] ?? '') !== $this->crud) {
                continue;
            }

            // Apply edulevel filter.
            if ($this->edulevel !== '' && (string)($info['edulevel'] ?? '') !== $this->edulevel) {
                continue;
            }

            // Apply free-text search (class name or human name).
            if ($this->search !== '') {
                $humanname = strtolower($classname::get_name_with_info());
                $lowerclass = strtolower($classname);
                $lowersearch = strtolower($this->search);
                if (strpos($lowerclass, $lowersearch) === false && strpos($humanname, $lowersearch) === false) {
                    continue;
                }
            }

            // Extract "since" from docblock.
            $docblock = $ref->getDocComment();
            $since = null;
            if ($docblock && preg_match('/since\s+Moodle\s+([0-9]+\.[0-9]+)/i', $docblock, $m)) {
                $since = $m[1];
            }

            $row = (object) [
                'classname'   => $classname,
                'info'        => $info,
                'since'       => $since,
                'explanation' => $classname::get_explanation(),
            ];

            $this->rowcount++;
            $this->add_data_keyed(
                $this->format_row($row),
                $this->get_row_class($row),
            );
        }

        // Restore debug settings.
        $CFG->debug          = $debuglevel;
        $CFG->debugdisplay   = $debugdisplay;
        $CFG->debugdeveloper = $debugdeveloper;

        $this->finish_output(false);
    }

    /**
     * Render the details column: human name (linked), class name, type badges.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_details(stdClass $row): string {
        $classname = $row->classname;
        $url  = new moodle_url('/report/eventlist/eventdetail.php', ['eventname' => '\\' . $classname]);
        $name = $classname::get_name_with_info();

        $output  = html_writer::link($url, $name, ['class' => 'fw-bold']);
        $output .= html_writer::tag(
            'div',
            html_writer::tag('code', $classname, ['class' => 'small text-muted']),
        );

        if (!empty($row->explanation)) {
            $output .= html_writer::tag(
                'div',
                html_writer::tag('small', s($row->explanation)),
            );
        }

        $badges = $this->get_type_badges($row);
        if ($badges) {
            $output .= html_writer::div($badges, 'mt-1');
        }

        return $output;
    }

    /**
     * Render the component column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_component(stdClass $row): string {
        $component = $row->info['component'];
        $manager = get_string_manager();

        if ($component === 'core') {
            $label = get_string('core', 'report_eventlist');
        } else if ($manager->string_exists('pluginname', $component)) {
            $label = get_string('pluginname', $component);
        } else {
            $label = $component;
        }

        return html_writer::div($label, 'fw-semibold') .
               html_writer::tag('small', $component, ['class' => 'text-muted d-block']);
    }

    /**
     * Render the crud column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_crud(stdClass $row): string {
        $crudmap = [
            'c' => ['create', 'success'],
            'u' => ['update', 'warning'],
            'd' => ['delete', 'danger'],
            'r' => ['read', 'info'],
        ];
        $crud = $row->info['crud'] ?? null;
        if (!$crud || !isset($crudmap[$crud])) {
            return '';
        }
        [$string, $type] = $crudmap[$crud];
        return $this->get_badge(get_string($string, 'report_eventlist'), $type);
    }

    /**
     * Render the edulevel column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_edulevel(stdClass $row): string {
        $edulevelmap = [
            \core\event\base::LEVEL_TEACHING      => ['teaching', 'info'],
            \core\event\base::LEVEL_PARTICIPATING => ['participating', 'primary'],
            \core\event\base::LEVEL_OTHER         => ['other', 'secondary'],
        ];
        $edulevel = $row->info['edulevel'] ?? null;
        if ($edulevel === null || !isset($edulevelmap[$edulevel])) {
            return '';
        }
        [$string, $type] = $edulevelmap[$edulevel];
        return $this->get_badge(get_string($string, 'report_eventlist'), $type);
    }

    /**
     * Render the since column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_since(stdClass $row): string {
        if ($row->since === null) {
            return '';
        }
        return $this->get_badge($row->since, 'secondary');
    }

    /**
     * Render the objecttable column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_objecttable(stdClass $row): string {
        return $row->info['objecttable'] ?? '';
    }

    /**
     * Render the observers column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_observers(stdClass $row): string {
        $eventkey  = '\\' . $row->classname;
        $basekey   = '\\core\\event\\base';
        $observers = [];

        // Include observers registered on the base event class (catch-all), flagged as such.
        // Don't show catch-all observers when filtering for directly observed events only.
        if ($this->observerfilter !== 'observed' && isset($this->allobservers[$basekey])) {
            foreach ($this->allobservers[$basekey] as $observer) {
                $observers[] = ['observer' => $observer, 'catchall' => true];
            }
        }
        if (isset($this->allobservers[$eventkey])) {
            foreach ($this->allobservers[$eventkey] as $observer) {
                $observers[] = ['observer' => $observer, 'catchall' => false];
            }
        }

        if (empty($observers)) {
            return html_writer::tag('span', get_string('none'), ['class' => 'text-muted']);
        }

        $items = [];
        foreach ($observers as ['observer' => $observer, 'catchall' => $catchall]) {
            is_callable($observer->callable, false, $callbackname);
            $label = ltrim($callbackname, '\\') . "&nbsp;({$observer->priority})";
            if ($catchall) {
                $label .= '&nbsp;' . $this->get_badge('*', 'secondary', get_string('observerwatchesallevents', 'report_eventlist'));
            }
            $items[] = html_writer::tag('li', $label);
        }

        return html_writer::tag('ol', implode('', $items), ['class' => 'mb-0']);
    }

    /**
     * Build type badges for the details column (CRUD and edulevel now have own columns).
     *
     * @param stdClass $row
     * @return string
     */
    protected function get_type_badges(stdClass $row): string {
        $badges = '';

        // Since badge — removed; shown in its own column.

        return $badges;
    }

    /**
     * Render a Bootstrap badge span with optional tooltip.
     *
     * @param string $label Badge text.
     * @param string|null $type Bootstrap contextual type (success, warning, etc.).
     * @param string|null $tooltip Tooltip text.
     * @return string
     */
    protected function get_badge(string $label, ?string $type = null, ?string $tooltip = null): string {
        $type ??= 'secondary';
        $attrs = ['class' => "badge badge-{$type} me-1"];
        if ($tooltip) {
            $attrs['data-bs-toggle'] = 'tooltip';
            $attrs['title']          = $tooltip;
        }
        return html_writer::tag('span', $label, $attrs);
    }

    /**
     * Return CSS class for a row.
     *
     * @param stdClass $row
     * @return string
     */
    protected function get_row_class(stdClass $row): string {
        return '';
    }
}

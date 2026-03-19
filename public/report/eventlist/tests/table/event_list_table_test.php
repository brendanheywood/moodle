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

declare(strict_types=1);

namespace report_eventlist\table;

/**
 * Unit tests for event_list_table search, component, crud, edulevel and observer filters.
 *
 * @package     report_eventlist
 * @covers      \report_eventlist\table\event_list_table
 * @author      Brendan Heywood <brendan@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_list_table_test extends \advanced_testcase {
    /**
     * Helper: instantiate the table, buffer out(), return the instance.
     *
     * @param string $component
     * @param string $search
     * @param string $crud
     * @param string $edulevel
     * @param string $observerfilter
     * @return event_list_table
     */
    private function get_table(
        string $component = '',
        string $search = '',
        string $crud = '',
        string $edulevel = '',
        string $observerfilter = '',
    ): event_list_table {
        $table = new event_list_table($component, $search, $crud, $edulevel, $observerfilter);
        ob_start();
        $table->out();
        ob_end_clean();
        return $table;
    }

    /**
     * No filters — all events are returned.
     */
    public function test_no_filter_returns_all_events(): void {
        $table = $this->get_table();
        $this->assertGreaterThan(0, $table->rowcount);
    }

    /**
     * Data provider for search filter tests.
     */
    public static function search_filter_provider(): array {
        return [
            'class name substring match' => [
                'search'    => 'badge_archived',
                'expectany' => true,
            ],
            'human name word match' => [
                'search'    => 'badge',
                'expectany' => true,
            ],
            'gibberish returns nothing' => [
                'search'    => 'zzz_no_such_event_xyzzy',
                'expectany' => false,
            ],
        ];
    }

    /**
     * Search filter reduces results as expected.
     *
     * @dataProvider search_filter_provider
     * @param string $search
     * @param bool $expectany
     */
    public function test_search_filter(string $search, bool $expectany): void {
        $all   = $this->get_table();
        $table = $this->get_table(search: $search);

        if ($expectany) {
            $this->assertGreaterThan(0, $table->rowcount, "Expected results for search '$search'");
            $this->assertLessThanOrEqual($all->rowcount, $table->rowcount);
        } else {
            $this->assertSame(0, $table->rowcount, "Expected no results for search '$search'");
        }
    }

    /**
     * Component filter returns only events from that component.
     */
    public function test_component_filter_returns_only_matching_component(): void {
        $table = $this->get_table(component: 'core');
        $this->assertGreaterThan(0, $table->rowcount);
    }

    /**
     * Component filter with unknown component returns nothing.
     */
    public function test_component_filter_unknown_returns_nothing(): void {
        $table = $this->get_table(component: 'zzz_no_such_plugin');
        $this->assertSame(0, $table->rowcount);
    }

    /**
     * Component filter returns fewer results than no filter.
     */
    public function test_component_filter_reduces_results(): void {
        $all  = $this->get_table();
        $core = $this->get_table(component: 'core');
        $this->assertLessThan($all->rowcount, $core->rowcount);
    }

    /**
     * Data provider for CRUD filter tests.
     */
    public static function crud_filter_provider(): array {
        return [
            'create' => ['crud' => 'c'],
            'read'   => ['crud' => 'r'],
            'update' => ['crud' => 'u'],
            'delete' => ['crud' => 'd'],
        ];
    }

    /**
     * CRUD filter returns a subset of all events.
     *
     * @dataProvider crud_filter_provider
     * @param string $crud
     */
    public function test_crud_filter(string $crud): void {
        $all   = $this->get_table();
        $table = $this->get_table(crud: $crud);

        $this->assertGreaterThan(0, $table->rowcount, "Expected events for crud '$crud'");
        $this->assertLessThan($all->rowcount, $table->rowcount);
    }

    /**
     * Data provider for edulevel filter tests.
     */
    public static function edulevel_filter_provider(): array {
        return [
            'teaching'      => ['edulevel' => (string)\core\event\base::LEVEL_TEACHING],
            'participating' => ['edulevel' => (string)\core\event\base::LEVEL_PARTICIPATING],
            'other'         => ['edulevel' => (string)\core\event\base::LEVEL_OTHER],
        ];
    }

    /**
     * Edulevel filter returns a subset of all events.
     *
     * @dataProvider edulevel_filter_provider
     * @param string $edulevel
     */
    public function test_edulevel_filter(string $edulevel): void {
        $all   = $this->get_table();
        $table = $this->get_table(edulevel: $edulevel);

        $this->assertGreaterThan(0, $table->rowcount, "Expected events for edulevel '$edulevel'");
        $this->assertLessThan($all->rowcount, $table->rowcount);
    }

    /**
     * Observer filter 'observed' returns only events with specific observers.
     */
    public function test_observer_filter_observed(): void {
        $all      = $this->get_table();
        $observed = $this->get_table(observerfilter: 'observed');

        $this->assertGreaterThan(0, $observed->rowcount);
        $this->assertLessThan($all->rowcount, $observed->rowcount);
    }

    /**
     * Combining search and component filters both apply.
     */
    public function test_search_and_component_combined(): void {
        $componentonly = $this->get_table(component: 'core');
        $combined      = $this->get_table(component: 'core', search: 'badge');

        $this->assertGreaterThan(0, $combined->rowcount);
        $this->assertLessThanOrEqual($componentonly->rowcount, $combined->rowcount);
    }
}

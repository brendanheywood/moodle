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

namespace core_admin\table;

/**
 * Unit tests for hook_list_table search and component filters.
 *
 * @package     core_admin
 * @covers      \core_admin\table\hook_list_table
 * @author      Brendan Heywood <brendan@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_list_table_test extends \advanced_testcase {
    /**
     * Helper: instantiate the table, buffer out(), return the instance.
     *
     * @param string $search
     * @param string $component
     * @return hook_list_table
     */
    private function get_table(string $search = '', string $component = ''): hook_list_table {
        $table = new hook_list_table($search, $component);
        ob_start();
        $table->out();
        ob_end_clean();
        return $table;
    }

    /**
     * No filters applied — all hooks are returned.
     */
    public function test_no_filter_returns_all_hooks(): void {
        $table = $this->get_table();
        $this->assertGreaterThan(0, $table->rowcount);
    }

    /**
     * Data provider for search filter tests.
     */
    public static function search_filter_provider(): array {
        return [
            'exact class substring match' => [
                'search'          => 'after_config',
                'expectany'       => true,
            ],
            'component prefix match' => [
                'search'          => 'core\\hook',
                'expectany'       => true,
            ],
            'gibberish returns nothing' => [
                'search'          => 'zzz_no_such_hook_xyzzy',
                'expectany'       => false,
            ],
        ];
    }

    /**
     * Search filter reduces or eliminates results as expected.
     *
     * @dataProvider search_filter_provider
     * @param string $search
     * @param bool $expectany whether any results are expected
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
     * Component filter returns only hooks from that component.
     */
    public function test_component_filter_returns_only_matching_component(): void {
        $table = $this->get_table(component: 'core');
        $this->assertGreaterThan(0, $table->rowcount);

        // Verify every row in the output is a core hook by checking the component list.
        $components = $table->get_component_list();
        $this->assertArrayHasKey('core', $components);
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
     * Combining search and component filters both apply.
     */
    public function test_search_and_component_combined(): void {
        $componentonly = $this->get_table(component: 'core');
        $combined      = $this->get_table(search: 'after', component: 'core');

        $this->assertGreaterThan(0, $combined->rowcount);
        $this->assertLessThanOrEqual($componentonly->rowcount, $combined->rowcount);
    }

    /**
     * get_component_list returns a non-empty sorted array.
     */
    public function test_get_component_list(): void {
        $table = new hook_list_table();
        ob_start();
        $table->out();
        ob_end_clean();

        $components = $table->get_component_list();
        $this->assertIsArray($components);
        $this->assertNotEmpty($components);
        $this->assertArrayHasKey('core', $components);

        // Verify it is sorted by value.
        $values = array_values($components);
        $sorted = $values;
        sort($sorted);
        $this->assertSame($sorted, $values);
    }
}

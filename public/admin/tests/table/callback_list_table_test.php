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

use core_admin\local\callback_scanner;

/**
 * Unit tests for callback_list_table search, component and status filters.
 *
 * @package     core_admin
 * @covers      \core_admin\table\callback_list_table
 * @author      Brendan Heywood <brendan@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class callback_list_table_test extends \advanced_testcase {
    /**
     * Helper: instantiate the table, buffer out(), return the instance.
     *
     * @param string $search
     * @param string $component
     * @param string $status
     * @return callback_list_table
     */
    private function get_table(
        string $search = '',
        string $component = '',
        string $status = ''
    ): callback_list_table {
        $table = new callback_list_table($search, $component, $status);
        ob_start();
        $table->out();
        ob_end_clean();
        return $table;
    }

    /**
     * No filters: all call-sites from the scanner should be visible.
     */
    public function test_no_filter_returns_all_callbacks(): void {
        $unfiltered = $this->get_table();
        $total      = count(callback_scanner::scan());

        $this->assertSame($total, $unfiltered->rowcount);
        $this->assertGreaterThan(0, $unfiltered->rowcount);
    }

    /**
     * Search filter matches on callback name substring.
     */
    public function test_search_filter_by_callback_name(): void {
        // The string 'extend_navigation' appears in several call-sites.
        $filtered   = $this->get_table('extend_navigation');
        $unfiltered = $this->get_table();

        $this->assertGreaterThan(0, $filtered->rowcount);
        $this->assertLessThan($unfiltered->rowcount, $filtered->rowcount);
    }

    /**
     * Search filter matches on component substring.
     */
    public function test_search_filter_by_component(): void {
        $filtered   = $this->get_table('core');
        $unfiltered = $this->get_table();

        $this->assertGreaterThan(0, $filtered->rowcount);
        $this->assertLessThanOrEqual($unfiltered->rowcount, $filtered->rowcount);
    }

    /**
     * Search filter matches on file path substring.
     */
    public function test_search_filter_by_file_path(): void {
        // The string 'lib.php' appears in virtually every call-site's file path.
        $filtered   = $this->get_table('lib.php');
        $unfiltered = $this->get_table();

        $this->assertGreaterThan(0, $filtered->rowcount);
        $this->assertLessThanOrEqual($unfiltered->rowcount, $filtered->rowcount);
    }

    /**
     * File path search is case-insensitive.
     */
    public function test_search_filter_by_file_path_case_insensitive(): void {
        $lower = $this->get_table('lib.php');
        $upper = $this->get_table('LIB.PHP');

        $this->assertSame($lower->rowcount, $upper->rowcount);
    }

    /**
     * File path search for a specific directory matches only relevant rows.
     */
    public function test_search_filter_by_file_path_directory(): void {
        // Anything under admin/ should return fewer rows than all callbacks.
        $filtered   = $this->get_table('public/admin/');
        $unfiltered = $this->get_table();

        $this->assertGreaterThan(0, $filtered->rowcount);
        $this->assertLessThan($unfiltered->rowcount, $filtered->rowcount);
    }

    /**
     * A search that matches nothing returns zero rows.
     */
    public function test_search_filter_no_match_returns_zero(): void {
        $table = $this->get_table('xyzzy_no_such_callback_ever');
        $this->assertSame(0, $table->rowcount);
    }

    /**
     * Status filter 'legacy' excludes all migrated callbacks.
     */
    public function test_status_filter_legacy(): void {
        $table   = $this->get_table('', '', 'legacy');
        $records = callback_scanner::scan();

        $legacycount = count(array_filter($records, fn($r) => !$r['migratedtohook']));
        $this->assertSame($legacycount, $table->rowcount);
    }

    /**
     * Status filter 'migrated' includes only migrated callbacks.
     */
    public function test_status_filter_migrated(): void {
        $table   = $this->get_table('', '', 'migrated');
        $records = callback_scanner::scan();

        $migratedcount = count(array_filter($records, fn($r) => $r['migratedtohook']));
        $this->assertSame($migratedcount, $table->rowcount);
    }

    /**
     * Legacy + migrated counts must sum to the unfiltered total.
     */
    public function test_status_filter_legacy_and_migrated_sum_to_total(): void {
        $total    = $this->get_table()->rowcount;
        $legacy   = $this->get_table('', '', 'legacy')->rowcount;
        $migrated = $this->get_table('', '', 'migrated')->rowcount;

        $this->assertSame($total, $legacy + $migrated);
    }

    /**
     * Component filter restricts rows to the specified component.
     */
    public function test_component_filter_restricts_to_component(): void {
        $table = $this->get_table('', 'core');

        $this->assertGreaterThan(0, $table->rowcount);

        $records = callback_scanner::scan();
        $corecount = count(array_filter($records, fn($r) => $r['component'] === 'core'));
        $this->assertSame($corecount, $table->rowcount);
    }

    /**
     * A non-existent component returns zero rows.
     */
    public function test_component_filter_unknown_returns_zero(): void {
        $table = $this->get_table('', 'nonexistent_xyzzy');
        $this->assertSame(0, $table->rowcount);
    }

    /**
     * Combining search and component filters restricts results further.
     */
    public function test_search_and_component_combined(): void {
        $searchonly    = $this->get_table('extend_navigation');
        $combined      = $this->get_table('extend_navigation', 'core');

        $this->assertGreaterThan(0, $combined->rowcount);
        $this->assertLessThanOrEqual($searchonly->rowcount, $combined->rowcount);
    }

    /**
     * get_component_list() returns an array with 'core' present.
     */
    public function test_get_component_list_contains_core(): void {
        $table = new callback_list_table();
        $list  = $table->get_component_list();

        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
        $this->assertArrayHasKey('core', $list);
    }
}

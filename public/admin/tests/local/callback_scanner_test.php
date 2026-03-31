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

namespace core_admin\local;

/**
 * Unit tests for callback_scanner static analysis.
 *
 * These tests exercise the regex parsing helpers using a temporary PHP file
 * written to the system temp directory, so they do not depend on specific
 * call-sites that may change across Moodle versions.
 *
 * @package     core_admin
 * @covers      \core_admin\local\callback_scanner
 * @author      Brendan Heywood <brendan@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class callback_scanner_test extends \advanced_testcase {
    /**
     * Create a temporary PHP file in a scannable directory and return its path.
     *
     * @param string $contents PHP source code to write.
     * @return string  Absolute path to the temp file.
     */
    private function write_temp_php(string $contents): string {
        $dir  = make_temp_directory('callback_scanner_test');
        $path = $dir . '/fixture_' . uniqid() . '.php';
        file_put_contents($path, $contents);
        return $path;
    }

    /**
     * Call the private do_scan()-equivalent by invoking scan() against a controlled
     * fixture file via the public interface, bypassing the cache.
     *
     * Since scan() walks predefined directories we instead test the individual
     * parse helpers via Reflection, which lets us pass arbitrary argument strings
     * without touching the filesystem scanner loop.
     *
     * @param string $method  Private method name.
     * @param array  $args    Method arguments.
     * @return mixed
     */
    private function call_parse(string $method, array $args): mixed {
        $ref = new \ReflectionClass(callback_scanner::class);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    // Tests for parse_get_plugins_with_function.

    /**
     * Positional string literal as first argument.
     */
    public function test_parse_positional_callback_name(): void {
        global $CFG;
        $result = $this->call_parse('parse_get_plugins_with_function', [
            "'before_footer', 'lib.php'",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('before_footer', $result['callbackname']);
        $this->assertFalse($result['migratedtohook']);
        $this->assertSame('get_plugins_with_function', $result['sourcefn']);
        $this->assertNull($result['plugintype']);
    }

    /**
     * Named-argument syntax: function: 'callbackname'.
     */
    public function test_parse_named_arg_callback_name(): void {
        global $CFG;
        $result = $this->call_parse('parse_get_plugins_with_function', [
            "function: 'standard_after_main_region_html', migratedtohook: true",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('standard_after_main_region_html', $result['callbackname']);
        $this->assertTrue($result['migratedtohook']);
    }

    /**
     * The migratedtohook flag is detected when passed as the 4th positional arg.
     */
    public function test_parse_migratedtohook_positional(): void {
        global $CFG;
        $result = $this->call_parse('parse_get_plugins_with_function', [
            "'bulk_user_actions', 'lib.php', true, true",
            '/admin/somefile.php',
            $CFG->dirroot . '/admin/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('bulk_user_actions', $result['callbackname']);
        $this->assertTrue($result['migratedtohook']);
    }

    /**
     * A variable as the first argument (dynamic callback name) returns null.
     */
    public function test_parse_dynamic_callback_name_returns_null(): void {
        global $CFG;
        $result = $this->call_parse('parse_get_plugins_with_function', [
            '$function, $file',
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNull($result);
    }

    // Tests for parse_get_plugin_list_with_function.

    /**
     * Standard positional two-string-literal arguments.
     */
    public function test_parse_plugin_list_standard(): void {
        global $CFG;
        $result = $this->call_parse('parse_get_plugin_list_with_function', [
            "'report', 'extend_navigation_course', 'lib.php'",
            '/lib/navigation.php',
            $CFG->dirroot . '/lib/navigation.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('extend_navigation_course', $result['callbackname']);
        $this->assertSame('report', $result['plugintype']);
        $this->assertFalse($result['migratedtohook']);
        $this->assertSame('get_plugin_list_with_function', $result['sourcefn']);
    }

    /**
     * Dynamic arguments return null.
     */
    public function test_parse_plugin_list_dynamic_returns_null(): void {
        global $CFG;
        $result = $this->call_parse('parse_get_plugin_list_with_function', [
            '$type, $function',
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNull($result);
    }

    // Tests for parse_component_callback.

    /**
     * Standard component_callback with string literal component and function.
     */
    public function test_parse_component_callback_standard(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_callback', [
            "'mod_forum', 'cron', [], null",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('cron', $result['callbackname']);
        $this->assertSame('mod_forum', $result['plugintype']);
        $this->assertFalse($result['migratedtohook']);
        $this->assertSame('component_callback', $result['sourcefn']);
    }

    /**
     * component_callback with migratedtohook: true named argument.
     */
    public function test_parse_component_callback_migrated(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_callback', [
            "'auth_saml2', 'before_logout', [], null, migratedtohook: true",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('before_logout', $result['callbackname']);
        $this->assertTrue($result['migratedtohook']);
    }

    /**
     * Dynamic component variable returns null.
     */
    public function test_parse_component_callback_dynamic_returns_null(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_callback', [
            '$component, $function',
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNull($result);
    }

    // Tests for parse_component_class_callback.

    /**
     * Standard component_class_callback with ::class syntax.
     */
    public function test_parse_component_class_callback_with_class_constant(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_class_callback', [
            '\mod_forum\output\email\renderer::class, \'render_digest\'',
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('render_digest', $result['callbackname']);
        $this->assertSame('component_class_callback', $result['sourcefn']);
        $this->assertFalse($result['migratedtohook']);
    }

    /**
     * component_class_callback with quoted class name string.
     */
    public function test_parse_component_class_callback_quoted_class(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_class_callback', [
            "'\mod_quiz\privacy\provider', 'get_metadata'",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('get_metadata', $result['callbackname']);
        $this->assertSame('component_class_callback', $result['sourcefn']);
    }

    /**
     * component_class_callback with migratedtohook flag.
     */
    public function test_parse_component_class_callback_migrated(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_class_callback', [
            "'\core\output\renderer', 'render_page', [], null, true",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertTrue($result['migratedtohook']);
    }

    /**
     * Dynamic variable as classname returns null.
     */
    public function test_parse_component_class_callback_dynamic_returns_null(): void {
        global $CFG;
        $result = $this->call_parse('parse_component_class_callback', [
            '$classname, $method',
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNull($result);
    }

    // Tests for parse_plugin_callback.

    /**
     * Standard plugin_callback with four string literal arguments.
     */
    public function test_parse_plugin_callback_standard(): void {
        global $CFG;
        $result = $this->call_parse('parse_plugin_callback', [
            "'mod', 'forum', 'cron', 'task'",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('cron_task', $result['callbackname']);
        $this->assertSame('mod', $result['plugintype']);
        $this->assertFalse($result['migratedtohook']);
        $this->assertSame('plugin_callback', $result['sourcefn']);
    }

    /**
     * plugin_callback with fewer than four string args returns null.
     */
    public function test_parse_plugin_callback_too_few_args_returns_null(): void {
        global $CFG;
        $result = $this->call_parse('parse_plugin_callback', [
            "'mod', 'forum', 'cron'",
            '/lib/somefile.php',
            $CFG->dirroot . '/lib/somefile.php',
            $CFG->dirroot,
        ]);

        $this->assertNull($result);
    }

    // Tests for component_from_path.

    /**
     * A path under lib/ resolves to 'core'.
     */
    public function test_component_from_path_core(): void {
        global $CFG;
        $result = $this->call_parse('component_from_path', [
            $CFG->dirroot . '/lib/classes/hook/manager.php',
            $CFG->dirroot,
        ]);
        $this->assertSame('core', $result);
    }

    /**
     * A path under mod/forum/ resolves to 'mod_forum'.
     */
    public function test_component_from_path_mod(): void {
        global $CFG;
        $result = $this->call_parse('component_from_path', [
            $CFG->dirroot . '/mod/forum/lib.php',
            $CFG->dirroot,
        ]);
        $this->assertSame('mod_forum', $result);
    }

    /**
     * A path under admin/tool/curlmanager/ resolves to 'tool_curlmanager'.
     */
    public function test_component_from_path_tool(): void {
        global $CFG;
        $result = $this->call_parse('component_from_path', [
            $CFG->dirroot . '/admin/tool/curlmanager/lib.php',
            $CFG->dirroot,
        ]);
        $this->assertSame('tool_curlmanager', $result);
    }

    // Integration smoke test for scan().

    /**
     * scan() returns an array and every record has the expected keys.
     */
    public function test_scan_returns_valid_records(): void {
        $records = callback_scanner::scan();

        $this->assertIsArray($records);
        $this->assertNotEmpty($records, 'Expected at least one callback call-site in the codebase');

        $requiredkeys = ['callbackname', 'file', 'component', 'migratedtohook', 'sourcefn', 'plugintype'];
        foreach ($records as $record) {
            foreach ($requiredkeys as $key) {
                $this->assertArrayHasKey($key, $record, "Record missing key: $key");
            }
            $this->assertIsString($record['callbackname']);
            $this->assertNotEmpty($record['callbackname']);
            $this->assertIsBool($record['migratedtohook']);
        }
    }

    /**
     * scan() results are sorted by callback name.
     */
    public function test_scan_results_are_sorted(): void {
        $records = callback_scanner::scan();
        $names   = array_column($records, 'callbackname');
        $sorted  = $names;
        sort($sorted);
        $this->assertSame($sorted, $names, 'scan() results should be sorted by callbackname');
    }

    /**
     * get_component_list() returns a non-empty associative array containing 'core'.
     */
    public function test_get_component_list_contains_core(): void {
        $list = callback_scanner::get_component_list();

        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
        $this->assertArrayHasKey('core', $list);
    }

    /**
     * scan(refresh: true) returns the same structure as a normal scan.
     */
    public function test_scan_refresh_returns_valid_records(): void {
        $records = callback_scanner::scan(true);

        $this->assertIsArray($records);
        $this->assertNotEmpty($records);
        foreach ($records as $record) {
            $this->assertArrayHasKey('callbackname', $record);
            $this->assertIsBool($record['migratedtohook']);
        }
    }
}

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

namespace core_admin\local;

/**
 * Static-analysis scanner for legacy plugin callbacks.
 *
 * Finds all call-sites that use get_plugins_with_function() or
 * get_plugin_list_with_function() across the Moodle codebase, and records
 * whether each call has been marked as migrated to a hook.
 *
 * Results are cached in the MUC application cache 'core/callback_scan'
 * (TTL 1 hour) so the filesystem scan only runs once per cache lifetime.
 *
 * @package   core_admin
 * @copyright 2025 onwards
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callback_scanner {
    /** @var string MUC cache component */
    private const CACHE_COMPONENT = 'core';

    /** @var string MUC cache area */
    private const CACHE_AREA = 'callback_scan';

    /** @var string MUC cache key */
    private const CACHE_KEY = 'callbacks';

    /**
     * Top-level directories (relative to dirroot) to scan.
     * Excludes generated content (vendor, node_modules) and test code.
     */
    private const SCAN_DIRS = [
        'lib',
        'admin',
        'mod',
        'blocks',
        'course',
        'enrol',
        'auth',
        'theme',
        'local',
        'report',
        'files',
        'group',
        'message',
        'grade',
        'calendar',
        'notes',
        'user',
        'badges',
        'analytics',
        'competency',
        'portfolio',
        'repository',
        'webservice',
    ];

    /**
     * Return all discovered callback call-sites, using the MUC cache.
     *
     * Each returned record is an array with keys:
     *   - callbackname   string  Short callback name, e.g. 'before_footer'
     *   - file           string  Path relative to dirroot
     *   - component      string  Moodle component, e.g. 'core', 'mod_forum'
     *   - migratedtohook bool    Whether the call passes migratedtohook=true
     *   - sourcefn       string  'get_plugins_with_function' or 'get_plugin_list_with_function'
     *   - plugintype     ?string For get_plugin_list_with_function, the first arg (plugin type)
     *
     * @param bool $refresh  Force a fresh scan, ignoring the cache.
     * @return array[]
     */
    public static function scan(bool $refresh = false): array {
        $cache = \cache::make(self::CACHE_COMPONENT, self::CACHE_AREA);
        if (!$refresh) {
            $cached = $cache->get(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        $results = static::do_scan();
        $cache->set(self::CACHE_KEY, $results);
        return $results;
    }

    /**
     * Perform the filesystem scan and return results.
     *
     * @return array[]
     */
    private static function do_scan(): array {
        global $CFG;

        $dirroot = $CFG->dirroot;
        $results = [];
        $seen = [];

        foreach (self::SCAN_DIRS as $subdir) {
            $scanpath = $dirroot . '/' . $subdir;
            if (!is_dir($scanpath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($scanpath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileinfo) {
                if ($fileinfo->getExtension() !== 'php') {
                    continue;
                }

                $abspath = $fileinfo->getRealPath();

                // Skip vendor dirs, node_modules, test files and behat fixtures.
                if (
                        str_contains($abspath, '/vendor/')
                        || str_contains($abspath, '/node_modules/')
                        || str_contains($abspath, '/tests/')
                        || str_contains($abspath, '/behat/')
                ) {
                    continue;
                }

                $contents = file_get_contents($abspath);
                if ($contents === false) {
                    continue;
                }

                // Quick check before running regexes.
                if (
                        strpos($contents, 'get_plugins_with_function') === false
                        && strpos($contents, 'get_plugin_list_with_function') === false
                        && strpos($contents, 'component_callback') === false
                        && strpos($contents, 'plugin_callback') === false
                ) {
                    continue;
                }

                $relpath = str_replace(dirname($dirroot) . '/', '', $abspath);

                if (
                    preg_match_all(
                        '/\bget_plugins_with_function\s*\(\s*([^)]+?)\s*\)/s',
                        $contents,
                        $matches,
                        PREG_OFFSET_CAPTURE
                    )
                ) {
                    foreach ($matches[1] as [$args, $offset]) {
                        $record = static::parse_get_plugins_with_function($args, $relpath, $abspath, $dirroot);
                        if ($record !== null) {
                            $record['line'] = substr_count(substr($contents, 0, $offset), "\n") + 1;
                            $key = $record['file'] . '|' . $record['callbackname'];
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $results[] = $record;
                            }
                        }
                    }
                }

                if (
                    preg_match_all(
                        '/\bget_plugin_list_with_function\s*\(\s*([^)]+?)\s*\)/s',
                        $contents,
                        $matches,
                        PREG_OFFSET_CAPTURE
                    )
                ) {
                    foreach ($matches[1] as [$args, $offset]) {
                        $record = static::parse_get_plugin_list_with_function($args, $relpath, $abspath, $dirroot);
                        if ($record !== null) {
                            $record['line'] = substr_count(substr($contents, 0, $offset), "\n") + 1;
                            $key = $record['file'] . '|' . $record['callbackname'];
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $results[] = $record;
                            }
                        }
                    }
                }
                if (
                    preg_match_all(
                        '/\bcomponent_callback\s*\(\s*([^)]+?)\s*\)/s',
                        $contents,
                        $matches,
                        PREG_OFFSET_CAPTURE
                    )
                ) {
                    foreach ($matches[1] as [$args, $offset]) {
                        $record = static::parse_component_callback($args, $relpath, $abspath, $dirroot);
                        if ($record !== null) {
                            $record['line'] = substr_count(substr($contents, 0, $offset), "\n") + 1;
                            $key = $record['file'] . '|' . $record['callbackname'] . '|' . $record['sourcefn'];
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $results[] = $record;
                            }
                        }
                    }
                }

                if (
                    preg_match_all(
                        '/\bcomponent_class_callback\s*\(\s*([^)]+?)\s*\)/s',
                        $contents,
                        $matches,
                        PREG_OFFSET_CAPTURE
                    )
                ) {
                    foreach ($matches[1] as [$args, $offset]) {
                        $record = static::parse_component_class_callback($args, $relpath, $abspath, $dirroot);
                        if ($record !== null) {
                            $record['line'] = substr_count(substr($contents, 0, $offset), "\n") + 1;
                            $key = $record['file'] . '|' . $record['callbackname'] . '|' . $record['sourcefn'];
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $results[] = $record;
                            }
                        }
                    }
                }

                if (
                    preg_match_all(
                        '/\bplugin_callback\s*\(\s*([^)]+?)\s*\)/s',
                        $contents,
                        $matches,
                        PREG_OFFSET_CAPTURE
                    )
                ) {
                    foreach ($matches[1] as [$args, $offset]) {
                        $record = static::parse_plugin_callback($args, $relpath, $abspath, $dirroot);
                        if ($record !== null) {
                            $record['line'] = substr_count(substr($contents, 0, $offset), "\n") + 1;
                            $key = $record['file'] . '|' . $record['callbackname'] . '|' . $record['sourcefn'];
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $results[] = $record;
                            }
                        }
                    }
                }
            }
        }

        usort($results, fn($a, $b) => strcmp($a['callbackname'], $b['callbackname']));

        // Resolve which plugins implement each callback at runtime.
        foreach ($results as &$record) {
            $record['implementors'] = static::resolve_implementors($record);
        }
        unset($record);

        return $results;
    }

    /**
     * Parse the argument string from a get_plugins_with_function() call.
     *
     * @param string $args     Raw argument string between the parentheses.
     * @param string $relpath  Path relative to dirroot.
     * @param string $abspath  Absolute path to the file.
     * @param string $dirroot  Moodle dirroot.
     * @return ?array          Record array, or null if the callback name is dynamic.
     */
    private static function parse_get_plugins_with_function(
        string $args,
        string $relpath,
        string $abspath,
        string $dirroot
    ): ?array {
        // Named-argument syntax: function: 'callbackname'.
        if (preg_match('/\bfunction\s*:\s*[\'"]([a-z_]+)[\'"]/', $args, $m)) {
            $callbackname = $m[1];
        } else if (preg_match('/^\s*[\'"]([a-z_]+)[\'"]/', $args, $m)) {
            // Positional first argument as a string literal.
            $callbackname = $m[1];
        } else {
            // Dynamic variable — cannot statically determine the name.
            return null;
        }

        // Detect migratedtohook flag (named arg or 4th positional true).
        $migratedtohook = (bool) preg_match('/\bmigratedtohook\s*:\s*true\b/', $args);
        if (!$migratedtohook) {
            // Check if migratedtohook is passed as the fourth positional argument.
            $migratedtohook = (bool) preg_match(
                '/[\'"][a-z._]*[\'"]\s*,\s*(?:true|false)\s*,\s*true\s*$/',
                trim($args)
            );
        }

        return [
            'callbackname'   => $callbackname,
            'file'           => $relpath,
            'component'      => static::component_from_path($abspath, $dirroot),
            'migratedtohook' => $migratedtohook,
            'sourcefn'       => 'get_plugins_with_function',
            'plugintype'     => null,
            'callbackfile'   => static::parse_second_string_arg($args) ?? 'lib.php',
        ];
    }

    /**
     * Parse the argument string from a get_plugin_list_with_function() call.
     *
     * @param string $args     Raw argument string between the parentheses.
     * @param string $relpath  Path relative to dirroot.
     * @param string $abspath  Absolute path to the file.
     * @param string $dirroot  Moodle dirroot.
     * @return ?array          Record array, or null if arguments are dynamic.
     */
    private static function parse_get_plugin_list_with_function(
        string $args,
        string $relpath,
        string $abspath,
        string $dirroot
    ): ?array {
        // Parse plugin type and function name from positional string arguments.
        if (preg_match('/^\s*[\'"]([a-z_]+)[\'"]\s*,\s*[\'"]([a-z_]+)[\'"]/', $args, $m)) {
            $plugintype   = $m[1];
            $callbackname = $m[2];
        } else {
            return null;
        }

        return [
            'callbackname'   => $callbackname,
            'file'           => $relpath,
            'component'      => static::component_from_path($abspath, $dirroot),
            'migratedtohook' => false,
            'sourcefn'       => 'get_plugin_list_with_function',
            'plugintype'     => $plugintype,
            'callbackfile'   => static::parse_third_string_arg($args) ?? 'lib.php',
        ];
    }

    /**
     * Parse the argument string from a component_callback() call.
     * component_callback($component, $function, $params, $default, $migratedtohook)
     *
     * @param string $args     Raw argument string between the parentheses.
     * @param string $relpath  Path relative to dirroot parent.
     * @param string $abspath  Absolute path to the file.
     * @param string $dirroot  Moodle dirroot.
     * @return ?array          Record array, or null if arguments are dynamic.
     */
    private static function parse_component_callback(
        string $args,
        string $relpath,
        string $abspath,
        string $dirroot
    ): ?array {
        // First arg: component string literal. Second arg: function name string literal.
        if (!preg_match('/^\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*[\'"]([a-z_]+)[\'"]/', $args, $m)) {
            return null;
        }
        $migratedtohook = (bool) preg_match('/\bmigratedtohook\s*:\s*true\b|,\s*true\s*$/', trim($args));

        return [
            'callbackname'   => $m[2],
            'file'           => $relpath,
            'component'      => static::component_from_path($abspath, $dirroot),
            'migratedtohook' => $migratedtohook,
            'sourcefn'       => 'component_callback',
            'plugintype'     => $m[1],
            'callbackfile'   => null,
        ];
    }

    /**
     * Parse the argument string from a component_class_callback() call.
     * component_class_callback($classname, $methodname, $params, $default, $migratedtohook)
     *
     * @param string $args     Raw argument string between the parentheses.
     * @param string $relpath  Path relative to dirroot parent.
     * @param string $abspath  Absolute path to the file.
     * @param string $dirroot  Moodle dirroot.
     * @return ?array          Record array, or null if arguments are dynamic.
     */
    private static function parse_component_class_callback(
        string $args,
        string $relpath,
        string $abspath,
        string $dirroot
    ): ?array {
        // First arg: class name (string literal or ::class). Second arg: method name string literal.
        if (!preg_match('/^\s*[\'"]?([\\\\a-zA-Z0-9_]+)(?:::class)?[\'"]?\s*,\s*[\'"]([a-z_]+)[\'"]/', $args, $m)) {
            return null;
        }
        $migratedtohook = (bool) preg_match('/\bmigratedtohook\s*:\s*true\b|,\s*true\s*$/', trim($args));

        return [
            'callbackname'   => $m[2],
            'file'           => $relpath,
            'component'      => static::component_from_path($abspath, $dirroot),
            'migratedtohook' => $migratedtohook,
            'sourcefn'       => 'component_class_callback',
            'plugintype'     => trim($m[1], '\\'),
            'callbackfile'   => null,
        ];
    }

    /**
     * Parse the argument string from a plugin_callback() call.
     * plugin_callback($type, $name, $feature, $action, ...)
     *
     * @param string $args     Raw argument string between the parentheses.
     * @param string $relpath  Path relative to dirroot parent.
     * @param string $abspath  Absolute path to the file.
     * @param string $dirroot  Moodle dirroot.
     * @return ?array          Record array, or null if arguments are dynamic.
     */
    private static function parse_plugin_callback(
        string $args,
        string $relpath,
        string $abspath,
        string $dirroot
    ): ?array {
        // Parse type, name, feature, and action from positional string arguments.
        if (
            !preg_match(
                '/^\s*[\'"]([a-z_]+)[\'"]\s*,\s*[\'"]([a-z_]+)[\'"]\s*,\s*[\'"]([a-z_]+)[\'"]\s*,\s*[\'"]([a-z_]+)[\'"]/',
                $args,
                $m
            )
        ) {
            return null;
        }
        $migratedtohook = (bool) preg_match('/\bmigratedtohook\s*:\s*true\b|,\s*true\s*$/', trim($args));

        return [
            'callbackname'   => $m[3] . '_' . $m[4],
            'file'           => $relpath,
            'component'      => static::component_from_path($abspath, $dirroot),
            'migratedtohook' => $migratedtohook,
            'sourcefn'       => 'plugin_callback',
            'plugintype'     => $m[1],
            'callbackfile'   => null,
        ];
    }

    /**
     * Resolve which plugins implement the callback for a given record.
     *
     * Returns an array of records, each with keys:
     *   - component   string   e.g. 'mod_forum'
     *   - function    string   The actual PHP function or method name
     *   - file        string   Path relative to dirroot parent
     *   - hookstatus  ?string  null (not migrated), 'both' (hook adopted), 'legacy_only'
     *
     * @param array $record  A scanner record.
     * @return array[]
     */
    private static function resolve_implementors(array $record): array {
        global $CFG;

        $fn         = $record['sourcefn'];
        $name       = $record['callbackname'];
        $file       = $record['callbackfile'] ?? 'lib.php';
        $dirrootlen = strlen(dirname($CFG->dirroot) . '/');

        // Pre-compute set of components that have adopted the replacing hook (if any).
        $hookadopters = $record['migratedtohook'] ? static::get_hook_adopters($name) : [];

        $addstatus = function (array $impl) use ($record, $hookadopters): array {
            if (!$record['migratedtohook']) {
                $impl['hookstatus'] = null;
            } else {
                $impl['hookstatus'] = isset($hookadopters[$impl['component']]) ? 'both' : 'legacy_only';
            }
            return $impl;
        };

        if ($fn === 'get_plugins_with_function' || $fn === 'get_plugin_list_with_function') {
            $found  = get_plugins_with_function($name, $file, false);
            $result = [];
            if ($fn === 'get_plugin_list_with_function' && $record['plugintype'] !== null) {
                $found = isset($found[$record['plugintype']]) ? [$record['plugintype'] => $found[$record['plugintype']]] : [];
            }
            foreach ($found as $type => $plugins) {
                $plugindirs = \core_component::get_plugin_list($type);
                foreach ($plugins as $plugin => $functionname) {
                    $plugindir = $plugindirs[$plugin] ?? null;
                    $filepath  = $plugindir ? substr($plugindir . '/' . $file, $dirrootlen) : '';
                    $result[]  = $addstatus([
                        'component' => $type . '_' . $plugin,
                        'function'  => $functionname,
                        'file'      => $filepath,
                    ]);
                }
            }
            return $result;
        }

        if ($fn === 'component_callback') {
            $component = $record['plugintype'];
            if ($component === null) {
                return [];
            }
            $dir = \core_component::get_component_directory($component);
            return [$addstatus([
                'component' => $component,
                'function'  => $component . '_' . $name,
                'file'      => $dir ? substr($dir . '/lib.php', $dirrootlen) : '',
            ])];
        }

        if ($fn === 'component_class_callback') {
            $classname = $record['plugintype'];
            if ($classname === null) {
                return [];
            }
            $filepath = '';
            if (class_exists($classname)) {
                $ref      = new \ReflectionClass($classname);
                $filepath = substr($ref->getFileName(), $dirrootlen);
            }
            return [$addstatus([
                'component' => $classname,
                'function'  => $classname . '::' . $name . '()',
                'file'      => $filepath,
            ])];
        }

        if ($fn === 'plugin_callback') {
            $type = $record['plugintype'];
            if ($type === null) {
                return [];
            }
            $found      = get_plugins_with_function($name, 'lib.php', false);
            $plugindirs = \core_component::get_plugin_list($type);
            $result     = [];
            if (isset($found[$type])) {
                foreach ($found[$type] as $plugin => $functionname) {
                    $plugindir = $plugindirs[$plugin] ?? null;
                    $filepath  = $plugindir ? substr($plugindir . '/lib.php', $dirrootlen) : '';
                    $result[]  = $addstatus([
                        'component' => $type . '_' . $plugin,
                        'function'  => $functionname,
                        'file'      => $filepath,
                    ]);
                }
            }
            return $result;
        }

        return [];
    }

    /**
     * Return a set (keyed by component) of components that have registered a listener
     * for any hook that replaces the given legacy callback name.
     *
     * @param string $callbackname  Short legacy callback name.
     * @return array  component => true
     */
    private static function get_hook_adopters(string $callbackname): array {
        $hookmanager  = \core\di::get(\core\hook\manager::class);
        $hookreplaces = $hookmanager->get_hooks_deprecating_plugin_callback($callbackname);
        if (empty($hookreplaces)) {
            return [];
        }
        $allcallbacks = (array) $hookmanager->get_all_callbacks();
        $adopters = [];
        foreach ($hookreplaces as $hookclass) {
            foreach ($allcallbacks[$hookclass] ?? [] as $definition) {
                // Callback is e.g. 'mod_forum\hook\callbacks::handle' — extract the component.
                $cb        = $definition['callback'] ?? '';
                $component = explode('\\', ltrim($cb, '\\'))[0];
                if ($component) {
                    $adopters[$component] = true;
                }
            }
        }
        return $adopters;
    }

    /**
     * Extract the second string-literal argument from a comma-separated arg string.
     * Used to capture the $file parameter from get_plugins_with_function calls.
     *
     * @param string $args  Raw argument string.
     * @return ?string
     */
    private static function parse_second_string_arg(string $args): ?string {
        if (preg_match('/^\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $args, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract the third string-literal argument from a comma-separated arg string.
     * Used to capture the $file parameter from get_plugin_list_with_function calls.
     *
     * @param string $args  Raw argument string.
     * @return ?string
     */
    private static function parse_third_string_arg(string $args): ?string {
        if (preg_match('/^\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $args, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Determine the Moodle component from an absolute file path.
     *
     * Iterates all known plugin type directories from core_component and matches
     * by path prefix. Falls back to 'core' for anything under lib/.
     *
     * @param string $abspath  Absolute file path.
     * @param string $dirroot  Moodle dirroot.
     * @return string  Component string, e.g. 'core', 'mod_forum', 'tool_curlmanager'.
     */
    private static function component_from_path(string $abspath, string $dirroot): string {
        $plugintypes = \core_component::get_plugin_types();
        foreach ($plugintypes as $type => $typedir) {
            // Normalise typedir (it may be absolute already).
            if (!str_starts_with($typedir, '/')) {
                $typedir = $dirroot . '/' . $typedir;
            }
            if (str_starts_with($abspath, $typedir . '/')) {
                $rest = substr($abspath, strlen($typedir) + 1);
                $pluginname = explode('/', $rest)[0];
                // Skip hidden dirs and any name that would produce an invalid component string.
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $pluginname)) {
                    return 'core';
                }
                return $type . '_' . $pluginname;
            }
        }
        return 'core';
    }

    /**
     * Return a sorted list of unique components found in the scan, for use in a filter select.
     *
     * @return array  component => display label
     */
    public static function get_component_list(): array {
        $callbacks  = static::scan();
        $components = [];
        $manager    = get_string_manager();

        foreach ($callbacks as $record) {
            $component = $record['component'];
            if (isset($components[$component])) {
                continue;
            }
            if ($component === 'core') {
                $label = get_string('core', 'core_admin');
            } else if (preg_match('/^[a-z][a-z0-9_]*$/', $component) && $manager->string_exists('pluginname', $component)) {
                $label = get_string('pluginname', $component) . ' (' . $component . ')';
            } else {
                $label = $component;
            }
            $components[$component] = $label;
        }

        asort($components);
        return $components;
    }
}

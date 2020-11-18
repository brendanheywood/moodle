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
 * A file system instrumentation stream wrapper
 *
 * @package    core
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\local\stream;

// @codingStandardsIgnoreStart
// Under normal conditions this would autoload but early in the bootstrap we get
// a chicken and egg situation when it tries to instrument the loading of this file.
// For the similar reasons we don't check MOODLE_INTERNAL as it would die.
require_once($CFG->libdir . '/classes/files/path_utils.php');
// @codingStandardsIgnoreEnd

use \core\files\path_utils;

/**
 * This file stream wrapper instruments the Moodle file system.
 *
 * Because this class is potentially used so early in the Moodle bootstrap it
 * MUST not have any dependancies on any Moodle libraries except setuplib.php.
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fileio_wrapper extends stream_wrapper_base {

    /** @var array to store performance stats */
    static private $perf = [];

    /**
     * Return collected stats around file path usage
     *
     * @return array of stats for each path
     */
    public static function get_perf_stats(): array {
        return self::$perf;
    }

    /**
     * Collect stats for a single file operation
     *
     * @param string $operation the type of file operation
     * @param string $path the absolute file path operated on
     * @param int $size how much to increment
     */
    public static function record_operation(string $operation, string $path, int $size = 1) {

        global $CFG, $ME;

        // We can be called so early that $ME is not setup.
        $me = isset($ME) ? $ME : 'bootstrap';

        $cfgname = path_utils::get_config_from_path($path, false, true);

        if (!isset(self::$perf[$cfgname])) {
            self::$perf[$cfgname] = [
                'miss' => 0,
                'stat' => 0,
                'read' => 0,
                'write' => 0,
                'bytes' => 0,
            ];
        }
        self::$perf[$cfgname][$operation] += $size;

        if (!isset($CFG->debugfileio)) {
            return;
        }
        $loglevel = (int)$CFG->debugfileio;

        // Operating on certain paths is worse in real life, eg datadir which
        // must be shared. Remote filesytems NFS / Gluster and have much higher
        // latency. Also certain operations such as writes and file_exists. So
        // allow a fine grained variable level of file IO logging.
        $level = 0;
        switch ($operation) {
            case 'write':
                $level += 1;
                break;
            case 'miss':
                $level += 2;
                break;
            case 'read':
                $level += 3;
                break;
            case 'stat':
                $level += 4;
                break;
            case 'bytes':
                $level += 5;
                break;
        }
        // Most of the time we are only interested in shared file IO.
        if ($cfgname !== 'dataroot') {
            $level += 10;
        }

        if ($level <= $loglevel) {
            if (isset($CFG->debugfileiostacksize)) {
                $stacksize = max(1, (int)$CFG->debugfileiostacksize);
            } else {
                $stacksize = 1;
            }

            $callers = debug_backtrace(true, $stacksize + 2);
            $caller = format_backtrace(array_slice($callers, 2, $stacksize), 1);
            $caller = trim($caller);

            // @codingStandardsIgnoreStart
            error_log("FILEIO [$level] $operation $path $me $caller");
            // @codingStandardsIgnoreEnd
        }
    }

    /**
     * Collect data on opened files
     *
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string $openedpath
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedpath): bool {
        if ($mode === 'r' || $mode === 'rb') {
            self::record_operation('read', $path);
        } else {
            self::record_operation('write', $path);
        }
        return parent::stream_open($path, $mode, $options, $openedpath);
    }

    /**
     * Collect data on file_exists
     *
     * @param string $path
     * @param int $flags
     */
    public function url_stat(string $path, int $flags) {

        $stat = parent::url_stat($path, $flags);
        if (empty($stat)) {
            self::record_operation('miss', $path);
        } else {
            self::record_operation('stat', $path);
        }
        return $stat;
    }

    /**
     * stream_read
     * @param int $count
     */
    public function stream_read(int $count) {
        $chunk = parent::stream_read($count);
        self::record_operation('bytes', $this->path, strlen($chunk));
        return $chunk;
    }

    /**
     * stream_write
     * @param string $data
     */
    public function stream_write(string $data) {
        self::record_operation('bytes', $this->path, strlen($data));
        return parent::stream_write($data);
    }

}

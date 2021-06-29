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

        // We can be called so early in the bootstrap that $ME is not setup.
        $me = isset($ME) ? $ME : 'bootstrap';

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            if (!empty($_SERVER['argv'])) {
                $args = $_SERVER['argv'];
                $me = $args[0];
            }
        }

        $cfgname = path_utils::get_config_from_path($path, false, true);

        if (!isset(self::$perf[$cfgname])) {
            self::$perf[$cfgname] = [
                'miss'  => 0,
                'stat'  => 0,
                'read'  => 0,
                'write' => 0,
                'bytesread'  => 0,
                'byteswrite' => 0,
            ];
        }
        self::$perf[$cfgname][$operation] += $size;

        // Operating on certain paths is worse in real life, eg datadir which
        // must be shared. Remote filesytems like NFS and Gluster will have higher
        // latency. Also certain operations such as writes and file_exists. So
        // allow a fine grained variable level of file IO logging. See config-dist.php
        // for details on how to configure this.
        $level = 0;
        switch ($operation) {
            case 'write':
                $level = 1;
                break;
            case 'miss':
                $level = 2;
                break;
            case 'read':
                $level = 4;
                break;
            case 'stat':
                $level = 8;
                break;
            case 'bytesread':
                $level = 16;
                break;
            case 'byteswrite':
                $level = 32;
                break;
        }
        // Most of the time we are more interested in shared file IO. So we assume
        // that anything not in dataroot can be local so that we can either selectively
        // watch just for shared disk IO, or add fake latency to just the shared paths.
        if ($cfgname !== 'dataroot') {
            // There are 6 different operations above, so by shifting the local paths
            // another 6 bits we end up with 12 types of operations we can filter on.
            $level = $level << 6;
        }

        // Optionally we can also just slow things down artificially to enable some
        // classes of race conditions to be visible in human time.
        if (isset($CFG->debugfileiodelay) && is_array($CFG->debugfileiodelay)) {
            $delays = $CFG->debugfileiodelay;
            if (isset($delays[$level])) {
                usleep((float)$delays[$level] * 1000);
            }
        }

        // We do a bitwise comparison so we can selectively turn on and off logging
        // for each type of operation.
        if (isset($CFG->debugfileio) && ($level & (int)$CFG->debugfileio)) {

            // Format the time manually as we want microsecond time accuracy.
            $time = microtime(true);
            $micro = sprintf("%06d", ($time - floor($time)) * 1000000);
            $d = new \DateTime( date('Y-m-d H:i:s.' . $micro, $time));
            $time = $d->format("H:i:s.u");

            // This prefix is quite verbose because we want enough information here
            // to make it easy to grep for the parts we want to help see exactly
            // how multiple processes are interacting or clashing. Effort has also
            // been made to keep whitespace consistent between all rows to make it
            // easy to to analyse afterwards with standard log tools.
            if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
                $prefix = sprintf("\nFILEIO %s %s %s %s ", $time, "[$level:$operation]", $me, $path);
            } else {
                $prefix = sprintf("\nFILEIO %s %s %s ", "[$level:$operation]", $me, $path);
            }

            $stacksize = 1;
            if (isset($CFG->debugfileiostacksize)) {
                $stacksize = max(1, (int)$CFG->debugfileiostacksize);
            }
            $callers = debug_backtrace(true, $stacksize + 2);
            $caller = format_backtrace(array_slice($callers, 2, $stacksize), 1);
            $caller = trim($caller);
            $log = str_replace("\n", $prefix, "\n" . $caller);

            // @codingStandardsIgnoreStart
            error_log(trim($log));
            // @codingStandardsIgnoreEnd
        }

    }

    /**
     * Instrument file opens
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
     * Instrument file stat calls
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
     * Instrument file reads
     * @param int $count
     */
    public function stream_read(int $count) {
        $chunk = parent::stream_read($count);
        self::record_operation('bytesread', $this->path, strlen($chunk));
        return $chunk;
    }

    /**
     * Instrument file writes
     * @param string $data
     */
    public function stream_write(string $data) {
        self::record_operation('byteswrite', $this->path, strlen($data));
        $bytes = parent::stream_write($data);
        return $bytes;
    }

    /**
     * Instrument file renames
     * @param string $pathfrom
     * @param string $pathto
     */
    public function rename(string $pathfrom, string $pathto): bool {
        // Be careful to not introduce extra whitespace in this log entry.
        self::record_operation('write', "$pathfrom->$pathto");
        return parent::rename($pathfrom, $pathto);
    }

    /**
     * Instrument file deletes
     * @param string $path
     */
    public function unlink(string $path): bool {
        self::record_operation('write', $path);
        return parent::unlink($path);
    }

}

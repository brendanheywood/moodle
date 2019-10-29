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
 * A Redis locking factory.
 *
 * @package    core
 * @category   lock
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\lock;

use RedisException;

defined('MOODLE_INTERNAL') || die();

/**
 * A redis locking factory.
 *
 * @package   core
 * @category  lock
 * @copyright Brendan Heywood <brendan@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class redis_lock_factory implements lock_factory {

    /** @var string $type Used to prefix lock keys */
    protected $type;

    /** @var array $openlocks - List of held locks - used by auto-release */
    protected $openlocks = array();

    /**
     * Almighty constructor.
     * @param string $type - Used to prefix lock keys.
     */
    public function __construct($type) {
        global $CFG;

        $this->type = $type;

        // $options = $CFG->lock_factory_redis_options;

        $defaults = (object)[
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'prefix' => '',
            'auth' => ''
        ];

$options = $defaults;

        if (empty($options->host)) {
            throw new exception('sessionhandlerproblem', 'error', '', null,
                    '$CFG->session_redis_host must be specified in config.php');
        }

        $this->connection = new \Redis();

        if (!$this->connection->connect($options->host, $options->port)) {
            throw new RedisException('sessionhandlerproblem', 'error', '', null,
                    '$CFG->session_redis_host must be specified in config.php');

        }

        if ($options->auth !== '') {
            $this->connection->auth($options->auth);
        }

        if ($options->prefix !== '') {
            // Use custom prefix on sessions.
            $this->connection->setOption(\Redis::OPT_PREFIX, $options->prefix);
        }
        if ($options->database !== 0) {
            $this->connection->select($options->database);
        }
        $this->connection->ping();

        \core_shutdown_manager::register_function(array($this, 'auto_release'));
    }

    /**
     * Is available.
     * @return boolean - True if this lock type is available in this environment.
     */
    public function is_available() {
        if (!extension_loaded('redis')) {
            return false;
        }

        // The lock handler requires a version of Redis with the SETEX command (at least 2.0).
        $version = phpversion('Redis');
        if (!$version or version_compare($version, '2.0') <= 0) {
            return true;
        }
        return true;
    }

    /**
     * Return information about the blocking behaviour of the lock type on this platform.
     * @return boolean - Defer to the DB driver.
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * Will this lock type will be automatically released when a process ends.
     *
     * @return boolean - Via shutdown handler.
     */
    public function supports_auto_release() {
        return true;
    }

    /**
     * Multiple locks for the same resource can be held by a single process.
     * @return boolean - Defer to the DB driver.
     */
    public function supports_recursion() {
        return true;
    }

    /**
     * Create and get a lock
     * @param string $resource - The identifier for the lock. Should use frankenstyle prefix.
     * @param int $timeout - The number of seconds to wait for a lock before giving up.
     * @param int $maxlifetime - Unused by this lock type.
     * @return boolean - true if a lock was obtained.
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {

        $key = $this->type . '_' . $resource;

        $haslock = isset($this->openlocks[$key]);
        // $haslock = isset($this->openlocks[$key]) && time() < $this->openlocks[$key];
        $startlocktime = time();

        while (!$haslock) {
            $haslock = $this->connection->setnx($key, '1');

            if ($haslock) {
                $this->openlocks[$key] = time() + $maxlifetime;
                // $this->connection->expire($key, $maxlifetime);
                return new lock($key, $this);
            }

            if (time() >= $startlocktime + $timeout) {
                return false;
            }

            usleep(rand(100000, 1000000));
        }

        // Should never get here.
        return false;
    }

    /**
     * Release a lock that was previously obtained with @lock.
     * @param lock $lock - a lock obtained from this factory.
     * @return boolean - true if the lock is no longer held (including if it was never held).
     */
    public function release_lock(lock $lock) {

        $key = $lock->get_key();

        if (isset($this->openlocks[$key])) {
            $this->connection->del($key);
            unset($this->openlocks[$key]);
        }

        return true;
    }

    /**
     * Extend a lock that was previously obtained with @lock.
     * @param lock $lock - a lock obtained from this factory.
     * @param int $maxlifetime - the new lifetime for the lock (in seconds).
     * @return boolean - true if the lock was extended.
     */
    public function extend_lock(lock $lock, $maxlifetime = 86400) {
        // Not supported by this factory.
        return false;
    }

    /**
     * Auto release any open locks on shutdown.
     */
    public function auto_release() {
        // Called from the shutdown handler. Must release all open locks.
        foreach ($this->openlocks as $key => $unused) {
            $lock = new lock($key, $this);
            $lock->release();
        }
    }

}


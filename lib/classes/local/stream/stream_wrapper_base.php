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
 * Stream wrapper helper
 *
 * Heavily inspired by code written by David Grudl in
 * https://github.com/dg/bypass-finals
 *
 * @package    core
 * @copyright  2004, 2013 David Grudl (https://davidgrudl.com)
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\local\stream;

/**
 * A generic stream_wrapper base class
 *
 * This file MUST have no dependancies on Moodle.
 *
 * This class is designed to be extended and by itself has no net effect on file
 * streams but handles all the hard work proxying to the real file stream. See also:
 *
 * https://www.php.net/manual/en/class.streamwrapper.php
 *
 * @copyright  2004, 2013 David Grudl (https://davidgrudl.com)
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stream_wrapper_base {

    /** @var string Type of streams we can proxy */
    private const PROTOCOL = 'file';

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $handle;

    /** @var path|null */
    public $path;

    /**
     * Enable file stats collection
     */
    public static function enable(): void {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, static::class);
    }

    /**
     * dir_closedir
     */
    public function dir_closedir(): void {
        closedir($this->handle);
    }

    /**
     * dir_opendir
     * @param string $path
     * @param int $options
     */
    public function dir_opendir(string $path, int $options): bool {
        $this->handle = $this->context
            ? $this->native('opendir', $path, $this->context)
            : $this->native('opendir', $path);
        return (bool) $this->handle;
    }

    /**
     * dir_readdir
     */
    public function dir_readdir() {
        return readdir($this->handle);
    }

    /**
     * dir_rewinddir
     */
    public function dir_rewinddir(): bool {
        return (bool) rewinddir($this->handle);
    }

    /**
     * mkdir
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir(string $path, int $mode, int $options): bool {
        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
        return $this->context
            ? $this->native('mkdir', $path, $mode, $recursive, $this->context)
            : $this->native('mkdir', $path, $mode, $recursive);
    }

    /**
     * rename
     * @param string $pathfrom
     * @param string $pathto
     */
    public function rename(string $pathfrom, string $pathto): bool {
        return $this->context
            ? $this->native('rename', $pathfrom, $pathto, $this->context)
            : $this->native('rename', $pathfrom, $pathto);
    }

    /**
     * rmdir
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir(string $path, int $options): bool {
        return $this->context
            ? $this->native('rmdir', $path, $this->context)
            : $this->native('rmdir', $path);
    }

    /**
     * stream_cast
     * @param int $castas
     * @return resource
     */
    public function stream_cast(int $castas) {
        return $this->handle;
    }

    /**
     * stream_close
     */
    public function stream_close(): void {
        fclose($this->handle);
    }

    /**
     * stream_eof
     */
    public function stream_eof(): bool {
        return feof($this->handle);
    }

    /**
     * stream_flush
     */
    public function stream_flush(): bool {
        return fflush($this->handle);
    }

    /**
     * stream_lock
     * @param int $operation
     */
    public function stream_lock(int $operation): bool {
        return $operation
            ? flock($this->handle, $operation)
            : true;
    }

    /**
     * stream_metadata
     * @param string $path
     * @param int $option
     * @param mixed $value
     * @return bool
     */
    public function stream_metadata(string $path, int $option, $value): bool {
        switch ($option) {
            case STREAM_META_TOUCH:
                return $this->native('touch', $path, $value[0] ?? time(), $value[1] ?? time());
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
              return $this->native('chown', $path, $value);
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                return $this->native('chgrp', $path, $value);
            case STREAM_META_ACCESS:
                return $this->native('chmod', $path, $value);
        }
        return false;
    }

    /**
     * stream_open
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string $openedpath
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedpath): bool {
        $usepath = (bool) ($options & STREAM_USE_PATH);
        $this->path = $path;
        $this->handle = $this->context
            ? $this->native('fopen', $path, $mode, $usepath, $this->context)
            : $this->native('fopen', $path, $mode, $usepath);
        return (bool) $this->handle;
    }

    /**
     * stream_read
     * @param int $count
     */
    public function stream_read(int $count) {
        return fread($this->handle, $count);
    }

    /**
     * stream_seek
     * @param int $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool {
        return fseek($this->handle, $offset, $whence) === 0;
    }

    /**
     * stream_set_option
     * @param int $option
     * @param int $arg1
     * @param int $arg2
     * @return bool
     */
    public function stream_set_option($option, $arg1, $arg2): bool {
        return false;
    }

    /**
     * stream_stat
     */
    public function stream_stat() {
        return fstat($this->handle);
    }

    /**
     * stream_tell
     */
    public function stream_tell(): int {
        return ftell($this->handle);
    }

    /**
     * stream_truncate
     * @param int $newsize
     */
    public function stream_truncate(int $newsize): bool {
        return ftruncate($this->handle, $newsize);
    }

    /**
     * stream_write
     * @param string $data
     */
    public function stream_write(string $data) {
        $ret = fwrite($this->handle, $data);
        // For some reason the file stat cache isn't reset or updated when
        // using the wrapper so clear it here.
        clearstatcache(true, $this->path);
        return $ret;
    }

    /**
     * unlink
     * @param string $path
     */
    public function unlink(string $path): bool {
        return $this->native('unlink', $path);
    }

    /**
     * url_stat
     * @param string $path
     * @param int $flags
     */
    public function url_stat(string $path, int $flags) {
        $func = $flags & STREAM_URL_STAT_LINK ? 'lstat' : 'stat';
        return $flags & STREAM_URL_STAT_QUIET
            ? @$this->native($func, $path)
            : $this->native($func, $path);
    }

    /**
     * This temporarily removes the wrapper so we can call the real wrapper
     * @param string $func
     */
    private function native(string $func) {
        stream_wrapper_restore(self::PROTOCOL);
        try {
            return $func(...array_slice(func_get_args(), 1));
        } catch (\Throwable $e) {
            // Sometimes stat fails with a RuntimeException.
        } finally {
            stream_wrapper_unregister(self::PROTOCOL);
            stream_wrapper_register(self::PROTOCOL, static::class);
        }
    }

}

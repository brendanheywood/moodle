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
 * dataformatlib.php - Contains core dataformat related functions.
 *
 * @package    core
 * @subpackage dataformat
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/spout/src/Spout/Autoloader/autoload.php");
use Box\Spout\Writer\WriterFactory;

/**
 * Sends a formated data file to the browser
 *
 * @package    core
 * @subpackage dataformat
 *
 * @param string $filename The base filename without an extension
 * @param string $dataformat A dataformat name
 * @param array $columns An ordered map of column keys and labels
 * @param Iterator $iterator An iterator over the records, usually a RecordSet
 * @param function $callback An option function applied to each record before writing
 * @param mixed $extra An optional value which is passed into the callback function
 */
function download_as_dataformat($filename, $dataformat, $columns, $iterator, $callback = null, $extra = null) {

    if (!NO_OUTPUT_BUFFERING) {
        throw new coding_exception("NO_OUTPUT_BUFFERING must be set to true before calling download_as_dataformat");
    }

    $classname = 'dataformat_' . $dataformat . '\writer';
    if (!class_exists($classname)) {
        throw new coding_exception("Unable to locate dataformat/$type/classes/writer.php");
    }
    $format = new $classname;

    // The data format export could take a while to generate...
    set_time_limit(0);

    // Close the session so that the users other tabs in the same session are not blocked.
    \core\session\manager::write_close();

    $format->set_filename($filename);
    $format->send_http_headers();
    $format->write_header($columns);
    $c = 0;
    foreach ($iterator as $row) {
        if ($callback) {
            $row = $callback($row, $extra);
        }
        if ($row === null) {
            continue;
        }
        $format->write_record($row, $c++);
    }
    $format->write_footer($columns);
}

/**
 * Base class for dataformat.
 *
 * @package    core
 * @subpackage dataformat
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class moodle_dataformat {

    /** @var $mimetype */
    protected $mimetype = "text/plain";

    /** @var $extension */
    protected $extension = ".txt";

    /** @var $filename */
    protected $filename = '';

    /**
     * Get the file extension
     *
     * @return string file extension
     */
    public function get_extension() {
        return $this->extension;
    }

    /**
     * Set download filename base
     *
     * @param string $filename
     */
    public function set_filename($filename) {
        $this->filename = $filename;
    }

    /**
     * Output file headers to initialise the download of the file.
     */
    public function send_http_headers() {
        global $CFG;

        if (defined('BEHAT_SITE_RUNNING')) {
            // For text based formats - we cannot test the output with behat if we force a file download.
            return;
        }
        if (is_https()) {
            // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: max-age=10');
            header('Pragma: ');
        } else {
            // Normal http - prevent caching at all cost.
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
            header('Pragma: no-cache');
        }
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header("Content-Type: $this->mimetype\n");
        $filename = $this->filename . $this->get_extension();
        header("Content-Disposition: attachment; filename=\"$filename\"");
    }

    /**
     * Write the start of the format
     *
     * @param array $columns
     */
    public function write_header($columns) {
        // Override me if needed.
    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    abstract public function write_record($record, $rownum);

    /**
     * Write the end of the format
     *
     * @param array $columns
     */
    public function write_footer($columns) {
        // Override me if needed.
    }

}

/**
 * Common Spout class for dataformat.
 *
 * @package    core
 * @subpackage dataformat
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class spout_dataformat extends moodle_dataformat {

    /** @var $spouttype */
    protected $spouttype = '';

    /** @var $writer */
    protected $writer;

    /**
     * Output file headers to initialise the download of the file.
     */
    public function send_http_headers() {
        $this->writer = WriterFactory::create($this->spouttype);
        $filename = $this->filename . $this->get_extension();
        $this->writer->openToBrowser($filename);
    }

    /**
     * Write the start of the format
     *
     * @param array $columns
     */
    public function write_header($columns) {
        $this->writer->addRow(array_values((array)$columns));
    }

    /**
     * Write a single record
     *
     * @param object $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        $this->writer->addRow(array_values((array)$record));
    }

    /**
     * Write the end of the format
     *
     * @param array $columns
     */
    public function write_footer($columns) {
        $this->writer->close();
        $this->writer = null;
    }

}


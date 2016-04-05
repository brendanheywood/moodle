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
 * csvlib data format writer
 *
 * @package    dataformat_csvlib
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace dataformat_csvlib;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/csvlib.class.php");

/**
 * csvlib data format writer
 *
 * @package    dataformat_csvlib
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer extends \moodle_dataformat {

    /** @var $mimetype */
    protected $mimetype = "text/csv";

    /** @var $extension */
    protected $extension = ".csv";

    /** @var $worksheet */
    protected $worksheet = null;

    /**
     * Write the start of the format
     *
     * @param array $columns
     */
    public function write_header($columns) {

        $this->csvexport->add_data($columns);

    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        $this->csvexport->add_data(array_values((array)$record));
    }

    /**
     * Write the end of the format
     *
     * @param array $columns
     */
    public function write_footer($columns) {
        $this->csvexport->download_file();
    }
    public function send_http_headers() {

        $filename = $this->filename . $this->get_extension();
        $this->csvexport = new \csv_export_writer();
        $this->csvexport->set_filename($filename);
    }

}


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
 * excellib data format writer
 *
 * @package    dataformat_excellib
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace dataformat_excellib;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/excellib.class.php");

/**
 * excellib data format writer
 *
 * @package    dataformat_excellib
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer extends \moodle_dataformat {

    /** @var $mimetype */
    protected $mimetype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    /** @var $extension */
    protected $extension = ".xlsx";

    /** @var $worksheet */
    protected $worksheet = null;

    /**
     * Write the start of the format
     *
     * @param array $columns
     */
    public function write_header($columns) {
        $filename = clean_filename(get_string('users').'.xls');

        $this->workbook = new \MoodleExcelWorkbook('-');
        // $workbook->send($filename);

        $this->worksheet = $this->workbook->add_worksheet('');
        $col = 0;
        foreach ($columns as $key => $value) {
            $this->worksheet->write(0, $col, $value);
            $col++;
        }
    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        $col = 0;
        foreach ($record as $key => $value) {
            $this->worksheet->write($rownum, $col, $value);
            $col++;
        }
    }

    /**
     * Write the end of the format
     *
     * @param array $columns
     */
    public function write_footer($columns) {
        $this->workbook->close();
    }

}


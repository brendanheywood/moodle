<?php

namespace core_dataformat;

/**
 * Common Spout class for dataformat.
 *
 * @package    core
 * @subpackage dataformat
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class spout_base extends \core_dataformat\dataformat_base {

    /** @var $spouttype */
    protected $spouttype = '';

    /** @var $writer */
    protected $writer;

    /** @var $sheettitle */
    protected $sheettitle;

    /**
     * Output file headers to initialise the download of the file.
     */
    public function send_http_headers() {
        $this->writer = \Box\Spout\Writer\WriterFactory::create($this->spouttype);
        $filename = $this->filename . $this->get_extension();
        $this->writer->openToBrowser($filename);
    }

    /**
     * Set the title of the worksheet inside a spreadsheet
     *
     * For some formats this will be ignored.
     *
     * @param string $sheettitle
     */
    public function set_sheettitle($title) {
        if (!$title) {
            return;
        }
        $title = preg_replace('/[\\\\\/\\?\\*\\[\\]]/', '', $title);
        $title = substr($title, 0, 31);
        $this->sheettitle = $title;
        $sheet = $this->writer->getCurrentSheet();
        $sheet->setName($title);
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

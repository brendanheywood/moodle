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
 * A table of check results
 *
 * @package    core
 * @category   check
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\check;

defined('MOODLE_INTERNAL') || die();

/**
 * A table of check results
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table implements \renderable {

    /**
     * @var \moodle_url $url
     */
    protected $url = '';

    /**
     * @var string $type What type of checks
     */
    protected $type = '';

    /**
     * @var check $detail a specific check to focus on
     */
    public $detail = '';

    /**
     * @var array $checks shown in this table
     */
    public $checks = [];

    /**
     * Constructor
     *
     * @param string $type of check
     * @param string $url of report
     * @param string $detail check to focus on
     */
    public function __construct($type, $url, $detail = '') {

        // We may need a bit more memory and this may take a long time to process.
        \raise_memory_limit(MEMORY_EXTRA);
        \core_php_time_limit::raise();

        $this->type = $type;
        $this->url = $url;
        $this->checks = \core\check\manager::get_checks($type);

        if ($detail) {
            $this->checks = array_filter($this->checks, function($check) use ($detail) {
                return $detail == $check->get_ref();
            });
            if (!empty($this->checks)) {
                $this->detail = reset($this->checks);
            }
        }
    }

    /**
     * Render a table of checks
     *
     * @param renderer $output to use
     * @return string html output
     */
    public function render($output) {

        $waiting = $output->pix_icon('i/loading', get_string('loading'));

        $table = new \html_table();
        $table->data = [];
        $table->head = [];
        $th = new \html_table_cell(get_string('status'));
        $th->style = 'width: 6em';
        $table->head[] = $th;

        $th = new \html_table_cell(get_string('check'));
        $th->style = 'width: 10em';
        $table->head[] = $th;

        $th = new \html_table_cell(get_string('summary'));
        $table->head[] = $th;

        $th = new \html_table_cell(get_string('action'));
        $th->style = 'width: 20%';
        $table->head[] = $th;

        $table->colclasses = [
            'rightalign status',
            'leftalign check',
            'leftalign summary',
            'leftalign action',
        ];
        $table->id = $this->type . 'reporttable';
        $table->attributes = ['class' => 'admintable ' . $this->type . 'report generaltable'];

        foreach ($this->checks as $check) {
            $ref = $check->get_ref();
            $component = $check->get_component();
            $actionlink = $check->get_action_link();

            $link = new \moodle_url($this->url, ['detail' => $ref]);

            $row = [];
            $row[] = \html_writer::tag('span', $waiting, ['class' => 'status']);
            $row[] = $output->action_link($link, $check->get_name());

            $row[] = \html_writer::tag('span', $waiting, ['class' => 'summarytext'])
                . '<br>'
                . \html_writer::start_tag('small')
                . $output->action_link($link, get_string('moreinfo'))
                . \html_writer::end_tag('small');
            if ($actionlink) {
                $row[] = $output->render($actionlink);
            } else {
                $row[] = '';
            }

            $tablerow = new \html_table_row($row);
            $tablerow->id = 'row_' . $ref;
            $table->data[] = $tablerow;
        }
        $html = \html_writer::table($table);

        if ($this->detail) {
            // Just render a placeholder for the details.
            $html .= $output->heading(get_string('details'), 3);
            $loading = \html_writer::tag('div', '', ['class' => 'bg-pulse-grey', 'style' => 'min-height: 4em']);
            $html .= $output->box($loading, 'generalbox boxwidthnormal boxaligncenter', 'checkdetails');
            $html .= $output->continue_button($this->url);
        }

        return $html;
    }

    /**
     * Runs the checks asynchronously
     * @param renderer $output page renderer
     * @return string html
     */
    public function run_checks($output) {
        foreach ($this->checks as $check) {
            $ref = $check->get_ref();
            $id = 'row_' . $ref;
            $result = $check->get_result();
            $status = $output->check_result($result);
            echo $output->select_element_for_replace("#$id .status", $status);

            $summary = $result->get_summary();
            echo $output->select_element_for_replace("#$id .summarytext", $summary);

            if ($this->detail) {
                echo $output->select_element_for_replace('#checkdetails', $result->get_details());
            }
        }
    }
}


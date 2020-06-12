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
 * Table to show the list of tours.
 *
 * @package    tool_usertours
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_usertours\local\table;

defined('MOODLE_INTERNAL') || die();

use tool_usertours\helper;
use tool_usertours\tour;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table to show the list of tours.
 *
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tour_list extends \flexible_table {
    use \action_table_trait;
    /**
     * Construct the tour table.
     */
    public function __construct() {
        parent::__construct('tours');

        $baseurl = new \moodle_url('/tool/usertours/configure.php');
        $this->define_baseurl($baseurl);

        // Column definition.
        $this->define_columns(array(
            'name',
            'description',
            'appliesto',
            'enabled',
            'actions',
        ));

        $this->define_headers(array(
            get_string('name', 'tool_usertours'),
            get_string('description', 'tool_usertours'),
            get_string('appliesto', 'tool_usertours'),
            get_string('enabled', 'tool_usertours'),
            get_string('actions', 'tool_usertours'),
        ));

        $this->set_attribute('class', 'admintable generaltable');
        $this->setup();

        $this->tourcount = helper::count_tours();
    }

    /**
     * Format the current row's name column.
     *
     * @param   tour    $tour       The tour for this row.
     * @return  string
     */
    protected function col_name(tour $tour) {
        global $OUTPUT;
        return $OUTPUT->render(helper::render_tourname_inplace_editable($tour));
    }

    /**
     * Format the current row's description column.
     *
     * @param   tour    $tour       The tour for this row.
     * @return  string
     */
    protected function col_description(tour $tour) {
        global $OUTPUT;
        return $OUTPUT->render(helper::render_tourdescription_inplace_editable($tour));
    }

    /**
     * Format the current row's appliesto column.
     *
     * @param   tour    $tour       The tour for this row.
     * @return  string
     */
    protected function col_appliesto(tour $tour) {
        return $tour->get_pathmatch();
    }

    /**
     * Format the current row's enabled column.
     *
     * @param   tour    $tour       The tour for this row.
     * @return  string
     */
    protected function col_enabled(tour $tour) {
        global $OUTPUT;
        return $OUTPUT->render(helper::render_tourenabled_inplace_editable($tour));
    }

    /**
     * Used by the action_table_trait (col_actions function) to
     * render the table's actions as action_links.
     * i.e. action_link(url, text, component_action, attributes, icon)
     *
     * @param  object $row
     * @return array  An array of action_links.
     */
    public function get_table_actions($row) {

        // The actions list for the table.
        $actions = [];

        if ($row->is_first_tour()) {
            $actions[] = new \action_link(
                $row->get_moveup_link(),
                get_string('movetourup', 'tool_usertours'),
                null,
                ['disabled' => true],
                new \pix_icon('t/up', get_string('movetourup', 'tool_usertours'))
            );
        } else {
            $actions[] = new \action_link(
                $row->get_moveup_link(),
                get_string('movetourup', 'tool_usertours'),
                null,
                null,
                new \pix_icon('t/up', get_string('movetourup', 'tool_usertours'))
            );
        }

        if ($row->is_last_tour($this->tourcount)) {
            $actions[] = new \action_link(
                $row->get_movedown_link(),
                get_string('movetourdown', 'tool_usertours'),
                null,
                ['disabled' => true],
                new \pix_icon('t/down', get_string('movetourdown', 'tool_usertours'))
            );
        } else {
            $actions[] = new \action_link(
                $row->get_movedown_link(),
                get_string('movetourdown', 'tool_usertours'),
                null,
                null,
                new \pix_icon('t/down', get_string('movetourdown', 'tool_usertours'))
            );
        }

        $actions[] = new \action_link(
            $row->get_view_link(),
            get_string('view'),
            null,
            null,
            new \pix_icon('t/viewdetails', get_string('view'))
        );

        $actions[] = new \action_link(
            $row->get_edit_link(),
            get_string('edit'),
            null,
            null,
            new \pix_icon('t/edit', get_string('edit'))
        );

        $actions[] = new \action_link(
            $row->get_duplicate_link(),
            get_string('duplicate'),
            null,
            null,
            new \pix_icon('t/copy', get_string('duplicate'))
        );

        $actions[] = new \action_link(
            $row->get_export_link(),
            get_string('exporttour', 'tool_usertours'),
            null,
            null,
            new \pix_icon('t/export', get_string('exporttour', 'tool_usertours'), 'tool_usertours')
        );

        $deleteurl = $row->get_delete_link();
        $deleteurl->params(['id' => $row->get_id()]);

        $actions[] = new \action_link(
            $deleteurl,
            get_string('delete'),
            new \confirm_action(get_string('areyousure')),
            null,
            new \pix_icon('t/delete', get_string('delete'))
        );

        return $actions;
    }
}
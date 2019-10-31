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
 * License manager.
 *
 * @package    tool_licenses
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_licenses;

use html_table;
use html_writer;
use stdClass;
use license_manager;
use tool_licenses\form\edit_license;

defined('MOODLE_INTERNAL') || die();

/**
 * License manager, main controller for tool_licenses.
 *
 * @package    tool_licenses
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Action for creating a new custom license.
     */
    const ACTION_CREATE = 'create';

    /**
     * Action for reading a license.
     */
    const ACTION_READ = 'read';

    /**
     * Action for updating a custom license's details.
     */
    const ACTION_UPDATE = 'update';

    /**
     * Action for deleting a custom license.
     */
    const ACTION_DELETE = 'delete';

    /**
     * Action for deleting a custom license.
     */
    const ACTION_DISABLE = 'disable';

    /**
     * Action for deleting a custom license.
     */
    const ACTION_ENABLE = 'enable';

    /**
     * Action for displaying the license list view.
     */
    const ACTION_VIEW_LICENSE_MANAGER = 'viewlicensemanager';

    /**
     * Action for moving a license up priority order.
     */
    const ACTION_MOVE_UP = 'moveup';

    /**
     * Action for deleting a license down priority order.
     */
    const ACTION_MOVE_DOWN = 'movedown';

    /**
     * Entry point for internal license manager.
     *
     * @param string $action the api action to carry out.
     * @param string|object $license the license object or shortname of license to carry action out on.
     */
    public function execute($action, $license) {
        admin_externalpage_setup('tool_licenses/manager');

        // Convert license to a string if it's a full license object.
        if (is_object($license)) {
            $license = $license->shortname;
        }

        $return = true;

        switch ($action) {
            case self::ACTION_DISABLE:
                license_manager::disable($license);
                break;

            case self::ACTION_ENABLE:
                license_manager::enable($license);
                break;

            case self::ACTION_DELETE:
                license_manager::delete($license);
                break;

            case self::ACTION_CREATE:
            case self::ACTION_UPDATE:
                $this->edit($action, $license);
                $return = false;
                break;

            case self::ACTION_MOVE_UP:
            case self::ACTION_MOVE_DOWN:
                $this->change_license_priority($action, $license);
                break;

            case self::ACTION_READ:
                return license_manager::get_license_by_shortname($license);
                break;

            case self::ACTION_VIEW_LICENSE_MANAGER:
            default:
                $this->view_license_manager();
                $return = false;
                break;
        }

        if ($return) {
            redirect(helper::get_view_license_manager_url());
        }
    }

    /**
     * Edit an existing license or create a new license.
     *
     * @param string $action the form action to carry out.
     * @param string $licenseshortname the shortname of the license to edit.
     */
    private function edit(string $action, string $licenseshortname) {

        $form = new form\edit_license($action, $licenseshortname);

        if ($form->is_cancelled()) {
            redirect(helper::get_view_license_manager_url());
        } else if ($data = $form->get_data()) {
            // Process the form data and create or update a license record.
            $existing = license_manager::get_license_by_shortname($data->shortname);

            if (!empty($existing) && $action == self::ACTION_CREATE) {
                print_error('duplicatelicenseshortname', 'tool_licenses', helper::get_view_license_manager_url(),
                    $data->shortname);
            }

            $license = new stdClass();
            $license->shortname = $data->shortname;
            $license->fullname = $data->fullname;
            $license->source = $data->source;
            $license->version = date('Ymd', $data->version) . '00';
            $license->custom = license_manager::CUSTOM_LICENSE;
            license_manager::add($license);
            license_manager::enable($licenseshortname);

            redirect(helper::get_view_license_manager_url());
        } else {
            $this->view_license_editor($action, $licenseshortname, $form);
        }
    }

    /**
     * Change license priority by moving up or down license priority order.
     *
     * @param $direction
     * @param $licenseshortname
     */
    private function change_license_priority($direction, $licenseshortname) {

        if (in_array($direction, [self::ACTION_MOVE_UP, self::ACTION_MOVE_DOWN]) && !empty($licenseshortname)) {
            $priorityorder = explode(',', get_config('', 'licensepriority'));

            $currentindex = array_search($licenseshortname, $priorityorder);

            // Can only move priority up if not already at the top or move priority down if not at bottom of order.
            if (($currentindex > 0 && $direction == self::ACTION_MOVE_UP)
                    || ($currentindex < count($priorityorder) - 1) && $direction == self::ACTION_MOVE_DOWN) {
                $newindex = $direction == self::ACTION_MOVE_UP ? $currentindex - 1 : $currentindex + 1;
                $license = array_splice($priorityorder, $currentindex, 1);
                array_splice($priorityorder, $newindex, 0, $license);
            }

            set_config('licensepriority', implode(',', $priorityorder));
        }
    }

    /**
     * Display the main license manager view.
     *
     */
    private function view_license_manager() {
        global $PAGE;

        $licenses = license_manager::get_licenses_in_priority_order();
        $renderer = $PAGE->get_renderer('tool_licenses');

        $return = $renderer->header();
        $return .= $renderer->heading(get_string('managelicenses', 'tool_licenses'), 3, 'main', true);

        $return .= $renderer->box_start('generalbox editorsui');

        $table = new html_table();
        $table->head  = array(
            get_string('up') .  '/' . get_string('down'),
            get_string('shortname', 'tool_licenses'),
            get_string('fullname', 'tool_licenses'),
            get_string('version', 'tool_licenses'),
            get_string('source', 'tool_licenses'),
            get_string('enable'), get_string('edit'), get_string('delete')
        );
        $table->colclasses = array('text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-center', 'text-center', 'text-center');
        $table->id = 'availablelicenses';
        $table->attributes['class'] = 'admintable generaltable';
        $table->data  = array();

        $keys = array_keys($licenses);
        $lastkey = end($keys);
        $firstkey = reset($keys);

        foreach ($licenses as $key => $value) {
            $canmoveup = $key != $firstkey;
            $canmovedown = $key != $lastkey;
            $table->data[] = $this->get_license_table_row_data($value, $renderer, $canmoveup, $canmovedown);
        }

        $return .= html_writer::table($table);
        $return .= $renderer->box_end();
        $return .= $renderer->single_button(helper::get_create_license_url(),
            get_string('createlicense', 'tool_licenses'));
        $return .= $renderer->footer();

        echo $return;
    }

    /**
     * View the license editor to create or edit a license.
     *
     * @param string $action
     * @param string $licenseshortname the shortname of the license to create/edit.
     * @param \tool_licenses\form\edit_license $form the form for submitting edit data.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function view_license_editor(string $action, string $licenseshortname, edit_license $form) {
        global $PAGE;

        $renderer = $PAGE->get_renderer('tool_licenses');
        $return = $renderer->header();

        if ($action == self::ACTION_CREATE) {
            $return .= $renderer->heading(get_string('createlicense', 'tool_licenses'));
        } else if ($action == self::ACTION_UPDATE) {
            $return .= $renderer->heading(get_string('editlicense', 'tool_licenses'));

            $license = license_manager::get_license_by_shortname($licenseshortname);

            if (!empty($license)) {
                $form->set_data(['shortname' => $license->shortname]);
                $form->set_data(['fullname' => $license->fullname]);
                $form->set_data(['source' => $license->source]);
                $form->set_data(['version' => $license->version]);
            } else {
                // There is no license to update, so redirect to creation url.
                redirect(helper::get_create_license_url());
            }
        }
        $return .= $form->render();
        $return .= $renderer->footer();

        echo $return;
    }

    /**
     * Get table row data for a license.
     *
     * @param object $license the license to populate row data for.
     * @param \renderer_base $renderer the PAGE renderer.
     * @param bool $canmoveup can this row move up.
     * @param bool $canmovedown can this row move down.
     *
     * @return array of columns values for row.
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function get_license_table_row_data($license, $renderer, bool $canmoveup, bool $canmovedown) {
        global $CFG;

        $source = html_writer::link($license->source, $license->source, ['target' => '_blank']);

        if ($license->shortname == $CFG->sitedefaultlicense) {
            $source .= ' ' . $renderer->pix_icon('t/locked', get_string('default'));
            $hideshow = $renderer->pix_icon('t/locked', get_string('default'));
            $editlicense = $renderer->pix_icon('t/locked', get_string('default'));
            $deletelicense = $renderer->pix_icon('t/locked', get_string('default'));
        } else {
            if ($license->enabled == license_manager::LICENSE_ENABLED) {
                $hideshow = html_writer::link(helper::get_disable_license_url($license->shortname),
                    $renderer->pix_icon('t/hide', get_string('disable')));
            } else {
                $hideshow = html_writer::link(helper::get_enable_license_url($license->shortname),
                    $renderer->pix_icon('t/show', get_string('enable')));
            }

            if ($license->custom == license_manager::CUSTOM_LICENSE) {
                $editlicense = html_writer::link(helper::get_update_license_url($license->shortname),
                    $renderer->pix_icon('t/editinline', get_string('edit')));
            } else {
                $editlicense = $renderer->pix_icon('t/block', get_string('editlock'));
            }

            if ($license->custom == license_manager::CUSTOM_LICENSE) {
                $deletelicense = html_writer::link(helper::get_delete_license_url($license->shortname),
                    $renderer->pix_icon('i/trash', get_string('delete')));
            } else {
                $deletelicense = $renderer->pix_icon('t/block', get_string('editlock'));
            }
        }

        $spacer = $renderer->pix_icon('spacer', '', 'moodle', array('class' => 'iconsmall'));
        $updown = '';
        if ($canmoveup) {
            $updown .= html_writer::link(helper::get_moveup_license_url($license->shortname),
                    $renderer->pix_icon('t/up', get_string('up'), 'moodle', array('class' => 'iconsmall'))). '';
        } else {
            $updown .= $spacer;
        }

        if ($canmovedown) {
            $updown .= '&nbsp;'.html_writer::link(helper::get_movedown_license_url($license->shortname),
                    $renderer->pix_icon('t/down', get_string('down'), 'moodle', array('class' => 'iconsmall')));
        } else {
            $updown .= $spacer;
        }
        
        return [$updown, $license->shortname, $license->fullname, $license->version ,$source, $hideshow, $editlicense, $deletelicense];
    }

}

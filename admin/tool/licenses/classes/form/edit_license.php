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
 * Form for creating/updating a custom license.
 *
 * @package    tool_licenses
 * @copyright  2019 Tom Dickman <tom.dickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_licenses\form;

use tool_licenses\helper;
use tool_licenses\manager;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Form for creating/updating a custom license.
 *
 * @package    tool_licenses
 * @copyright  2019 Tom Dickman <tom.dickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_license extends \moodleform {

    /**
     * edit_license constructor.
     *
     * @param string $action the license_manager action to be taken by form.
     * @param string $licenseshortname the shortname of the license to edit.
     */
    public function __construct(string $action, string $licenseshortname) {
        if ($action == manager::ACTION_UPDATE && !empty($licenseshortname)) {
            parent::__construct(helper::get_update_license_url($licenseshortname));
        } else {
            parent::__construct(helper::get_create_license_url());
        }
    }

    /**
     * Form definition for creation and editing of licenses.
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_licenses'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'fullname', get_string('fullname', 'tool_licenses'));
        $mform->setType('fullname', PARAM_TEXT);

        $mform->addElement('text', 'source', get_string('source', 'tool_licenses'));
        $mform->setType('source', PARAM_URL);
        $mform->addHelpButton('source', 'source', 'tool_licenses');

        $mform->addElement('date_selector', 'version', get_string('version', 'tool_licenses'), get_string('from'));
        $mform->addHelpButton('version', 'version', 'tool_licenses');

        $this->add_action_buttons();
    }

    /**
     * Validate form data and return errors (if any).
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (array_key_exists('source', $data)  && !filter_var($data['source'], FILTER_VALIDATE_URL)) {
            $errors['source'] = get_string('invalidurl', 'tool_licenses');
        }

        return $errors;
    }
}

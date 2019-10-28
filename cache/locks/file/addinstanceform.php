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
 * Redis Cache Store - Add instance form
 *
 * @package   cachestore_redis
 * @copyright 2013 Adam Durana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/cache/forms.php');

/**
 * Form for adding instance of Redis Cache Store.
 *
 * @copyright   2013 Adam Durana
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachelock_file_addinstance_form extends cache_lock_form {
    /**
     * Builds the form for creating an instance.
     */
    protected function configuration_definition() {
        $form = $this->_form;

        $form->addElement('text', 'dir', get_string('dir', 'cachelock_file'), array('size' => 24));
        $form->addHelpButton('dir', 'dir', 'cachelock_file');
        $form->setType('dir', PARAM_TEXT);
        $form->addRule('dir', get_string('required'), 'required');

        $form->addElement('text', 'maxlife', get_string('maxlife', 'cachelock_file'), array('size' => 16));
        $form->addHelpButton('maxlife', 'maxlife', 'cachelock_file');
        $form->setType('maxlife', PARAM_INT);

        $form->addElement('text', 'blockattempts', get_string('blockattempts', 'cachelock_file'), array('size' => 16));
        $form->addHelpButton('blockattempts', 'blockattempts', 'cachelock_file');
        $form->setType('blockattempts', PARAM_INT);

    }
}


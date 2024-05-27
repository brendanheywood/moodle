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
 * Class urlpreview
 *
 * @package    core
 * @copyright  2024 Team "the Z" <https://github.com/Catalyst-QUT-2023>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\form;

use core\persistent;

/**
 * Class urlpreview
 * This clas handles the persistent storage of URL preview data.
 */
class urlpreview extends persistent {
    /**
     * The name of the table used to store URL preview data.
     */
    const TABLE = 'urlpreview';

    /**
     * Define the properties of this persistent.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'url' => [
                'type' => PARAM_URL,
                'description' => 'URL to be linted',
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'description' => 'Title of the page',
            ],
            'type' => [
                'type' => PARAM_TEXT,
                'description' => 'Type of content',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'imageurl' => [
                'type' => PARAM_URL,
                'description' => 'URL of the preview image',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'sitename' => [
                'type' => PARAM_TEXT,
                'description' => 'Name of the site',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'description' => 'Description of the page',
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'timecreated' => [
                'type' => PARAM_INT,
                'description' => 'Timestamp when the record was created',
                'default' => 0,
            ],
            'timemodified' => [
                'type' => PARAM_INT,
                'description' => 'Timestamp when the record was last modified',
                'default' => 0,
            ],
            'lastpreviewed' => [
                'type' => PARAM_INT,
                'description' => 'Timestamp of the last previewed time',
            ],
        ];
    }
}

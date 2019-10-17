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
 * This file contains the unit tests for the task manager.
 *
 * @package   core
 * @copyright 2019 Brendan Heywood <brendan@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/task_fixtures.php');


/**
 * This file contains the unit tests for the task manager.
 *
 * @copyright 2019 Brendan Heywood <brendan@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_task_logmanager extends advanced_testcase {

    public function test_ensure_adhoc_task_qos_provider() {
        return [
            [
                1,
                [],
                [],
            ],
            // A queue with a lopside initial load that needs to be staggered.
            [
                1,
                [
                    (object)['id' => 1, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 2, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 3, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 1, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 2, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 3, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                ],
            ],
            // The same lopsided queue but now the first item is gone.
            [
                1,
                [
                    (object)['id' => 2, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 3, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 2, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 3, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                ],
            ],
            // The same lopsided queue but now the first two items is gone.
            [
                1,
                [
                    (object)['id' => 3, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 3, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                ],
            ],
            // The same lopsided queue but now the first three items are gone.
            [
                1,
                [
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 4, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                ],
            ],
            [
                1,
                [
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 5, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 7, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],

                    (object)['id' => 6, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 8, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],

                    (object)['id' => 9, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
            ],
            [
                2,
                [
                    (object)['id' => 11, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 12, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 13, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 14, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 15, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 16, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 17, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 18, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 19, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 17, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 11, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 12, 'classname' => '\core\task\asynchronous_backup_task'],

                    (object)['id' => 18, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 13, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 14, 'classname' => '\core\task\asynchronous_backup_task'],

                    (object)['id' => 19, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 15, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 16, 'classname' => '\core\task\asynchronous_backup_task'],
                ],
            ],
            [
                3,
                [
                    (object)['id' => 21, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 22, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 23, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 24, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 25, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 26, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 27, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 28, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 29, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
                [
                    (object)['id' => 27, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 21, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 22, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 23, 'classname' => '\core\task\asynchronous_backup_task'],

                    (object)['id' => 28, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 24, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 25, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 26, 'classname' => '\core\task\asynchronous_backup_task'],

                    (object)['id' => 29, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
            ],
            [
                2,
                [
                    (object)['id' => 31, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 32, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 33, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 34, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 35, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 36, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 37, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 38, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 39, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 40, 'classname' => '\core\task\build_installed_themes_task'],
                    (object)['id' => 41, 'classname' => '\core\task\build_installed_themes_task'],
                    (object)['id' => 42, 'classname' => '\core\task\build_installed_themes_task'],
                    (object)['id' => 43, 'classname' => '\core\task\build_installed_themes_task'],
                ],
                [
                    (object)['id' => 31, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 32, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 37, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 40, 'classname' => '\core\task\build_installed_themes_task'],
                    (object)['id' => 41, 'classname' => '\core\task\build_installed_themes_task'],

                    (object)['id' => 33, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 34, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 38, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                    (object)['id' => 42, 'classname' => '\core\task\build_installed_themes_task'],
                    (object)['id' => 43, 'classname' => '\core\task\build_installed_themes_task'],

                    (object)['id' => 35, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 36, 'classname' => '\core\task\asynchronous_backup_task'],
                    (object)['id' => 39, 'classname' => '\tool_dataprivacy\task\process_data_request_task'],
                ],
            ],
        ];
    }

    /**
     * Test that the Quality of Service reordering works.
     * @dataProvider test_ensure_adhoc_task_qos_provider
     */
    public function test_ensure_adhoc_task_qos(int $max, array $input, array $expected) {
        global $CFG;

        $this->resetAfterTest();
        $CFG->default_concurrency_limit = $max;

        $result = \core\task\manager::ensure_adhoc_task_qos($input);

        $this->assertEquals($expected, $result);

    }

}


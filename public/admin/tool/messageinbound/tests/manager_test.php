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

namespace tool_messageinbound;

use core_privacy\tests\provider_testcase;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use tool_messageinbound\privacy\provider;

/**
 * Manager testcase class.
 *
 * @package    tool_messageinbound
 * @category   test
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends provider_testcase {

    public function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();

        // Pretend the system is enabled.
        $CFG->messageinbound_enabled = true;
        $CFG->messageinbound_mailbox = 'mailbox';
        $CFG->messageinbound_domain = 'example.com';
    }

    public function test_tidy_old_verification_failures(): void {
        global $DB;

        $now = time();
        $stale = $now - DAYSECS - 1;    // Make a second older because PHP Unit is too damn fast!!

        $this->create_messagelist(['timecreated' => $now]);
        $this->create_messagelist(['timecreated' => $now - HOURSECS]);
        $this->create_messagelist(['timecreated' => $stale]);
        $this->create_messagelist(['timecreated' => $stale - HOURSECS]);
        $this->create_messagelist(['timecreated' => $stale - YEARSECS]);

        $this->assertEquals(5, $DB->count_records('messageinbound_messagelist', []));
        $this->assertEquals(3, $DB->count_records_select('messageinbound_messagelist', 'timecreated < :t', ['t' => $stale + 1]));

        $manager = new \tool_messageinbound\manager();
        $manager->tidy_old_verification_failures();

        $this->assertEquals(2, $DB->count_records('messageinbound_messagelist', []));
        $this->assertEquals(0, $DB->count_records_select('messageinbound_messagelist', 'timecreated < :t', ['t' => $stale + 1]));
    }

    /**
     * Test that process_message_body_structure_parameters correctly parses key-value attribute pairs.
     *
     * @covers \tool_messageinbound\manager::process_message_body_structure_parameters
     */
    public function test_process_message_body_structure_parameters_with_valid_attributes(): void {
        $manager = new \tool_messageinbound\manager();
        $method = new \ReflectionMethod($manager, 'process_message_body_structure_parameters');

        $result = $method->invoke($manager, ['CHARSET', 'UTF-8', 'NAME', 'test.txt'], []);

        $this->assertSame(['CHARSET' => 'UTF-8', 'NAME' => 'test.txt'], $result);
    }

    /**
     * Test that process_message_body_structure_parameters returns an empty array when no attributes given.
     *
     * @covers \tool_messageinbound\manager::process_message_body_structure_parameters
     */
    public function test_process_message_body_structure_parameters_with_empty_attributes(): void {
        $manager = new \tool_messageinbound\manager();
        $method = new \ReflectionMethod($manager, 'process_message_body_structure_parameters');

        $result = $method->invoke($manager, [], []);

        $this->assertSame([], $result);
    }

    /**
     * Test that process_message_body_structure_parameters handles an odd number of attributes (unpaired last entry).
     *
     * @covers \tool_messageinbound\manager::process_message_body_structure_parameters
     */
    public function test_process_message_body_structure_parameters_with_odd_attributes(): void {
        $manager = new \tool_messageinbound\manager();
        $method = new \ReflectionMethod($manager, 'process_message_body_structure_parameters');

        // BOUNDARY has no paired value — should be silently dropped.
        $result = $method->invoke($manager, ['CHARSET', 'UTF-8', 'BOUNDARY'], []);

        $this->assertSame(['CHARSET' => 'UTF-8'], $result);
    }

    /**
     * Test that process_message_data_body_part does not throw when IMAP returns a non-array (e.g. NIL string)
     * for the parameters field ($partstructure[2]) of a BODYSTRUCTURE response.
     *
     * Regression test for the TypeError:
     *   process_message_body_structure_parameters(): Argument #1 ($attributes) must be of type array,
     *   string given
     *
     * @covers \tool_messageinbound\manager::process_message_data_body_part
     */
    public function test_process_message_data_body_part_with_nil_parameters(): void {
        $manager = new \tool_messageinbound\manager();

        // Build a minimal fake IMAP fetch result with a body part.
        $bodypartkey = '1';
        $fakeheader = new \rcube_message_header();
        $fakeheader->bodypart = [$bodypartkey => 'Hello world'];

        $fakeclient = $this->createMock(\rcube_imap_generic::class);
        $fakeclient->selected = 'INBOX';
        $fakeclient->method('fetch')->willReturn([$fakeheader]);

        // Inject the mock client via reflection.
        $clientprop = new \ReflectionProperty($manager, 'client');
        $clientprop->setValue($manager, $fakeclient);

        $method = new \ReflectionMethod($manager, 'process_message_data_body_part');

        // Index 2 is an empty string simulating a NIL IMAP BODYSTRUCTURE parameter.
        // Before the fix this would throw a TypeError: Argument #1 must be of type array, string given.
        $partstructure = [
            0 => 'TEXT', // Type.
            1 => 'PLAIN', // Subtype.
            2 => '', // Parameters, NIL from IMAP server, parsed as string.
            3 => null, // Content-id.
            4 => null, // Description.
            5 => '7BIT', // Encoding.
            6 => 11, // Size.
        ];

        $contentplain = '';
        $contenthtml  = '';
        $attachments  = ['inline' => [], 'attachment' => []];
        $parameters   = [];

        // Use invokeArgs with references, as the method takes by-reference parameters.
        $args = [1, $partstructure, $bodypartkey, &$contentplain, &$contenthtml, &$attachments, &$parameters];
        $method->invokeArgs($manager, $args);

        $this->assertSame('Hello world', $contentplain);
    }

    /**
     * Create a message to validate.
     *
     * @param array $params The params.
     * @return stdClass
     */
    protected function create_messagelist(array $params) {
        global $DB, $USER;
        $record = (object) array_merge([
            'messageid' => 'abc',
            'userid' => $USER->id,
            'address' => 'text@example.com',
            'timecreated' => time(),
        ], $params);
        $record->id = $DB->insert_record('messageinbound_messagelist', $record);
        return $record;
    }

}

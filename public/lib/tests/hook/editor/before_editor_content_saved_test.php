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

namespace core\hook\editor;

/**
 * Tests for the before_editor_content_saved hook and its two dispatch points.
 *
 * @coversDefaultClass \core\hook\editor\before_editor_content_saved
 *
 * @package    core
 * @copyright  2026 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class before_editor_content_saved_test extends \advanced_testcase {

    // -------------------------------------------------------------------------
    // Hook unit tests
    // -------------------------------------------------------------------------

    /**
     * Test that the hook stores and returns text correctly.
     * @covers ::__construct
     * @covers ::get_text
     */
    public function test_get_text(): void {
        $hook = new before_editor_content_saved(
            text: '<p>Hello</p>',
            contextid: 1,
            component: 'mod_forum',
            filearea: 'post',
            itemid: 42,
            draftitemid: 99,
            format: FORMAT_HTML,
        );
        $this->assertSame('<p>Hello</p>', $hook->get_text());
    }

    /**
     * Test that set_text replaces the stored content.
     * @covers ::set_text
     * @covers ::get_text
     */
    public function test_set_text(): void {
        $hook = new before_editor_content_saved(
            text: '<p>Original</p>',
            contextid: 1,
            component: 'mod_forum',
            filearea: 'post',
            itemid: 42,
            draftitemid: 99,
        );
        $hook->set_text('<p>Replaced</p>');
        $this->assertSame('<p>Replaced</p>', $hook->get_text());
    }

    /**
     * Test that readonly properties are accessible.
     * @covers ::__construct
     */
    public function test_readonly_properties(): void {
        $hook = new before_editor_content_saved(
            text: 'text',
            contextid: 5,
            component: 'mod_forum',
            filearea: 'post',
            itemid: 10,
            draftitemid: 20,
            format: FORMAT_HTML,
        );
        $this->assertSame(5, $hook->contextid);
        $this->assertSame('mod_forum', $hook->component);
        $this->assertSame('post', $hook->filearea);
        $this->assertSame(10, $hook->itemid);
        $this->assertSame(20, $hook->draftitemid);
        $this->assertSame(FORMAT_HTML, $hook->format);
    }

    /**
     * Test that nullable properties default to null correctly.
     * @covers ::__construct
     */
    public function test_nullable_properties(): void {
        $hook = new before_editor_content_saved(
            text: 'text',
            contextid: 1,
            component: null,
            filearea: null,
            itemid: null,
            draftitemid: null,
        );
        $this->assertNull($hook->component);
        $this->assertNull($hook->filearea);
        $this->assertNull($hook->itemid);
        $this->assertNull($hook->draftitemid);
        $this->assertNull($hook->format);
    }

    // -------------------------------------------------------------------------
    // Injection point 1: file_save_draft_area_files()
    // -------------------------------------------------------------------------

    /**
     * Test that the hook is dispatched from file_save_draft_area_files() and
     * that a subscriber can transform the returned text.
     *
     * @covers \file_save_draft_area_files
     */
    public function test_hook_dispatched_from_file_save_draft_area_files(): void {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir . '/filelib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $syscontext = \context_system::instance();
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();

        // Create a draft file so the draft area is non-empty.
        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $syscontext->id, 'core', 'unittest', 1);
        $filerecord = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftitemid,
            'filepath'  => '/',
            'filename'  => 'test.txt',
        ];
        $fs->create_file_from_string($filerecord, 'test content');

        $originaltext = '<p>Hello @@PLUGINFILE@@/test.txt</p>';

        $receivedhook = null;
        $this->redirectHook(
            before_editor_content_saved::class,
            function (before_editor_content_saved $hook) use (&$receivedhook): void {
                $receivedhook = $hook;
                $hook->set_text($hook->get_text() . '<!-- transformed -->');
            },
        );

        $result = file_save_draft_area_files(
            $draftitemid,
            $syscontext->id,
            'core',
            'unittest',
            1,
            null,
            $originaltext,
        );

        $this->assertInstanceOf(before_editor_content_saved::class, $receivedhook);
        $this->assertSame($syscontext->id, $receivedhook->contextid);
        $this->assertSame('core', $receivedhook->component);
        $this->assertSame('unittest', $receivedhook->filearea);
        $this->assertSame(1, $receivedhook->itemid);
        $this->assertSame($draftitemid, $receivedhook->draftitemid);
        $this->assertNull($receivedhook->format);

        // The transformed text should be returned.
        $this->assertStringContainsString('<!-- transformed -->', $result);
    }

    /**
     * Test that the hook is NOT dispatched when $text is null.
     *
     * @covers \file_save_draft_area_files
     */
    public function test_hook_not_dispatched_when_text_is_null(): void {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $syscontext = \context_system::instance();

        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $syscontext->id, 'core', 'unittest', 1);

        $dispatched = false;
        $this->redirectHook(
            before_editor_content_saved::class,
            function (before_editor_content_saved $hook) use (&$dispatched): void {
                $dispatched = true;
            },
        );

        $result = file_save_draft_area_files(
            $draftitemid,
            $syscontext->id,
            'core',
            'unittest',
            1,
            null,
            null,
        );

        $this->assertNull($result);
        $this->assertFalse($dispatched, 'Hook should not be dispatched when $text is null');
    }

    // -------------------------------------------------------------------------
    // Injection point 2: file_postupdate_standard_editor() with maxfiles == 0
    // -------------------------------------------------------------------------

    /**
     * Test that the hook is dispatched from file_postupdate_standard_editor()
     * when maxfiles == 0 (no draft file area involved) and that a subscriber
     * can transform the stored text.
     *
     * @covers \file_postupdate_standard_editor
     */
    public function test_hook_dispatched_from_file_postupdate_standard_editor_no_files(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $this->resetAfterTest();

        $context = \context_system::instance();

        $data = (object) [
            'description_editor' => [
                'text'   => '<p>Original content</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
        ];

        $receivedhook = null;
        $this->redirectHook(
            before_editor_content_saved::class,
            function (before_editor_content_saved $hook) use (&$receivedhook): void {
                $receivedhook = $hook;
                $hook->set_text('<p>Transformed content</p>');
            },
        );

        $options = ['maxfiles' => 0, 'trusttext' => false, 'noclean' => true];
        $result = file_postupdate_standard_editor($data, 'description', $options, $context);

        $this->assertInstanceOf(before_editor_content_saved::class, $receivedhook);
        $this->assertSame($context->id, $receivedhook->contextid);
        $this->assertNull($receivedhook->draftitemid);
        $this->assertSame(FORMAT_HTML, $receivedhook->format);

        // The transformed text should be stored on $data.
        $this->assertSame('<p>Transformed content</p>', $result->description);
    }

    /**
     * Test that the hook is NOT dispatched from file_postupdate_standard_editor()
     * when $context is null (no context path).
     *
     * @covers \file_postupdate_standard_editor
     */
    public function test_hook_not_dispatched_when_context_is_null(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $this->resetAfterTest();

        $data = (object) [
            'description_editor' => [
                'text'   => '<p>Some content</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
        ];

        $dispatched = false;
        $this->redirectHook(
            before_editor_content_saved::class,
            function (before_editor_content_saved $hook) use (&$dispatched): void {
                $dispatched = true;
            },
        );

        $options = ['maxfiles' => 0, 'trusttext' => false, 'noclean' => true];
        $result = file_postupdate_standard_editor($data, 'description', $options, null);

        $this->assertFalse($dispatched, 'Hook should not be dispatched when context is null');
        $this->assertSame('<p>Some content</p>', $result->description);
    }
}

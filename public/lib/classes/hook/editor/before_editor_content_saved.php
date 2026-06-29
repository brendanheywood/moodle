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
 * Hook dispatched just before editor content is persisted to the database.
 *
 * This hook fires after the standard draft-file URL rewriting has already taken place
 * (draft URLs have been converted to @@PLUGINFILE@@ tokens), giving subscribers a
 * chance to perform additional content transformations or security checks before the
 * final value is stored.
 *
 * Typical use cases:
 *  - Rewriting additional URL patterns at save time (similar to file_rewrite_urls_to_pluginfile).
 *  - Scanning editor content for potentially malicious markup such as injected JavaScript.
 *
 * Subscribers may call {@see set_text()} to replace the content that will be stored.
 * To reject the content entirely, subscribers should throw an appropriate exception.
 *
 * This hook is dispatched from two locations:
 *  1. {@see file_save_draft_area_files()} – when the editor has an associated draft file area.
 *     In this case $draftitemid is set but $format may be null.
 *  2. {@see file_postupdate_standard_editor()} – when no file area is used (maxfiles == 0).
 *     In this case $format is set but $draftitemid is null.
 *
 * @package    core
 * @copyright  2026 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\tags('editor', 'files')]
#[\core\attribute\label('Allows plugins to transform or validate editor content before it is saved to the database')]
final class before_editor_content_saved {
    /**
     * Constructor.
     *
     * @param string      $text        The editor content after standard URL rewriting, ready to be stored.
     * @param int         $contextid   The context id for the file/content area.
     * @param string|null $component   The component owning the file area (e.g. 'mod_forum'), or null when no file area.
     * @param string|null $filearea    The file area name (e.g. 'post'), or null when no file area.
     * @param int|null    $itemid      The item id within the file area, or null when no file area.
     * @param int|null    $draftitemid The draft item id used during the edit session, or null when no file area.
     * @param int|null    $format      The text format constant (FORMAT_HTML, etc.), or null when not available.
     */
    public function __construct(
        /** @var string The editor content to be stored */
        private string $text,
        /** @var int The context id */
        public readonly int $contextid,
        /** @var string|null The component owning the content area */
        public readonly ?string $component,
        /** @var string|null The file area name */
        public readonly ?string $filearea,
        /** @var int|null The item id within the file area */
        public readonly ?int $itemid,
        /** @var int|null The draft file area item id from the edit session */
        public readonly ?int $draftitemid,
        /** @var int|null The text format (FORMAT_HTML etc.), null when not available */
        public readonly ?int $format = null,
    ) {
    }

    /**
     * Returns the current editor content that will be stored.
     *
     * @return string
     */
    public function get_text(): string {
        return $this->text;
    }

    /**
     * Replaces the editor content that will be stored.
     *
     * @param string $text The transformed content.
     */
    public function set_text(string $text): void {
        $this->text = $text;
    }
}

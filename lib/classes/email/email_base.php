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

namespace core\email;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * A renderable email base
 *
 * Please see http://docs.moodle.org/en/Developement:How_Moodle_outputs_HTML
 *
 * @package core
 * @category output
 * @copyright 2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class email_base implements \renderable {

    /**
     * @var stdClass A user object with at least fields all columns specified
     * in $fields array constant set.
     */
    public $from;

    /**
     * @var stdClass A user object with at least fields all columns specified
     * in $fields array constant set.
     */
    public $to;

    /**
     * @var array Arbitrary data needed by the email
     */
    public $data;

    /**
     * All emails
     *
     * @param stdclass $from
     * @param stdclass $to
     * @param stdclass $to
     */
    public function __construct(stdClass $from, stdClass $to, array $data) {
        $this->from = $from;
        $this->to = $to;
        $this->data = $data;
    }

    /**
     * A convenience function to dual render in text and html
     */
    public function render_text_and_html($component, $subtype = null) {

        // First setup a blank page. The theme used by the email should be
        // either the same as the site's theme, or it should be themed based
        // on the recipient of the email and not the theme of the person
        // logged in who might be triggering the email.
        $page = new \moodle_page();
        $page->set_url('/');
        $page->set_context(\context_system::instance());

        // TODO now optionally set the theme based on the recipient
        // TODO AND the email is in the language of the recipient!!??!!

        $text_renderer = $page->get_renderer($component, $subtype, RENDERER_TARGET_TEXTEMAIL);
        $html_renderer = $page->get_renderer($component, $subtype, RENDERER_TARGET_HTMLEMAIL);

        return (object)[
            'text' => $this->render($text_renderer),
            'html' => $this->render($html_renderer),
        ];
    }

}


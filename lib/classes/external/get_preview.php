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


namespace core\external;
defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_api;
use core_external\external_value;
use core\form\urlpreview;
require_once($CFG->dirroot . '/lib/classes/url/urlpreview.php');
require_once($CFG->libdir . '/classes/url/unfurler.php');
require_once($CFG->dirroot . '/lib/externallib.php');

/**
 * Implementation of web service core_get_preview
 *
 * @package    core
 * @copyright  2024 Team "the Z" <https://github.com/Catalyst-QUT-2023>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_preview extends external_api {

    /**
     * Describes the parameters for core_url_get_preview
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            ['url' => new external_value(PARAM_URL, 'The URL to get data')]);
    }

    /**
     * Implementation of web service core_url_get_preview
     *
     * @param string $url
     */
    public static function execute($url) {
        // Parameter validation.
        ['url' => $url] = self::validate_parameters(
            self::execute_parameters(),
            ['url' => $url]
        );

        // From web services we don't call require_login(), but rather validate_context.
        $context = \context_system::instance();
        self::validate_context($context);

        global $DB;

        // Check if the linted data for this URL is already in the database.
        $sql = "SELECT * FROM {urlpreview} WHERE " . $DB->sql_compare_text('url') . " = ?";
        $linteddata = $DB->get_record_sql($sql, [$url]);

        if (!$linteddata) {
            // If not in the database, lint the URL.
            $unfurler = new \unfurl($url);
            $renderedoutput = $unfurler->render_unfurl_metadata();

            // Save the linted data to the database using the persistent class.
            $record = new urlpreview();
            $record->set('url', $url);
            $record->set('title', $unfurler->title);
            $record->set('type', $unfurler->type);
            $record->set('imageurl', $unfurler->image);
            $record->set('sitename', $unfurler->sitename);
            $record->set('description', $unfurler->description);
            $record->set('timecreated', time());
            $record->set('timemodified', time());
            $record->set('lastpreviewed', time());
            $record->create();
        } else {
            // Update the 'lastpreviewed' timestamp only if it's been more than an hour.
            $currenttime = time();
            if (($currenttime - $linteddata->lastpreviewed) > (1 * HOURSECS)) {
                $linteddata->lastpreviewed = $currenttime;
                $DB->update_record('urlpreview', $linteddata);
            }
            $renderedoutput = \unfurl::format_preview_data($linteddata);
        }

        return $renderedoutput;
    }

    /**
     * Describe the return structure for tool_urlpreview_get_preview
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'HTML Output');
    }
}

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
 * Abstract class for common properties of scheduled_task and adhoc_task.
 *
 * @package    core
 * @category   check
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\check;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for checks
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check {

    /**
     * This is used to notify if a check does not apply.
     *
     * In most cases if a check doesn't apply a check object shouldn't be made.
     * This state exists for when you always want visibilty of the check itself.
     * Can be useful for a check which depends on another check and it helps
     * focus on the other check which matters more.
     */
    const NA = 0;

    /**
     * Ideally all checks should be ok.
     */
    const OK = 1;

    /**
     * This is used to show info for a check.
     *
     * This is equivalent to OK but could be used for alerting to potential
     * future warnings such as a deprecation in a service.
     */
    const INFO = 2;

    /**
     * This means we could not determine the state.
     *
     * An example might be an expensive check done via cron, and it has never run.
     * It would be prudent to consider an unknown check as a warning or error.
     */
    const UNKNOWN = 3;

    /**
     * Warnings
     *
     * Something is not ideal and should be addressed, eg usability or the
     * speed of the site may be affected, but it may self heal (eg a load spike)
     */
    const WARNING = 4;

    /**
     * This is used to notify if a check failed.
     *
     * Something is wrong with a component and a feature is not working.
     */
    const ERROR = 5;

    /**
     * This is used to notify if a check is a major critical issue.
     *
     * An error which is affecting everyone in a major way.
     */
    const CRITICAL = 6;

    /**
     * @var $string $component - The component this task belongs to.
     */
    protected $component = 'core';

    /**
     * @var string $id - Shold be unique identifier across all instances of all checks.
     */
    protected $id = '';

    /**
     * @var string $name - Name for the check, should be the same regardless of state.
     */
    protected $name = '';

    /**
     * @var int $state - state
     */
    protected $state = self::UNKNOWN;

    /**
     * @var int $checkruntime - When this check was last run. If 0 assume now.
     */
    protected $checktime = 0;

    /**
     * @var string summary - should be roughly 1 line of plain text and may change depending on the state.
     */
    protected $summary = '';

    /**
     * @var string details about check.
     *
     * This may be a large amount of preformatted html text, possibly describing all the
     * different states and actions to address them.
     */
    protected $details = '';

    /**
     * @var action_link an optional link to a place to address the check.
     */
    protected $link = null;

    /**
     * Get the frankenstyle component name
     * @return string
     */
    public function get_component() : string {
        return $this->component;
    }

    /**
     * Get the check id
     * @return string must be unique for it's component
     */
    public function get_id() : string {
        return $this->id;
    }

    /**
     * Get the short check name
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Get the check status
     * @return int one of the consts eg check::OK
     */
    public function get_status() : int {
        return $this->status;
    }

    /**
     * Summary of the check
     * @return string formatted html
     */
    public function get_summary() : string {
        return $this->summary;
    }

    /**
     * Get the check detailed info
     * @return string formatted html
     */
    public function get_details() : string {
        return $this->details;
    }

    /**
     * Get the url of details
     * @return moodle_url|null could be internal or external (eg moodle wiki)
     */
    public function get_link() : \moodle_url {
        return null;
    }

    /**
     * A link to a place to action this
     *
     * @return action_link|null
     */
    public function get_action_link() {
        return $this->link;
    }

}


<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Sheduled ad hoc task bridge class.
 *
 * @package    core
 * @category   task
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright  Catalyst IT, 2025
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

use core\check\check;

/**
 * Sheduled adhoc task bridge class.
 */
class adhoc_task_scheduled extends scheduled_task {
    /** @var adhoc_task $adhoctaskclassname - The adhoc task class. */
    private string $adhoctaskclassname;

    /** @var string $name - The adhoc task name. */
    private string $name;

    /** @var int $nextstoptime - When this task will stop */
    private int $nextstoptime = 0;

    /** @var bool $disabled - When true, the task will never run. */
    private bool $disabled = false;
    /**
     * Constructor.
     * @param adhoc_task $adhoctask The adhoc class.
     */
    public function __construct(adhoc_task $adhoctask) {
        $this->adhoctaskclassname = get_class($adhoctask);
        $this->name = $adhoctask->get_name();
    }

    /**
     * Retrieves the name of the scheduled task.
     * @return string The name of the scheduled task.
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * We should never call this method.
     */
    public function execute() {
        throw new \coding_exception('execute() should not be called on this task.');
    }

    /**
     * Retrieves the adhoc class.
     * @return adhoc_task Returns the class property value.
     */
    public function get_classname() {
        return $this->adhoctaskclassname;
    }

    /**
     * Get the next stop time for this task.
     * @return int timestamp
     */
    public function get_next_stop_time() {
        return $this->nextstoptime;
    }

    /**
     * Set the next stop time for this task.
     * @param int $nextstoptime
     */
    public function set_next_stop_time($nextstoptime) {
        $this->nextstoptime = $nextstoptime;
    }

    /**
     * Determine if this task is using its default configuration changed from the default. Returns true
     * if it is and false otherwise. Does not rely on the customised field.
     *
     * @return bool
     */
    public function has_default_configuration(): bool {
        return false;
    }

    /**
     * Setter for $disabled.
     *
     * @param bool $disabled
     */
    public function set_disabled($disabled) {
        $this->disabled = (bool)$disabled;
    }

    /**
     * Getter for $disabled.
     * @return bool
     */
    public function get_disabled() {
        return $this->disabled;
    }

    /**
     * Returns true if this task is enabled and allowed to run.
     * Consistent with scheduled_task::is_enabled().
     * @return bool
     */
    public function is_enabled(): bool {
        return !$this->get_disabled();
    }

    /**
     * Disable the task — it will not run until re-enabled.
     */
    public function disable(): void {
        $this->set_disabled(true);
        \core\task\manager::configure_scheduled_adhoc_task($this);
    }

    /**
     * Enable the task so it can run according to its schedule.
     */
    public function enable(): void {
        $this->set_disabled(false);
        \core\task\manager::configure_scheduled_adhoc_task($this);
    }

    /**
     * Get a list of max valid values according to the given field and stored expression.
     * Examples:
     *
     * Range type '10-20' [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20] > return [20]:
     *
     * Single value '10' return [10]:
     *
     * Value list '10,20,30' return [10, 20, 30]:
     *
     * Step value '* / 10' return [0, 10, 20, 30, 40, 50, 60]:
     *
     * @param string $field The field identifier.
     * @return array
     */
    private function get_max_valid(string $field): array {
        switch ($field) {
            case self::FIELD_MINUTE:
                $fieldvalue = $this->get_minute();
                $min = self::MINUTEMIN;
                $max = self::MINUTEMAX;
                break;
            case self::FIELD_HOUR:
                $fieldvalue = $this->get_hour();
                $min = self::HOURMIN;
                $max = self::HOURMAX;
                break;
            case self::FIELD_DAY:
                $fieldvalue = $this->get_day();
                $min = self::DAYMIN;
                $max = self::DAYMAX;
                break;
            case self::FIELD_MONTH:
                $fieldvalue = $this->get_month();
                $min = self::MONTHMIN;
                $max = self::MONTHMAX;
                break;
            case self::FIELD_DAYOFWEEK:
                $fieldvalue = $this->get_day_of_week();
                $min = self::DAYOFWEEKMIN;
                $max = self::DAYOFWEEKMAXINPUT;
                break;
            default:
                throw new \coding_exception("Field '$field' is not a valid crontab identifier.");
        }
        $result = $this->eval_cron_field($fieldvalue, $min, $max);
        $matches = [];
        preg_match_all('@[0-9]+|\*|,|/|-@', $fieldvalue, $matches);
        if (in_array('-', $matches[0])) {
            // For range type, return max valid number.
            return [max($result)];
        } else {
            // For value type, step type, value list type, return non-modified valid numbers.
            return $result;
        }
    }

    /**
     * Return the max valid scheduled time — i.e. the last minute of the window in which this
     * task can run, starting from $nextruntime.
     *
     * The "window" is the contiguous block of time during which the task can run before it needs
     * rescheduling. Its end is determined by the most significant constrained (non-wildcard) field:
     *
     *  *     | * | * | * | *    every minute, no window limit, returns 0.
     *  *     | * | * | * | 11   all of November, last minute is Nov 30 23:59.
     *  30-50 | * | * | * | *    :30–:50 each hour; window ends at :50.
     *  30    | * | * | * | *    only minute :30; window is just that one slot.
     *
     * Continuous specs (wildcards '*' and ranges 'X-Y') extend the window through their full span.
     * Discrete specs (single values, comma lists, step expressions) pin the window to the current slot.
     *
     * @param int $nextruntime The task's next run time.
     * @return int End of the run window as a Unix timestamp, or 0 if there is no limit.
     */
    public function get_max_next_scheduled_time(int $nextruntime): int {
        // All wildcards means the task runs every minute — no meaningful window end.
        $fields = [
            $this->get_minute(),
            $this->get_hour(),
            $this->get_day(),
            $this->get_day_of_week(),
            $this->get_month(),
        ];
        if (!array_diff($fields, ['*'])) {
            return 0;
        }

        \core_date::set_default_server_timezone();

        $minute = (int)date('i', $nextruntime);
        $hour   = (int)date('H', $nextruntime);
        $day    = (int)date('j', $nextruntime);
        $month  = (int)date('n', $nextruntime);
        $year   = (int)date('Y', $nextruntime);

        // For a continuous spec ('*' or 'X-Y'), the window spans the full valid range so we use
        // its maximum. For a discrete spec (single value, list, step), each occurrence is its own
        // window so we keep the current value from $nextruntime.
        $maxval = function(string $spec, int $current, array $validvalues): int {
            preg_match_all('@[0-9]+|\*|,|/|-@', $spec, $m);
            if ($spec === '*' || in_array('-', $m[0])) {
                return max($validvalues);
            }
            return $current;
        };

        $maxminute = $maxval($this->get_minute(), $minute, $this->get_max_valid(self::FIELD_MINUTE));
        $maxhour   = $maxval($this->get_hour(),   $hour,   $this->get_max_valid(self::FIELD_HOUR));
        $maxmonth  = $maxval($this->get_month(),  $month,  $this->get_max_valid(self::FIELD_MONTH));

        // Max day must be clamped to the actual days in the target month (e.g. November has 30).
        $rawmaxday      = $maxval($this->get_day(), $day, $this->get_max_valid(self::FIELD_DAY));
        $daysinthismonth = (int)date('t', mktime(0, 0, 0, $maxmonth, 1, $year));
        $maxday         = min($rawmaxday, $daysinthismonth);

        // Build the end-of-window timestamp, preserving more-significant fields from $nextruntime
        // for any that are unconstrained (i.e. remain at the level above the outermost constraint).
        if ($this->get_month() !== '*') {
            return mktime($maxhour, $maxminute, 59, $maxmonth, $maxday, $year) + 1;
        }

        if ($this->get_day() !== '*' || $this->get_day_of_week() !== '*') {
            // For day-of-week constraints we keep the current day since computing the last matching
            // weekday in an arbitrary range is complex; day-of-month ranges use their max.
            $targetday = $this->get_day() !== '*' ? $maxday : $day;
            return mktime($maxhour, $maxminute, 59, $month, $targetday, $year) + 1;
        }

        if ($this->get_hour() !== '*') {
            return mktime($maxhour, $maxminute, 59, $month, $day, $year) + 1;
        }

        return mktime($hour, $maxminute, 59, $month, $day, $year) + 1;
    }
}

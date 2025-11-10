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

    /** @var int $disabled - Is this task disabled in cron? */
    private bool $disabled = true;
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
     * Disable the task.
     */
    public function disable(): void {
        $this->set_disabled(true);
        \core\task\manager::configure_scheduled_adhoc_task($this);
    }

    /**
     * Enable the task.
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
     * Return the max valid scheduled time.
     * Examples:
     *
     * '| * | * | * | * | * |'
     * If the task runs at 9:00, the maximum valid time is 9:00.
     *
     * Range type '| 10-20 | * | * | * | * |':
     * If the task runs at 9:10, the maximum valid time is 9:20.
     * At 9:21, the task will be rescheduled to run at 10:10.
     *
     * Single value '| 10 | * | * | * | * |':
     * If the task runs at 9:10, the maximum valid time is 9:10.
     * At 9:11, the task will be rescheduled to run at 10:10.
     *
     * Value list '| 10,20,30 | * | * | * | * |':
     * If the task runs at 9:10, the maximum valid time is 9:10.
     * At 9:11, the task will be rescheduled to run at 9:20.
     *
     * Step value '| * / 10 | * | * | * | * |':
     * If the task runs at 9:10, the maximum valid time is 9:10.
     * At 9:11, the task will be rescheduled to run at 9:20.
     *
     * @param int $nextruntime The tasks next run time.
     */
    public function get_max_next_scheduled_time(int $nextruntime): int {
        // If the task is scheduled to run at every minute, return never tun time as it don't have max next scheduled time.
        // TODO: REMOVE.
        // $fields = [
        //     $this->get_minute(),
        //     $this->get_hour(),
        //     $this->get_day(),
        //     $this->get_day_of_week(),
        //     $this->get_month(),
        // ];
        // if (!array_diff($fields, ['*'])) {
        //     return self::NEVER_RUN_TIME;
        // }

        $validminutes = $this->get_max_valid(self::FIELD_MINUTE);
        $validhours = $this->get_max_valid(self::FIELD_HOUR);
        $validdays = $this->get_max_valid(self::FIELD_DAY);
        $validdaysofweek = $this->get_max_valid(self::FIELD_DAYOFWEEK);
        $validmonths = $this->get_max_valid(self::FIELD_MONTH);

        // Subtract 60 seconds from the task next run time, to get current range max valid time.
        return $this->get_next_scheduled_time_inner(
            $nextruntime - 60,
            $validminutes,
            $validhours,
            $validdays,
            $validdaysofweek,
            $validmonths
        );
    }
}

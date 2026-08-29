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

namespace core_admin\setting\setting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the configtext admin setting class.
 *
 * @package    core_admin
 * @category   test
 * @copyright  2026 Brendan Heywood <brendan@catalyst-au.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(configtext::class)]
final class configtext_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Data provider for test_output_html_formats_numeric_default.
     *
     * @return array
     */
    public static function output_html_formats_numeric_default_provider(): array {
        return [
            'PARAM_INT large number' => [
                PARAM_INT,
                100000,
                '100,000',
            ],
            'PARAM_INT small number' => [
                PARAM_INT,
                42,
                '42',
            ],
            'PARAM_INT zero' => [
                PARAM_INT,
                0,
                '0',
            ],
            'PARAM_FLOAT with decimals' => [
                PARAM_FLOAT,
                1234.5,
                '1,234.5',
            ],
            'PARAM_FLOAT whole number strips zeros' => [
                PARAM_FLOAT,
                1000.0,
                '1,000',
            ],
            'PARAM_RAW not formatted' => [
                PARAM_RAW,
                100000,
                '100000',
            ],
        ];
    }

    /**
     * Test that numeric defaults are formatted with locale-aware thousands separators
     * for PARAM_INT and PARAM_FLOAT types, and left unformatted for other types.
     *
     * @param int|string $paramtype The parameter type constant.
     * @param mixed $default The default setting value.
     * @param string $expected The expected formatted default in the output HTML.
     */
    #[DataProvider('output_html_formats_numeric_default_provider')]
    public function test_output_html_formats_numeric_default(int|string $paramtype, mixed $default, string $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Use a locale where thousands separator is a comma and decimal point is a dot.
        $setting = new configtext(
            'report_sql/enumrowceiling',
            'Dropdown filter row ceiling',
            'Maximum rows for enum filter dropdown',
            $default,
            $paramtype,
        );

        $html = $setting->output_html('');

        $this->assertStringContainsString('Default: ' . $expected, $html);
    }
}

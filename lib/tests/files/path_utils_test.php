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
 * Contains a class providing functions used to check the allowed/blocked host/ports for curl.
 *
 * @package    core
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\files;

defined('MOODLE_INTERNAL') || exit();

/**
 * A collection of utils to help manage the various file paths
 *
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_utils_test extends \advanced_testcase {

    /**
     * Returns data for data provider containing:
     * - raw path
     * - match deeper first
     * - expected config name
     *
     * @return array
     */
    public function get_config_from_path_provider(): array {
        global $CFG;
        return [
            [$CFG->dirroot,         false,  false,  'dirroot' ],
            [$CFG->dataroot,        false,  false,  'dataroot'],
            [$CFG->tempdir,         false,  false,  'dataroot'],
            [$CFG->tempdir . '/me', false,  false,  'dataroot'],
            [$CFG->tempdir,         true,   false,  'tempdir' ],
            [$CFG->localcachedir,   false,  false,  'dataroot'],
            [$CFG->localcachedir,   true,   false,  'localcachedir'],
            ['/foo/bar',            false,  false,  ''],
            ['/foo/bar',            false,  true,   '/foo'],
            ['./foo/bar',           false,  false,  ''],
            ['./foo/bar',           false,  true,   './foo'],
        ];
    }

    /**
     * Check file to config detection
     *
     * @dataProvider get_config_from_path_provider
     * @param string $path
     * @param bool $deepest
     * @param bool $showother
     * @param string $expected
     */
    public function test_get_config_from_path(string $path, bool $deepest, bool $showother, string $expected): void {

        $config = \core\files\path_utils::get_config_from_path($path, $deepest, $showother);
        $this->assertEquals($expected, $config);
    }

    /**
     * Check file path redaction
     */
    public function test_redact_paths(): void {

        global $CFG;
        $this->resetAfterTest();

        // This defaults to the same as langotherroot.
        $CFG->langlocalroot = '/tmp/localroot';

        // This defaults to empty.
        $CFG->themedir = '/tmp/themedir';

        $message = <<<EOD
dirroot         {$CFG->dirroot}
dataroot        {$CFG->dataroot}
localcachedir   {$CFG->localcachedir}
cachedir        {$CFG->cachedir}
langotherroot   {$CFG->langotherroot}
langlocalroot   {$CFG->langlocalroot}
tempdir         {$CFG->tempdir}
backuptempdir   {$CFG->backuptempdir}
themedir        {$CFG->themedir}
localrequestdir {$CFG->localrequestdir}
EOD;

        $expected = <<<EOD
dirroot         [dirroot]
dataroot        [dataroot]
localcachedir   [localcachedir]
cachedir        [cachedir]
langotherroot   [langotherroot]
langlocalroot   [langlocalroot]
tempdir         [tempdir]
backuptempdir   [backuptempdir]
themedir        [themedir]
localrequestdir [localrequestdir]
EOD;
        $redacted = \core\files\path_utils::redact_paths($message);
        $this->assertEquals($expected, $redacted);
    }
}

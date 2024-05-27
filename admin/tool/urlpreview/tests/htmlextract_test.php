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

namespace tool_urlpreview;

defined('MOODLE_INTERNAL') || die();

global $CFG;
use core\url\unfurl;

require_once($CFG->libdir . '/classes/url/unfurler.php');

/**
 * URLPreview HTML Extract unit tests
 *
 * @package    tool_urlpreview
 * @copyright  2024 Team "the Z" <https://github.com/Catalyst-QUT-2023>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class htmlextract_test extends \advanced_testcase {
    /**
     * @var \unfurl The Unfurl instance used for extracting HTML metadata.
     */
    private $unfurler;

    protected function setUp(): void {
        parent::setUp();
        $this->unfurler = new \unfurl('http://example.xyz'); // Pass a URL to the constructor.
    }

    /**
     * Unit test for \tool_urlpreview
     *
     * @dataProvider providetestfiles
     * @param string $file name of the test fixture html file
     * @param string $expectedtitle expected title
     * @param string $expectedsitename expected site name
     * @param string $expectedimage expected image
     * @param string $expecteddescription expected description
     * @param string $expectedcanonicalurl expected canonical URL
     * @param string $expectedtype expected type
     * @return void
     */
    public function test_extract_html_metadata(
        string $file,
        string $expectedtitle,
        string $expectedsitename,
        string $expectedimage,
        string $expecteddescription,
        string $expectedcanonicalurl,
        string $expectedtype
    ): void {
        $responseurl = file_get_contents(__DIR__ . "/fixtures/$file");

        // Extract metadata from the HTML file.
        $this->unfurler->extract_html_metadata('http://example.com', $responseurl); // Pass the URL to the method.

        // Check the extracted metadata.
        $this->assertEquals($expectedtitle, $this->unfurler->title);
        $this->assertEquals($expectedsitename, $this->unfurler->sitename);
        $this->assertEquals($expectedimage, $this->unfurler->image);
        $this->assertEquals($expecteddescription, $this->unfurler->description);
        $this->assertEquals($expectedcanonicalurl, $this->unfurler->canonicalurl);
        $this->assertEquals($expectedtype, $this->unfurler->type);
    }
    /**
     * Provides data for test function
     * @return array
     */
    public static function providetestfiles(): array {
        return [
            [
                '01-no_metadata.html',
                'Basic html with no metadata',
                '',
                '',
                '',
                '',
                '',
            ],

            [
                '02-basic_metadata.html',
                'Basic html with og image metadata',
                '',
                'https://picsum.photos/600/300',
                '',
                '',
                '',
            ],

            [
                '03-no_title.html',
                '',
                '',
                '',
                '',
                '',
                '',
            ],

            [
                '04-only_h1.html',
                'Title 1',
                '',
                '',
                '',
                '',
                '',
            ],

            [
                '05-h1_h2.html',
                'Title 1',
                '',
                '',
                '',
                '',
                '',
            ],

            [
                '06-no_title_only_h2.html',
                'Title 2',
                '',
                '',
                '',
                '',
                '',
            ],

            [
                '07-malicious.html',
                'alert(\'Malicious script executed for og:title!\');',
                'alert(\'Malicious script executed for og:site_name!\');',
                'https://picsum.photos/600/300',
                'alert(\'Malicious script executed for og:description!\');',
                '',
                'scriptalertMaliciousscriptexecutedforogtypescript',
            ],

            [
                '08-long_strings.html',
                'TitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitle',
                '',
                'https://picsum.photos/600/300',
                '',
                '',
                '',
            ],

            [
                '09-longer_strings.html',
                'TitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitle'
                . 'TitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitleTitle',
                'NameNameNameNameNameNameNameNameNameNameNameNameNameNameNameNameNameName'
                . 'NameNameNameNameNameNameNameNameNameNameNameName',
                'http://example.comImageImageImageImageImageImageImageImageImageImageI'
                . 'mageImageImageImageImageImageImageImageImageImageImageImageImageImageImageImageImageImageImage',
                'DescriptionDescriptionDescriptionDescriptionDescriptionDescription'
                . 'DescriptionDescriptionDescriptionDescriptionDescriptionDescription',
                '',
                'PagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePage',
            ],

            [
                '10-longer_strings_whitespace.html',
                'Title TitleTitle TitleTitle TitleTitle TitleTitle TitleTitle TitleTitle '
                . 'TitleTitle TitleTitle TitleTitle TitleTitle TitleTitle TitleTitle TitleTitle Title',
                'Name NameName NameName NameName NameName NameName NameName NameName '
                . 'NameName NameName NameName NameName NameName NameName NameName Name',
                'http://example.comImage ImageImage ImageImage ImageImage ImageImage ImageImage '
                . 'ImageImage ImageImage ImageImage ImageImage ImageImage ImageImage ImageImage ImageImage ImageImage',
                'Description DescriptionDescription DescriptionDescription DescriptionDescription '
                . 'DescriptionDescription DescriptionDescription Description',
                '',
                'PagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePagePage',
            ],

            [
                '11-404.html',
                '404 Not Found',
                'Example Site',
                'https://example.com/image.jpg',
                'Example description.',
                'https://example.com',
                'website',
            ],

            [
                '12-missing_title.html',
                '',
                'Example Site',
                'https://example.com/image.jpg',
                'Example description.',
                'https://missingtitle.com',
                'article',
            ],

            [
                '13-missing_sitename.html',
                'Missing Sitename',
                '',
                'https://example.com/image.jpg',
                'Example description.',
                'https://example.com',
                'website',
            ],

            [
                '14-missing_image.html',
                'Missing Image',
                'Example Site',
                '',
                'Example description.',
                'https://example.com',
                'website',
            ],

            [
                '15-missing_description.html',
                'Missing Description',
                'Example Site',
                'https://example.com/image.jpg',
                '',
                'https://example.com',
                'website',
            ],

            [
                '16-missing_canonicalurl.html',
                'Missing URL',
                'Example Site',
                'https://example.com/image.jpg',
                'Example description.',
                '',
                'website',
            ],

            [
                '17-missing_type.html',
                'Missing Type',
                'Example Site',
                'https://example.com/image.jpg',
                'Example description.',
                'https://example.com',
                '',
            ],
        ];
    }
}

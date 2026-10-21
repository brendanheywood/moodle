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

namespace core;

/**
 * A set of tests for some of the gd functionality within Moodle.
 *
 * @package    core
 * @category   test
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('generate_image_thumbnail')]
#[\PHPUnit\Framework\Attributes\CoversFunction('generate_image_thumbnail_from_string')]
#[\PHPUnit\Framework\Attributes\CoversFunction('process_new_icon')]
#[\PHPUnit\Framework\Attributes\CoversFunction('resize_image')]
#[\PHPUnit\Framework\Attributes\CoversFunction('resize_image_from_image')]
final class gdlib_test extends \advanced_testcase {
    private $fixturepath = null;

    public function setUp(): void {
        parent::setUp();
        $this->fixturepath = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
    }

    public function test_generate_image_thumbnail(): void {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        // Test with meaningless data.

        // Now use a fixture.
        $pngpath = $this->fixturepath . 'gd-logo.png';
        $pngthumb = generate_image_thumbnail($pngpath, 24, 24);
        $this->assertTrue(is_string($pngthumb));

        // And check that the generated image was of the correct proportions and mimetype.
        $imageinfo = getimagesizefromstring($pngthumb);
        $this->assertEquals(24, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    public function test_generate_image_thumbnail_from_string(): void {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        // Test with meaningless data.

        // First empty values.
        $this->assertFalse(generate_image_thumbnail_from_string('', 24, 24));
        $this->assertFalse(generate_image_thumbnail_from_string('invalid', 0, 24));
        $this->assertFalse(generate_image_thumbnail_from_string('invalid', 24, 0));

        // Now an invalid string.
        $this->assertFalse(generate_image_thumbnail_from_string('invalid', 24, 24));

        // Now use a fixture.
        $pngpath = $this->fixturepath . 'gd-logo.png';
        $pngdata = file_get_contents($pngpath);
        $pngthumb = generate_image_thumbnail_from_string($pngdata, 24, 24);
        $this->assertTrue(is_string($pngthumb));

        // And check that the generated image was of the correct proportions and mimetype.
        $imageinfo = getimagesizefromstring($pngthumb);
        $this->assertEquals(24, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    public function test_resize_image(): void {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        $pngpath = $this->fixturepath . 'gd-logo.png';

        // Preferred height.
        $newpng = resize_image($pngpath, null, 24);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(90, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width.
        $newpng = resize_image($pngpath, 100, null);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(100, $imageinfo[0]);
        $this->assertEquals(27, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width and height.
        $newpng = resize_image($pngpath, 50, 50);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(50, $imageinfo[0]);
        $this->assertEquals(13, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    public function test_resize_image_from_image(): void {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        $pngpath = $this->fixturepath . 'gd-logo.png';
        $origimageinfo = getimagesize($pngpath);
        $imagecontent = file_get_contents($pngpath);

        // Preferred height.
        $imageresource = imagecreatefromstring($imagecontent);
        $newpng = resize_image_from_image($imageresource, $origimageinfo, null, 24);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(90, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width.
        $imageresource = imagecreatefromstring($imagecontent);
        $newpng = resize_image_from_image($imageresource, $origimageinfo, 100, null);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(100, $imageinfo[0]);
        $this->assertEquals(27, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width and height.
        $imageresource = imagecreatefromstring($imagecontent);
        $newpng = resize_image_from_image($imageresource, $origimageinfo, 50, 50);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(50, $imageinfo[0]);
        $this->assertEquals(13, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    /**
     * Create an opaque red image and process it as an icon.
     *
     * @param int $width Source image width.
     * @param int $height Source image height.
     * @param bool $fit Whether to fit the image instead of cropping it.
     * @param bool $jpeg Whether the source image is a JPEG rather than a PNG.
     * @return array Icon dimensions and pixel values keyed by icon name.
     */
    private function process_image(int $width, int $height, bool $fit, bool $jpeg = false): array {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        $source = make_request_directory() . '/source';
        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 0, 0));
        $jpeg ? imagejpeg($im, $source) : imagepng($im, $source);

        $context = \context_system::instance();
        $this->assertNotFalse(process_new_icon($context, 'core', 'testicon', 1, $source, false, $fit));

        $fs = get_file_storage();
        $result = [];
        foreach (['f1' => 100, 'f2' => 35, 'f3' => 512] as $name => $size) {
            $file = $fs->get_file($context->id, 'core', 'testicon', 1, '/', $name . '.png');
            $icon = imagecreatefromstring($file->get_content());
            $half = intdiv($size, 2);
            $result[$name] = [
                'width' => imagesx($icon),
                'height' => imagesy($icon),
                // Alpha is 0 for opaque and 127 for fully transparent.
                'centre' => (imagecolorat($icon, $half, $half) >> 24) & 0x7F,
                'top' => (imagecolorat($icon, $half, 1) >> 24) & 0x7F,
                'bottom' => (imagecolorat($icon, $half, $size - 2) >> 24) & 0x7F,
                'left' => (imagecolorat($icon, 1, $half) >> 24) & 0x7F,
                'right' => (imagecolorat($icon, $size - 2, $half) >> 24) & 0x7F,
            ];
        }
        return $result;
    }

    /**
     * By default the icon is a centred square crop, so it is fully opaque.
     */
    public function test_process_new_icon_crops_by_default(): void {
        $this->resetAfterTest();

        foreach ($this->process_image(200, 100, false) as $icon) {
            $this->assertEquals($icon['width'], $icon['height']);
            foreach (['centre', 'top', 'bottom', 'left', 'right'] as $point) {
                $this->assertEquals(0, $icon[$point]);
            }
        }
    }

    /**
     * With fit, a wide image stays square and is padded with transparency above and below.
     */
    public function test_process_new_icon_fit_wide(): void {
        $this->resetAfterTest();

        $icons = $this->process_image(200, 100, true);
        $this->assertEquals([100, 35, 512], [$icons['f1']['width'], $icons['f2']['width'], $icons['f3']['width']]);
        foreach ($icons as $icon) {
            $this->assertEquals($icon['width'], $icon['height']);
            $this->assertEquals(0, $icon['centre']);
            $this->assertEquals(0, $icon['left']);
            $this->assertEquals(0, $icon['right']);
            $this->assertEquals(127, $icon['top']);
            $this->assertEquals(127, $icon['bottom']);
        }
    }

    /**
     * With fit, a tall image stays square and is padded with transparency left and right.
     */
    public function test_process_new_icon_fit_tall(): void {
        $this->resetAfterTest();

        foreach ($this->process_image(100, 200, true) as $icon) {
            $this->assertEquals($icon['width'], $icon['height']);
            $this->assertEquals(0, $icon['centre']);
            $this->assertEquals(0, $icon['top']);
            $this->assertEquals(0, $icon['bottom']);
            $this->assertEquals(127, $icon['left']);
            $this->assertEquals(127, $icon['right']);
        }
    }

    /**
     * A JPEG source is saved as a PNG when fitted, so the padding can be transparent.
     */
    public function test_process_new_icon_fit_jpeg_to_png(): void {
        $this->resetAfterTest();

        foreach ($this->process_image(200, 100, true, true) as $icon) {
            $this->assertEquals(0, $icon['centre']);
            $this->assertEquals(127, $icon['top']);
        }
    }
}

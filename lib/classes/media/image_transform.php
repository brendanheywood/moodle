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
 * Image transform
 *
 * @package    core
 * @category   media
 * @copyright  2023 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\media;

defined('MOODLE_INTERNAL') || die();

/**
 * Image transformer
 *
 * @package    core
 * @category   media
 * @copyright  2023 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_transform {

    public $file;

    public $transform;

    public function __construct($file, $transform) {
        $this->file = $file;
        $this->transform = $transform;
    }

    public function get_final_image() {

        // This creates an image which is a white box.
        $image = imagecreate(100,100);
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        // Output the transform into the image so we know its being passed along.
        imagestring($image, 1, 5, 5,  $this->transform, $white);

        // Save the image into a string so we can return it instead of serving it.
        ob_start();
        imagepng($image);
        $data = ob_get_clean();

        imagedestroy($image);
        return $data;
    }

}

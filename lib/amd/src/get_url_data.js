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
 * This script fetches and returns the url preview data and writes the
 * data to a passed HMTL ID
 *
 * @module     core/get_url_data
 * @copyright  2024 Team "the Z" <https://github.com/Catalyst-QUT-2023>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

export const getPreview = (
    url
) => fetchMany([{
    methodname: 'core_url_get_preview',
    args: {
        url
    },
}])[0];

export const getPreviewTemplate = async(url) => {
    const response = await getPreview(url);
    document.getElementById("previewField").innerHTML = response;
};
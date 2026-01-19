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
 * IP Lookup utility functions
 *
 * @package    core
 * @subpackage iplookup
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @copyright  2026 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns location information
 *
 * If possible returns names in the language passed in.
 *
 * @param string $ip
 * @param string $lang
 * @return array
 */
function iplookup_find_location(string $ip, string $lang = '') {
    global $CFG;

    if (empty($lang)) {
        $lang = current_language();
    }

    $info = [
        'city' => null,
        'region' => null,
        'country' => null,
        'longitude' => null,
        'latitude' => null,
        'error' => null,
        'note' => '',
    ];

    if (!\core\ip_utils::is_ip_address($ip)) {
        $info['error'] = get_string('invalidipformat', 'error');
        return $info;
    }
    if (!ip_is_public($ip)) {
        $info['error'] = get_string('iplookupprivate', 'error');
        return $info;
    }

    if (!empty($CFG->geoip2file) && file_exists($CFG->geoip2file)) {
        $reader = new GeoIp2\Database\Reader($CFG->geoip2file);

        try {
            $record = $reader->city($ip);
        } catch (Exception $e) {
            $info['error'] = $e->getMessage();
            return $info;
        }

        /**
         * Where possible use a localized place names
         *
         * @param stdClass $record
         * @param string $field
         * @param string $lang which language to extract
         * @return string local name
         */
        function get_translated_name($record, string $field, string $lang): string {
            if (!empty($record->{$field}->names[$lang])) {
                return $record->{$field}->names[$lang];
            }
            return $record->{$field}->name;
        }

        if (empty($record)) {
            $info['error'] = get_string('iplookupfailed', 'error', $ip);
            return $info;
        }

        $info['city'] = get_translated_name($record, 'city', $lang);
        $info['region'] = get_translated_name($record, 'mostSpecificSubdivision', $lang);
        $info['country'] = get_translated_name($record, 'country', $lang);

        $countrycode = $record->country->isoCode;
        $info['countrycode'] = $countrycode;

        $countries = get_string_manager()->get_list_of_countries(true);
        if (isset($countries[$countrycode])) {
            // Prefer our localized country names.
            $info['country'] = $countries[$countrycode];
        }

        $info['longitude'] = $record->location->longitude;
        $info['latitude'] = $record->location->latitude;
        $info['note'] = get_string('iplookupmaxmindnote', 'admin');

        return $info;
    } else if (!empty($CFG->geopluginapikey)) {
        require_once($CFG->libdir . '/filelib.php');

        if (strpos($ip, ':') !== false) {
            // IPv6 is not supported by geoplugin.net.
            $info['error'] = get_string('invalidipformat', 'error');
            return $info;
        }

        $requesturl = new moodle_url('https://api.geoplugin.com', ['ip' => $ip, 'auth' => $CFG->geopluginapikey]);
        $response = download_file_content($requesturl->out(false), null, null, true);
        if ($response->response_code != 200) {
            $info['error'] = get_string('cannotgeoplugin', 'error');
            return $info;
        }
        $ipdata = json_decode($response->results, true);
        if (!is_array($ipdata)) {
            $info['error'] = get_string('cannotgeoplugin', 'error');
            return $info;
        }
        $info['latitude'] = (float)$ipdata['geoplugin_latitude'];
        $info['longitude'] = (float)$ipdata['geoplugin_longitude'];

        $info['city'] = s($ipdata['geoplugin_city']);
        $info['region'] = s($ipdata['geoplugin_region']);
        $info['accuracyRadius'] = (int)$ipdata['geoplugin_locationAccuracyRadius']; // Unit is in Miles.

        $countrycode = $ipdata['geoplugin_countryCode'];
        $info['countrycode'] = $countrycode;
        $countries = get_string_manager()->get_list_of_countries(true);
        if (isset($countries[$countrycode])) {
            // Prefer our localized country names.
            $info['country'] = $countries[$countrycode];
        } else {
            $info['country'] = s($ipdata['geoplugin_countryName']);
        }

        $info['note'] = get_string('iplookupgeoplugin', 'admin');
        return $info;
    }

    $info['error'] = get_string('iplookupfailed', 'error', $ip);
    return $info;
}

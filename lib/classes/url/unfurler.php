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
 * Receives Open Graph protocol metadata (a.k.a social media metadata) from link
 *
 * @package    core
 * @copyright  2024 Team "the Z" https://github.com/Catalyst-QUT-2023 based on code from 2021 Jon Green <jgreen01@stanford.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

/**
 * Class unfurl
 * This class is responsible for unfurling URLs to extract and format metadata.
 */
class unfurl {
    /**
     * The title of the URL.
     * @var string
     */
    public $title = '';
    /**
     * The sitename of the URL.
     * @var string
     */
    public $sitename = '';
    /**
     * The image of the URL.
     * @var string
     */
    public $image = '';
    /**
     * The description of the URL.
     * @var string
     */
    public $description = '';
    /**
     * The canonical URL.
     * @var string
     */
    public $canonicalurl = '';
    /**
     * The type of the URL.
     * @var string
     */
    public $type = '';
    /**
     * Indicates whether Noog metadata is enabled.
     * @var bool
     */
    public $noogmetadata = true;
    /**
     * The response data.
     * @var string
     */
    private $response;

    /**
     * Unfurler contructor.
     * @param string $url
     */
    public function __construct($url) {

        // Initialize cURL session.
        $curl = new curl();
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 5,
        ];
        $this->response = $curl->get($url, $options);

        $curlresponse = $this->response;

        $errorno = $curl->get_errno();
        if ($errorno === CURLE_OPERATION_TIMEOUTED) {
            echo "Timeout occurred while fetching URL: $url";
            return;
        }
        $this->extract_html_metadata($url, $curlresponse);
    }
    /**
     * Extract metadata from url.
     * @param string $url The URL from which to extract metadata.
     * @param string $responseurl The URL to respond to with the extracted metadata.
     */
    public function extract_html_metadata($url, $responseurl) {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $responseurl);
        $metataglist = $doc->getElementsByTagName('meta');
        // Default html title.
        $titleelement = $doc->getElementsByTagName('title')->item(0);
        $h1element = $doc->getElementsByTagName('h1')->item(0);
        $h2element = $doc->getElementsByTagName('h2')->item(0);

        if ($titleelement) {
            $this->title = $titleelement->textContent;
        } else if ($h1element) {
            $this->title = $h1element->textContent;
        } else if ($h2element) {
            $this->title = $h2element->textContent;
        }

        // Iterate through meta tags.
        foreach ($metataglist as $metatag) {
            $propertyattribute = strtolower(s($metatag->getAttribute('property')));
            if (
                !empty($propertyattribute) &&
                preg_match ('/^og:\w/i', $propertyattribute) === 1
            ) {
                $this->noogmetadata = false;
                break;
            }
        }

        if ($this->noogmetadata) {
            return;
        }

        foreach ($metataglist as $metatag) {
            $propertyattribute = strtolower(s($metatag->getAttribute('property')));
            $contentattribute = $metatag->getAttribute('content');
            if (
                !empty($propertyattribute) &&
                !empty($contentattribute) &&
                preg_match ('/^og:\w/i', $propertyattribute) === 1
            ) {
                $sanitizedcontent = clean_param($contentattribute, PARAM_TEXT);
                switch ($propertyattribute) {
                    case 'og:title':
                        $this->title = $sanitizedcontent;
                        break;
                    case 'og:site_name':
                        $this->sitename = $sanitizedcontent;
                        break;
                    case 'og:image':
                        $imageurlparts = parse_url($contentattribute);
                        if (empty($imageurlparts['host']) && !empty($imageurlparts['path'])) {
                            $urlparts = parse_url($url);
                            $this->image = $urlparts['scheme'].'://'.$urlparts['host'].$imageurlparts['path'];
                        } else {
                            $sanitizedcontent = clean_param($contentattribute, PARAM_URL);
                            $this->image = $sanitizedcontent;
                        }
                        break;
                    case 'og:description':
                        $this->description = $sanitizedcontent;
                        break;
                    case 'og:url':
                        $sanitizedcontent = clean_param($contentattribute, PARAM_URL);
                        $this->canonicalurl = $sanitizedcontent;
                        break;
                    case 'og:type':
                        $sanitizedcontent = clean_param($contentattribute, PARAM_ALPHANUMEXT);
                        $this->type = $sanitizedcontent;
                    default:
                        break;
                }
            }
        }
    }
    /**
     * Render metadata.
     */
    public function render_unfurl_metadata() {
        global $OUTPUT;
        // Get the properties of this object as an array.
        $unfurldata = get_object_vars($this);

        // Use the render_from_template method to render Mustache template.
        return $OUTPUT->render_from_template('tool_urlpreview/metadata', $unfurldata);
    }
    /**
     * Formats url preview data by passing it to the render_from_template function.
     * @param array $data retrieved data from urlpreview table to be rendered.
     */
    public static function format_preview_data($data) {
        global $OUTPUT;

        $templatedata = [
            'noogmetadata' => empty($data->title) && empty($data->imageurl) && empty($data->sitename)
                && empty($data->description) && empty($data->type),
            'canonicalurl' => $data->url,
            'title'        => $data->title,
            'image'        => $data->imageurl,
            'sitename'     => $data->sitename,
            'description'  => $data->description,
            'type'         => $data->type,
        ];
        return $OUTPUT->render_from_template('tool_urlpreview/metadata', $templatedata);
    }
}

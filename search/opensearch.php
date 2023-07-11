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
 * Opensearch XML file for Global Search 
 *
 * @package   core_search
 * @copyright Brendan Heywood (brendan@catalyst-au.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');

// header('Content-Type: application/opensearchdescription+xml');
header('Content-Type: text/xml');

$shortname = $SITE->fullname;
$description = 'rcap';
$searchterms = (new moodle_url('/search/index.php', ['context' => 1, 'q' => '']))->out();
$searchurl = (new moodle_url('/search/index.php'))->out();
$favicon = (new moodle_url('/search/index.php'))->out();

print <<<EOF
<?xml version="1.0"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"
                       xmlns:moz="http://www.mozilla.org/2006/browser/search/">
    <ShortName>$shortname</ShortName>
    <Description>$shortname</Description>
    <Image width="16" height="16" type="image/x-icon">$favicon</Image>
    <Url type="text/html" template="$searchterms={searchTerms}"/>
    <moz:SearchForm>$searchurl</moz:SearchForm>
</OpenSearchDescription>
EOF;


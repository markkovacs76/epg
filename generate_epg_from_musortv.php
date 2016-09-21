<?php
/**
 * @author Márk Kovács <markkovacs76gmail.com>
 *
 * EPG Grabber for the site musor.tv
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

// Read configuration
$ini_array = parse_ini_file("generate_epg.ini");

$siteName   = $ini_array["siteName"];
$timeoffset = $ini_array["timeoffset"];
$output_xml = $ini_array["output_xml"];
$logfile    = $ini_array["logfile"];

$version    = "v1.0";

include('simple_html_dom.php');

function epg_log($string) {
    global $logfile;
    $logstring = date("Y-m-d H:i:s").": ".$string."\n";
    if ($logfile) {
        file_put_contents($logfile, $logstring, FILE_APPEND | LOCK_EX);
    }
    echo $logstring;    
}

function get_programs_of_channel($channelID) {
	global $xml;
	global $xml_tv;
	global $siteName;
    global $timeoffset;
	
    # 2 days are considered
	$days[] = new DateTime();
    $days[] = new DateTime('tomorrow');
    
    $no_of_programme = 0;
    
    foreach($days as $process_day) {
        
        # check today
        if ($process_day->format('Ymd')==date('Ymd')) {
            $url = $siteName . "/mai/tvmusor/" . $channelID;
        } else {
            $url = $siteName . "/napi/tvmusor/" . $channelID . "/" . $process_day->format('Y.m.d');
        }
        
        // Get links from actual date page of channel
        $html = file_get_html($url);

        $hrefs = array();

        $table=$html->find('table.content_outer',1);
        foreach($table->find('table.dailyprogentry') as $e) {	
            $td = $e->find('td.dailyprogtitleold,td.dailyprogtitle',0);		
            $hrefs[] = $td->find('a',0)->href;		    
        }
        
        foreach($hrefs as $href) {
            $url = $siteName."/".$href;

            // Get EPG info from site 
            $html = file_get_html($url);
            $infotimeyear = $html->find('span.eventinfotimeyear',0);
            $infosmallline = $html->find('div.eventinfosmallline',0)->plaintext;
            $matches = null;	
            $returnValue = preg_match('/(\\d\\d):(\\d\\d).*-.*(\\d\\d):(\\d\\d)/',$infosmallline, $matches);
            $from = $matches[1] * 100 + $matches[2];
            $to   = $matches[3] * 100 + $matches[4];

            $timestring_from = $process_day->format('Ymd').$matches[1].$matches[2]."00 ".$timeoffset;        
            if ($from > $to) {
                // Program ends after midnight
                $nextday = clone $process_day;
                $nextday->modify("+1 day");
                $timestring_to = $nextday->format('Ymd').$matches[3].$matches[4]."00 ".$timeoffset;
            } else {
                $timestring_to = $process_day->format('Ymd').$matches[3].$matches[4]."00 ".$timeoffset;    
            }

            $infotitle = $html->find('td.eventinfotitle',0);
            $infoshortdesc = $html->find('td.eventinfoshortdesc',0);
            $infolongdesc = $html->find('td.eventinfolongdesc',0);
            $infolongdesc_plain = trim(html_entity_decode($infolongdesc->plaintext));

            // Build xml to store guide

            $xml_programme = $xml->createElement("programme");
            $xml_programme->setAttribute("start",$timestring_from);
            $xml_programme->setAttribute("stop",$timestring_to);
            $xml_programme->setAttribute("channel",$channelID);

            $xml_title = $xml->createElement("title");
            $xml_title->setAttribute("lang","hu");
            $xml_titleText = $xml->createTextNode($infotitle->innertext);
            $xml_subtitle = $xml->createElement("sub-title");
            $xml_subtitleText = $xml->createTextNode($infoshortdesc->innertext);
            $xml_title->appendChild($xml_titleText);	
            $xml_subtitle->appendChild($xml_subtitleText);

            $xml_title_en = null;
            $matches = null;
            if (preg_match('/^\\(.*\\)/', $infolongdesc_plain, $matches)) {
                $description = substr($infolongdesc_plain,strlen($matches[0]));
                $xml_title_en = $xml->createElement("title");
                $xml_title_en->setAttribute("lang","en");

                $replace_chars = array("(",")");
                $title_en = str_replace($replace_chars,"",$matches[0]);
                $title_en = ucfirst(strtolower($title_en));

                $xml_titleText_en = $xml->createTextNode($title_en);
                $xml_title_en->appendChild($xml_titleText_en);
            } else {
                $description = $infolongdesc_plain;
            }		
            if (preg_match('/\w+/',$description)) {
                // Description exists
                $description = str_replace("\r\n"," ",$description);
                $description = trim($description);
                
                $xml_desc = $xml->createElement("desc");
                $xml_descText = $xml->createTextNode($description);
                $xml_desc->appendChild($xml_descText);    
                $xml_programme->appendChild($xml_desc);
            }            

            $xml_programme->appendChild($xml_title);	
            if ($xml_title_en) {
                $xml_programme->appendChild($xml_title_en);
            }
            $xml_programme->appendChild($xml_subtitle);	            	
            $xml_tv->appendChild($xml_programme);

            $no_of_programme++;

        }
        
    }
    
	return $no_of_programme;
}

 
ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)'); 

epg_log("EPG Grabber for site $siteName started ...");
$time_start = microtime(true);

// Get DOM from URL
$html = file_get_html($siteName);

// Find all channels
$channels = array();

foreach($html->find('a.channellistentry') as $e) {
    $href = $e->href;
    $code = substr($href,strrpos($href,'/')+1);
	$channelName = substr($e->innertext,0,-7);
    $channels[$code] = $channelName;
}

ksort($channels);

// Create output xml object
$xml = new DOMDocument('1.0', 'UTF-8');
// Create tv element
$xml_tv = $xml->createElement( "tv" );
// Set the attributes
$xml_tv->setAttribute("generator-info-name","musor.tv grabber by Márk Kovács, 2016-09-19, Version ".$version);
$xml_tv->setAttribute("generator-info-url","http://epg.gravi.hu");
$xml->appendChild($xml_tv);

$no_of_channels = 0;

foreach($channels as $channelID => $channelName) {
	$xml_channel = $xml->createElement( "channel" );
	$xml_channel->setAttribute("id",$channelID);
	$xml_displayName = $xml->createElement( "display-name" );
	$xml_displayName->setAttribute("lang","hu");
	$xml_displayNameText = $xml->createTextNode($channelName);
	$xml_icon = $xml->createElement( "icon" );
	$xml_icon->setAttribute("src",$siteName."/images/".strtolower($channelID)."_small.png");
	$xml_url = $xml->createElement( "url" );
	$xml_url_text = $xml->createTextNode($siteName."/mai/tvmusor/".$channelID);
	$xml_url->appendChild($xml_url_text);
	$xml_displayName->appendChild($xml_displayNameText);
	$xml_tv->appendChild($xml_channel);
	$xml_channel->appendChild($xml_displayName);
	$xml_channel->appendChild($xml_icon);
	$xml_channel->appendChild($xml_url);
	
	$no_of_channels++;
}

epg_log("Channel list updated, number of channels: ".$no_of_channels);

$channel_counter = 1;

foreach($channels as $channelID => $channelName) {
    
    //if ($channelID!="TV2") continue;   
    
	$programs_grabbed = get_programs_of_channel($channelID);
	epg_log("EPG of channel $channelID ($channel_counter/$no_of_channels) completed, number of programs found: $programs_grabbed");	
	$channel_counter++;    
}

$time_end = microtime(true);
$time = round($time_end - $time_start);
$time_mins = round($time / 60);
$time_secs = $time % 60;

$xml->formatOutput = true;
$xml->save($output_xml);
epg_log("EPG stored in XMLTV format to file ".realpath($output_xml));
epg_log("EPG Grabber for site $siteName completed, running time: $time_mins minutes, $time_secs seconds.");

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

$siteName         = $ini_array["siteName"];
$timeoffset       = $ini_array["timeoffset"];
$no_of_days       = $ini_array["no_of_days"];
$output_xml       = $ini_array["output_xml"];
$logfile          = $ini_array["logfile"];
$max_attempt      = $ini_array["max_attempt"];

if (isset($ini_array["exclude_channels"])) {
    $exclude_channels = $ini_array["exclude_channels"];
}

$version    = "v1.3";

include('simple_html_dom.php');

set_error_handler(
    create_function(
        '$severity, $message, $file, $line',
        'throw new ErrorException($message, $severity, $severity, $file, $line);'
    )
);

function epg_log($string, $delete=false) {
    global $logfile;    
    $logstring = date("Y-m-d H:i:s").": ".$string."\n";
    if ($logfile) {
        if ($delete) {
            unlink($logfile);
        }
        file_put_contents($logfile, $logstring, FILE_APPEND | LOCK_EX);
    }
    echo $logstring;    
}

function get_programs_of_channel($channelID) {
	global $xml;
	global $xml_tv;
	global $siteName;
    global $timeoffset;
	global $no_of_days;
    global $max_attempt;

    // search from today
    $process_day = new DateTime();

    $no_of_programme = 0;
    
    for ($daycount = 0; $daycount < $no_of_days; $daycount++) {
        
        // check today
        if ($process_day->format('Ymd')==date('Ymd')) {
            $url = $siteName . "/mai/tvmusor/" . $channelID;
        } else {
            $url = $siteName . "/napi/tvmusor/" . $channelID . "/" . $process_day->format('Y.m.d');
        }
                
        
        for($attempt=1;$attempt<=$max_attempt;$attempt++) {
            try {
                // Get links from actual date page of channel
                $html = file_get_html($url);                
                break;
            } 
            catch (Exception $e) {
                epg_log("WARNING: Failed to load page $url! Attempt: $attempt");
                sleep(3);
            }    
        }
        if ($attempt > $max_attempt) {
            epg_log("ERROR: Failed to load page $url! Omitting...");
            continue;
        }
        
        $hrefs = array();

        $table=$html->find('table.content_outer',1);
        foreach($table->find('table.dailyprogentry') as $e) {	
            $td = $e->find('td.dailyprogtitleold,td.dailyprogtitle',0);		
            $hrefs[] = $td->find('a',0)->href;		    
        }
            
        foreach($hrefs as $href) {
            if ($href[0] !== "/") {
                $url = $siteName . "/" . $href;
            } else {
                $url = $siteName . $href;
            }
                    
            // Get EPG info from site             
            for($attempt=1;$attempt<=$max_attempt;$attempt++) {
                try {
                    $html = file_get_html($url);
                    break;
                } 
                catch (Exception $e) {
                    epg_log("WARNING: Failed to load page $url! Attempt: $attempt");
                    sleep(3);
                }    
            }
            if ($attempt > $max_attempt) {
                epg_log("ERROR: Failed to load page $url! Omitting...");
                continue;
            }
            
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
            $xml_category = $xml->createElement("category");
            $xml_category->setAttribute("lang","hu");
            
            list($category) = explode(",",$infoshortdesc->innertext);
            $xml_categoryText = $xml->createTextNode($category);
            $xml_title->appendChild($xml_titleText);	
            $xml_category->appendChild($xml_categoryText);

            $xml_title_en = null;
            $matches = null;
            if (preg_match('/^\\(.*\\)/', $infolongdesc_plain, $matches)) {
                $description = substr($infolongdesc_plain,strlen($matches[0]));
                $xml_title_en = $xml->createElement("title");
                $xml_title_en->setAttribute("lang","en");

                $replace_chars = array("(",")");
                $title_en = str_replace($replace_chars,"",$matches[0]);
                $title_en = ucwords(strtolower($title_en));

                $xml_titleText_en = $xml->createTextNode($title_en);
                $xml_title_en->appendChild($xml_titleText_en);
            } else {
                $description = $infolongdesc_plain;
            }		
            $xml_desc = null;
            if (preg_match('/\w+/',$description)) {
                // Description exists
                $description = str_replace("\r\n"," ",$description);
                $description = trim($description);
                
                $xml_desc = $xml->createElement("desc");
                $xml_descText = $xml->createTextNode($description);
                $xml_desc->appendChild($xml_descText);    
                
            }            

            $xml_programme->appendChild($xml_title);	
            if ($xml_title_en) {
                $xml_programme->appendChild($xml_title_en);
            }
            $xml_programme->appendChild($xml_category);
            if ($xml_desc) {
                $xml_programme->appendChild($xml_desc);
            }            
            
            $xml_tv->appendChild($xml_programme);

            $no_of_programme++;

        }
        
        $process_day->modify('+1 day');
        
    }
    
	return $no_of_programme;
}

 
ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)'); 

epg_log("EPG Grabber for site $siteName started ...", true);
$time_start = microtime(true);

for($attempt=1;$attempt<=$max_attempt;$attempt++) {
    try {
        // Get DOM from URL
        $html = file_get_html($siteName);
        break;
    } 
    catch (Exception $e) {
        epg_log("WARNING: Failed to load page $siteName! Attempt: $attempt");
        sleep(3);
    }    
}
if ($attempt > $max_attempt) {
    epg_log("ERROR: Failed to load page $siteName! Exiting...");    
    exit(1);
}

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
    
    if (isset($exclude_channels) && 
        is_array($exclude_channels) &&
        in_array($channelID, $exclude_channels)) {
        epg_log("Channel $channelID ($channel_counter/$no_of_channels) excluded by configuration!");        
    } else {
        $programs_grabbed = get_programs_of_channel($channelID);
	    epg_log("EPG of channel $channelID ($channel_counter/$no_of_channels) completed, number of programs found: $programs_grabbed");	    
    }
	$channel_counter++;    
}

restore_error_handler();

$time_end = microtime(true);
$time = round($time_end - $time_start);
$time_mins = round($time / 60);
$time_secs = $time % 60;

$xml->formatOutput = true;
try {
    $xml->save($output_xml);
}
catch (Exception $e) {
    epg_log("ERROR: EPG is not stored in XMLTV format to file ".realpath($output_xml)."!");
    exit(1);
}

epg_log("EPG stored in XMLTV format to file ".realpath($output_xml));
epg_log("EPG Grabber for site $siteName completed, number of days processed: $no_of_days, running time: $time_mins minutes, $time_secs seconds.");

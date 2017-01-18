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

$siteName    = $ini_array["siteName"];
$timeoffset  = (isset($ini_array["timeoffset"])?$ini_array["timeoffset"]:date('O'));
$no_of_days  = $ini_array["no_of_days"];
$output_xml  = $ini_array["output_xml"];
$logfile     = $ini_array["logfile"];
$max_attempt = $ini_array["max_attempt"];
$user_agent  = $ini_array["user_agent"];

if (isset($ini_array["exclude_channels"])) {
    $exclude_channels = $ini_array["exclude_channels"];
}

$incremental_mode = false;
$expired_hours = 0;

if (isset($ini_array["incremental_mode"])) {
    $incremental_mode = $ini_array["incremental_mode"] && file_exists($output_xml);
    $expired_hours = $ini_array["expired_hours"];
}

$version = "v2.0";

include('simple_html_dom.php');
include('guess_category.php');

set_error_handler(create_function('$severity, $message, $file, $line', 'throw new ErrorException($message, $severity, $severity, $file, $line);'));

function epg_log($string, $delete = false)
{
    global $logfile;
    $logstring = date("Y-m-d H:i:s") . ": " . $string . "\n";
    if ($logfile) {
        if ($delete&&file_exists($logfile)) {
            unlink($logfile);
        }
        file_put_contents($logfile, $logstring, FILE_APPEND | LOCK_EX);
    }
    echo $logstring;
}

function url_exists($url)
{   
    
    
    $headers = get_headers($url, 1);
    if ($headers[0] == 'HTTP/1.1 200 OK') {
        return true;
    }
    return false;
}

function get_programs_of_channel($channelID)
{
    global $xml;
    global $xml_tv;
    global $xpath;    
    global $siteName;
    global $timeoffset;
    global $no_of_days;
    global $max_attempt;
    global $incremental_mode;
    global $ini_array;
        
    // search from today
    $process_day = new DateTime();
    
    $no_of_programme = 0;
    
    for ($daycount = 0; $daycount < $no_of_days; $daycount++) {
        
        // check today
        if ($process_day->format('Ymd') == date('Ymd')) {
            $url = $siteName . "/mai/tvmusor/" . $channelID;
        } else {
            $url = $siteName . "/napi/tvmusor/" . $channelID . "/" . $process_day->format('Y.m.d');
        }
        
        for ($attempt = 1; $attempt <= $max_attempt; $attempt++) {
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
        
        if ($daycount == 0) {
            // Look for channel icon, only in the 1st page
            $meta_image = $html->find('meta[property="og:image"]', 0);
            if ($meta_image) {
                $icon_url = $meta_image->attr["content"];
                if ($icon_url[0] !== "/") {
                    $icon_url = "/" . $icon_url;
                }
                
                $xml_channels = $xml->getElementsByTagName("channel");
                foreach ($xml_channels as $xml_channel) {
                    // Look for corresponding channel in xml
                    if ($xml_channel->getAttribute("id") === $channelID) {
                        // Channel found                        
                        if (isset($ini_array["override_icon_url"][$channelID])) {
                            // Override grabbed value to configured                             
                            $icon_url = $ini_array["override_icon_url"][$channelID];
                        }
                        // Check if url exists
                        if (url_exists($siteName . $icon_url)) {                            
                            $xml_icon = $xml->createElement("icon");
                            $xml_icon->setAttribute("src", $siteName . $icon_url);
                            $xml_channel->appendChild($xml_icon);
                        } else {
                            epg_log("WARNING: Icon url '${siteName}${icon_url}' does not exist, icon tag will not be generated!");
                        }
                        break;
                    }
                }
            }
        }
        
        $hrefs = array();
                
        $table = $html->find('table.content_outer', 1);
        foreach ($table->find('table.dailyprogentry') as $e) {
                        
            $td_title = $e->find('td.dailyprogtitleold,td.dailyprogtitlenow,td.dailyprogtitle', 0);
            $program_exists = false;
            
            if ($incremental_mode) {                
                $td_time  = $e->find('td.dailyprogtimeold,td.dailyprogtimenow,td.dailyprogtime', 0);
                $start_hm = str_replace(":","",trim($td_time->plaintext));
                $start    = $process_day->format('Ymd').$start_hm."00 ".$timeoffset;                
                
                // Search existing program in the xml
                $xml_programs_match = $xpath->query("/tv/programme[@start='$start'][@channel='$channelID']");
                if ($xml_programs_match->length > 0) {
                    $xml_titles = $xml_programs_match->item(0)->getElementsByTagName('title');                
                    foreach($xml_titles as $xml_title) {
                        if ($xml_title->hasAttribute('lang') && $xml_title->getAttribute('lang')==="hu") {
                            $program_title = trim($xml_title->textContent);                        
                            if (trim($td_title->find('a', 0)->plaintext) === $program_title) {
                                // No need to grab
                                $program_exists = true;
                            } else {
                                // Program found but the title differs -> remove existing program and grab the new one
                                $xml_tv->removeChild($xml_programs_match->item(0));
                            }
                        break;
                        }
                    }
                }    
            }
                        
            if (!$program_exists) {
                // Only new programs shall be grabbed in case of incremental mode, or all programs
                $hrefs[]  = $td_title->find('a', 0)->href;
            }            
        }

        // Grab the programs separately
        foreach($hrefs as $href) {
            
            if ($href[0] !== "/") {
                $url = $siteName . "/" . $href;
            } else {
                $url = $siteName . $href;
            }
            
            // Load program site to get EPG info
            for ($attempt = 1; $attempt <= $max_attempt; $attempt++) {
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
            
            $infotimeyear  = $html->find('span.eventinfotimeyear', 0);
            $infosmallline = $html->find('div.eventinfosmallline', 0)->plaintext;
            $matches       = null;
            $returnValue   = preg_match('/(\\d\\d):(\\d\\d).*-.*(\\d\\d):(\\d\\d)/', $infosmallline, $matches);
            $from          = $matches[1] * 100 + $matches[2];
            $to            = $matches[3] * 100 + $matches[4];
            
            $timestring_from = $process_day->format('Ymd') . $matches[1] . $matches[2] . "00 " . $timeoffset;
            if ($from > $to) {
                // Program ends after midnight
                $nextday = clone $process_day;
                $nextday->modify("+1 day");
                $timestring_to = $nextday->format('Ymd') . $matches[3] . $matches[4] . "00 " . $timeoffset;
            } else {
                $timestring_to = $process_day->format('Ymd') . $matches[3] . $matches[4] . "00 " . $timeoffset;
            }
            
            $infotitle          = $html->find('td.eventinfotitle', 0);
            $infoshortdesc      = $html->find('td.eventinfoshortdesc', 0);
            $infolongdesc       = $html->find('td.eventinfolongdesc', 0);
            $infolongdesc_plain = trim(html_entity_decode($infolongdesc->plaintext));
            
            // Build xml to store guide
            
            $xml_programme = $xml->createElement("programme");
            $xml_programme->setAttribute("start", $timestring_from);
            $xml_programme->setAttribute("stop", $timestring_to);
            $xml_programme->setAttribute("channel", $channelID);
            
            $xml_title = $xml->createElement("title");
            $xml_title->setAttribute("lang", "hu");
            $xml_titleText = $xml->createTextNode($infotitle->innertext);
            
            $xml_subTitle = $xml->createElement("sub-title");
            $xml_subTitle->setAttribute("lang", "hu");
            
            list($category) = explode(",", $infoshortdesc->innertext);
            $xml_subTitleText = $xml->createTextNode($category);
            $xml_title->appendChild($xml_titleText);
            $xml_subTitle->appendChild($xml_subTitleText);
            
            $xml_category_en = null;
            if ($category !== "") {
                $category_en = guess_genre_category($category);
                if ($category_en !== "") {
                    $xml_category_en = $xml->createElement("category");
                    $xml_category_en->setAttribute("lang", "en");
                    $xml_categoryText_en = $xml->createTextNode($category_en);
                    $xml_category_en->appendChild($xml_categoryText_en);
                }
            }
            
            $xml_title_en = null;
            $matches      = null;
            if (preg_match('/^\\(.*\\)/', $infolongdesc_plain, $matches)) {
                $description  = substr($infolongdesc_plain, strlen($matches[0]));
                $xml_title_en = $xml->createElement("title");
                $xml_title_en->setAttribute("lang", "en");

                $title_en = str_replace(array("(",")"), "", $matches[0]);
                $title_en = ucwords(strtolower($title_en));
                
                $xml_titleText_en = $xml->createTextNode($title_en);
                $xml_title_en->appendChild($xml_titleText_en);
            } else {
                $description = $infolongdesc_plain;
            }
            $xml_desc = null;
            if (preg_match('/\w+/', $description)) {
                // Description exists
                $description = str_replace(array("\r\n","\xC2\xA0"), " ", $description);
                $description = trim($description);
                
                $xml_desc     = $xml->createElement("desc");
                $xml_descText = $xml->createTextNode($description);
                $xml_desc->appendChild($xml_descText);
            }
            
            $xml_programme->appendChild($xml_title);
            if ($xml_title_en) {
                $xml_programme->appendChild($xml_title_en);
            }            
            if ($xml_subTitle) {
                $xml_programme->appendChild($xml_subTitle);
            }            
            if ($xml_category_en) {
                $xml_programme->appendChild($xml_category_en);
            }            
            if ($xml_desc) {
                $xml_programme->appendChild($xml_desc);
            }            
            
            if ($incremental_mode) {
                // Place the new tag to the correct position
                $xml_position = $xpath->query("/tv/programme[@channel='$channelID'][last()]/following-sibling::programme")->item(0);
                if ($xml_position) {
                    $xml_tv->insertBefore($xml_programme, $xml_position);
                } else {
                    // Place the new tag to the end
                    $xml_tv->appendChild($xml_programme);
                }    
            } else {
                // Place the new tag to the end
                $xml_tv->appendChild($xml_programme);
            }
            
            $no_of_programme++;            
        }
        
        $process_day->modify('+1 day');
        
    }
    
    return $no_of_programme;
}

function remove_expired_programs($channelID) {
    global $xml;
    global $xpath;
    global $now;
    global $expired_hours;
    
    $no_of_expired = 0;
    
    $xml_tv = $xpath->query("/tv")->item(0);
    $xml_programs = $xpath->query("/tv/programme[@channel='$channelID'][@start][@stop]");
    
    foreach ($xml_programs as $xml_program) {
        
        $stop = $xml_program->getAttribute("stop");
        list($stop_year, $stop_month, $stop_day, $stop_hour, $stop_min, $stop_secs, $stop_tz) = sscanf($stop,"%4s%2s%2s%2s%2s%2s %5s");
        $stop_date = new DateTime("$stop_year/$stop_month/$stop_day $stop_hour:$stop_min:$stop_secs");
        
        $diff_seconds = $now->getTimestamp() - $stop_date->getTimestamp();
        
        if ($diff_seconds >= $expired_hours*3600) {        
            $xml_tv->removeChild($xml_program);
            $no_of_expired++;
        }
        
    }
    if ($no_of_expired > 0) {
        epg_log("Expired EPGs of channel $channelID removed, number of removed programs: $no_of_expired");
    }
}

ini_set('user_agent', $user_agent);

epg_log("EPG Grabber for site $siteName started ...", true);
if ($incremental_mode) {
    epg_log("Incremental mode is set: expired programs will be removed + only new programs will be added");
}
$time_start = microtime(true);
$now = new DateTime();

for ($attempt = 1; $attempt <= $max_attempt; $attempt++) {
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

foreach ($html->find('a.channellistentry') as $e) {
    $href            = $e->href;
    $code            = substr($href, strrpos($href, '/') + 1);
    $channelName     = $e->innertext; 
    $channels[$code] = $channelName;
}

ksort($channels);

// Create output xml object
$xml = new DOMDocument('1.0', 'UTF-8');    

$xml_first_program = null;
if ($incremental_mode) {
    $xml->preserveWhiteSpace = false;
    $xml->load($output_xml);
    $xpath = new DOMXpath($xml);
    // Remove existing channel entries in incremental mode, because it will be recreated
    $xml_tv = $xpath->query("/tv")->item(0);
    if ($xml_tv !== null) {
        $xml_channels_to_remove = $xpath->query("/tv/channel");
        foreach($xml_channels_to_remove as $xml_channel) {
            $xml_tv->removeChild($xml_channel);
        }
        $xml_first_program = $xpath->query("/tv/programme[1]")->item(0);
    } else {
        $xml_tv = $xml->createElement("tv");
    }
} else {
    // Create tv element
    $xml_tv = $xml->createElement("tv");
    // Set the attributes
    $xml_tv->setAttribute("generator-info-name", "musor.tv grabber by Márk Kovács, 2016-09-19, Version " . $version);
    $xml_tv->setAttribute("generator-info-url", "http://epg.gravi.hu");
    $xml->appendChild($xml_tv);    
}

$no_of_channels = 0;

foreach ($channels as $channelID => $channelName) {    
    $xml_channel = $xml->createElement("channel");
    $xml_channel->setAttribute("id", $channelID);
    $xml_displayName = $xml->createElement("display-name");
    $xml_displayName->setAttribute("lang", "hu");
    $xml_displayNameText = $xml->createTextNode($channelName);
    $xml_url = $xml->createElement("url");
    $xml_url_text = $xml->createTextNode($siteName . "/mai/tvmusor/" . $channelID);
    $xml_url->appendChild($xml_url_text);
    $xml_displayName->appendChild($xml_displayNameText);
    if ($xml_first_program) {
        // Place channel tags on top of the file
        $xml_tv->insertBefore($xml_channel, $xml_first_program);
    } else {
        $xml_tv->appendChild($xml_channel);
    }
    
    $xml_channel->appendChild($xml_displayName);
    $xml_channel->appendChild($xml_url);
    
    $no_of_channels++;
}

epg_log("Channel list updated, number of channels: " . $no_of_channels);

$channel_counter = 1;

foreach ($channels as $channelID => $channelName) {
        
    if (isset($exclude_channels) && is_array($exclude_channels) && in_array($channelID, $exclude_channels)) {
        epg_log("Channel $channelID ($channel_counter/$no_of_channels) excluded by configuration!");
    } else {
        $programs_grabbed = get_programs_of_channel($channelID);
        epg_log("EPG grab of channel $channelID ($channel_counter/$no_of_channels) completed, number of programs found: $programs_grabbed");
    }
    if ($incremental_mode) {
        // Remove expired programs of given channel
        remove_expired_programs($channelID);
    }
    $channel_counter++;
}

restore_error_handler();

$time_end  = microtime(true);
$time      = round($time_end - $time_start);
$time_mins = round($time / 60);
$time_secs = $time % 60;

$xml->formatOutput = true;
try {
    $xml->save($output_xml);
}
catch (Exception $e) {
    epg_log("ERROR: EPG is not stored in XMLTV format to file " . realpath($output_xml) . "!");
    exit(1);
}

epg_log("EPG stored in XMLTV format to file " . realpath($output_xml));
epg_log("EPG Grabber for site $siteName completed, number of days processed: $no_of_days, running time: $time_mins minutes, $time_secs seconds.");

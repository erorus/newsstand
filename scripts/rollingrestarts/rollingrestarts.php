<?php

require_once(__DIR__.'/../../incl/incl.php');
require_once(__DIR__.'/../../incl/wowtoken-twitter.credentials.php');

define('ALERT_URL', 'http://launcher.worldofwarcraft.com/alert');

define('LAST_ALERT_PATH', __DIR__.'/rollingrestarts.txt');
define('LAST_VERSION_PATH', [
    'wow' => __DIR__.'/liveversion.txt',
    'wow_classic' => __DIR__.'/classicversion.txt',
]);
define('FONT_PATH', __DIR__.'/FRIZQT__.TTF');

define('TWITTER_ACCOUNT', 'RollingRestarts');
define('TWEET_MAX_LENGTH', 140);

function Main() {
    CheckForNewAlert();
    CheckForNewVersion('wow');
    CheckForNewVersion('wow_classic');
}

function CheckForNewVersion($product) {
    $version = GetCurrentVersion($product);
    if (!$version) {
        return;
    }

    if ($version == GetLastVersion($product)) {
        return;
    }

    file_put_contents(LAST_VERSION_PATH[$product], $version);

    DebugMessage("New $product version: $version");

    SendTweet("#WorldofWarcraft #Patch " . $version);
}

function GetCurrentVersion($product) {
    $stuff = shell_exec("echo v1/products/{$product}/versions | nc -w 10 ribbit.everynothing.net 1119");
    if (!$stuff) {
        return '';
    }

    if (preg_match('/^us\|[^\n]+/m', $stuff, $res)) {
        $parts = explode('|', $res[0], 7);
        if (isset($parts[5])) {
            return $parts[5];
        } else {
            DebugMessage("Could not find version in line: ".$res[0]."\n");
        }
    } else {
        DebugMessage("Could not find US line:\n$stuff\n");
    }
    return '';
}

function GetLastVersion($product) {
    if (file_exists(LAST_VERSION_PATH[$product])) {
        return file_get_contents(LAST_VERSION_PATH[$product]);
    }
    return '';
}

function CheckForNewAlert() {
    $alert = GetCurrentAlert();
    if (!$alert) {
        return;
    }

    if ($alert == GetLastAlert()) {
        return;
    }

    file_put_contents(LAST_ALERT_PATH, $alert);

    DebugMessage("New alert:\n$alert");

    $tweet = GetTwitterSnippet($alert);
    $pngImage = GetTextImage($alert);

    SendTweet($tweet, $pngImage);
}

function ConvertToText($html) {
    $html = strip_tags($html, '<br><p>');
    $html = preg_replace('/<\/?(\w+)[^>]*>/', '<$1>', $html);
    $html = str_replace('<p>', '<br><br>', $html);
    $html = trim(str_replace('<br>', "\n", $html));
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_XHTML);

    return $html;
}

function GetCurrentAlert() {
    $html = \Newsstand\HTTP::Get(ALERT_URL);

    return ConvertToText(preg_replace('/^SERVERALERT:\s*/', '', $html));
}

function GetLastAlert() {
    if (file_exists(LAST_ALERT_PATH)) {
        return file_get_contents(LAST_ALERT_PATH);
    }
    return '';
}

function GetTwitterSnippet($txt) {
    $txt = preg_replace('/\b[a-z]+:\/\//i', '', $txt); // remove any http:// protocols
    $txt = preg_replace('/\bwww\./i', '', $txt); // remove www.
    $txt = preg_replace('/\.(?=[A-Za-z])/', "\xE2\x80\xA4", $txt); // change any period followed by a letter to a lookalike dot, so twitter doesn't linkify

    $txt = AbbreviateForTweet($txt);
    if (mb_strlen($txt) <= TWEET_MAX_LENGTH) {
        return $txt;
    }

    $part = '';
    while (mb_strlen($txt)) {
        $res  = [];
        $thisPart = mb_ereg('^[\w\W]+?\.(?=(?:\s+[A-Z0-9])|\s*$)', $txt,
            $res) ? $res[0] : $txt; // find the first full sentence
        $txt = mb_substr($txt, mb_strlen($thisPart));

        if (!$part) {
            // first sentence
            $part = $thisPart;
            if (mb_strlen($part) > TWEET_MAX_LENGTH) { // sentence longer than max tweet length
                $pos = mb_strrpos($part, ' ',
                    TWEET_MAX_LENGTH - mb_strlen($part)); // find last space before max tweet length
                if ($pos !== false) {
                    $part = mb_substr($part, 0, $pos); // cut mid-sentence
                }
            }
        } else {
            // trying to add later sentences
            if (mb_strlen($part) + mb_strlen($thisPart) > TWEET_MAX_LENGTH) {
                break;
            }
            $part .= $thisPart;
        }
    }
    if (mb_strlen($part) > TWEET_MAX_LENGTH) {
        $part = mb_substr($part, 0, TWEET_MAX_LENGTH);
    }
    return $part;
}

function AbbreviateForTweet($msg) {
    $PregCallbackFixAM = function($m) {
        $hour = intval($m[1]);
        if ($hour < 12) {
            return $m[0].'am';
        }
        if ($hour > 12) {
            $hour -= 12;
        }
        return ''.$hour.$m[2].'pm';
    };

    $PregCallbackInitCap = function($m) {
        $func = (ord(substr(trim($m[0]), 0, 1)) <= ord('Z')) ? 'strtoupper' : 'strtolower';
        $addSpace = substr($m[1], 0, 1) == ' ' ? ' ' : '';
        return $addSpace . $func(substr(trim($m[1]), 0, 1)) . substr(trim($m[1]), 1);
    };

    $PregCallbackLowerAMPM = function($m) {
        return $m[1].strtolower($m[2]);
    };

    $PregCallbackDate = function($m) {
        return (abs(strtotime($m[0]) - time()) < 604800) ? $m[1] : $m[0];
    };

    $msg = str_replace(' ,', ',', $msg);
    $msg = str_replace('’', '\'', $msg);
    $msg = preg_replace('/ {2,}/', ' ', $msg);
    $msg = str_replace(' already', '', $msg);
    $msg = preg_replace_callback('/ ?(?:cur+ently|approximately|temporarily|as soon as possible|actively|during this time,?)( ?.)?/i', $PregCallbackInitCap, $msg);
    $msg = str_replace(' undergoing ', ' in ', $msg);
    $msg = preg_replace('/Update: /', '', $msg);
    $msg = preg_replace('/(?:the )?world of warcraft (realms|servers|players)/i', '$1', $msg);
    $msg = preg_replace('/world of warcraft(?: in-game)? shop/i', 'in-game shop', $msg);
    $msg = preg_replace('/ affecting world of warcraft\./i', '.', $msg);
    $msg = str_replace(' has been brought offline', ' is offline', $msg);
    $msg = preg_replace_callback('/We will be performing (\w)/', $PregCallbackInitCap, $msg);
    $msg = preg_replace_callback('/We(?: wi|\')ll be (\w)/', $PregCallbackInitCap, $msg);
    $msg = preg_replace_callback('/We are aware that (\w)/', $PregCallbackInitCap, $msg);
    $msg = preg_replace_callback('/We are working on an issue where (\w)/', $PregCallbackInitCap, $msg);
    $msg = preg_replace_callback('/\b((?:\w+)day), \w+ \d+(?:st|nd|rd|th),?/', $PregCallbackDate, $msg);
    $msg = preg_replace('/ (?:beginning|will begin) (on|at) /', ' from ', $msg);
    $msg = preg_replace('/,? (?:running|and will last) until /', ' until ', $msg);
    $msg = preg_replace_callback('/(\d\d?)(:\d\d)(?! ?[ap]m)/i', $PregCallbackFixAM, $msg);
    $msg = preg_replace('/0(\d:\d\d)/', '$1', $msg);
    $msg = preg_replace_callback('/(\d:\d\d) ?(am|pm)/i', $PregCallbackLowerAMPM, $msg);
    $msg = preg_replace('/ ?\((P[SD]T)\),?/', ' $1', $msg);
    $msg = preg_replace('/^We are (aware of|investigating) (?:the )?/', 'There\'s ', $msg);
    $msg = preg_replace('/Players (are experiencing|may experience) /', 'There\'s ', $msg);
    $msg = str_replace(' as a result of ', ' due to ', $msg);
    $msg = preg_replace('/internet service provider/i', 'ISP', $msg);
    $msg = preg_replace('/On (\w+day)\b/', '$1', $msg);
    $msg = preg_replace('/\bwe will perform /', 'has ', $msg);
    $msg = str_replace(' our ', ' ', $msg);
    $msg = preg_replace_callback('/\bthat is (\w+ing)\b/i', $PregCallbackInitCap, $msg);
    $msg = preg_replace_callback('/\bdue to technical issues, (\w)/i', $PregCallbackInitCap, $msg);
    $msg = preg_replace('/( and|;)? we expect the services? to be available again( at)?( around)?/', ' until', $msg);
    $msg = preg_replace('/We anticipate( the service to be available again| they\'ll be completed)( at| by)? /', 'Should be done by ', $msg);
    $msg = preg_replace('/ (the game|world of warcraft) will be unavailable for play\./i', '', $msg);
    $msg = preg_replace('/(For updates, )?Please follow @BlizzardCS(?:EU_EN)?( on Twitter)?( for( further| more)? (updates|information))?\.?/i', '', $msg);
    $msg = preg_replace('/,? which may cause some interruption in communication, inability to log in, or disconnections\./', '.', $msg);
    $msg = preg_replace('/, and( we)? hope to have a resolution soon\./', '.', $msg);
    $msg = preg_replace('/,? and( we)? are working to bring it back online\./', '.', $msg);
    $msg = preg_replace('/We are( investigating( the cause| these reports)| monitoring this situation)( and we thank you for your patience)?( and will provide( updates| further information) as( they (are|become)| it becomes) available)?\. ?/', '', $msg);
    $msg = preg_replace('/(Our realm technicians|We) are working on a resolution\. ?/', '', $msg);
    $msg = preg_replace('/(We apologi(ze|es)|Apologies) for any inconvenience( this may cause| caused)?( and thank you for your patience while this is being resolved)?\./', '', $msg);
    $msg = preg_replace('/An in-game notice[^\.]+\./', '', $msg);
    $msg = preg_replace('/Thank you for( your)? (patience|understanding)[\.!]/', '', $msg);

    $msg = preg_replace('/\bat (\d\d:)/i', '$1', $msg);

    $msg = preg_replace('/ {2,}/', ' ', $msg);
    $msg = preg_replace('/\n[ \t]+\n/', "\n\n", $msg);
    $msg = preg_replace('/\n{3,}/', "\n\n", $msg);
    return trim($msg);
}

function GetTextImage($txt, $format='png') {
    $exec = 'convert -background ' . escapeshellarg('#0b0d18') . ' -fill white -pointsize 18 -size 450x ';
    $exec .= ' -font '.escapeshellarg(FONT_PATH).' -kerning 0.5 caption:' . escapeshellarg($txt);
    $exec .= ' -bordercolor ' . escapeshellarg('#0b0d18') . ' -border 10 ' . escapeshellarg($format).':-';

    return shell_exec($exec);
}

function SendTweet($msg, $png = false) {
    global $twitterCredentials;
    if ($twitterCredentials === false) {
        return true;
    }

    $media = $png ? UploadTweetMedia($png) : false;

    $params = array();
    if ($media) {
        $params['media_ids'][] = $media;
    }
    $params['status'] = $msg;

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($twitterCredentials[TWITTER_ACCOUNT]['accessToken'], $twitterCredentials[TWITTER_ACCOUNT]['accessTokenSecret']);
    $url = 'https://api.twitter.com/1.1/statuses/update.json';

    try {
        $didWork = $oauth->fetch($url, $params, 'POST', array('Connection' => 'close'));
    } catch (OAuthException $e) {
        $didWork = false;
    }

    $ri = FixNullKeys($oauth->getLastResponseInfo());
    $r = $oauth->getLastResponse();

    if ($didWork && ($ri['http_code'] == '200')) {
        $json = json_decode($r, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            if (isset($json['id_str'])) {
                return $json['id_str'];
            }
        }
        return true;
    }
    if (isset($ri['http_code'])) {
        DebugMessage('Twitter returned HTTP code ' . $ri['http_code'], E_USER_WARNING);
    } else {
        DebugMessage('Twitter returned unknown HTTP code', E_USER_WARNING);
    }

    DebugMessage('Twitter returned: '.print_r($ri, true), E_USER_WARNING);
    DebugMessage('Twitter returned: '.print_r($r, true), E_USER_WARNING);
}

function UploadTweetMedia($data) {
    global $twitterCredentials;
    if ($twitterCredentials === false) {
        return false;
    }

    $boundary = '';
    $mimedata['media'] = "content-disposition: form-data; name=\"media\"\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: binary\r\n\r\n".$data;

    while ($boundary == '') {
        for ($x = 0; $x < 16; $x++) $boundary .= chr(rand(ord('a'),ord('z')));
        foreach ($mimedata as $d) if (strpos($d,$boundary) !== false) $boundary = '';
    }
    $mime = '';
    foreach ($mimedata as $d) $mime .= "--$boundary\r\n$d\r\n";
    $mime .= "--$boundary--\r\n";

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($twitterCredentials[TWITTER_ACCOUNT]['accessToken'], $twitterCredentials[TWITTER_ACCOUNT]['accessTokenSecret']);
    $url = 'https://upload.twitter.com/1.1/media/upload.json';

    $requestHeader = $oauth->getRequestHeader('POST',$url);

    $inHeaders = ["Authorization: $requestHeader", 'Content-Type: multipart/form-data; boundary=' . $boundary];
    $outHeaders = [];

    $ret = \Newsstand\HTTP::Post($url, $mime, $inHeaders, $outHeaders);

    if ($ret) {
        $json = json_decode($ret, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            if (isset($json['media_id_string'])) {
                return $json['media_id_string'];
            } else {
                DebugMessage('Parsed JSON response from post to twitter, no media id', E_USER_WARNING);
                DebugMessage(print_r($json, true), E_USER_WARNING);
                return false;
            }
        } else {
            DebugMessage('Non-JSON response from post to twitter', E_USER_WARNING);
            DebugMessage($ret, E_USER_WARNING);
            return false;
        }
    } else {
        DebugMessage('No/bad response from post to twitter', E_USER_WARNING);
        DebugMessage(print_r($outHeaders, true), E_USER_WARNING);
        return false;
    }
}

Main();


<?php

require_once(__DIR__.'/../../incl/incl.php');
require_once(__DIR__.'/../../incl/wowtoken-twitter.credentials.php');

define('ALERT_URL', 'http://launcher.worldofwarcraft.com/alert');
define('TWITTER_ACCOUNT', 'RollingRestarts');
define('LAST_ALERT_PATH', __DIR__.'/rollingrestarts.txt');
define('FONT_PATH', __DIR__.'/FRIZQT__.TTF');

define('TWEET_MAX_LENGTH', 120);

function Main() {
    $alert = GetCurrentAlert();
    if (!$alert) {
        return 0;
    }

    if ($alert == GetLastAlert()) {
        return 0;
    }

    file_put_contents(LAST_ALERT_PATH, $alert);

    DebugMessage("New alert:\n$alert");

    $tweet = GetTwitterSnippet($alert);
    $pngImage = GetTextImage($alert);

    SendTweet($tweet, $pngImage);

    return 0;
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
    if (preg_match('/^[\w\W]+?\.(?=(?:\s+[A-Z0-9])|\s*$)/', $txt, $res)) {
        $part = $res[0];
    } else {
        $pos = strrpos($txt, ' ', TWEET_MAX_LENGTH);
        if ($pos === false) {
            $part = $txt;
        } else {
            $part = substr($txt, 0, $pos-1);
        }
    }
    if (strlen($part) > TWEET_MAX_LENGTH) {
        $pos = strrpos($part, ' ', TWEET_MAX_LENGTH);
        if ($pos !== false) {
            $part = substr($part, 0, $pos-1);
        }
    }
    if (strlen($part) > TWEET_MAX_LENGTH) {
        $part = substr($part, 0, TWEET_MAX_LENGTH);
    }
    return $part;
}

function GetTextImage($txt, $format='png') {
    $exec = 'convert -background ' . escapeshellarg('#0b0d18') . ' -fill white -pointsize 18 -size 450x ';
    $exec .= ' -font '.escapeshellarg(FONT_PATH).' -kerning 0.5 caption:' . escapeshellarg($txt);
    $exec .= ' -bordercolor ' . escapeshellarg('#0b0d18') . ' -border 10 ' . escapeshellarg($format).':-';

    return shell_exec($exec);
}

function SendTweet($msg, $png) {
    global $twitterCredentials;
    if ($twitterCredentials === false) {
        return true;
    }

    $media = UploadTweetMedia($png);

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

    $ri = $oauth->getLastResponseInfo();
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

exit(Main());
<?php

/*
 * sudo pecl install oauth
 * and in /etc/php.d/oauth.ini add:
 * extension=oauth.so
 */

chdir(__DIR__);

require_once '../incl/incl.php';
require_once '../incl/wowtoken-twitter.credentials.php';

echo "Your message: ";
$line = trim(fgets(STDIN));

if (strlen($line) < 10) {
    echo "Line too short: ".strlen($line)." \"$line\"\n";
    exit;
}
if (strlen($line) > 160) {
    echo "Line too long: ".strlen($line)." \"$line\"\n";
    exit;
}

echo "Send \"$line\" (".strlen($line).")? Y/N: ";
$yn = strtoupper(substr(trim(fgets(STDIN)), 0, 1));
if ($yn != "Y") {
    echo "Aborting.\n";
    exit;
}

echo "Sending \"$line\"\n";
SendTweets($line);

function SendTweets($msg)
{
    $tweetId = SendTweet($msg);
    if ($tweetId && ($tweetId !== true)) {
        Retweet($tweetId, 'WoWTokenNA');
        Retweet($tweetId, 'WoWTokenEU');
    }
}

function Retweet($tweetId, $accountName) {
    global $twitterCredentials;

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($twitterCredentials[$accountName]['accessToken'], $twitterCredentials[$accountName]['accessTokenSecret']);
    $url = 'https://api.twitter.com/1.1/statuses/retweet/'.$tweetId.'.json';

    $params = ['id' => $tweetId];

    try {
        $didWork = $oauth->fetch($url, $params, 'POST', array('Connection' => 'close'));
    } catch (OAuthException $e) {
        $didWork = false;
    }

    $ri = $oauth->getLastResponseInfo();
    $r = $oauth->getLastResponse();

    if ($didWork && ($ri['http_code'] == '200')) {
        return true;
    }
    if (isset($ri['http_code'])) {
        DebugMessage('Twitter returned HTTP code ' . $ri['http_code'], E_USER_WARNING);
    } else {
        DebugMessage('Twitter returned unknown HTTP code', E_USER_WARNING);
    }

    DebugMessage('Twitter returned: '.print_r($ri, true), E_USER_WARNING);
    DebugMessage('Twitter returned: '.print_r($r, true), E_USER_WARNING);

    return false;

}

function SendTweet($msg)
{
    if ($msg == '') {
        return false;
    }

    DebugMessage('Sending tweet of ' . strlen($msg) . " chars:\n" . $msg);

    global $twitterCredentials;
    if ($twitterCredentials === false) {
        return true;
    }

    $params = array();
    $params['status'] = $msg;

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($twitterCredentials['WoWTokens']['accessToken'], $twitterCredentials['WoWTokens']['accessTokenSecret']);
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

    return false;
}

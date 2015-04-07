<?php

/*
 * sudo pecl install oauth
 * and in /etc/php.d/oauth.ini add:
 * extension=oauth.so
 */

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/wowtoken-twitter.credentials.php');

RunMeNTimes(1);
CatchKill();

define('SNAPSHOT_PATH', '/home/wowtoken/pending/');
define('TWEET_FREQUENCY_MINUTES', 360); // tweet at least every 6 hours
define('PRICE_CHANGE_THRESHOLD', 0.2);

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

$resultCodes = [
    1 => 'Success',
    2 => 'Disabled',
    3 => 'Other Error',
    4 => 'None For Sale',
    5 => 'Too Many Tokens',
    6 => 'No',
    8 => 'Auctionable Token Owned',
    9 => 'Trial Restricted',
];

$timeZones = [
    'US' => 'America/New_York',
    'EU' => 'Europe/Paris',
];

$loopStart = time();
$loops = 0;
$gotData = [];
while ((!$caughtKill) && (time() < ($loopStart + 60))) {
    heartbeat();
    if (!($region = NextDataFile())) {
        break;
    }
    if ($region !== true) {
        $gotData[] = $region;
    }
    if ($loops++ > 30) {
        break;
    }
}
if ($gotData || (isset($argv[1]) && $argv[1] == 'build')) {
    $gotData = array_unique($gotData);
    if (!$gotData) {
        $gotData = ['US','EU'];
    }
    BuildIncludes($gotData);
    SendTweets($gotData);
    DebugMessage('Done! Started ' . TimeDiff($startTime));
}

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $gotFile = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)-(US|EU)\.lua$/', $fileName, $res)) {
            if (($handle = fopen(SNAPSHOT_PATH . $fileName, 'rb')) === false) {
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);
                continue;
            }

            if (feof($handle)) {
                fclose($handle);
                unlink(SNAPSHOT_PATH . $fileName);
                continue;
            }

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile) {
        return false;
    }

    $snapshot = intval($res[1], 10);
    $region = $res[2];

    DebugMessage(
        "Region $region data file from " . TimeDiff(
            $snapshot, array(
                'parts'     => 2,
                'precision' => 'second'
            )
        )
    );
    $lua = LuaDecode(fread($handle, filesize(SNAPSHOT_PATH . $fileName)), true);

    ftruncate($handle, 0);
    fclose($handle);
    unlink(SNAPSHOT_PATH . $fileName);

    if (!$lua) {
        DebugMessage("Region $region $snapshot data file corrupted!", E_USER_WARNING);
        return true;
    }

    return ParseTokenData($region, $snapshot, $lua);
}

function ParseTokenData($region, $snapshot, &$lua)
{
    global $db;

    if (!isset($lua['now']) || !isset($lua['region'])) {
        DebugMessage("Region $region $snapshot data file does not have snapshot or region!", E_USER_WARNING);
        return false;
    }
    $snapshotString = Date('Y-m-d H:i:s', $lua['now']);
    foreach (['selltime', 'market', 'result', 'guaranteed'] as $col) {
        if (!isset($lua[$col])) {
            $lua[$col] = null;
        }
    }

    $sql = 'replace into tblWowToken (`region`, `when`, `marketgold`, `timeleft`, `guaranteedgold`, `result`) values (?, ?, floor(?/10000), ?, floor(?/10000), ?)';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssiiii',
        $lua['region'],
        $snapshotString,
        $lua['market'],
        $lua['selltime'],
        $lua['guaranteed'],
        $lua['result']
    );
    $stmt->execute();
    $stmt->close();

    return $lua['region'];
}

function LuaDecode($rawLua) {
    $tr = [];
    $c = preg_match_all('/\["([^"]+)"\] = ("[^"]+"|\d+),/', $rawLua, $res);
    if (!$c) {
        return false;
    }
    for ($x = 0; $x < $c; $x++) {
        $tr[$res[1][$x]] = trim($res[2][$x],'"');
    }

    return $tr;
}

function BuildIncludes($regions)
{
    global $db;

    $resultCodes = [
        1 => 'Success',
        2 => 'Disabled',
        3 => 'Other Error',
        4 => 'None For Sale',
        5 => 'Too Many Tokens',
        6 => 'No',
        8 => 'Auctionable Token Owned',
        9 => 'Trial Restricted',
    ];

    $timeZones = [
        'US' => 'America/New_York',
        'EU' => 'Europe/Paris',
    ];

    $htmlFormat = <<<EOF
<div class="table-wrapper">
    <table>
        <tbody>
        <tr>
            <td>Buy Price</td>
            <td>##BUY##g</td>
        </tr>
        <tr>
            <td>Guaranteed Sell Price </td>
            <td>##SELL##g</td>
        </tr>
        <tr>
            <td>Estimated Time to Sell</td>
            <td>##TIMETOSELL##</td>
        </tr>
        <tr>
            <td>API Result</td>
            <td>##RESULT##</td>
        </tr>
        <tr>
            <td>Updated</td>
            <td>##UPDATED##</td>
        </tr>
        </tbody>
    </table>
</div>
EOF;

    foreach ($regions as $region) {
        $fileRegion = strtolower($region);
        if ($fileRegion == 'us') {
            $fileRegion = 'na';
        }
        $filenm = __DIR__.'/../wowtoken/'.$fileRegion.'.incl.html';

        $sql = 'select * from tblWowToken w where region = ? and `when` = (select max(w2.`when`) from tblWowToken w2 where w2.region = ?)';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $region, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $tokenData = DBMapArray($result, null);
        $tokenData = array_pop($tokenData);
        $stmt->close();

        $d = new DateTime('now', timezone_open($timeZones[$region]));
        $d->setTimestamp(strtotime($tokenData['when']));

        $replacements = [
            'BUY' => number_format($tokenData['marketgold']),
            'SELL' => number_format($tokenData['guaranteedgold']),
            'TIMETOSELL' => $tokenData['timeleft'],
            'RESULT' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
            'UPDATED' => $d->format('M jS, Y g:ia T'),
        ];

        $html = preg_replace_callback('/##([a-zA-Z0-9]+)##/', function ($m) use ($replacements) {
                if (isset($replacements[$m[1]])) {
                    return $replacements[$m[1]];
                }
                return $m[0];
            }, $htmlFormat);

        file_put_contents($filenm, $html);
    }
}

function SendTweets($regions)
{
    global $db;
    global $resultCodes, $timeZones;

    foreach ($regions as $region) {
        $fileRegion = strtolower($region);
        if ($fileRegion == 'us') {
            $fileRegion = 'na';
        }
        $filenm = __DIR__.'/wowtoken_cache/'.$fileRegion.'.tweets.json';

        $lastTweetData = [];
        if (file_exists($filenm)) {
            $lastTweetData = json_decode(file_get_contents($filenm), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $lastTweetData = [];
            }
        }

        $sql = 'select * from tblWowToken w where region = ? and `when` = (select max(w2.`when`) from tblWowToken w2 where w2.region = ?)';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $region, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $tokenData = DBMapArray($result, null);
        $tokenData = array_pop($tokenData);
        $stmt->close();

        $d = new DateTime('now', timezone_open($timeZones[$region]));
        $d->setTimestamp(strtotime($tokenData['when']));

        $tweetData = [
            'timestamp' => strtotime($tokenData['when']),
            'record' => $tokenData,
            'formatted' => [
                'BUY' => number_format($tokenData['marketgold']),
                'SELL' => number_format($tokenData['guaranteedgold']),
                'TIMETOSELL' => $tokenData['timeleft'],
                'RESULT' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
                'UPDATED' => $d->format('M jS, Y g:ia T'),
            ],
        ];

        $needTweet = !isset($lastTweetData['timestamp']); // no last tweet data
        if (!$needTweet) {
            $needTweet |= ($lastTweetData['timestamp'] < ($tweetData['timestamp'] - TWEET_FREQUENCY_MINUTES * 60 + 5)) && ($tweetData['record']['result'] == 1); // tweet at least every X minutes when result is good
            $needTweet |= $lastTweetData['record']['result'] != $tweetData['record']['result']; // result changed
            $needTweet |= $lastTweetData['record']['marketgold'] * (1 + PRICE_CHANGE_THRESHOLD) < $tweetData['record']['marketgold']; // market price went up over X percent
            $needTweet |= $lastTweetData['record']['marketgold'] * (1 - PRICE_CHANGE_THRESHOLD) > $tweetData['record']['marketgold']; // market price went down over X percent
            $needTweet |= $lastTweetData['record']['guaranteedgold'] * (1 + PRICE_CHANGE_THRESHOLD) < $tweetData['record']['guaranteedgold']; // guaranteed sell price went up over X percent
            $needTweet |= $lastTweetData['record']['guaranteedgold'] * (1 - PRICE_CHANGE_THRESHOLD) > $tweetData['record']['guaranteedgold']; // guaranteed sell price went down over X percent
        }

        if ($needTweet) {
            if (SendTweet(strtoupper($fileRegion), $tweetData)) {
                file_put_contents($filenm, json_encode($tweetData));
            }
        }
    }
}

function SendTweet($region, $tweetData)
{
    $msg = "$region Region \nBuy: " . $tweetData['formatted']['BUY'] . "g, \nSell: " . $tweetData['formatted']['SELL'] . "g, \nSells in " . $tweetData['formatted']['TIMETOSELL'] . '.';
    if ($tweetData['record']['result'] != 1) {
        $msg .= " \n" . $tweetData['formatted']['RESULT'] . ".";
    }
    if ($tweetData['timestamp'] < (time() - 30 * 60)) { // show timestamp if older than 30 mins
        $msg .= " \nFrom " . TimeDiff($tweetData['timestamp'], ['parts' => 2, 'precision' => 'minute']) . '.';
    }

    if (strlen($msg) < 120) {
        $msg .= " \n#WoWToken";
    }

    DebugMessage('Sending tweet of ' . strlen($msg) . " chars:\n" . $msg);

    global $twitterCredentials;

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($twitterCredentials['accessToken'], $twitterCredentials['accessTokenSecret']);
    $url = 'https://api.twitter.com/1.1/statuses/update.json';

    if ($msg == '') {
        return false;
    }

    $params = array();
    $params['status'] = $msg;

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

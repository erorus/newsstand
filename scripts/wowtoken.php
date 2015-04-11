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
define('PRICE_CHANGE_THRESHOLD', 0.02); // was 0.2, for 20% change required. 0 means tweet every change

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

$timeLeftCodes = [
    'Short' => 'less than 30 minutes',
    'Medium' => '30 minutes to 2 hours',
    'Long' => '2 to 12 hours',
    'Very Long' => 'over 12 hours',
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
$forceBuild = (isset($argv[1]) && $argv[1] == 'build');
if ($gotData || $forceBuild) {
    BuildIncludes(array_keys($timeZones));
    SendTweets($forceBuild ? array_keys($timeZones) : array_unique($gotData));
    DebugMessage('Done! Started ' . TimeDiff($startTime));
}

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $gotFile = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)-(US|EU)\.lua$/', $fileName, $res)) {
            if (filesize(SNAPSHOT_PATH . $fileName) == 0) {
                continue;
            }

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
    global $resultCodes, $timeZones, $timeLeftCodes;

    $blankImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    $htmlFormat = <<<EOF
                <table class="results">
                    <tr>
                        <td>Buy Price</td>
                        <td>##buy##g</td>
                    </tr>
                    <tr>
                        <td>Time to Sell</td>
                        <td>##timeToSell##</td>
                    </tr>
                    <tr>
                        <td>API Result</td>
                        <td>##result##</td>
                    </tr>
                    <tr>
                        <td>Updated</td>
                        <td>##updated##</td>
                    </tr>
                </table>
EOF;

    $json = [];
    $historyJson = [];

    foreach ($regions as $region) {
        $fileRegion = strtoupper($region);
        if ($fileRegion == 'US') {
            $fileRegion = 'NA';
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

        $sparkUrl = GetChartURL($region);
        if (!$sparkUrl) {
            $sparkUrl = $blankImage;
        }

        $historyJson[$fileRegion] = BuildHistoryJson($region);

        $json[$fileRegion] = [
            'timestamp' => strtotime($tokenData['when']),
            'raw' => [
                'buy' => $tokenData['marketgold'],
                'timeToSell' => $tokenData['timeleft'],
                'result' => $tokenData['result'],
                'updated' => strtotime($tokenData['when']),
            ],
            'formatted' => [
                'buy' => number_format($tokenData['marketgold']),
                'timeToSell' => isset($timeLeftCodes[$tokenData['timeleft']]) ? $timeLeftCodes[$tokenData['timeleft']] : $tokenData['timeleft'],
                'result' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
                'updated' => $d->format('M jS, Y g:ia T'),
                'sparkurl' => $sparkUrl,
            ],
        ];

        $replacements = $json[$fileRegion]['formatted'];

        $html = preg_replace_callback('/##([a-zA-Z0-9]+)##/', function ($m) use ($replacements) {
                if (isset($replacements[$m[1]])) {
                    return $replacements[$m[1]];
                }
                return $m[0];
            }, $htmlFormat);

        file_put_contents($filenm, $html);
    }

    file_put_contents(__DIR__.'/../wowtoken/snapshot.json', json_encode($json, JSON_NUMERIC_CHECK));
    file_put_contents(__DIR__.'/../wowtoken/history.json', json_encode($historyJson, JSON_NUMERIC_CHECK));
}

function BuildHistoryJson($region) {
    global $db;

    $sql = 'select unix_timestamp(`when`) `dt`, `marketgold` `buy`, `timeleft`+0 `time` from tblWowToken where region = ? and `result` = 1 order by `when` asc';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenData = [];
    while ($row = $result->fetch_row()) {
        $tokenData[] = $row;
    }
    $result->close();
    $stmt->close();

    return $tokenData;
}

function SendTweets($regions)
{
    global $db;
    global $resultCodes, $timeZones, $timeLeftCodes;

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

        $sql = 'select * from tblWowToken w where region = ? order by `when` desc limit 2';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $bothTokenData = DBMapArray($result, null);
        $tokenData = array_shift($bothTokenData);
        $prevTokenData = count($bothTokenData) ? array_shift($bothTokenData) : [];
        $stmt->close();

        $d = new DateTime('now', timezone_open($timeZones[$region]));
        $d->setTimestamp(strtotime($tokenData['when']));

        $tweetData = [
            'timestamp' => strtotime($tokenData['when']),
            'direction' => 0,
            'record' => $tokenData,
            'formatted' => [
                'BUY' => number_format($tokenData['marketgold']),
                'SELL' => number_format($tokenData['guaranteedgold']),
                'TIMETOSELL' => isset($timeLeftCodes[$tokenData['timeleft']]) ? $timeLeftCodes[$tokenData['timeleft']] : $tokenData['timeleft'],
                'RESULT' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
                'UPDATED' => $d->format('M jS, Y g:ia T'),
            ],
        ];

        $needTweet = false;
        $direction = 0;

        if (isset($lastTweetData['direction'])) {
            $lastAmt = $lastTweetData['record']['marketgold'];
            if ($lastAmt > $tokenData['marketgold']) {
                $direction = -1;
            } elseif ($lastAmt < $tokenData['marketgold']) {
                $direction = 1;
            }
            if ($direction != 0) { // some change happened
                if ($lastAmt) {
                    $tweetData['formatted']['BUYCHANGEPERCENT'] = ($direction == 1 ? '+' : '') . round(($tokenData['marketgold'] / $lastAmt - 1) * 100, 2).'%';
                    $tweetData['formatted']['BUYCHANGEAMOUNT'] = ($direction == 1 ? '+' : '') . number_format($tokenData['marketgold'] - $lastAmt);
                }
                switch (abs($lastTweetData['direction'])) {
                    case 0: // change happened after unknown direction
                        $tweetData['direction'] = $direction; // sets to +1 or -1
                        break;
                    case 1: // this change after a recent direction change
                        if (($lastTweetData['direction'] > 0) == ($direction > 0)) { // this change in same direction as before
                            $tweetData['direction'] = $lastTweetData['direction'] + $direction; // sets to +2 or -2
                            $needTweet = true; // this is a new confirmed, consistent direction
                            break;
                        }
                    // fall through, change back to other direction, possible flipflop
                    default:
                        if (($lastTweetData['direction'] > 0) != ($direction > 0)) { // this change is in different direction than before
                            $tweetData['direction'] = $direction; // sets to +1 or -1
                        }
                        break;
                }
            }
        }

        $needTweet |= !isset($lastTweetData['timestamp']); // no last tweet data
        if (!$needTweet) {
            $needTweet |= ($lastTweetData['timestamp'] < ($tweetData['timestamp'] - TWEET_FREQUENCY_MINUTES * 60 + 5)) && ($tweetData['record']['result'] == 1); // tweet at least every X minutes when result is good
            $needTweet |= $lastTweetData['record']['result'] != $tweetData['record']['result']; // result changed
            $needTweet |= $lastTweetData['record']['marketgold'] * (1 + PRICE_CHANGE_THRESHOLD) < $tweetData['record']['marketgold']; // market price went up over X percent
            $needTweet |= $lastTweetData['record']['marketgold'] * (1 - PRICE_CHANGE_THRESHOLD) > $tweetData['record']['marketgold']; // market price went down over X percent

            //$needTweet |= $lastTweetData['record']['guaranteedgold'] * (1 + PRICE_CHANGE_THRESHOLD) < $tweetData['record']['guaranteedgold']; // guaranteed sell price went up over X percent
            //$needTweet |= $lastTweetData['record']['guaranteedgold'] * (1 - PRICE_CHANGE_THRESHOLD) > $tweetData['record']['guaranteedgold']; // guaranteed sell price went down over X percent
        }

        $changePct = (isset($prevTokenData['marketgold']) && $prevTokenData['marketgold']) ? round(($tokenData['marketgold'] / $prevTokenData['marketgold'] - 1) * 10000) : 0;
        if (($direction != 0) && ($changePct != 0) && (abs($changePct) != 100)) { // change happened this snapshot, and not by 1%, possible turnaround
            $needTweet = true;
            $tweetData['formatted']['TURNAROUND'] = 'Possible '.($direction > 0 ? 'maximum' : 'minimum').'.';
        }

        if (!$needTweet) {
            continue;
        }

        if (SendTweet(strtoupper($fileRegion), $tweetData, GetChartURL($region))) {
            file_put_contents($filenm, json_encode($tweetData));
        }
    }
}

function SendTweet($region, $tweetData, $chartUrl)
{
    $msg = "#WoW$region #WoWToken: " . $tweetData['formatted']['BUY'] . "g, sells in " . $tweetData['formatted']['TIMETOSELL'] . '.';
    if ($tweetData['timestamp'] < (time() - 30 * 60)) { // show timestamp if older than 30 mins
        $msg .= " From " . TimeDiff($tweetData['timestamp'], ['parts' => 2, 'precision' => 'minute']) . '.';
    } else {
        if ($tweetData['record']['result'] != 1) {
            $msg .= " " . $tweetData['formatted']['RESULT'] . ".";
        } else {
            if (isset($tweetData['formatted']['BUYCHANGEAMOUNT']) && ($tweetData['formatted']['BUYCHANGEAMOUNT'] != '0')) {
                $msg .= " Change: ".$tweetData['formatted']['BUYCHANGEAMOUNT'].'g';
                if (isset($tweetData['formatted']['BUYCHANGEPERCENT'])) {
                    $msg .= ' ('.$tweetData['formatted']['BUYCHANGEPERCENT'].')';
                }
                $msg .= '.';
            }
            if (isset($tweetData['formatted']['TURNAROUND'])) {
                $msg .= ' '.$tweetData['formatted']['TURNAROUND'];
            }
        }
    }

    if ($msg == '') {
        return false;
    }

    DebugMessage('Sending tweet of ' . strlen($msg) . " chars:\n" . $msg);

    global $twitterCredentials;
    if ($twitterCredentials === false) {
        return true;
    }

    $media = false;
    if ($chartUrl) {
        $media = UploadTweetMedia($chartUrl);
    }

    $params = array();
    if ($media) {
        $params['media_ids'][] = $media;
    }
    $params['status'] = $msg;

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($twitterCredentials['accessToken'], $twitterCredentials['accessTokenSecret']);
    $url = 'https://api.twitter.com/1.1/statuses/update.json';

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

function GetChartURL($region) {
    global $db;

    static $cache = [];
    if (isset($cache[$region])) {
        return $cache[$region];
    }

    $sql = <<<EOF
SELECT 96 - floor((unix_timestamp() - unix_timestamp(`when`)) / 900) x, marketgold y
FROM `tblWowToken`
WHERE region = ?
and result = 1
and `when` >= timestampadd(hour, -24, now())
EOF;
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $sparkData = DBMapArray($result, null);
    $stmt->close();

    $cache[$region] = EncodeChartData($sparkData);
    if ($cache[$region]) {
        $cache[$region] = 'https://chart.googleapis.com/chart?chs=600x280&cht=lxy&chco=0000FF&chm=B,CCCCFF99,0,0,0|v,9999FF,0,,1&chg=100,25,5,0&chxt=x,y&chf=c,s,FFFFFF&chma=8,8,8,8' . $cache[$region];
    }

    return $cache[$region];

}

function EncodeChartData($xy) {
    if (count($xy) == 0) {
        return false;
    }
    $xPoints = [];
    $yPoints = [];
    for ($i = 0; $i < count($xy); $i++) {
        $x = $xy[$i]['x'];
        $y = $xy[$i]['y'];
        $yPoints[$x] = $y;
        if ($i == 0) {
            $minX = $maxX = $x;
            $minY = $maxY = $y;
            continue;
        }
        $minX = min($minX, $x);
        $maxX = max($maxX, $x);
        $minY = min($minY, $y);
        $maxY = max($maxY, $y);
    }
    $minY = floor($minY / 1000) * 1000;
    $maxY = ceil($maxY / 1000) * 1000;
    $range = $maxY - $minY;
    if ($range == 0) {
        return false;
    }
    foreach ($yPoints as $x => &$y) {
        $y = EncodeValue(min(floor(($y - $minY) / $range * 4096), 4095));
        $xPoints[$x] = EncodeValue(min(floor(($x - $minX) / ($maxX - $minX) * 4096), 4095));
    }
    unset($y);
    ksort($xPoints);
    ksort($yPoints);
    $dataString = '';
    $dataString .= '&chxr=0,'.floor(($minX-96)/4).','.floor(($maxX-96)/4).'|1,'.$minY.','.$maxY;
    $dataString .= '&chd=e:' . implode($xPoints).','.implode($yPoints);

    return $dataString;
}

function EncodeValue($v) {
    $encoding = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.';
    $quotient = floor($v / strlen($encoding));
    $remainder = $v - (strlen($encoding) * $quotient);
    return substr($encoding, $quotient, 1) . substr($encoding, $remainder, 1);
}

function UploadTweetMedia($mediaUrl) {
    global $twitterCredentials;
    if ($twitterCredentials === false) {
        return false;
    }

    if (!$mediaUrl) {
        return false;
    }

    $data = FetchHTTP($mediaUrl);
    if (!$data) {
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
    $oauth->setToken($twitterCredentials['accessToken'], $twitterCredentials['accessTokenSecret']);
    $url = 'https://upload.twitter.com/1.1/media/upload.json';

    $requestHeader = $oauth->getRequestHeader('POST',$url);

    $inHeaders = ['Authorization' => $requestHeader, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary];
    $outHeaders = [];

    $ret = PostHTTP($url, $mime, $inHeaders, $outHeaders);

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

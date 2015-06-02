<?php

/*
 * sudo pecl install oauth
 * and in /etc/php.d/oauth.ini add:
 * extension=oauth.so
 */

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/wowtoken-twitter.credentials.php');
require_once('../incl/android.gcm.credentials.php');

RunMeNTimes(1);
CatchKill();

define('SNAPSHOT_PATH', '/home/wowtoken/pending/');
define('TWEET_FREQUENCY_MINUTES', 360); // tweet at least every 6 hours
define('PRICE_CHANGE_THRESHOLD', 0.15); // was 0.2, for 20% change required. 0 means tweet every change

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
    'CN' => 'Asia/Shanghai',
    'TW' => 'Asia/Taipei',
    'KR' => 'Asia/Seoul',
];

$timeLeftCodes = [
    'Short' => 'less than 30 minutes',
    'Medium' => '30 minutes to 2 hours',
    'Long' => '2 to 12 hours',
    'Very Long' => 'over 12 hours',
];

$timeLeftNumbers = [
    'Short' => 1,
    'Medium' => 2,
    'Long' => 3,
    'Very Long' => 4,
];

$regionNames = [
    'NA' => 'North American',
    'EU' => 'European',
    'CN' => 'Chinese',
    'TW' => 'Taiwanese',
    'KR' => 'Korean',
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
    SendAndroidNotifications(array_unique($gotData));
    DebugMessage('Done! Started ' . TimeDiff($startTime));
}

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $gotFile = false;
    $wait = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)-(US|EU|CN|TW|KR)\.lua$/', $fileName, $res)) {
            if (filemtime(SNAPSHOT_PATH . $fileName) > (time() - 5)) {
                $wait = true;
                continue;
            }

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

    if ($wait && !$gotFile) {
        heartbeat();
        sleep(5);
        return true;
    }

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
    foreach (['selltime', 'market', 'result', 'selltimeraw'] as $col) {
        if (!isset($lua[$col])) {
            $lua[$col] = null;
        }
    }

    $sql = 'replace into tblWowToken (`region`, `when`, `marketgold`, `timeleft`, `timeleftraw`, `result`) values (?, ?, floor(?/10000), ?, ?, ?)';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssiiii',
        $lua['region'],
        $snapshotString,
        $lua['market'],
        $lua['selltime'],
        $lua['selltimeraw'],
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
    global $resultCodes, $timeZones, $timeLeftCodes, $timeLeftNumbers;

    $blankImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    $htmlFormat = <<<EOF
                <table class="results">
                    <tbody>
                        <tr>
                            <td>Buy Price</td>
                            <td id="##region##-buy">##buy##</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: bottom">24-Hour Range</td>
                            <td>
                                <table>
                                    <tr>
                                        <td colspan="2"><img src="##rangeImg##" width="150" height="75"></td>
                                    </tr>
                                    <tr>
                                        <td>##24min##</td>
                                        <td align="right">##24max##</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <!-- tr>
                            <td>Time to Sell</td>
                            <td id="##region##-timeToSell">##timeToSell##</td>
                        </tr -->
                        <tr>
                            <td>API Result</td>
                            <td id="##region##-result">##result##</td>
                        </tr>
                        <tr>
                            <td>Updated</td>
                            <td id="##region##-updatedhtml">##updatedhtml##</td>
                        </tr>
                    </tbody>
                </table>
EOF;

    $json = [];
    $historyJson = [];
    $csv = "Region,UTC Date,Buy Price\r\n"; //,Time Left

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

        $sql = 'select min(`marketgold`) `min`, max(`marketgold`) `max` from tblWowToken w where region = ? and `when` between timestampadd(hour, -24, ?) and ?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('sss', $region, $tokenData['when'], $tokenData['when']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tokenData['24min'] = $row['min'];
            $tokenData['24max'] = $row['max'];
        }
        $result->close();
        $stmt->close();

        $d = new DateTime('now', timezone_open($timeZones[$region]));
        $d->setTimestamp(strtotime($tokenData['when']));

        $sparkUrl = GetChartURL($region, $fileRegion);
        if (!$sparkUrl) {
            $sparkUrl = $blankImage;
        }

        $historyJson[$fileRegion] = BuildHistoryJson($region);
        $prevPrice = -1;
        foreach ($historyJson[$fileRegion] as $row) {
            if ($row[1] != $prevPrice) {
                $prevPrice = $row[1];
                $csv .= "$fileRegion,".Date('Y-m-d H:i:s', $row[0]).",{$row[1]}\r\n"; //,{$row[2]}
            }
        }

        $json[$fileRegion] = [
            'timestamp' => strtotime($tokenData['when']),
            'raw' => [
                'buy' => $tokenData['marketgold'],
                '24min' => $tokenData['24min'],
                '24max' => $tokenData['24max'],
                'timeToSell' => isset($timeLeftNumbers[$tokenData['timeleft']]) ? $timeLeftNumbers[$tokenData['timeleft']] : $tokenData['timeleft'],
                'timeToSellSeconds' => $tokenData['timeleftraw'],
                'result' => $tokenData['result'],
                'updated' => strtotime($tokenData['when']),
            ],
            'formatted' => [
                'buy' => number_format($tokenData['marketgold']).'g',
                '24min' => number_format($tokenData['24min']).'g',
                '24max' => number_format($tokenData['24max']).'g',
                'rangeImg' => 'data:image/png;base64,'.base64_encode(FetchHTTP('https://chart.googleapis.com/chart?chs=150x75&cht=gom&chd=t:'.round(($tokenData['marketgold'] - $tokenData['24min'])/($tokenData['24max'] - $tokenData['24min'])*100).'&chco=3333CC,CC3333&chf=bg,s,FFFFFF00')),
                //'buyimg' => BuildImageURI(number_format($tokenData['marketgold']).'g'),
                'timeToSell' => isset($timeLeftCodes[$tokenData['timeleft']]) ? $timeLeftCodes[$tokenData['timeleft']] : $tokenData['timeleft'],
                'result' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
                'updated' => $d->format('M jS, Y g:ia T'),
                'updatedhtml' => $d->format('M jS, Y g:ia\\&\\n\\b\\s\\p\\;T'),
                'sparkurl' => $sparkUrl,
                'region' => $fileRegion,
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

        unset($json[$fileRegion]['formatted']['rangeImg']);
    }

    file_put_contents(__DIR__.'/../wowtoken/snapshot.json', json_encode($json, JSON_NUMERIC_CHECK));
    file_put_contents(__DIR__.'/../wowtoken/snapshot-history.csv', $csv);
    file_put_contents(__DIR__.'/../wowtoken/snapshot-history.json', json_encode([
                'attention' => 'Please see usage guidelines on https://wowtoken.info/',
                'update' => $json,
                'history' => $historyJson
            ], JSON_NUMERIC_CHECK));
    MCSet('wowtoken-json-etag', time());

    $shtmlPath = __DIR__.'/../wowtoken/index-template.shtml';
    if (file_exists($shtmlPath)) {
        $shtml = file_get_contents($shtmlPath);
        $shtmlPath = str_replace('-template', '', $shtmlPath);
        $html = preg_replace_callback('/<!--#include virtual="([^"]+)"-->/', function($m) {
                $path = __DIR__.'/../wowtoken/'.$m[1];
                if (file_exists($path)) {
                    return file_get_contents($path);
                }
                return '';
            }, $shtml);
        file_put_contents($shtmlPath, $html);

        $htmlPath = preg_replace('/\.shtml$/', '.html', $shtmlPath);
        if (strpos($html, '<!--#') === false) {
            file_put_contents($htmlPath, $html);
        } else {
            unlink($htmlPath);
        }
    }
}

function DurationString($s) {
    if ($s <= 0) {
        return 'Immediately';
    }
    if ($s <= 90) {
        return "$s seconds";
    }
    if ($s <= (90 * 60)) {
        return ''.round($s/60).' minutes';
    }
    return TimeDiff(time()+$s, ['parts' => 2, 'distance' => false]);
}

function BuildImageURI($s) {
    $imgdata = shell_exec('convert -background transparent -fill black -weight Bold -pointsize 14 label:'.escapeshellarg($s).' png:-');
    //return 'data:image/png;i=<!--#echo var="REMOTE_ADDR"-->;base64,'.base64_encode($imgdata);
    return 'data:image/png;base64,'.base64_encode($imgdata);
}

function BuildHistoryJson($region) {
    global $db;

    // , `timeleft`+0 `time`, `timeleftraw`
    $sql = 'select unix_timestamp(`when`) `dt`, `marketgold` `buy` from tblWowToken where region = ? and `result` = 1 order by `when` asc'; // and `when` < timestampadd(minute, -70, now())
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenData = [];
    $prevPrice = -1;
    $lately = time() - (3 * 24 * 60 * 60) - 5 * 60;
    while ($row = $result->fetch_row()) {
        if (($row[0] > $lately) || ($prevPrice != $row[1])) {
        //if ($prevPrice != $row[1]) {
            $tokenData[] = $row;
        }
        $prevPrice = $row[1];
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
                'TIMETOSELL' => isset($timeLeftCodes[$tokenData['timeleft']]) ? $timeLeftCodes[$tokenData['timeleft']] : $tokenData['timeleft'],
                'RESULT' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
                'UPDATED' => $d->format('M jS, Y g:ia T'),
            ],
        ];

        $needTweet = false;

        $lastAmt = isset($lastTweetData['record']) ? $lastTweetData['record']['marketgold'] : 0;
        if ($lastAmt && ($lastAmt != $tokenData['marketgold'])) {
            $tweetData['formatted']['BUYCHANGEPERCENT'] = ($lastAmt < $tokenData['marketgold'] ? '+' : '') . round(($tokenData['marketgold'] / $lastAmt - 1) * 100, 2).'%';
            $tweetData['formatted']['BUYCHANGEAMOUNT'] = ($lastAmt < $tokenData['marketgold'] ? '+' : '') . number_format($tokenData['marketgold'] - $lastAmt);
        }

        $direction = 0;

        $sql = <<<EOF
select if(count(*) < 2, 0, sign(sum(direction)))
from (
    select direction
    from (
        select aa.*, sign(cast(market as signed) - cast(@prev as signed)) direction, @prev := market ignoreme
        from (
            SELECT `when`, marketgold market
            FROM `tblWowToken`
            WHERE region = ?
            and result=1
            order by `when` desc
            limit 100
        ) aa, (select @prev := null) ab
        order by aa.`when` asc
    ) bb
    where bb.direction != 0
    order by bb.`when` desc
    limit 2
) cc
EOF;
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $region);
        $stmt->execute();
        $stmt->bind_result($direction);
        if (!$stmt->fetch()) {
            DebugMessage('Direction fetch failed: '.$stmt->error);
            $direction = 0;
        }
        $stmt->close();

        // direction is either 1 (going up), 0 (mix/not sure), or -1 (going down)
        $tweetData['direction'] = $direction;

        //DebugMessage('Debug: '.$region.' last direction: '.(isset($lastTweetData['direction']) ? $lastTweetData['direction'] : 'unset').', cur direction: '.$direction);

        if (false && !$needTweet && $direction &&
            isset($lastTweetData['direction']) &&
            $lastTweetData['direction'] &&
            $direction != $lastTweetData['direction']
        ) {
            $needTweet = true; // this is a new confirmed, consistent direction
            DebugMessage('Need '.$region.' tweet after confirmed direction change from '.$lastTweetData['direction'].' to '.$direction);
            $tweetData['formatted']['TURNAROUND'] = 'Price going '.($direction > 0 ? 'up' : 'down').'.';
        }

        if (!$needTweet && !isset($lastTweetData['timestamp'])) {
            $needTweet = true;
            DebugMessage('Need '.$region.' tweet after no last tweet data');
        }
        if (!$needTweet && ($lastTweetData['timestamp'] < ($tweetData['timestamp'] - TWEET_FREQUENCY_MINUTES * 60 + 5)) && ($tweetData['record']['result'] == 1)) { // tweet at least every X minutes when result is good
            $needTweet = true;
            DebugMessage('Need '.$region.' tweet after '.TWEET_FREQUENCY_MINUTES.' minutes. ('.$lastTweetData['timestamp'].')');
        }
        if (!$needTweet && $lastTweetData['record']['result'] != $tweetData['record']['result']) {
            $needTweet = true;
            DebugMessage('Need '.$region.' tweet after result changed');
        }
        if (!$needTweet && $lastTweetData['record']['marketgold'] * (1 + PRICE_CHANGE_THRESHOLD) < $tweetData['record']['marketgold']) {
            $needTweet = true;
            DebugMessage('Need '.$region.' tweet after market price went up over '.PRICE_CHANGE_THRESHOLD.'%');
        }
        if (!$needTweet && $lastTweetData['record']['marketgold'] * (1 - PRICE_CHANGE_THRESHOLD) > $tweetData['record']['marketgold']) {
            $needTweet = true;
            DebugMessage('Need '.$region.' tweet after market price went down over '.PRICE_CHANGE_THRESHOLD.'%');
        };

        /*
        $changePct = (isset($prevTokenData['marketgold']) && $prevTokenData['marketgold']) ? round(($tokenData['marketgold'] / $prevTokenData['marketgold'] - 1) * 2000) : 0;
        if (($direction != 0) && ($changePct != 0) && (abs($changePct) != 20)) { // change happened this snapshot, and not by 1%, possible turnaround
            if (!$needTweet) {
                DebugMessage('Need '.$region.' tweet after non-1% change happened this snapshot ('.$changePct.')');
                $needTweet = true;
            }
            if (!isset($tweetData['formatted']['TURNAROUND'])) {
                $tweetData['formatted']['TURNAROUND'] = 'Possible '.($direction > 0 ? 'maximum' : 'minimum').'.';
            }
        }
        */

        if (!$needTweet) {
            DebugMessage('No '.$region.' tweet needed.');
            continue;
        }

        /*
        DebugMessage(print_r($prevTokenData, true));
        DebugMessage(print_r($tweetData, true));
        DebugMessage(print_r($lastTweetData, true));
        */

        if ($tweetId = SendTweet(strtoupper($fileRegion), $tweetData, GetChartURL($region, strtoupper($fileRegion)), $lastTweetData)) {
            file_put_contents($filenm, json_encode($tweetData));
        }
        if ($tweetId && ($tweetId !== true)) {
            switch (strtoupper($fileRegion)) {
                case 'NA':
                case 'EU':
                    Retweet($tweetId, 'WoWToken'.strtoupper($fileRegion));
                    break;
            }
        }
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

function SendTweet($region, $tweetData, $chartUrl, $lastTweetData)
{
    global $regionNames;

    $msg = isset($regionNames[$region]) ? $regionNames[$region] : $region;
    $msg .= " WoW Token: " . $tweetData['formatted']['BUY'] . "g."; //, sells in " . $tweetData['formatted']['TIMETOSELL'] . '.';
    if ($tweetData['timestamp'] < (time() - 30 * 60)) { // show timestamp if older than 30 mins
        $msg .= " From " . TimeDiff($tweetData['timestamp'], ['parts' => 2, 'precision' => 'minute']) . '.';
    }
    if ($tweetData['record']['result'] != 1) {
        $msg .= " " . $tweetData['formatted']['RESULT'] . ".";
    } else {
        if (isset($tweetData['formatted']['BUYCHANGEAMOUNT']) && ($tweetData['formatted']['BUYCHANGEAMOUNT'] != '0')) {
            $msg .= " Changed ".$tweetData['formatted']['BUYCHANGEAMOUNT'].'g';
            if (isset($tweetData['formatted']['BUYCHANGEPERCENT'])) {
                $msg .= ' or '.$tweetData['formatted']['BUYCHANGEPERCENT'];
                if (isset($lastTweetData['timestamp'])) {
                    $msg .= ' since '.round((time()-$lastTweetData['timestamp'])/3600,1).'h ago';
                }
            } elseif (isset($lastTweetData['timestamp'])) {
                $msg .= ' since '.round((time()-$lastTweetData['timestamp'])/3600,1).'h ago';
            }
            $msg .= '.';
        }
        if (isset($tweetData['formatted']['TURNAROUND'])) {
            $msg .= ' '.$tweetData['formatted']['TURNAROUND'];
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

function GetChartURL($region, $regionName = '') {
    global $db, $timeZones, $regionNames;

    if (!$regionName) {
        $regionName = strtoupper($region);
    }

    static $cache = [];
    if (isset($cache[$regionName])) {
        return $cache[$regionName];
    }

    $sql = <<<EOF
SELECT 1440 - floor((unix_timestamp() - unix_timestamp(`when`)) / 60) x, marketgold y
FROM `tblWowToken`
WHERE region = ?
and result = 1
and `when` >= timestampadd(minute, -1460, now())
EOF;
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $sparkData = DBMapArray($result, null);
    $stmt->close();

    $colors = [
        'line' => '0000FF',
        'fill' => 'CCCCFF99',
        'point' => '9999FF',
    ];

    if ($region == 'EU') {
        $colors = [
            'line' => 'FF0000',
            'fill' => 'FFCCCC99',
            'point' => 'FF9999',
        ];
    }

    if ($region == 'CN') {
        $colors = [
            'line' => '00CC00',
            'fill' => 'B2E6B299',
            'point' => '99CC99',
        ];
    }

    if ($region == 'TW') {
        $colors = [
            'line' => 'CCCC00',
            'fill' => 'E6E6B299',
            'point' => 'CCCC99',
        ];
    }

    if ($region == 'KR') {
        $colors = [
            'line' => '00CCCC',
            'fill' => 'B2E6E699',
            'point' => '99CCCC',
        ];
    }

    $cache[$regionName] = EncodeChartData($sparkData);
    if ($cache[$regionName]) {
        $dThen = new DateTime('-24 hours', timezone_open($timeZones[$region]));
        $dNow = new DateTime('now', timezone_open($timeZones[$region]));

        $title = (isset($regionNames[$regionName]) ? $regionNames[$regionName] : $regionName) . " WoW Token Prices - wowtoken.info|".$dThen->format('F jS').' - '.$dNow->format('F jS H:i T');
        $cache[$regionName] = 'https://chart.googleapis.com/chart?chs=600x300&cht=lxy&chtt=' . urlencode($title)
            . '&chco='.$colors['line'].'&chm=B,'.$colors['fill'].',0,0,0|v,'.$colors['point'].',0,,1&chg=100,25,5,0&chxt=x,y&chf=c,s,FFFFFF&chma=8,8,8,8'
            . $cache[$regionName];
    }

    return $cache[$regionName];

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
            $minY = $maxY = $y;
            continue;
        }
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
        $xPoints[$x] = EncodeValue(max(0, min(floor($x / 1440 * 4096), 4095)));
    }
    unset($y);
    ksort($xPoints);
    ksort($yPoints);
    $dataString = '';
    $dataString .= '&chxr=0,-24,0|1,'.$minY.','.$maxY;
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
    $oauth->setToken($twitterCredentials['WoWTokens']['accessToken'], $twitterCredentials['WoWTokens']['accessTokenSecret']);
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

function SendAndroidNotifications($regions)
{
    global $db;
    global $timeZones, $timeLeftCodes, $regionNames;

    $sent = [];

    foreach ($regions as $region) {
        $properRegion = strtoupper($region);
        if ($properRegion == 'US') {
            $properRegion = 'NA';
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

        if (!$prevTokenData) {
            continue;
        }

        if ($tokenData['marketgold'] == $prevTokenData['marketgold']) {
            continue;
        }

        if (($tokenData['result'] != 1) || ($tokenData['result'] != $prevTokenData['result'])) {
            continue;
        }

        $d = new DateTime('now', timezone_open($timeZones[$region]));
        $d->setTimestamp(strtotime($tokenData['when']));

        $formatted = [
            'BUY' => number_format($tokenData['marketgold']),
            'TIMETOSELL' => isset($timeLeftCodes[$tokenData['timeleft']]) ? $timeLeftCodes[$tokenData['timeleft']] : $tokenData['timeleft'],
            'UPDATED' => $d->format('M j g:ia T'),
        ];

        $direction = $tokenData['marketgold'] > $prevTokenData['marketgold'] ? 'over' : 'under';

        $sql = <<<EOF
select s.endpoint, s.id, s.subid, e.region, e.value
from tblWowTokenSubs s
join tblWowTokenEvents e on e.subid = s.id
where s.lastfail is null
and e.direction = '$direction'
and e.region = '$properRegion'
EOF;

        if ($direction == 'over') {
            $sql .= ' and e.value >= ' . $prevTokenData['marketgold'] . ' and e.value < ' . $tokenData['marketgold'];
        } else {
            $sql .= ' and e.value <= ' . $prevTokenData['marketgold'] . ' and e.value > ' . $tokenData['marketgold'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = DBMapArray($result, ['endpoint', 'id']);
        $stmt->close();

        if (count($rows) == 0) {
            continue;
        }

        //sells in " . $formatted['TIMETOSELL'] . '
        $message = $regionNames[$properRegion] . ' price %s: now '.$formatted['BUY']."g, as of ".$formatted['UPDATED'].'.';

        foreach ($rows as $endpoint => $allIds) {
            $chunks = array_chunk($allIds, 50, true);
            foreach ($chunks as $chunk) {
                $lookup = [];
                $toSend = [];
                $failed = [];
                $successful = [];
                foreach ($chunk as $id => $row) {
                    $msg = sprintf($message, $direction.' '.number_format($row['value'], 0).'g');
                    $key = md5($endpoint.'#'.$row['subid']);
                    if (!isset($sent[$key])) {
                        $lookup[] = $id;
                        $toSend[] = $row['subid'];
                        $sent[$key] = $msg;
                    } else {
                        $sent[$key] .= " \n".$msg;
                    }
                    MCSet('tokennotify-'.$key, $sent[$key], 8 * 60 * 60);
                }
                if (!count($toSend)) {
                    continue;
                }
                $toSend = json_encode([
                    'registration_ids' => $toSend,
                    'time_to_live' => 4 * 60 * 60
                ]);
                $headers = [
                    'Authorization' => 'key='.ANDROID_GCM_KEY,
                    'Content-Type' => 'application/json',
                ];
                $outHeaders = [];
                $ret = PostHTTP($endpoint, $toSend, $headers, $outHeaders);
                $ret = json_decode($ret, true);
                if ((json_last_error() != JSON_ERROR_NONE) || (!isset($ret['results']))) {
                    if ((count($lookup) == 1) && isset($outHeaders['responseCode']) && ($outHeaders['responseCode'] == '404')) {
                        // only sent one, which failed, so mark it as failed
                        $successful = [];
                        $failed = $lookup;
                    } else {
                        // can only assume all went through
                        DebugMessage("Bad response from $endpoint\n".print_r($headers, true).$toSend."\n".print_r($outHeaders, true)."\n$ret");
                        $successful = $lookup;
                        $failed = [];
                    }
                } else {
                    for ($x = 0; $x < count($ret['results']); $x++) {
                        if (isset($ret['results'][$x]['error'])) {
                            $failed[] = $lookup[$x];
                        } else {
                            $successful[] = $lookup[$x];
                        }
                    }
                }

                $stmt = $db->prepare('update tblWowTokenEvents set lasttrigger=now() where subid in ('.implode(',', $lookup).') and region=\''.$properRegion.'\' and direction=\''.$direction.'\'');
                $stmt->execute();
                $stmt->close();

                if (count($successful)) {
                    $stmt = $db->prepare('update tblWowTokenSubs set lastpush=now() where id in ('.implode(',', $successful).')');
                    $stmt->execute();
                    $stmt->close();
                }
                if (count($failed)) {
                    $stmt = $db->prepare('update tblWowTokenSubs set lastpush=now(), lastfail=now() where id in ('.implode(',', $failed).')');
                    $stmt->execute();
                    $stmt->close();
                }

                DebugMessage('Sent '.count($lookup).' messages to '.$endpoint.' - '.count($successful).' successful, '.count($failed).' failed.');
            }
        }

    }
}


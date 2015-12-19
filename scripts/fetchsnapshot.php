<?php

chdir(__DIR__);
$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/battlenet.incl.php');

RunMeNTimes(1);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/');

$regions = array('US', 'EU');

if (!isset($argv[1]) || !in_array($argv[1], $regions)) {
    DebugMessage('Need region US or EU', E_USER_ERROR);
}

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

$region = $argv[1];

$loopStart = time();
$toSleep = 0;
while ((!$caughtKill) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    sleep(min($toSleep, 30));
    if ($caughtKill) {
        break;
    }
    $toSleep = FetchSnapshot();
    if ($toSleep === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function FetchSnapshot()
{
    global $db, $region;

    $nextRealmSql = <<<ENDSQL
    select r.house, min(r.canonical), count(*) c, ifnull(hc.nextcheck, s.nextcheck) upd, s.lastupdate
    from tblRealm r
    left join (
        select deltas.house, timestampadd(second, least(ifnull(min(delta)+15-110, 45*60), 150*60), max(deltas.updated)) nextcheck, max(deltas.updated) lastupdate
        from (
            select sn.updated,
            if(@prevhouse = sn.house and sn.updated > timestampadd(hour, -72, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
            @prevdate := unix_timestamp(sn.updated) updated_ts,
            @prevhouse := sn.house house
            from (select @prevhouse := null, @prevdate := null) setup, tblSnapshot sn
            order by sn.house, sn.updated) deltas
        group by deltas.house
        ) s on s.house = r.house
    left join tblHouseCheck hc on hc.house = r.house
    where r.region = ?
    and r.house is not null
    and r.canonical is not null
    group by r.house
    order by ifnull(upd, '2000-01-01') asc, c desc, r.house asc
    limit 1
ENDSQL;

    $stmt = $db->prepare($nextRealmSql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $stmt->bind_result($house, $slug, $realmCount, $nextDate, $lastDate);
    $gotRealm = $stmt->fetch() === true;
    $stmt->close();

    if (!$gotRealm) {
        DebugMessage("No $region realms to fetch!");
        return 30;
    }

    if (strtotime($nextDate) > time() && (strtotime($nextDate) < (time() + 3.5 * 60 * 60))) {
        DebugMessage("No $region realms ready yet, waiting " . TimeDiff(strtotime($nextDate)));
        return strtotime($nextDate) - time();
    }

    DebugMessage("$region $slug fetch for house $house to update $realmCount realms, due since " . (is_null($nextDate) ? 'unknown' : TimeDiff(strtotime($nextDate), array('precision' => 'second'))));

    $url = GetBattleNetURL($region, "wow/auction/data/$slug");

    $outHeaders = [];
    $dta = [];
    $json = FetchHTTP($url, [], $outHeaders);
    if ($json !== false) {
        $dta = json_decode($json, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            $dta = [];
        }
    } else {
        if (isset($outHeaders['body'])) {
            $json = $outHeaders['body'];
        }
    }
    if (!isset($dta['files'])) {
        $delay = GetCheckDelay(strtotime($lastDate));
        DebugMessage("$region $slug returned no files. Waiting " . floor($delay / 60) . " minutes.", E_USER_WARNING);
        SetHouseNextCheck($house, time() + $delay, $json);
        return 0;
    }

    usort($dta['files'], 'AuctionFileSort');
    $fileInfo = array_pop($dta['files']);

    $modified = intval($fileInfo['lastModified'], 10) / 1000;
    if ($modified <= strtotime($lastDate)) {
        $delay = GetCheckDelay($modified);
        DebugMessage("$region $slug still not updated. Waiting " . floor($delay / 60) . " minutes.");
        SetHouseNextCheck($house, time() + $delay, $json);

        return 0;
    }

    DebugMessage("$region $slug updated " . TimeDiff($modified) . ", fetching auction data file");
    $dlStart = microtime(true);
    $data = FetchHTTP(preg_replace('/^http:/', 'https:', $fileInfo['url']), array(), $outHeaders);
    $dlDuration = microtime(true) - $dlStart;
    if (!$data) {
        DebugMessage("$region $slug data file empty. Waiting 5 seconds and trying again.");
        sleep(5);
        $dlStart = microtime(true);
        $data = FetchHTTP($fileInfo['url'] . (parse_url($fileInfo['url'], PHP_URL_QUERY) ? '&' : '?') . 'please', array(), $outHeaders);
        $dlDuration = microtime(true) - $dlStart;
    }
    if (!$data) {
        DebugMessage("$region $slug data file empty. Will try again in 30 seconds.");
        SetHouseNextCheck($house, time() + 30, $json);
        http_persistent_handles_clean();

        return 10;
    }

    $xferBytes = isset($outHeaders['X-Original-Content-Length']) ? $outHeaders['X-Original-Content-Length'] : strlen($data);
    DebugMessage("$region $slug data file " . strlen($data) . " bytes" . ($xferBytes != strlen($data) ? (' (transfer length ' . $xferBytes . ', ' . round($xferBytes / strlen($data) * 100, 1) . '%)') : '') . ", " . round($dlDuration, 2) . "sec, " . round($xferBytes / 1000 / $dlDuration) . "KBps");
    if ($xferBytes >= strlen($data) && strlen($data) > 65536) {
        DebugMessage('No compression? ' . print_r($outHeaders, true));
    }

    if ($xferBytes / 1000 / $dlDuration < 200) {
        DebugMessage("Speed under 200KBps, closing persistent connections");
        http_persistent_handles_clean();
    }

    $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, nextcheck, lastcheck, lastcheckresult, lastchecksuccess, lastchecksuccessresult) VALUES (?, NULL, now(), ?, now(), ?) ON DUPLICATE KEY UPDATE nextcheck=values(nextcheck), lastcheck=values(lastcheck), lastcheckresult=values(lastcheckresult), lastchecksuccess=values(lastchecksuccess), lastchecksuccessresult=values(lastchecksuccessresult)');
    $stmt->bind_param('iss', $house, $json, $json);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('INSERT INTO tblSnapshot (house, updated) VALUES (?, from_unixtime(?))');
    $stmt->bind_param('ii', $house, $modified);
    $stmt->execute();
    $stmt->close();

    MCSet('housecheck_'.$house, time(), 0);

    file_put_contents(SNAPSHOT_PATH . "$modified-" . str_pad($house, 5, '0', STR_PAD_LEFT) . ".json", $data, LOCK_EX);

    return 0;
}

function GetCheckDelay($modified)
{
    $delayMinutes = 2;
    if ($modified < strtotime('3 hours ago')) {
        $delayMinutes = 5;
    }
    if ($modified < strtotime('6 hours ago')) {
        $delayMinutes = 15;
    }

    return $delayMinutes * 60;
}

function SetHouseNextCheck($house, $nextCheck, $json)
{
    global $db;

    $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, nextcheck, lastcheck, lastcheckresult) VALUES (?, from_unixtime(?), now(), ?) ON DUPLICATE KEY UPDATE nextcheck=values(nextcheck), lastcheck=values(lastcheck), lastcheckresult=values(lastcheckresult)');
    $stmt->bind_param('iis', $house, $nextCheck, $json);
    $stmt->execute();
    $stmt->close();

    MCSet('housecheck_'.$house, time(), 0);
}

function AuctionFileSort($a, $b)
{
    $am = intval($a['lastModified'], 10) / 1000;
    $bm = intval($b['lastModified'], 10) / 1000;
    return $am - $bm;
}

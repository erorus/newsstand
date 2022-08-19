<?php

chdir(__DIR__);
$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/battlenet.incl.php');

ini_set('memory_limit', '256M');

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/');
define('EARLY_CHECK_SECONDS', 120);
define('MINIMUM_INTERVAL_SECONDS', 1220);
define('DATA_FILE_CURLOPTS', [
    CURLOPT_LOW_SPEED_LIMIT => 50*1024,
    CURLOPT_LOW_SPEED_TIME => 5,
    CURLOPT_TIMEOUT => 20,
]);

$regions = ['US','EU','CN','TW','KR'];

if (!isset($argv[1]) || !in_array($argv[1], $regions)) {
    DebugMessage('Need region '.implode(', ', $regions), E_USER_ERROR);
}

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

$region = $argv[1];
$runNTimes = 1;
if (isset($argv[2])) {
    $runNTimes = intval($argv[2], 10);
    if ($runNTimes <= 0) {
        $runNTimes = 1;
    }
}

RunMeNTimes($runNTimes);
CatchKill();

$loopStart = time();
$toSleep = 0;
while ((!CatchKill()) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    sleep(min($toSleep, 30));
    if (CatchKill()) {
        break;
    }
    ob_start();
    $toSleep = FetchSnapshot();
    ob_end_flush();
    if ($toSleep === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function FetchSnapshot()
{
    global $db, $region;

    $lockName = "fetchsnapshot_$region";

    $stmt = $db->prepare('select get_lock(?, 30)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $lockSuccess = null;
    $stmt->bind_result($lockSuccess);
    if (!$stmt->fetch()) {
        $lockSuccess = null;
    }
    $stmt->close();
    if ($lockSuccess != '1') {
        DebugMessage("Could not get mysql lock for $lockName.");
        return 30;
    }
    $earlyCheckSeconds = EARLY_CHECK_SECONDS;

    $nextRealmSql = <<<ENDSQL
    select r.house, min(IFNULL(r.canonical, r.slug)), IFNULL(min(blizzConnection), -1), count(*) c, ifnull(hc.nextcheck, s.nextcheck) upd, 
    s.lastupdate, if(s.lastupdate < timestampadd(hour, -36, now()), 0, ifnull(s_id.maxid, 0)) maxid, s.mindelta
    from tblRealm r
    left join (
        select deltas.house, timestampadd(second, least(ifnull(min(delta)-$earlyCheckSeconds, 45*60), 150*60), max(deltas.updated)) nextcheck, max(deltas.updated) lastupdate, least(min(delta), 150*60) mindelta
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
    left join tblSnapshot s_id on s_id.house = s.house and s_id.updated = s.lastupdate
    where r.region = ?
    and r.house is not null
    and ((r.canonical is not null AND r.blizzConnection is not null) OR r.slug = 'commodities')
    group by r.house
    order by ifnull(upd, '2000-01-01') asc, c desc, r.house asc
    limit 1
ENDSQL;

    $house = $slug = $connectionId = $realmCount = $nextDate = $lastDate = $maxId = $minDelta = null;

    $stmt = $db->prepare($nextRealmSql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $stmt->bind_result($house, $slug, $connectionId, $realmCount, $nextDate, $lastDate, $maxId, $minDelta);
    $gotRealm = $stmt->fetch() === true;
    $stmt->close();

    if (!$gotRealm) {
        DebugMessage("No $region realms to fetch!");
        ReleaseDBLock($lockName);
        return 30;
    }

    if (strtotime($nextDate) > time() && (strtotime($nextDate) < (time() + 3.5 * 60 * 60))) {
        $delay = strtotime($nextDate) - time();
        DebugMessage("No $region realms ready yet, waiting ".SecondsOrMinutes($delay).".");
        ReleaseDBLock($lockName);
        return $delay;
    }

    SetHouseNextCheck($house, time() + 600);
    ReleaseDBLock($lockName);

    DebugMessage("$region $slug $connectionId fetch for house $house to update $realmCount realms, due since " . (is_null($nextDate) ? 'unknown' : (SecondsOrMinutes(time() - strtotime($nextDate)).' ago')));

    if ($slug === 'commodities') {
        $requestInfo = GetBattleNetURL($region, "data/wow/auctions/commodities");
    } else {
        $requestInfo = GetBattleNetURL($region, "data/wow/connected-realm/$connectionId/auctions");
    }
    if (!is_null($lastDate)) {
        $requestInfo[1][] = 'If-Modified-Since: ' . date(DATE_RFC7231, strtotime($lastDate));
    }

    $outHeaders = [];
    $dlStart = microtime(true);
    $data = $requestInfo ? \Newsstand\HTTP::Get($requestInfo[0], $requestInfo[1], $outHeaders) : false;
    $dlDuration = microtime(true) - $dlStart;

    $xferBytes = isset($outHeaders['X-Original-Content-Length']) ? $outHeaders['X-Original-Content-Length'] : strlen($data ?: '');
    if ($xferBytes && $data) {
        DebugMessage("$region $slug $connectionId data file " . strlen($data) . " bytes" . ($xferBytes != strlen($data) ? (' (transfer length ' . $xferBytes . ', ' . round($xferBytes / strlen($data) * 100, 1) . '%)') : '') . ", " . round($dlDuration, 2) . "sec, " . round($xferBytes / 1000 / $dlDuration) . "KBps");
        if ($xferBytes >= strlen($data) && strlen($data) > 65536) {
            DebugMessage('No compression? ' . print_r($outHeaders, true));
        }

        if ($xferBytes && $xferBytes / 1000 / $dlDuration < 200 && in_array($region, ['US','EU'])) {
            DebugMessage("Speed under 200KBps, closing persistent connections");
            \Newsstand\HTTP::AbandonConnections();
        }
    }

    $modified = isset($outHeaders['Last-Modified']) ? strtotime($outHeaders['Last-Modified']) : false;
    if ($modified === false) {
        $delay = 60 * 60; // 1 hour.
        DebugMessage("$region $slug $connectionId returned no valid last-modified header! Waiting ".SecondsOrMinutes($delay).".");
        SetHouseNextCheck($house, time() + $delay);

        return 0;
    }

    $lastDateUnix = is_null($lastDate) ? ($modified - 1) : strtotime($lastDate);

    if ($outHeaders['responseCode'] == 304) {
        // We've seen the current snapshot already.
        if (!is_null($minDelta) && ($lastDateUnix + $minDelta) > time()) {
            // We checked for an earlier-than-expected snapshot, didn't see one
            $delay = ($lastDateUnix + $minDelta) - time() + 8; // next check will be 8 seconds after expected update
        } else {
            // We should've seen a new snapshot by now. Use standard delay before next check.
            $delay = GetCheckDelay($modified);
        }

        DebugMessage("$region $slug $connectionId still not updated since $modified ".date('H:i:s', $modified)." (" . SecondsOrMinutes(time() - $modified) . " ago). Waiting ".SecondsOrMinutes($delay).".");
        SetHouseNextCheck($house, time() + $delay);

        return 0;
    }

    DebugMessage("$region $slug $connectionId updated $modified ".date('H:i:s', $modified)." (" . SecondsOrMinutes(time() - $modified) . " ago).");

    if (!$data) {
        DebugMessage("$region $slug $connectionId data file empty. Will try again in 30 seconds.");
        SetHouseNextCheck($house, time() + 30);
        \Newsstand\HTTP::AbandonConnections();

        return 10;
    }
    if (!in_array(substr($data, -3), ['}]}', '"}}'])) {
        $delay = GetCheckDelay($modified);
        DebugMessage("$region $slug $connectionId data file probably malformed. Waiting ".SecondsOrMinutes($delay).".");
        SetHouseNextCheck($house, time() + $delay);

        return 0;
    }

    $nextCheck = null;
    if ($modified - $lastDateUnix <= MINIMUM_INTERVAL_SECONDS) {
        $nextCheck = date('Y-m-d H:i:s', $modified + MINIMUM_INTERVAL_SECONDS);
        DebugMessage(sprintf('%s %s %d update interval was %d seconds (<= %d), forcing next check at %s', $region, $slug, $connectionId, $modified - $lastDateUnix, MINIMUM_INTERVAL_SECONDS, date('H:i:s', $modified + MINIMUM_INTERVAL_SECONDS)));
    }

    $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, nextcheck, lastcheck, lastchecksuccess) VALUES (?, ?, now(), now()) ON DUPLICATE KEY UPDATE nextcheck=values(nextcheck), lastcheck=values(lastcheck), lastchecksuccess=values(lastchecksuccess)');
    $stmt->bind_param('is', $house, $nextCheck);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('INSERT INTO tblSnapshot (house, updated) VALUES (?, from_unixtime(?))');
    $stmt->bind_param('ii', $house, $modified);
    $stmt->execute();
    $stmt->close();

    MCSet('housecheck_'.$house, time(), 0);

    $fileName = "$modified-" . str_pad($house, 5, '0', STR_PAD_LEFT) . ".json";
    $data = gzencode($data);
    file_put_contents(SNAPSHOT_PATH . $fileName, $data, LOCK_EX);
    link(SNAPSHOT_PATH . $fileName, SNAPSHOT_PATH . 'parse/' . $fileName);
    if (in_array($region, ['US','EU'])) {
        link(SNAPSHOT_PATH . $fileName, SNAPSHOT_PATH . 'watch/' . $fileName);
    }
    unlink(SNAPSHOT_PATH . $fileName);

    return 0;
}

function ReleaseDBLock($lockName) {
    global $db;

    $stmt = $db->prepare('do release_lock(?)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->close();
}

function SecondsOrMinutes($sec) {
    if ($sec >= 180) {
        return round($sec/60,1).' minutes';
    }
    return "$sec seconds";
}

function GetCheckDelay($modified)
{
    $now = time();

    $delayMinutes = 0.5;
    if ($modified < ($now - 4200)) { // over 70 minutes ago
        $delayMinutes = 2;
    }
    if ($modified < ($now - 10800)) { // over 3 hours ago
        $delayMinutes = 5;
    }
    if ($modified < ($now - 21600)) { // over 6 hours ago
        $delayMinutes = 15;
    }

    return $delayMinutes * 60;
}

function SetHouseNextCheck($house, $nextCheck)
{
    global $db;

    $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, nextcheck, lastcheck) VALUES (?, from_unixtime(?), now()) ON DUPLICATE KEY UPDATE nextcheck=values(nextcheck), lastcheck=values(lastcheck)');
    $stmt->bind_param('ii', $house, $nextCheck);
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

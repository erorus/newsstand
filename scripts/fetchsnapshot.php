<?php

require_once('../incl/incl.php');

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/');

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$region = 'US'; //TODO: pull from command line

$loopStart = time();
$toSleep = 0;
while (time() < ($loopStart + 60 * 30))
{
    sleep(min($toSleep, 30));
    $toSleep = FetchSnapshot();
    if ($toSleep === false)
        break;
}
DebugMessage('Done!');

function FetchSnapshot()
{
    global $db, $region;

    $nextRealmSql = <<<ENDSQL
    select r.house, min(r.slug), count(*) c, ifnull(hc.nextcheck, s.nextcheck) upd, s.lastupdate
    from tblRealm r
    left join (
        select deltas.house, timestampadd(second, ifnull(min(delta), 45*60), max(deltas.updated)) nextcheck, max(deltas.updated) lastupdate
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

    if (!$gotRealm)
    {
        DebugMessage("No $region realms to fetch!");
        return 30;
    }

    if (strtotime($nextDate) > time())
    {
        DebugMessage("No $region realms ready yet, waiting ".TimeDiff(strtotime($nextDate)));
        return strtotime($nextDate) - time();
    }

    DebugMessage("$region $slug fetch for house $house to update $realmCount realms, due since ".(is_null($nextDate) ? 'unknown' : TimeDiff(strtotime($nextDate), array('precision' => 'second'))));

    $url = sprintf('https://%s.battle.net/api/wow/auction/data/%s', strtolower($region), $slug);

    $json = FetchHTTP($url);
    $dta = json_decode($json, true);
    if (!isset($dta['files']))
    {
        DebugMessage("$region $slug returned no files.", E_USER_WARNING);
        SetHouseNextCheck($house, time() + GetCheckDelay($modified));
        return 0;
    }

    usort($dta['files'], 'AuctionFileSort');
    $fileInfo = array_pop($dta['files']);

    $modified = intval($fileInfo['lastModified'], 10)/1000;
    if ($modified <= strtotime($lastDate))
    {
        $delay = GetCheckDelay($modified);
        DebugMessage("$region $slug still not updated. Waiting ".floor($delay/60)." minutes.");
        SetHouseNextCheck($house, time() + $delay);

        return 0;
    }

    DebugMessage("$region $slug updated ".TimeDiff($modified).", fetching auction data file");
    $dlStart = microtime(true);
    $data = FetchHTTP(preg_replace('/^http:\/\/((?:us|eu)\.battle\.net\/)/', 'https://$1', $fileInfo['url']), array(), $outHeaders);
    $dlDuration = microtime(true) - $dlStart;
    if (!$data)
    {
        DebugMessage("$region $slug data file empty. Waiting 30 seconds.");
        SetHouseNextCheck($house, time() + 30);
        http_persistent_handles_clean();

        return 10;
    }

    $xferBytes = isset($outHeaders['X-Original-Content-Length']) ? $outHeaders['X-Original-Content-Length'] : strlen($data);
    DebugMessage("$region $slug data file ".strlen($data)." bytes".($xferBytes != strlen($data) ? (' (transfer length '.$xferBytes.', '.round($xferBytes/strlen($data)*100,1).'%)') : '').", ".round($dlDuration,2)."sec, ".round($xferBytes/1000/$dlDuration)."KBps");

    $stmt = $db->prepare('insert into tblHouseCheck (house, nextcheck) values (?, null) on duplicate key update nextcheck=values(nextcheck)');
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('insert into tblSnapshot (house, updated) values (?, from_unixtime(?))');
    $stmt->bind_param('ii', $house, $modified);
    $stmt->execute();
    $stmt->close();

    file_put_contents(SNAPSHOT_PATH."$modified-".str_pad($house,5,'0',STR_PAD_LEFT).".json", $data, LOCK_EX);

    return 0;
}

function GetCheckDelay($modified)
{
    $delayMinutes = 2;
    if ($modified < strtotime('75 minutes ago'))
        $delayMinutes = 5;
    if ($modified < strtotime('3 hours ago'))
        $delayMinutes = 15;

    return $delayMinutes * 60;
}

function SetHouseNextCheck($house, $nextCheck)
{
    global $db;

    $stmt = $db->prepare('insert into tblHouseCheck (house, nextcheck) values (?, from_unixtime(?)) on duplicate key update nextcheck=values(nextcheck)');
    $stmt->bind_param('ii', $house, $nextCheck);
    $stmt->execute();
    $stmt->close();
}

function AuctionFileSort($a, $b)
{
    $am = intval($a['lastModified'], 10) / 1000;
    $bm = intval($b['lastModified'], 10) / 1000;
    return $am - $bm;
}

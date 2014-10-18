<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/battlenet.incl.php');

ini_set('memory_limit','512M');

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$regions = array('US','EU');

foreach ($regions as $region)
{
    heartbeat();
    if ($caughtKill)
        break;
    if (isset($argv[1]) && $argv[1] != $region)
        continue;
    $url = GetBattleNetURL($region, 'wow/realm/status');

    $json = FetchHTTP($url);
    $realms = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
    if (json_last_error() != JSON_ERROR_NONE)
    {
        DebugMessage("$url did not return valid JSON");
        continue;
    }

    if (!isset($realms['realms']) || (count($realms['realms']) == 0))
    {
        DebugMessage("$url returned no realms");
        continue;
    }

    $stmt = $db->prepare('select ifnull(max(id), 0) from tblRealm');
    $stmt->execute();
    $stmt->bind_result($nextId);
    $stmt->fetch();
    $stmt->close();
    $nextId++;

    $stmt = $db->prepare('insert into tblRealm (id, region, slug, name, locale) values (?, ?, ?, ?, ?) on duplicate key update name=values(name), locale=values(locale)');
    foreach ($realms['realms'] as $realm)
    {
        $stmt->bind_param('issss', $nextId, $region, $realm['slug'], $realm['name'], $realm['locale']);
        $stmt->execute();
        if ($db->affected_rows > 0)
            $nextId++;
        $stmt->reset();
    }
    $stmt->close();

    GetRussianOwnerRealms($region);
    if ($caughtKill)
        break;

    GetRealmPopulation($region);
    if ($caughtKill)
        break;

    $stmt = $db->prepare('select slug, house, name, ifnull(ownerrealm, replace(name, \' \', \'\')) as ownerrealm from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $bySlug = DBMapArray($result);
    $stmt->close();

    $canonicals = array();
    $bySellerRealm = array();
    $fallBack = array();
    $candidates = array();
    $winners = array();

    foreach ($bySlug as $row)
    {
        heartbeat();
        if ($caughtKill)
            break 2;

        $slug = $row['slug'];
        $bySellerRealm[$row['ownerrealm']] = $row['slug'];

        DebugMessage("Fetching $region $slug");
        $url = GetBattleNetURL($region, "wow/auction/data/$slug");

        $json = FetchHTTP($url);
        $dta = json_decode($json, true);
        if (!isset($dta['files']))
        {
            DebugMessage("$region $slug returned no files.", E_USER_WARNING);
            continue;
        }

        $hash = preg_match('/\b[a-f0-9]{32}\b/', $dta['files'][0]['url'], $res) > 0 ? $res[0] : '';
        if ($hash == '')
        {
            DebugMessage("$region $slug had no hash in the URL: {$dta['files'][0]['url']}", E_USER_WARNING);
            continue;
        }

        $a = GetDataRealms($region, $hash);
        if ($a['slug'])
        {
            $canonicals[$a['slug']][md5(json_encode($a))] = $a;
            $fallBack[$slug] = $a['slug'];
        }
    }

    foreach ($canonicals as $canon => $results)
    {
        if (count($results) > 1)
            DebugMessage("$region $canon has ".count($results)." results: ".print_r($results, true));

        foreach ($results as $result)
            foreach ($result['realms'] as $sellerRealm)
                if (isset($bySellerRealm[$sellerRealm]))
                    $candidates[$bySellerRealm[$sellerRealm]][$canon] = count($result['realms']) + (isset($candidates[$bySellerRealm[$sellerRealm]][$canon]) ? $candidates[$bySellerRealm[$sellerRealm]][$canon] : 0);
    }

    $byCanonical = array();
    foreach ($bySlug as $row)
    {
        if (isset($candidates[$row['slug']]))
        {
            arsort($candidates[$row['slug']]);
            $c2 = array();
            $c2cnt = 0;
            foreach ($candidates[$row['slug']] as $canon => $cnt)
            {
                if ($c2cnt == 0)
                {
                    $c2cnt = $cnt;
                    $c2[] = $canon;
                }
                else if ($c2cnt == $cnt)
                    $c2[] = $canon;
            }
            sort($c2);
            $winners[$row['slug']] = $c2[0];
        }
        else
            $winners[$row['slug']] = isset($fallBack[$row['slug']]) ? $fallBack[$row['slug']] : $row['slug'];

        $byCanonical[$winners[$row['slug']]][] = $row['slug'];
    }

    heartbeat();
    if ($caughtKill)
        break;

    $stmt = $db->prepare('select ifnull(max(house),0) from tblRealm');
    $stmt->execute();
    $stmt->bind_result($maxHouse);
    $stmt->fetch();
    $stmt->close();

    foreach ($byCanonical as $canon => $slugs)
    {
        sort($slugs);
        $rep = $slugs[0];
        $candidates = array();
        foreach ($slugs as $slug)
        {
            if ($slug == $canon)
                $rep = $slug;
            if (!is_null($bySlug[$slug]['house']))
            {
                if (!isset($candidates[$bySlug[$slug]['house']]))
                    $candidates[$bySlug[$slug]['house']] = 0;
                $candidates[$bySlug[$slug]['house']]++;
            }
        }
        if (count($candidates) > 0)
        {
            asort($candidates);
            $curHouse = array_keys($candidates);
            $curHouse = array_pop($curHouse);
        }
        else
            $curHouse = ++$maxHouse;
        $houseKeys = array_keys($bySlug);
        foreach ($houseKeys as $slug)
        {
            if (in_array($slug, $slugs))
                continue;
            if ($bySlug[$slug]['house'] == $curHouse)
                $bySlug[$slug]['house'] = null;
        }
        foreach ($slugs as $slug)
        {
            if ($bySlug[$slug]['house'] != $curHouse)
            {
                DebugMessage("$region $slug changing from ".(is_null($bySlug[$slug]['house']) ? 'null' : $bySlug[$slug]['house'])." to $curHouse");
                $db->real_query(sprintf('update tblRealm set house = %d where region = \'%s\' and slug = \'%s\'', $curHouse, $db->escape_string($region), $db->escape_string($slug)));
                $bySlug[$slug]['house'] = $curHouse;
            }
        }
        $db->real_query(sprintf('update tblRealm set canonical = null where house = %d', $curHouse));
        $db->real_query(sprintf('update tblRealm set canonical = \'%s\' where house = %d and region = \'%s\' and slug = \'%s\'', $db->escape_string($canon), $curHouse, $db->escape_string($region), $db->escape_string($rep)));
    }
    $memcache->delete('realms_'.$region);
}

CleanOldHouses();
//DebugMessage('Skipped cleaning old houses!');

DebugMessage('Done! Started '.TimeDiff($startTime));

function GetDataRealms($region, $hash)
{
    heartbeat();
    $region = strtolower($region);

    $pth = __DIR__.'/realms2houses_cache';
    if (!is_dir($pth))
        DebugMessage('Could not find realms2houses_cache!', E_USER_ERROR);

    $cachePath = "$pth/$region-$hash.json";

    if (file_exists($cachePath) && (filemtime($cachePath) > (time() - 23*60*60)))
        return json_decode(file_get_contents($cachePath), true);

    $result = array('slug' => false, 'realms' => array());

    $url = sprintf('http://%s.battle.net/auction-data/%s/auctions.json', $region, $hash);
    $outHeaders = array();
    $json = FetchHTTP($url, [], $outHeaders);
    if (!$json)
    {
        DebugMessage("No data from $url, waiting 5 secs");
        http_persistent_handles_clean();
        sleep(5);
        $json = FetchHTTP($url, [], $outHeaders);
    }

    if (!$json)
    {
        DebugMessage("No data from $url, waiting 15 secs");
        http_persistent_handles_clean();
        sleep(15);
        $json = FetchHTTP($url, [], $outHeaders);
    }

    if (!$json)
    {
        if (file_exists($cachePath) && (filemtime($cachePath) > (time() - 3*24*60*60)))
        {
            DebugMessage("No data from $url, using cache");
            return json_decode(file_get_contents($cachePath), true);
        }
        DebugMessage("No data from $url, giving up");
        return $result;
    }

    $xferBytes = isset($outHeaders['X-Original-Content-Length']) ? $outHeaders['X-Original-Content-Length'] : strlen($data);
    DebugMessage("$region $hash data file ".strlen($json)." bytes".($xferBytes != strlen($json) ? (' (transfer length '.$xferBytes.', '.round($xferBytes/strlen($json)*100,1).'%)') : ''));

    if (preg_match('/"slug":"([^"?]+)"/', $json, $m))
        $result['slug'] = $m[1];

    preg_match_all('/"ownerRealm":"([^"?]+)"/', $json, $m);
    $result['realms'] = array_values(array_unique($m[1]));

    file_put_contents($cachePath, json_encode($result));

    return $result;
}

function GetRussianOwnerRealms($region)
{
    global $db, $caughtKill;

    $ruID = 0;
    $ruSlug = '';
    $ruToRun = array();
    $stmt = $db->prepare('select id, slug from tblRealm where region=? and locale=\'ru_RU\' and ownerrealm is null');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $stmt->bind_result($ruID, $ruSlug);
    while ($stmt->fetch())
    {
        heartbeat();
        if ($caughtKill)
            return;

        DebugMessage("Getting ownerrealm for russian slug $ruSlug");
        $url = GetBattleNetURL($region, 'wow/realm/status?realms='.$ruSlug.'&locale=ru_RU');
        $ruRealm = json_decode(FetchHTTP($url), true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() != JSON_ERROR_NONE)
        {
            DebugMessage("$url did not return valid JSON");
            continue;
        }

        if (!isset($ruRealm['realms']) || (count($ruRealm['realms']) == 0))
        {
            DebugMessage("$url returned no realms");
            continue;
        }

        $ruOwner = str_replace(' ', '', $ruRealm['realms'][0]['name']);
        $ruToRun[] = sprintf('update tblRealm set ownerrealm = \'%s\' where id = %d', $db->escape_string($ruOwner), $ruID);
    }
    $stmt->close();
    if ($caughtKill)
        return;

    foreach ($ruToRun as $sql)
    {
        heartbeat();
        if ($caughtKill)
            return;
        if (!$db->real_query($sql))
            DebugMessage(sprintf("%s: %s", $sql, $db->error), E_USER_WARNING);
    }
}

function GetRealmPopulation($region)
{
    global $db, $caughtKill;

    $json = FetchHTTP('https://realmpop.com/'.strtolower($region).'.json');
    if ($json == '') {
        DebugMessage('Could not get realmpop json for '.$region, E_USER_WARNING);
        return;
    }

    if ($caughtKill)
        return;

    $stats = json_decode($json, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage('json decode error for realmpop json for '.$region, E_USER_WARNING);
        return;
    }

    $stats = $stats['realms'];

    $stmt = $db->prepare('select slug, id from tblRealm where region=?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $bySlug = DBMapArray($result);
    $stmt->close();

    if ($caughtKill)
        return;

    $sqlPattern = 'update tblRealm set population = %d where id = %d';
    foreach ($stats as $slug => $o) {
        if (isset($bySlug[$slug])) {
            $sql = sprintf($sqlPattern, ($o['counts']['Alliance'] + $o['counts']['Horde']), $bySlug[$slug]['id']);
            if (!$db->real_query($sql))
                DebugMessage(sprintf("%s: %s", $sql, $db->error), E_USER_WARNING);
        }
    }

}

function CleanOldHouses()
{
    global $db, $caughtKill;

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblAuction where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblAuction where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out auctions from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblAuctionPet where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblAuctionPet where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out pet auctions from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblHouseCheck where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();
    if (count($oldIds))
        $db->real_query(sprintf('delete from tblHouseCheck where house in (%s)', implode(',',$oldIds)));

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblItemHistory where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblItemHistory where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out item history from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblItemSummary where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblItemSummary where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out item summary from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblPetHistory where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblPetHistory where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out pet history from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblPetSummary where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblPetSummary where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out pet summary from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblSnapshot where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblSnapshot where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out snapshots from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

}
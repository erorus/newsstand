<?php

chdir(__DIR__);

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

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
    $url = sprintf('https://%s.battle.net/api/wow/realm/status', strtolower($region));

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
    foreach ($realms['realms'] as &$realm)
    {
        $stmt->bind_param('issss', $nextId, $region, $realm['slug'], $realm['name'], $realm['locale']);
        $stmt->execute();
        if ($db->affected_rows > 0)
            $nextId++;
        $stmt->reset();
    }
    $stmt->close();

    $stmt = $db->prepare('select slug, house from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result);
    $stmt->close();

    $stmt = $db->prepare('select ifnull(max(house),0) from tblRealm');
    $stmt->execute();
    $stmt->bind_result($maxHouse);
    $stmt->fetch();
    $stmt->close();

    $hashes = array();
    $retries = 0;

    while ($retries++ < 10 && (count($hashes) == 0))
    {
        $started = time();
        foreach ($houses as &$realm)
        {
            heartbeat();
            if ($caughtKill)
                break 3;
            DebugMessage("Fetching $region {$realm['slug']}");
            $url = sprintf('https://%s.battle.net/api/wow/auction/data/%s', strtolower($region), $realm['slug']);

            $json = FetchHTTP($url);
            $dta = json_decode($json, true);
            if (!isset($dta['files']))
            {
                DebugMessage("$region {$realm['slug']} returned no files.", E_USER_WARNING);
                continue;
            }

            $hash = preg_match('/\b[a-f0-9]{32}\b/', $dta['files'][0]['url'], $res) > 0 ? $res[0] : '';
            if ($hash == '')
            {
                DebugMessage("$region {$realm['slug']} had no hash in the URL: {$dta['files'][0]['url']}", E_USER_WARNING);
                continue;
            }

            $modified = intval($dta['files'][0]['lastModified'], 10)/1000;
            if ($modified > $started)
            {
                DebugMessage("$region {$realm['slug']} was updated after we started. Starting over.");
                $hashes = array();
                break;
            }

            $realm['hash'] = $hash;
            if (!isset($hashes[$hash]))
                $hashes[$hash] = array();

            $hashes[$hash][] = $realm['slug'];
        }
    }

    heartbeat();
    if ($caughtKill)
        break;
    $stmt = $db->prepare('update tblRealm set house = ? where region = ? and slug = ?');
    foreach ($hashes as $hash => $slugs)
    {
        $candidates = array();
        foreach ($slugs as $slug)
            if (!is_null($houses[$slug]['house']))
            {
                if (!isset($candidates[$houses[$slug]['house']]))
                    $candidates[$houses[$slug]['house']] = 0;
                $candidates[$houses[$slug]['house']]++;
            }
        if (count($candidates) > 0)
        {
            asort($candidates);
            $curHouse = array_keys($candidates);
            $curHouse = array_pop($curHouse);
        }
        else
            $curHouse = ++$maxHouse;
        foreach ($houses as $slug => &$row)
        {
            if (in_array($slug, $slugs))
                continue;
            if ($row['house'] == $curHouse)
                $row['house'] = null;
        }
        foreach ($slugs as $slug)
        {
            if ($houses[$slug]['house'] != $curHouse)
            {
                DebugMessage("$region $slug changing from ".(is_null($houses[$slug]['house']) ? 'null' : $houses[$slug]['house'])." to $curHouse");
                $stmt->bind_param('iss', $curHouse, $region, $slug);
                $stmt->execute();
                $stmt->reset();
                $houses[$slug]['house'] = $curHouse;
            }
        }
    }
    $stmt->close();
}

CleanOldHouses();

DebugMessage('Done!');

function CleanOldHouses()
{
    global $db, $caughtKill;

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblAuction where house not in (select cast(house as signed) * -1 from tblRealm union select cast(house as signed) from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);

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

    $sql = 'select distinct house from tblHouseCheck where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    if (count($oldIds))
        $db->real_query(sprintf('delete from tblHouseCheck where house in (%s)', implode(',',$oldIds)));

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblItemHistory where house not in (select cast(house as signed) * -1 from tblRealm union select cast(house as signed) from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);

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

    $sql = 'select distinct house from tblItemSummary where house not in (select cast(house as signed) * -1 from tblRealm union select cast(house as signed) from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);

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

    $sql = 'select distinct house from tblSnapshot where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);

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
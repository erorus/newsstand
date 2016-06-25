<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/battlenet.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

function PrintDebugNoise($message) {
    echo date('Y-m-d H:i:s') . " $message\n";
}

function PrintImportantMessage($message) {
    fwrite(STDERR, date('Y-m-d H:i:s') . " $message\n");
}

PrintDebugNoise('Starting: printing detailed debugging to stdout');
PrintImportantMessage('Starting: printing important messages to stderr');

$regions = [
    'US' => 'en_US',
    'EU' => 'en_GB',
//    'CN' => 'zh_CN',
//    'TW' => 'zh_TW',
//    'KR' => 'ko_KR',
];

foreach ($regions as $region => $realmListLocale) {
    heartbeat();
    if ($caughtKill) {
        break;
    }
    if (isset($argv[1]) && $argv[1] != $region) {
        continue;
    }
    $url = GetBattleNetURL($region, 'wow/realm/status?locale=' . $realmListLocale);

    $json = \Newsstand\HTTP::Get($url);
    $realms = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
    if (json_last_error() != JSON_ERROR_NONE) {
        PrintImportantMessage("$url did not return valid JSON");
        continue;
    }

    if (!isset($realms['realms']) || (count($realms['realms']) == 0)) {
        PrintImportantMessage("$url returned no realms");
        continue;
    }

    $stmt = $db->prepare('SELECT ifnull(max(id), 0) FROM tblRealm');
    $stmt->execute();
    $stmt->bind_result($nextId);
    $stmt->fetch();
    $stmt->close();
    $nextId++;

    $seenLocales = [];

    $stmt = $db->prepare('INSERT INTO tblRealm (id, region, slug, name, locale) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=values(name), locale=values(locale)');
    foreach ($realms['realms'] as $realm) {
        $seenLocales[$realm['locale']] = true;
        $stmt->bind_param('issss', $nextId, $region, $realm['slug'], $realm['name'], $realm['locale']);
        $stmt->execute();
        if ($db->affected_rows > 0) {
            $nextId++;
        }
        $stmt->reset();
    }
    $stmt->close();

    $challengeRealmsHtml = \Newsstand\HTTP::Get(sprintf('http://%s.battle.net/wow/en/challenge/', $region));
    if (preg_match('/<select [^>]*\bid="realm-select"[^>]*>([\w\W]+?)<\/select>/', $challengeRealmsHtml, $res2)) {
        if (($c = preg_match_all('/<option [^>]*\bvalue="([^"]+)"[^>]*>([^<]+)<\/option>/', $res2[1], $res)) > 0) {
            $stmt = $db->prepare('INSERT IGNORE INTO tblRealm (id, region, slug, name) VALUES (?, ?, ?, ?)');
            for ($x = 0; $x < $c; $x++) {
                if ($res[1][$x] == 'all') {
                    continue;
                }
                $stmt->bind_param('isss', $nextId, $region, $res[1][$x], $res[2][$x]);
                $stmt->execute();
                if ($db->affected_rows > 0) {
                    PrintImportantMessage("New $region realm from challenge mode page: " . $res[2][$x] . " (" . $res[1][$x] . ')');
                    $nextId++;
                }
                $stmt->reset();
            }
            $stmt->close();
        }
    }
    unset($challengeRealmsHtml, $res, $res2, $c, $x);

    $seenLocales = array_keys($seenLocales);
    foreach ($seenLocales as $locale) {
        if ($locale == $realmListLocale) {
            continue;
        }
        if ($caughtKill) {
            break;
        }
        GetLocalizedOwnerRealms($region, $locale);
    }
    if ($caughtKill) {
        break;
    }

    if (in_array($region, ['US','EU'])) {
        GetRealmPopulation($region);
        if ($caughtKill) {
            break;
        }
    }

    $stmt = $db->prepare('SELECT slug, house, name, ifnull(ownerrealm, replace(name, \' \', \'\')) AS ownerrealm FROM tblRealm WHERE region = ? AND locale is not null');
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

    foreach ($bySlug as $row) {
        heartbeat();
        if ($caughtKill) {
            break 2;
        }

        $slug = $row['slug'];
        $bySellerRealm[$row['ownerrealm']] = $row['slug'];

        PrintDebugNoise("Fetching $region $slug");
        $url = GetBattleNetURL($region, "wow/auction/data/".urlencode($slug));

        $json = \Newsstand\HTTP::Get($url);
        $dta = json_decode($json, true);
        if (!isset($dta['files'])) {
            PrintImportantMessage("$region $slug returned no files.");
            continue;
        }

        $hash = preg_match('/\b[a-f0-9]{32}\b/', $dta['files'][0]['url'], $res) > 0 ? $res[0] : '';
        if ($hash == '') {
            PrintImportantMessage("$region $slug had no hash in the URL: {$dta['files'][0]['url']}");
            continue;
        }

        $a = GetDataRealms($region, $hash);
        if ($a['slug']) {
            $canonicals[$a['slug']][md5(json_encode($a))] = $a;
            $fallBack[$slug] = $a['slug'];
        }
    }

    foreach ($canonicals as $canon => $results) {
        if (count($results) > 1) {
            PrintImportantMessage("$region $canon has " . count($results) . " results: " . print_r($results, true));
        }

        foreach ($results as $result) {
            foreach ($result['realms'] as $sellerRealm) {
                if (isset($bySellerRealm[$sellerRealm])) {
                    $candidates[$bySellerRealm[$sellerRealm]][$canon] = count($result['realms']) + (isset($candidates[$bySellerRealm[$sellerRealm]][$canon]) ? $candidates[$bySellerRealm[$sellerRealm]][$canon] : 0);
                }
            }
        }
    }

    $byCanonical = array();
    foreach ($bySlug as $row) {
        if (isset($candidates[$row['slug']])) {
            arsort($candidates[$row['slug']]);
            $c2 = array();
            $c2cnt = 0;
            foreach ($candidates[$row['slug']] as $canon => $cnt) {
                if ($c2cnt == 0) {
                    $c2cnt = $cnt;
                    $c2[] = $canon;
                } else {
                    if ($c2cnt == $cnt) {
                        $c2[] = $canon;
                    }
                }
            }
            sort($c2);
            $winners[$row['slug']] = $c2[0];
        } else {
            $winners[$row['slug']] = isset($fallBack[$row['slug']]) ? $fallBack[$row['slug']] : $row['slug'];
        }

        $byCanonical[$winners[$row['slug']]][] = $row['slug'];
    }

    heartbeat();
    if ($caughtKill) {
        break;
    }

    $stmt = $db->prepare('SELECT ifnull(max(house),0) FROM tblRealm');
    $stmt->execute();
    $stmt->bind_result($maxHouse);
    $stmt->fetch();
    $stmt->close();

    foreach ($byCanonical as $canon => $slugs) {
        sort($slugs);
        $rep = $slugs[0];
        $candidates = array();
        foreach ($slugs as $slug) {
            if ($slug == $canon) {
                $rep = $slug;
            }
            if (!is_null($bySlug[$slug]['house'])) {
                if (!isset($candidates[$bySlug[$slug]['house']])) {
                    $candidates[$bySlug[$slug]['house']] = 0;
                }
                $candidates[$bySlug[$slug]['house']]++;
            }
        }
        if (count($candidates) > 0) {
            asort($candidates);
            $curHouse = array_keys($candidates);
            $curHouse = array_pop($curHouse);
        } else {
            $curHouse = ++$maxHouse;
        }
        $houseKeys = array_keys($bySlug);
        foreach ($houseKeys as $slug) {
            if (in_array($slug, $slugs)) {
                continue;
            }
            if ($bySlug[$slug]['house'] == $curHouse) {
                $bySlug[$slug]['house'] = null;
            }
        }
        foreach ($slugs as $slug) {
            if ($bySlug[$slug]['house'] != $curHouse) {
                if (!MCHouseLock($curHouse) || !MCHouseLock($bySlug[$slug]['house'])) {
                    break;
                }
                PrintImportantMessage("$region $slug changing from " . (is_null($bySlug[$slug]['house']) ? 'null' : $bySlug[$slug]['house']) . " to $curHouse");
                DBQueryWithError($db, sprintf('UPDATE tblRealm SET house = %d WHERE region = \'%s\' AND slug = \'%s\'', $curHouse, $db->escape_string($region), $db->escape_string($slug)));
                $bySlug[$slug]['house'] = $curHouse;
            }
        }
        if (MCHouseLock($curHouse)) {
            DBQueryWithError($db, sprintf('UPDATE tblRealm SET canonical = NULL WHERE house = %d', $curHouse));
            DBQueryWithError($db, sprintf('UPDATE tblRealm SET canonical = \'%s\' WHERE house = %d AND region = \'%s\' AND slug = \'%s\'', $db->escape_string($canon), $curHouse, $db->escape_string($region), $db->escape_string($rep)));
            MCHouseUnlock($curHouse);
        } else {
            PrintImportantMessage("Could not lock $curHouse to set canonical to $canon");
        }
        MCHouseUnlock();
    }
    $memcache->delete('realms_' . $region);
}

//CleanOldHouses();
//DebugMessage('Skipped cleaning old houses!');

PrintImportantMessage('Done! Started ' . TimeDiff($startTime));

function GetDataRealms($region, $hash)
{
    heartbeat();
    $region = strtolower($region);

    $pth = __DIR__ . '/realms2houses_cache';
    if (!is_dir($pth)) {
        DebugMessage('Could not find realms2houses_cache!', E_USER_ERROR);
    }

    $cachePath = "$pth/$region-$hash.json";

    if (file_exists($cachePath) && (filemtime($cachePath) > (time() - 23 * 60 * 60))) {
        return json_decode(file_get_contents($cachePath), true);
    }

    $result = array('slug' => false, 'realms' => array());

    $url = sprintf('http://%s.battle.net/auction-data/%s/auctions.json', $region, $hash);
    $outHeaders = array();
    $json = \Newsstand\HTTP::Get($url, [], $outHeaders);
    if (!$json) {
        PrintDebugNoise("No data from $url, waiting 5 secs");
        \Newsstand\HTTP::AbandonConnections();
        sleep(5);
        $json = \Newsstand\HTTP::Get($url, [], $outHeaders);
    }

    if (!$json) {
        PrintDebugNoise("No data from $url, waiting 15 secs");
        \Newsstand\HTTP::AbandonConnections();
        sleep(15);
        $json = \Newsstand\HTTP::Get($url, [], $outHeaders);
    }

    if (!$json) {
        if (file_exists($cachePath) && (filemtime($cachePath) > (time() - 3 * 24 * 60 * 60))) {
            PrintDebugNoise("No data from $url, using cache");
            return json_decode(file_get_contents($cachePath), true);
        }
        PrintImportantMessage("No data from $url, giving up");
        return $result;
    }

    $xferBytes = isset($outHeaders['X-Original-Content-Length']) ? $outHeaders['X-Original-Content-Length'] : strlen($json);
    PrintDebugNoise("$region $hash data file " . strlen($json) . " bytes" . ($xferBytes != strlen($json) ? (' (transfer length ' . $xferBytes . ', ' . round($xferBytes / strlen($json) * 100, 1) . '%)') : ''));

    $realmSectionCount = preg_match('/"realms":\s*(\[[^\]]+\])/', $json, $realmMatch);
    if ($c = preg_match_all('/"slug":\s*"([^"?]+)"/', $realmSectionCount ? $realmMatch[1] : $json, $m)) {
        $slugs = [];
        for ($x = 0; $x < $c; $x++) {
            $slugs[$m[1][$x]] = true;
        }
        $slugs = array_keys($slugs);
        sort($slugs);
        $result['slug'] = array_shift($slugs);
    }

    if ($result['slug'] === false) {
        PrintImportantMessage("No slug found in $region $hash\n" . substr($json, 0, 2000));
    }

    $seenOwnerRealms = [];
    $pos = -1;
    while (($pos = strpos($json, '"ownerRealm":', $pos+1)) !== false) {
        $firstQuote = strpos($json, '"', $pos + 13);
        $nextQuote = strpos($json, '"', $firstQuote + 1);
        $ownerRealm = substr($json, $firstQuote+1, $nextQuote - $firstQuote - 1);
        $seenOwnerRealms[$ownerRealm] = true;
    }
    ksort($seenOwnerRealms);
    $result['realms'] = array_keys($seenOwnerRealms);

    file_put_contents($cachePath, json_encode($result));

    return $result;
}

function GetLocalizedOwnerRealms($region, $locale)
{
    global $db, $caughtKill;

    $realmId = 0;
    $slug = '';
    $sqlToRun = array();
    $stmt = $db->prepare('SELECT id, slug FROM tblRealm WHERE region=? AND locale=? AND ownerrealm IS NULL');
    $stmt->bind_param('ss', $region, $locale);
    $stmt->execute();
    $stmt->bind_result($realmId, $slug);
    while ($stmt->fetch()) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        PrintDebugNoise("Getting ownerrealm for $locale slug $slug");
        $url = GetBattleNetURL($region, 'wow/realm/status?realms=' . urlencode($slug) . '&locale=' . $locale);
        $realmJson = json_decode(\Newsstand\HTTP::Get($url), true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() != JSON_ERROR_NONE) {
            PrintDebugNoise("$url did not return valid JSON");
            continue;
        }

        if (!isset($realmJson['realms']) || (count($realmJson['realms']) == 0)) {
            PrintDebugNoise("$url returned no realms");
            continue;
        }

        if (count($realmJson['realms']) > 1) {
            PrintImportantMessage("Region $region slug $slug returned ".count($realmJson['realms'])." realms. $url");
        }

        $ownerRealm = str_replace(' ', '', $realmJson['realms'][0]['name']);
        $sqlToRun[] = sprintf('UPDATE tblRealm SET ownerrealm = \'%s\' WHERE id = %d', $db->escape_string($ownerRealm), $realmId);
    }
    $stmt->close();
    if ($caughtKill) {
        return;
    }

    foreach ($sqlToRun as $sql) {
        heartbeat();
        if ($caughtKill) {
            return;
        }
        if (!$db->real_query($sql)) {
            PrintImportantMessage(sprintf("%s: %s", $sql, $db->error), E_USER_WARNING);
        }
    }
}

function GetRealmPopulation($region)
{
    global $db, $caughtKill;

    $json = \Newsstand\HTTP::Get('https://realmpop.com/' . strtolower($region) . '.json');
    if (!$json) {
        DebugMessage('Could not get realmpop json for ' . $region, E_USER_WARNING);
        return;
    }

    if ($caughtKill) {
        return;
    }

    $stats = json_decode($json, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage('json decode error for realmpop json for ' . $region, E_USER_WARNING);
        return;
    }

    $stats = $stats['realms'];

    $stmt = $db->prepare('SELECT slug, id FROM tblRealm WHERE region=?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $bySlug = DBMapArray($result);
    $stmt->close();

    if ($caughtKill) {
        return;
    }

    $sqlPattern = 'UPDATE tblRealm SET population = %d WHERE id = %d';
    foreach ($stats as $slug => $o) {
        if (isset($bySlug[$slug])) {
            $sql = sprintf($sqlPattern, ($o['counts']['Alliance'] + $o['counts']['Horde']), $bySlug[$slug]['id']);
            if (!$db->real_query($sql)) {
                DebugMessage(sprintf("%s: %s", $sql, $db->error), E_USER_WARNING);
            }
        }
    }

}

function CleanOldHouses()
{
    global $db, $caughtKill;

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblAuction WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'DELETE FROM tblAuction WHERE house = %d LIMIT 2000';
    foreach ($oldIds as $oldId) {
        if ($caughtKill) {
            return;
        }

        PrintImportantMessage('Clearing out auctions from old house ' . $oldId);

        while (!$caughtKill) {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0) {
                break;
            }
        }
    }

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblHouseCheck WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();
    if (count($oldIds)) {
        $db->real_query(sprintf('DELETE FROM tblHouseCheck WHERE house IN (%s)', implode(',', $oldIds)));
    }

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblItemHistoryHourly WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'DELETE FROM tblItemHistoryHourly WHERE house = %d LIMIT 2000';
    foreach ($oldIds as $oldId) {
        if ($caughtKill) {
            return;
        }

        PrintImportantMessage('Clearing out item history from old house ' . $oldId);

        while (!$caughtKill) {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0) {
                break;
            }
        }
    }

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblItemSummary WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'DELETE FROM tblItemSummary WHERE house = %d LIMIT 2000';
    foreach ($oldIds as $oldId) {
        if ($caughtKill) {
            return;
        }

        PrintImportantMessage('Clearing out item summary from old house ' . $oldId);

        while (!$caughtKill) {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0) {
                break;
            }
        }
    }

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblPetHistory WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'DELETE FROM tblPetHistory WHERE house = %d LIMIT 2000';
    foreach ($oldIds as $oldId) {
        if ($caughtKill) {
            return;
        }

        PrintImportantMessage('Clearing out pet history from old house ' . $oldId);

        while (!$caughtKill) {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0) {
                break;
            }
        }
    }

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblPetSummary WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'DELETE FROM tblPetSummary WHERE house = %d LIMIT 2000';
    foreach ($oldIds as $oldId) {
        if ($caughtKill) {
            return;
        }

        PrintImportantMessage('Clearing out pet summary from old house ' . $oldId);

        while (!$caughtKill) {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0) {
                break;
            }
        }
    }

    if ($caughtKill) {
        return;
    }

    $sql = 'SELECT DISTINCT house FROM tblSnapshot WHERE house NOT IN (SELECT DISTINCT house FROM tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'DELETE FROM tblSnapshot WHERE house = %d LIMIT 2000';
    foreach ($oldIds as $oldId) {
        if ($caughtKill) {
            return;
        }

        PrintImportantMessage('Clearing out snapshots from old house ' . $oldId);

        while (!$caughtKill) {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0) {
                break;
            }
        }
    }
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = $db->query($sql);
    if (!$queryOk) {
        DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}
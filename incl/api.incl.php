<?php

require_once('memcache.incl.php');
require_once('incl.php');

define('API_VERSION', 82);
define('THROTTLE_PERIOD', 3600); // seconds
define('THROTTLE_MAXHITS', 200);
define('BANLIST_CACHEKEY', 'banlist_cidrs4');
define('BANLIST_FILENAME', __DIR__ . '/banlist.txt');
define('BANLIST_USE_DNSBL', false);

if ((PHP_SAPI != 'cli') && (($inMaintenance = APIMaintenance()) !== false)) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-type: application/json');
    header('Cache-Control: no-cache');

    echo json_encode(['maintenance' => $inMaintenance]);
    exit;
}

function json_return($json)
{
    if ($json === false) {
        header('HTTP/1.1 400 Bad Request');
        exit;
    }

    ini_set('zlib.output_compression', 1);

    if (!is_string($json)) {
        $json = json_encode($json, JSON_NUMERIC_CHECK);
    }

    header('Content-type: application/json');
    echo $json;
    exit;
}

function GetLocale()
{
    global $VALID_LOCALES;
    $localeKeys = array_flip($VALID_LOCALES);

    if (isset($_POST['locale']) && isset($localeKeys[$_POST['locale']])) {
        return $VALID_LOCALES[$localeKeys[$_POST['locale']]];
    }
    if (isset($_GET['locale']) && isset($localeKeys[$_GET['locale']])) {
        return $VALID_LOCALES[$localeKeys[$_GET['locale']]];
    }
    return 'enus';
}

function LocaleColumns($colName, $usesPattern = false)
{
    static $cols = [];
    $key = ($usesPattern ? 1 : 0) . $colName;
    if (isset($cols[$key])) {
        return $cols[$key];
    }

    global $VALID_LOCALES;

    $tr = '';
    foreach ($VALID_LOCALES as $locId) {
        $tr .= ($tr == '' ? '' : ', ') . ($usesPattern ? sprintf($colName, '_' . $locId) : ($colName . '_' . $locId));
    }

    $cols[$key] = $tr;
    return $tr;
}

function GetItemNames($itemIds, $renamedTo = false)
{
    global $VALID_LOCALES;

    $cacheKeyPrefix = 'itemnames_';
    $cacheKeyPrefixLen = strlen($cacheKeyPrefix);

    // assemble memcache keys, and fetch into $names
    $keys = [];
    foreach ($itemIds as $itemId) { // assumes $itemIds are unique
        $keys[] = $cacheKeyPrefix.intval($itemId, 10);
    }
    $names = MCGet($keys);

    // find which keys didn't come back, and fix $names so its keys are bare item IDs
    $keyNames = array_keys($names);
    $missingKeys = array_diff($keys, $keyNames);
    foreach ($keyNames as $keyName) {
        $names[substr($keyName, $cacheKeyPrefixLen)] = $names[$keyName];
        unset($names[$keyName]);
    }
    unset($keyNames);

    if (count($missingKeys)) {
        // split up missing keys into chunks of bare item IDs
        $missingIds = [];
        $chunk = 0;
        $chunkCount = 0;
        foreach ($missingKeys as $missingKey) {
            $missingIds[$chunk][] = substr($missingKey, $cacheKeyPrefixLen);
            if (++$chunkCount >= 100) {
                $chunk++;
                $chunkCount = 0;
            }
        }
        unset($missingKeys, $chunk, $chunkCount);

        // fetch missing IDs, and store them into memcache
        $db = DBConnect();
        $sql = 'select id, '.LocaleColumns('name').' from tblDBCItem where id in (%s)';
        foreach ($missingIds as $missingChunk) {
            $stmt = $db->prepare(sprintf($sql, implode(',', $missingChunk)));
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $id = array_shift($row);
                $names[$id] = $row;
                MCSet($cacheKeyPrefix.$id, $names[$id], 86400);
            }
            $result->close();
            $stmt->close();

            foreach ($missingChunk as $id) {
                if (!isset($names[$id])) { // if this id didn't come back from the db
                    foreach ($VALID_LOCALES as $locId) {
                        $names[$id]['name_'.$locId] = 'Item #'.$id;
                    }
                    MCSet($cacheKeyPrefix.$id, $names[$id], 1800);
                }
            }
        }
    }

    if ($renamedTo && $renamedTo != 'name') {
        foreach ($names as &$row) {
            $keys = array_keys($row);
            foreach ($keys as $key) {
                $row[str_replace('name', $renamedTo, $key)] = $row[$key];
                unset($row[$key]);
            }
        }
    }

    return $names;
}

function GetItemBonusNames($bonusGroups, $renamedTo = false)
{
    $results = [];
    foreach ($bonusGroups as $bonusesKey) {
        $c = preg_match_all('/\d+/', $bonusesKey, $res);
        $bonuses = [];
        for ($x = 0; $x < $c; $x++) {
            if ($res[0][$x]) {
                $bonuses[] = $res[0][$x];
            }
        }

        $bonuses = implode(',', array_filter($bonuses, 'is_numeric'));

        $cacheKey = 'itembonusnames2_' . $bonuses;

        $names = MCGet($cacheKey);

        if ($names === false) {
            if ($bonuses) {
                $db = DBConnect();

                $sql = 'select ' . LocaleColumns('ifnull(group_concat(distinct ind.desc%1$s order by ib.namepriority desc separator \' \'), \'\') bonusname%1$s', true);
                $sql .= ' from tblDBCItemBonus ib left join tblDBCItemNameDescription ind on ib.nameid = ind.id where ib.id in (' . $bonuses . ')';
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                $names = $result->fetch_assoc();
                $result->close();
                $stmt->close();
            }
            if ($names === false) {
                $names = [];
            }
            $names = array_filter($names);
            MCSet($cacheKey, $names, 86400);
        }

        if ($renamedTo && $renamedTo != 'bonusname') {
            $keys = array_keys($names);
            foreach ($keys as $key) {
                $names[str_replace('bonusname', $renamedTo, $key)] = $names[$key];
                unset($names[$key]);
            }
        }

        $results[$bonusesKey] = $names;
    }

    return $results;
}

function GetItemBonusTags($bonusGroups, $renamedTo = false)
{
    $results = [];
    foreach ($bonusGroups as $bonusesKey) {
        $c = preg_match_all('/\d+/', $bonusesKey, $res);
        $bonuses = [];
        for ($x = 0; $x < $c; $x++) {
            if ($res[0][$x]) {
                $bonuses[] = $res[0][$x];
            }
        }

        $bonuses = implode(',', array_filter($bonuses, 'is_numeric'));

        $cacheKey = 'itembonustags2_' . $bonuses;

        $names = MCGet($cacheKey);

        if ($names === false) {
            if ($bonuses) {
                $db = DBConnect();

                $sql = 'select ' . LocaleColumns('ifnull(group_concat(distinct ind.`desc%1$s` order by ib.tagpriority separator \' \'), \'\') bonustag%1$s', true);
                $sql .= ' from tblDBCItemBonus ib left join tblDBCItemNameDescription ind on ib.tagid = ind.id where ib.id in (' . $bonuses . ')';
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                $names = $result->fetch_assoc();
                $result->close();
                $stmt->close();
            }
            if ($names === false) {
                $names = [];
            }
            $names = array_filter($names);
            MCSet($cacheKey, $names, 86400);
        }

        if ($renamedTo && $renamedTo != 'bonustag') {
            $keys = array_keys($names);
            foreach ($keys as $key) {
                $names[str_replace('bonustag', $renamedTo, $key)] = $names[$key];
                unset($names[$key]);
            }
        }

        $results[$bonusesKey] = $names;
    }

    return $results;
}

function GetItemBonusTagsByTag($tagGroups, $renamedTo = false)
{
    $results = [];
    foreach ($tagGroups as $tagsKey) {
        $c = preg_match_all('/\d+/', $tagsKey, $res);
        $tagIds = [];
        for ($x = 0; $x < $c; $x++) {
            if ($res[0][$x]) {
                $tagIds[] = $res[0][$x];
            }
        }

        $tagIds = implode(',', array_filter($tagIds, 'is_numeric'));

        $cacheKey = 'itembonustagsbytag_' . $tagIds;

        $names = MCGet($cacheKey);

        if ($names === false) {
            if ($tagIds) {
                $db = DBConnect();

                $sql = 'select ' . LocaleColumns('group_concat(distinct `desc%1$s` separator \' \') bonustag%1$s', true);
                $sql .= ' from tblDBCItemNameDescription where id in (' . $tagIds . ')';
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                $names = $result->fetch_assoc();
                $result->close();
                $stmt->close();
            }
            if ($names === false) {
                $names = [];
            }
            $names = array_filter($names);
            MCSet($cacheKey, $names, 86400);
        }

        if ($renamedTo && $renamedTo != 'bonustag') {
            $keys = array_keys($names);
            foreach ($keys as $key) {
                $names[str_replace('bonustag', $renamedTo, $key)] = $names[$key];
                unset($names[$key]);
            }
        }

        $results[$tagsKey] = $names;
    }

    return $results;
}

function GetRandEnchantNames($randIds, $renamedTo = false)
{
    $cacheKeyPrefix = 'randenchantnames2_';
    $cacheKeyPrefixLen = strlen($cacheKeyPrefix);

    // assemble memcache keys, and fetch into $names
    $keys = [];
    foreach ($randIds as $randId) { // assumes $randIds are unique
        $keys[] = $cacheKeyPrefix.intval($randId, 10);
    }
    $names = MCGet($keys);

    // find which keys didn't come back, and fix $names so its keys are bare item IDs
    $keyNames = array_keys($names);
    $missingKeys = array_diff($keys, $keyNames);
    foreach ($keyNames as $keyName) {
        $names[substr($keyName, $cacheKeyPrefixLen)] = $names[$keyName];
        unset($names[$keyName]);
    }
    unset($keyNames);

    if (count($missingKeys)) {
        // split up missing keys into chunks of bare rand IDs
        $missingIds = [];
        $chunk = 0;
        $chunkCount = 0;
        foreach ($missingKeys as $missingKey) {
            $missingIds[$chunk][] = substr($missingKey, $cacheKeyPrefixLen);
            if (++$chunkCount >= 100) {
                $chunk++;
                $chunkCount = 0;
            }
        }
        unset($missingKeys, $chunk, $chunkCount);

        // fetch missing IDs, and store them into memcache
        $db = DBConnect();
        $sql = 'select id, '.LocaleColumns('name%1$s randname%1$s', true).' from tblDBCRandEnchants where id in (%s)';
        foreach ($missingIds as $missingChunk) {
            $stmt = $db->prepare(sprintf($sql, implode(',', $missingChunk)));
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $id = array_shift($row);
                $names[$id] = array_filter($row);
                MCSet($cacheKeyPrefix.$id, $names[$id], 86400);
            }
            $result->close();
            $stmt->close();

            foreach ($missingChunk as $id) {
                if (!isset($names[$id])) { // if this id didn't come back from the db
                    $names[$id] = [];
                    MCSet($cacheKeyPrefix.$id, $names[$id], 1800);
                }
            }
        }
    }

    if ($renamedTo && $renamedTo != 'randname') {
        $keys = array_keys($names);
        foreach ($keys as $key) {
            $names[str_replace('randname', $renamedTo, $key)] = $names[$key];
            unset($names[$key]);
        }
    }

    return $names;
}

function GetPetNames($speciesIds, $renamedTo = false)
{
    global $VALID_LOCALES;

    $cacheKeyPrefix = 'petnames_';
    $cacheKeyPrefixLen = strlen($cacheKeyPrefix);

    // assemble memcache keys, and fetch into $names
    $keys = [];
    foreach ($speciesIds as $species) { // assumes $speciesIds are unique
        $keys[] = $cacheKeyPrefix.intval($species, 10);
    }
    $names = MCGet($keys);

    // find which keys didn't come back, and fix $names so its keys are bare item IDs
    $keyNames = array_keys($names);
    $missingKeys = array_diff($keys, $keyNames);
    foreach ($keyNames as $keyName) {
        $names[substr($keyName, $cacheKeyPrefixLen)] = $names[$keyName];
        unset($names[$keyName]);
    }
    unset($keyNames);

    if (count($missingKeys)) {
        // split up missing keys into chunks of bare species IDs
        $missingIds = [];
        $chunk = 0;
        $chunkCount = 0;
        foreach ($missingKeys as $missingKey) {
            $missingIds[$chunk][] = substr($missingKey, $cacheKeyPrefixLen);
            if (++$chunkCount >= 100) {
                $chunk++;
                $chunkCount = 0;
            }
        }
        unset($missingKeys, $chunk, $chunkCount);

        // fetch missing IDs, and store them into memcache
        $db = DBConnect();
        $sql = 'select id, '.LocaleColumns('name').' from tblDBCPet where id in (%s)';
        foreach ($missingIds as $missingChunk) {
            $stmt = $db->prepare(sprintf($sql, implode(',', $missingChunk)));
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $id = array_shift($row);
                $names[$id] = $row;
                MCSet($cacheKeyPrefix.$id, $names[$id], 86400);
            }
            $result->close();
            $stmt->close();

            foreach ($missingChunk as $id) {
                if (!isset($names[$id])) { // if this id didn't come back from the db
                    foreach ($VALID_LOCALES as $locId) {
                        $names[$id]['name_'.$locId] = 'Species #'.$id;
                    }
                    MCSet($cacheKeyPrefix.$id, $names[$id], 1800);
                }
            }
        }
    }

    if ($renamedTo && $renamedTo != 'name') {
        $keys = array_keys($names);
        foreach ($keys as $key) {
            $names[str_replace('name', $renamedTo, $key)] = $names[$key];
            unset($names[$key]);
        }
    }

    return $names;
}

function PopulateLocaleCols(&$rows, $calls, $dry = false) {
    if (!is_array($rows)) {
        return;
    }

    $c = count($calls);
    if ($c == 0) {
        return;
    }
    $keys = [];
    $vals = [];
    for ($x = 0; $x < $c; $x++) {
        $keys[$x] = [];
        $vals[$x] = [];
    }

    foreach ($rows as $row) {
        for ($x = 0; $x < $c; $x++) {
            if (isset($row[$calls[$x]['key']])) {
                $keys[$x][$row[$calls[$x]['key']]] = true;
            }
        }
    }

    if ($dry) {
        $rows = [
            'data'    => $rows,
            'hydrate' => []
        ];
    }
    
    for ($x = 0; $x < $c; $x++) {
        $keys[$x] = array_keys($keys[$x]);
        $funcName = $calls[$x]['func'];
        $vals[$x] = array_filter($funcName($keys[$x], isset($calls[$x]['name']) ? $calls[$x]['name'] : false));
        unset($keys[$x]);
        
        if ($dry && count($vals[$x])) {
            $rows['hydrate'][] = [
                'key'    => $calls[$x]['key'],
                'values' => $vals[$x],
            ];
            unset($vals[$x]);
        }
    }

    if ($dry) {
        return;
    }
    
    foreach ($rows as &$row) {
        if ($c <= 4) {
            $row = array_merge(
                $row,
                (          isset($row[$calls[0]['key']]) && isset($vals[0][$row[$calls[0]['key']]])) ? $vals[0][$row[$calls[0]['key']]] : [],
                ($c > 1 && isset($row[$calls[1]['key']]) && isset($vals[1][$row[$calls[1]['key']]])) ? $vals[1][$row[$calls[1]['key']]] : [],
                ($c > 2 && isset($row[$calls[2]['key']]) && isset($vals[2][$row[$calls[2]['key']]])) ? $vals[2][$row[$calls[2]['key']]] : [],
                ($c > 3 && isset($row[$calls[3]['key']]) && isset($vals[3][$row[$calls[3]['key']]])) ? $vals[3][$row[$calls[3]['key']]] : []
            );
        } else {
            for ($x = 0; $x < $c; $x++) {
                if (isset($row[$calls[$x]['key']]) && isset($vals[$x][$row[$calls[$x]['key']]])) {
                    $row = array_merge($row, $vals[$x][$row[$calls[$x]['key']]]);
                }
            }
        }
    }
    unset($row);
}

// only used for old non-/api/ pages
function GetSiteRegion()
{
    return (isset($_SERVER['HTTP_HOST']) && (preg_match('/^eu./i', $_SERVER['HTTP_HOST']) > 0)) ? 'EU' : 'US';
}

function GetRealmById($realm) {
    $key = 'realm_by_id_' . $realm;

    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $sql = 'select * from tblRealm where id = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $realm);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    if (count($tr)) {
        $tr = $tr[0];
    }
    $stmt->close();

    MCSet($key, $tr);

    return $tr;
}

function GetRealms($region)
{
    global $db;

    $cacheKey = 'realms2_' . $region;

    if ($realms = MCGet($cacheKey)) {
        return $realms;
    }

    DBConnect();

    $stmt = $db->prepare('SELECT r.* FROM tblRealm r WHERE region = ? AND (locale IS NOT NULL OR (locale IS NULL AND exists (SELECT 1 FROM tblSnapshot s WHERE s.house=r.house) AND (SELECT count(*) FROM tblRealm r2 WHERE r2.house=r.house AND r2.id != r.id AND r2.locale IS NOT NULL) = 0))');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result);
    $stmt->close();

    MCSet($cacheKey, $realms);

    return $realms;
}

function GetRegion($house)
{
    global $db;

    $house = abs($house);
    if (($tr = MCGet('getregion_' . $house)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = 'SELECT max(region) FROM `tblRealm` WHERE house=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $region = null;
    $stmt->bind_result($region);
    if (!$stmt->fetch()) {
        $region = null;
    }
    $stmt->close();

    MCSet('getregion_' . $house, $region, 24 * 60 * 60);

    return $region;
}

function GetHouse($realm)
{
    global $db;

    if (($tr = MCGet('gethouse_' . $realm)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = 'SELECT house FROM `tblRealm` WHERE id=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $realm);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('gethouse_' . $realm, $tr);

    return $tr;
}

function BotCheck($returnReason = false)
{
    if ((PHP_SAPI == 'cli') || (!isset($_SERVER['REMOTE_ADDR']))) {
        return false;
    }

    $checked = $_SERVER['REMOTE_ADDR'];
    $reason = '';
    $banned = IPIsBanned($checked, $reason);

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $filterOpts = array(
            'default' => false,
            'flags' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        $otherIPs = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'], 6);
        if (count($otherIPs) == 6) {
            array_pop($otherIPs);
        }
        while (count($otherIPs) && !$banned) {
            if ($otherIP = filter_var(trim(array_shift($otherIPs)), FILTER_VALIDATE_IP, $filterOpts)) {
                $banned |= IPIsBanned($checked = $otherIP, $reason);
            }
        }
    }

    if ($returnReason) {
        return [
            'isbanned' => $banned,
            'ip' => $banned ? $checked : '',
            'reason' => $banned ? $reason : '',
        ];
    }

    if ($banned) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    $c = UserThrottleCount();
    if ($c > THROTTLE_MAXHITS * 2) {
        BanIP();
    } else {
        if ($c > THROTTLE_MAXHITS) {
            header('Expires: 0');
            json_return(array('captcha' => CaptchaDetails()));
        }
    }
}

function BanIP($ip = false)
{
    $exitAfter = false;
    $addedBan = false;

    if (($ip === false) && (PHP_SAPI != 'cli')){
        $ip = $_SERVER['REMOTE_ADDR'];
        $exitAfter = true;
    }
    if (!$ip) {
        return false;
    }
    $ip = trim(strtolower($ip));

    if (!IPIsBanned($ip)) {
        file_put_contents(BANLIST_FILENAME, "\n$ip # " . date('Y-m-d H:i:s'), FILE_APPEND | LOCK_EX);
        MCDelete(BANLIST_CACHEKEY);
        MCDelete(BANLIST_CACHEKEY . '_' . $ip);
        $addedBan = true;
    }

    if ($exitAfter) {
        header('HTTP/1.1 429 Too Many Requests');
        exit;
    }

    return $addedBan;
}

function IPInDNSBL($ip)
{
    if (!BANLIST_USE_DNSBL) {
        return false;
    }
    $ipv4 = (strpos($ip, ':') === false);
    if (!$ipv4) {
        return false;
    }

    $parts = explode('.', $ip);
    if (count($parts) != 4) {
        return false;
    }

    $lookup = implode('.', array_reverse($parts));
    $domains = [
        //'cbl' => 'cbl.abuseat.org.',
        'sorbs' => 'proxies.dnsbl.sorbs.net.',
    ];

    foreach ($domains as $r => $domain) {
        $domain = "$lookup.$domain";
        if (gethostbyname($domain) != $domain) {
            return $r;
        }
    }

    return false;
}

function IPIsBanned($ip = false, &$result = '')
{
    if ($ip === false) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (!$ip) {
        return false;
    }
    $ip = trim(strtolower($ip));

    $cacheKey = BANLIST_CACHEKEY . '_' . $ip;
    $result = MCGet($cacheKey);
    if ($result !== false) {
        return $result != 'no';
    }

    $banList = MCGet(BANLIST_CACHEKEY);
    if ($banList === false) {
        $banList = [];
        if (file_exists(BANLIST_FILENAME)) {
            $fh = fopen(BANLIST_FILENAME, 'r');
            if ($fh) {
                while (($line = fgets($fh, 4096)) !== false) {
                    if (preg_match('/^\s*([a-f\d\.:\/]+)/', $line, $res) > 0) {
                        $banList[] = strtolower($res[0]);
                    }
                }
            }
            fclose($fh);
        }
        MCSet(BANLIST_CACHEKEY, $banList, 86400);
    }

    $ipv4 = (strpos($ip, ':') === false);
    if ($ipv4) {
        $longIp = ip2long($ip);
        $result = IPInDNSBL($ip);
    } else {
        $binIp = inet_pton($ip);
    }

    if (!$result) {
        for ($x = 0; $x < count($banList); $x++) {
            if (strpos($banList[$x], '/') !== false) {
                // mask
                list($subnet, $mask) = explode('/', $banList[$x]);
                $mask = intval($mask, 10);
                if ($ipv4 && (strpos($banList[$x], ':') === false)) {
                    // ipv4
                    if (($longIp & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                        $result = 'mask';
                        break;
                    }
                } elseif (!$ipv4 && (strpos($banList[$x], ':') !== false)) {
                    // ipv6
                    $binMask = pack("H*", str_pad(trim(str_repeat('f', floor($mask / 4)).substr(' 8ce', $mask % 4, 1)), 32, '0'));
                    if (($binIp & $binMask) == (inet_pton($subnet) & $binMask)) {
                        $result = 'mask';
                        break;
                    }
                }
            } else {
                // single IP
                if ($ip == $banList[$x]) {
                    $result = 'ip';
                    break;
                }
            }
        }
    }

    MCSet($cacheKey, $result ? $result : 'no', 43200);

    return !!$result;
}

function CaptchaDetails()
{
    global $db;

    $cacheKey = 'captcha_' . $_SERVER['REMOTE_ADDR'];
    if (($details = MCGet($cacheKey)) !== false) {
        return $details['public'];
    }

    DBConnect();

    $races = array(
        'bloodelf' => 10,
        'draenei'  => 11,
        'dwarf'    => 3,
        'gnome'    => 7,
        'goblin'   => 9,
        'human'    => 1,
        'nightelf' => 4,
        'orc'      => 2,
        'tauren'   => 6,
        'troll'    => 8,
        'undead'   => 5,
    );

    $raceExclude = array(
        $races['bloodelf'] => array($races['nightelf']),
        $races['nightelf'] => array($races['bloodelf']),
    );

    $keys = array_keys($races);
    $goodRace = $races[$keys[rand(0, count($keys) - 1)]];

    $howMany = rand(2, 3);

    $sql = 'SELECT * FROM tblCaptcha WHERE race = ? AND helm = 0 ORDER BY rand() LIMIT ?';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $goodRace, $howMany);
    $stmt->execute();
    $result = $stmt->get_result();
    $goodRows = DBMapArray($result);
    $stmt->close();

    $sql = 'SELECT * FROM tblCaptcha WHERE race NOT IN (%s) ORDER BY rand() LIMIT %d';
    $exclude = array($goodRace);
    if (isset($raceExclude[$goodRace])) {
        $exclude = array_merge($exclude, $raceExclude[$goodRace]);
    }

    $sql = sprintf($sql, implode(',', $exclude), 12 - $howMany);
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $badRows = DBMapArray($result);
    $stmt->close();

    $allRows = array_merge($goodRows, $badRows);
    shuffle($allRows);

    $details = array(
        'answer' => '',
        'public' => array(
            'lookfor' => $goodRace,
            'ids'     => array()
        )
    );

    for ($x = 0; $x < count($allRows); $x++) {
        if (isset($goodRows[$allRows[$x]['id']])) {
            $details['answer'] .= ($x + 1);
        }
        $details['public']['ids'][] = $allRows[$x]['id'];
    };

    MCSet($cacheKey, $details);

    return $details['public'];
}

function UserThrottleCount($reset = false)
{
    static $returned = false;
    if (!$reset && $returned !== false) {
        return $returned;
    }

    $k = 'throttle_%s_' . $_SERVER['REMOTE_ADDR'];
    $kTime = sprintf($k, 'time');
    $kCount = sprintf($k, 'count');

    if ($reset) {
        MCDelete($kTime);
        return $returned = 0;
    }

    $vals = MCGet([$kTime, $kCount]);
    MCSet($kTime, time(), THROTTLE_PERIOD);
    if (!isset($vals[$kTime]) || !isset($vals[$kCount]) || ($vals[$kTime] < time() - THROTTLE_PERIOD)) {
        MCSet($kCount, 1, THROTTLE_PERIOD * 2);
        return $returned = 1;
    }

    if (!preg_match_all('/([^;,]+)(?:[^,]+)?(?:,|$)/',
        isset($_SERVER["HTTP_ACCEPT_ENCODING"]) ? str_replace(' ', '', strtolower($_SERVER["HTTP_ACCEPT_ENCODING"])) : '', $encodings)) {
        $encodings = [1=>[]];
    }
    $encodings = array_flip($encodings[1]);

    $amount = 1;
    if (!isset($encodings['br'])) {
        $amount *= 2;
    }
    if (!isset($encodings['gzip'])) {
        $amount *= 5;
    }
    MCIncrement($kCount, $amount);

    return $returned = $vals[$kCount] + $amount;
}

function HouseETag($house, $includeFetches = false)
{
    $curTag = $includeFetches ? MCGet('housecheck_'.$house) : '';
    if ($curTag === false) {
        $curTag = 'x';
    }
    $curTag = 'W/"' . MCGetHouse($house) . $curTag . '"';
    $theirTag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';

    if ($curTag && $curTag == $theirTag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    header('ETag: ' . $curTag);
}

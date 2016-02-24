<?php

require_once('memcache.incl.php');
require_once('incl.php');

define('API_VERSION', 17);
define('THROTTLE_PERIOD', 3600); // seconds
define('THROTTLE_MAXHITS', 200);
define('BANLIST_CACHEKEY', 'banlist_cidrs4');
define('BANLIST_FILENAME', __DIR__ . '/banlist.txt');
define('BANLIST_USE_DNSBL', false);

$VALID_LOCALES = ['enus','dede','eses','frfr','itit','ptbr','ruru'];
$LANG_LEVEL = [
    '__LEVEL_enus__' => 'Level',
    '__LEVEL_dede__' => 'Stufe',
    '__LEVEL_eses__' => 'Nivel',
    '__LEVEL_frfr__' => 'Niveau',
    '__LEVEL_itit__' => 'Livello',
    '__LEVEL_ptbr__' => 'Nível',
    '__LEVEL_ruru__' => 'Уровень',
];

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
    global $VALID_LOCALES;

    $tr = '';
    foreach ($VALID_LOCALES as $locId) {
        $tr .= ($tr == '' ? '' : ', ') . ($usesPattern ? sprintf($colName, '_' . $locId) : ($colName . '_' . $locId));
    }

    return $tr;
}

function GetSiteRegion()
{
    return (isset($_SERVER['HTTP_HOST']) && (preg_match('/^eu./i', $_SERVER['HTTP_HOST']) > 0)) ? 'EU' : 'US';
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
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('getregion_' . $house, $tr, 24 * 60 * 60);

    return $tr;
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
        file_put_contents(BANLIST_FILENAME, "\n$ip # " . Date('Y-m-d H:i:s'), FILE_APPEND | LOCK_EX);
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

    global $memcache;

    $k = 'throttle_%s_' . $_SERVER['REMOTE_ADDR'];
    $kTime = sprintf($k, 'time');
    $kCount = sprintf($k, 'count');

    if ($reset) {
        $memcache->delete($kTime);
        return $returned = 0;
    }

    $vals = $memcache->get(array($kTime, $kCount));
    $memcache->set($kTime, time(), false, THROTTLE_PERIOD);
    if (!isset($vals[$kTime]) || !isset($vals[$kCount]) || ($vals[$kTime] < time() - THROTTLE_PERIOD)) {
        $memcache->set($kCount, 1, false, THROTTLE_PERIOD * 2);
        return $returned = 1;
    }
    $memcache->increment($kCount);

    return $returned = ++$vals[$kCount];
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

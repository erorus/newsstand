<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/subscription.incl.php');

header('Cache-Control: no-cache');

if (isset($_COOKIE['__cfduid'])) { // cloudflare
    setcookie('__cfduid', '', strtotime('1 year ago'), '/', '.theunderminejournal.com', false, true);
}

$loginState = [];
if (isset($_POST['getuser'])) {
    $loginState = RedactLoginState(GetLoginState());
}

$result = [
    'version' => API_VERSION,
    'apiKey' => API_ENCRYPTION_KEY,
    'language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en-US,en;q=0.5',
    'banned' => BotCheck(true),
    'user' => $loginState,
    'bbgRealms' => [], //GetBBGRealms(),
    'realms' => [GetRealms('US'),GetRealms('EU'),GetRealms('KR'),GetRealms('TW')]
];

$preloadHouse = FetchHouseFromHash();
if ($preloadHouse === false) {
    $preloadHouse = FetchHouseFromDefault();
}

if ($preloadHouse !== false) {
    header(sprintf('Link: </api/house.php?house=%d>; as=script; rel=preload', $preloadHouse), false);
}

json_return($result);

function FetchHouseFromHash() {
    global $result;

    if (!isset($_POST['hash']) || !$_POST['hash']) {
        return false;
    }

    $hashParts = explode('/', substr($_POST['hash'], 0, 120), 3);

    if (count($hashParts) < 2) {
        return false;
    }

    $preloadHouse = false;

    foreach (['us','eu','kr','tw'] as $regionId => $regionName) {
        if ($hashParts[0] != $regionName) {
            continue;
        }
        foreach ($result['realms'][$regionId] as $realm) {
            if ($realm['slug'] == $hashParts[1]) {
                return $realm['house'];
            }
        }
        break;
    }

    return false;
}

function FetchHouseFromDefault() {
    global $result;

    if (!isset($_POST['defaultRealm'])) {
        return false;
    }

    $defaultRealm = intval($_POST['defaultRealm'], 10);

    foreach ($result['realms'] as $regionSet) {
        if (isset($regionSet[$defaultRealm])) {
            return $regionSet[$defaultRealm]['house'];
        }
    }

    return false;
}

function GetBBGRealms() {
    $cacheKey = 'bootybayrealms';

    $result = MCGet($cacheKey);
    if ($result !== false) {
        return $result;
    }

    $result = [];

    $json = \Newsstand\HTTP::Get('https://www.bootybaygazette.com/api/tujrealms.php');

    if ($json !== false) {
        $data = json_decode($json);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result = $data;
        }
    }

    MCSet($cacheKey, $result);

    return $result;
}

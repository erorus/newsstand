<?php

require_once __DIR__ . '/memcache.incl.php';
require_once __DIR__ . '/battlenet.credentials.php';
require_once __DIR__ . '/NewsstandHTTP.incl.php';

define('BATTLE_NET_REQUEST_LIMIT', 50); // per period
define('BATTLE_NET_REQUEST_PERIOD', 1); // seconds

function GetBattleNetURL($region, $path)
{
    $region = trim(strtolower($region));
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }

    $start = microtime(true);
    $finalUrl = '';
    while (!$finalUrl && ($start + 5 > microtime(true))) {
        $cacheKey = 'BattleNetKeyUsage';
        if (!MCAdd($cacheKey . '_critical', 1, 5 * BATTLE_NET_REQUEST_PERIOD)) {
            usleep(50000);
            continue;
        }

        $apiHits = MCGet($cacheKey);
        if ($apiHits === false) {
            $apiHits = [];
        }
        $hitCount = count($apiHits);
        if ($hitCount >= BATTLE_NET_REQUEST_LIMIT) {
            $now = microtime(true);
            while (($apiHits[0] < $now) && ($now < ($apiHits[0] + BATTLE_NET_REQUEST_PERIOD))) {
                usleep(50000);
                $now = microtime(true);
            }
        }
        $apiHits[] = microtime(true);
        $hitCount++;
        if ($hitCount > BATTLE_NET_REQUEST_LIMIT) {
            array_splice($apiHits, 0, $hitCount - BATTLE_NET_REQUEST_LIMIT);
        }
        MCSet($cacheKey, $apiHits, 10 * BATTLE_NET_REQUEST_PERIOD);

        MCDelete($cacheKey . '_critical');

        $useOldApi = ($region == 'cn' && substr($path, 0, 5) != 'data/' && substr($path, 0, 8) != 'profile/');
        if (!$useOldApi) {
            // new data api, uses client creds

            $qs = '';
            $pos = strpos($path, '?');
            if ($pos !== false) {
                $qs = substr($path, $pos + 1);
                $path = substr($path, 0, $pos);
            }

            parse_str($qs, $qsa);

            if (!isset($qsa['namespace'])) {
                $qsa['namespace'] = 'dynamic-' . $region;
            }
            if (!isset($qsa['locale'])) {
                $qsa['locale'] = 'en_US';
            }
            $qsa['access_token'] = GetBattleNetClientCredentials($region);

            $pattern = ($region == 'cn') ? 'https://api.battlenet.com.%s/%s?%s' : 'https://%s.api.blizzard.com/%s?%s';
            $finalUrl = sprintf($pattern, $region, $path, http_build_query($qsa));
        } else {
            // old api, uses api key
            $pattern = ($region == 'cn') ? 'https://api.battlenet.com.%s/%s%sapikey=%s' : 'https://%s.api.battle.net/%s%sapikey=%s';
            $finalUrl = sprintf($pattern, $region, $path, strpos($path, '?') !== false ? '&' : '?', MASHERY_KEY);
        }
    }

    return $finalUrl ? $finalUrl : false;
}

function GetBattleNetClientCredentials($region)
{
    static $knownCreds = [];

    $region = trim(strtolower($region));
    $partition = $region == 'cn' ? 'cn' : 'us';

    if (isset($knownCreds[$partition]) && ($knownCreds[$partition]['expire'] > time())) {
        return $knownCreds[$partition]['token'];
    }

    $cacheKey = 'BattleNetClientCredentials-' . $partition;

    $creds = MCGet($cacheKey);
    if ($creds && $creds['expire'] > time()) {
        $knownCreds[$partition] = $creds;
        return $creds['token'];
    }

    $endpoint = ($partition == 'cn') ? 'https://www.battlenet.com.cn/oauth/token' : sprintf(BATTLE_NET_TOKEN_URI, 'us');

    $key = ($partition == 'cn') ? MASHERY_KEY : BATTLE_NET_KEY;
    $secret = ($partition == 'cn') ? MASHERY_SECRET : BATTLE_NET_SECRET;

    $json = \Newsstand\HTTP::Get("{$endpoint}?grant_type=client_credentials",
        ['Accept: application/json', 'Authorization: Basic '. base64_encode("{$key}:{$secret}")]);

    if (!$json) {
        trigger_error('Could not get client credentials from ' . $endpoint, E_USER_ERROR);
        return '';
    }

    $data = json_decode($json, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        trigger_error('Invalid json (' . json_last_error_msg() . ') returned in client credentials from ' . $endpoint, E_USER_ERROR);
        return '';
    }

    if (isset($data['error'])) {
        trigger_error('Client credentials returned error ' . (isset($data['error_description']) ? $data['error_description'] : $data['error']) . ' from ' . $endpoint, E_USER_ERROR);
        return '';
    }

    if (!isset($data['access_token'])) {
        trigger_error('Client credentials had no access token from ' . $endpoint, E_USER_ERROR);
        return '';
    }

    $creds = [
        'token' => $data['access_token'],
        'expire' => time() + (isset($data['expires_in']) ? $data['expires_in'] : 86400),
    ];

    $knownCreds[$partition] = $creds;
    MCSet($cacheKey, $creds, $creds['expire']);

    return $creds['token'];
}

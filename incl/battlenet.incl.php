<?php

require_once(__DIR__ . '/memcache.incl.php');
require_once(__DIR__ . '/battlenet.credentials.php');

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

        $finalUrl = sprintf('https://%s.api.battle.net/%s%sapikey=%s', $region, $path, strpos($path, '?') !== false ? '&' : '?', BATTLE_NET_KEY);
    }

    return $finalUrl ? $finalUrl : false;
}
<?php

require_once(__DIR__.'/memcache.incl.php');
require_once(__DIR__.'/battlenet.credentials.php');

define('BATTLE_NET_REQUEST_LIMIT', 8); // per second

function GetBattleNetURL($region, $path)
{
    global $memcache;

    $region = trim(strtolower($region));
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }

    $start = time();
    $finalUrl = '';
    while (!$finalUrl && ($start+5 > time())) {
        $thisStart = time();

        $cacheKey = 'bnetapihit_'.$thisStart;
        $memcache->add($cacheKey, 0, 0, 5);
        $inFlight = $memcache->increment($cacheKey);
        if (!$inFlight || $inFlight > BATTLE_NET_REQUEST_LIMIT) {
            while (time() == $thisStart) {
                usleep(100000);
            }
            continue;
        }
        $finalUrl = sprintf('https://%s.api.battle.net/%s%sapikey=%s', $region, $path, strpos($path, '?') !== false ? '&' : '?', BATTLE_NET_KEY);
    }

    return $finalUrl ? $finalUrl : false;
}
<?php

require_once('../../incl/incl.php');

$validRegions = array_flip(array('us', 'eu'));

if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
    header('HTTP/1.1 403 Forbidden');
    echo $_SERVER['REMOTE_ADDR'];
    exit;
}

if (!isset($_GET['region']) || (!isset($validRegions[$_GET['region']]))) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Need region parameter';
    exit;
}

if (!isset($_GET['path'])) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-type: text/plain');
    echo 'Need path parameter';
    exit;
}

require_once('../../incl/memcache.incl.php');
require_once('../../incl/battlenet.credentials.php');

$region = trim(strtolower($_GET['region']));
$path = $_GET['path'];
if (substr($path, 0, 1) == '/') {
    $path = substr($path, 1);
}

define('REQUEST_LIMIT', 8);

$start = time();
$finalUrl = '';
while (!$finalUrl && ($start+5 > time())) {
    $thisStart = time();

    $cacheKey = 'bnetapihit_'.$thisStart;
    $memcache->add($cacheKey, 0, 0, 5);
    $inFlight = $memcache->increment($cacheKey);
    if (!$inFlight || $inFlight > REQUEST_LIMIT) {
        while (time() == $thisStart) {
            usleep(100000);
        }
        continue;
    }
    $finalUrl = sprintf('https://%s.api.battle.net/%s%sapikey=%s', $region, $path, strpos($path, '?') !== false ? '&' : '?', BATTLE_NET_KEY);
}

if (!$finalUrl) {
    header('HTTP/1.1 503 Service Unavailable');
} else {
    header('HTTP/1.1 302 Found');
    header('Location: '.$finalUrl);
}

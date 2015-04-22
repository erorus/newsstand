<?php

require_once('../incl/memcache.incl.php');
require_once('../incl/api.incl.php');

function GetETag() {
    static $c = false;
    if ($c !== false) {
        return $c;
    }
    $c = MCGet('wowtoken-json-etag');
    if ($c === false) {
        $c = '';
    }
    return $c;
}

$showOld = false;

if (IPIsBanned()) {
    $cacheKey = BANLIST_CACHEKEY . '_' . $_SERVER['REMOTE_ADDR'] . '_firsthit';
    $firstHit = MCGet($cacheKey);
    if ($firstHit === false) {
        MCSet($cacheKey, time(), 50*60*60);
    } elseif ($firstHit < (time() - 20 * 60)) {
        $showOld = true;
    }
}

if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    if ($_SERVER['HTTP_IF_NONE_MATCH'] == ('W/"'.GetETag().'"')) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
}

ini_set('zlib.output_compression', 1);

header('Content-type: application/json; charset=UTF-8');
header('Cache-Control: max-age=900');
$etag = GetETag();
if ($etag) {
    header('ETag: W/"'.$etag.'"');
}
if ($showOld) {
    readfile('snapshot-history-old.json');
} else {
    readfile('snapshot-history.json');
}


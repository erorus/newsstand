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
$banned = false;
$firstHit = false;

if (IPIsBanned()) {
    $banned = true;
    $showOld = true;
    /*
    $cacheKey = BANLIST_CACHEKEY . '_' . $_SERVER['REMOTE_ADDR'] . '_firsthit';
    $firstHit = MCGet($cacheKey);
    if ($firstHit === false) {
        MCSet($cacheKey, time(), 50*60*60);
    } elseif ($firstHit < (time() - 20 * 60)) {
        $showOld = true;
    }
    */
}

$suspicious = false;
$suspicious |= ($_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.1');
$suspicious |= !isset($_SERVER['HTTP_ACCEPT_ENCODING']);

$showOld |= $suspicious;

if ($suspicious || $banned) {
    $writeup = "IP: ".$_SERVER['REMOTE_ADDR'];
    if ($banned) {
        $writeup .= ' (Banned)';
    }
    if ($showOld) {
        $writeup .= ' (showing old)';
    }
    $writeup .= "\nTime: ".Date("Y-m-d H:i:s")."\n";
    if ($firstHit) {
        $writeup .= 'First hit '.TimeDiff($firstHit)."\n";
    }
    $writeup .= 'Protocol: '.$_SERVER['SERVER_PROTOCOL']."\n";
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        $writeup .= "$k: $v\n";
    }
    $writeup .= "\n";
    file_put_contents(__DIR__.'/../logs/wowtoken.suspicious.log', $writeup, FILE_APPEND | LOCK_EX);
}

if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    if ($_SERVER['HTTP_IF_NONE_MATCH'] == ('W/"'.GetETag().'"')) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
}

$showOld |= isset($_GET['old']);

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


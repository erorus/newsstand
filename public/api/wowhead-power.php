<?php

require_once '../../incl/incl.php';
require_once '../../incl/memcache.incl.php';
require_once '../../incl/api.incl.php';

function returnCode($code = '404 Not Found') {
    header($_SERVER['SERVER_PROTOCOL'].' '.$code);
    exit();
}

if (!isset($_GET['domain']) || !isset($_GET['path'])) {
    returnCode();
}

if (isset($_SERVER['HTTP_REFERER'])) {
    $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if (!is_null($refererHost) && 0 == preg_match('/(?:^|\.)(?:theunderminejournal\.com|newsstand)$/', $refererHost)) {
        returnCode('403 Forbidden');
    }
}

$qs = $_GET['path'];
if (substr($qs, -6) != '&power') {
    returnCode();
}

$wowheadDomains = ['www'];
foreach ($VALID_LOCALES as $loc) {
    if ($loc == 'enus') {
        continue;
    }
    $wowheadDomains[] = substr($loc, 0, 2);
}

$domain = $_GET['domain'];
if (!in_array($domain, $wowheadDomains)) {
    returnCode();
}

$cacheKey = 'whpower:' . $domain . ':' . rawurlencode($qs);
if (isset($_SERVER['MEMCACHED_KEY'])) {
    // thanks for double-escaping this, nginx. grr.
    $cacheKey = preg_replace_callback('/[\\x00-\\x1F %]/', function($m) {
        return '%'.str_pad(dechex(ord($m[0])),2,'0',STR_PAD_LEFT);
    }, $_SERVER['MEMCACHED_KEY']);
}

$url = $domain . '.wowhead.com/' . $qs;
$js = MCGet($cacheKey);
if ($js === false) {
    $js = FetchHTTP('http://' . $url);
    if ($js === false) {
        $js = '';
    }
    if (substr($js, 0, 13) != '$WowheadPower') {
        $js = '';
    }
    MCSet($cacheKey, $js, 86400);
}
if (!$js) {
    header('Location: https://' . $url);
    exit();
}

ini_set('zlib.output_compression', 1);

header('Content-Type: application/javascript; charset=utf-8');
header('Expires: ' . date(DATE_RFC1123, time() + 86400));
echo $js;


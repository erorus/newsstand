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

if (IPIsBanned()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
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
readfile('snapshot-history.json');


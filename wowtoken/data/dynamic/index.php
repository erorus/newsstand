<?php

require_once(__DIR__ . '/../../../incl/memcache.incl.php');

header('Location: data/' . GetIPSnapshotFile(), true, 303);

header('Expires: ' . date(DATE_RFC1123, time() + 600));
header('Cache-Control: max-age=600');

function GetLatestSnapshotFile() {
    $latest = MCGet('wowtoken_latest');
    if (!$latest) {
        $dynJsons = glob(__DIR__ . '/data/*.json');
        $jsonDates = [];
        foreach ($dynJsons as $jsonPath) {
            $jsonDates[basename($jsonPath)] = filemtime($jsonPath);
        }
        asort($jsonDates, SORT_NUMERIC);
        $jsonDates = array_keys($jsonDates);
        $latest = array_pop($jsonDates);

        MCSet('wowtoken_latest', $latest);
    }

    return $latest;
}

function GetIPSnapshotFile() {
    if (!isset($_SERVER['REMOTE_ADDR'])) {
        return GetLatestSnapshotFile();
    }

    $ip = $_SERVER['REMOTE_ADDR'];

    $ipv4 = (strpos($ip, ':') === false);
    if ($ipv4) {
        $key = dechex(ip2long($ip) & (~0xFF));
    } else {
        $key = bin2hex(substr(inet_pton($ip), 0, 8));
    }

    $key = 'wowtoken_snap_' . $key;
    $snap = MCGet($key);
    if ($snap !== false && file_exists(__DIR__ . '/data/' . $snap)) {
        return $snap;
    }

    $snap = GetLatestSnapshotFile();
    MCSet($key, $snap, 3*60*60);

    return $snap;
}

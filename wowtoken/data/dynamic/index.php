<?php

require_once(__DIR__ . '/../../../incl/memcache.incl.php');

header('Location: data/' . GetLatestSnapshotFile(), true, 303);
exit;

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
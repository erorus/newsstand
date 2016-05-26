<?php

require_once __DIR__.'/../incl/incl.php';
require_once __DIR__.'/../incl/battlenet.incl.php';

use \Newsstand\HTTP;

RunMeNTimes(1);
CatchKill();

$startTime = time();

$file = [];
$file['note'] = 'Brought to you by https://does.theapi.work/';
$file['started'] = JSNow();
foreach (['us','eu'] as $region) {
    $file['regions'][$region] = FetchRegionData($region);
    if ($caughtKill) {
        break;
    }
}
$file['finished'] = JSNow();

if (!$caughtKill) {
    $fn = isset($argv[1]) ? $argv[1] : __DIR__.'/../theapi.work/times.json';
    $pth = dirname($fn);

    $fnTmp = tempnam($pth, 'doestheapiwork-writing');
    $f = fopen($fnTmp, 'w');
    fwrite($f, json_encode($file, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
    fclose($f);

    chmod($fnTmp, 0644);

    if (!rename($fnTmp, $fn)) {
        DebugMessage("Could not rename $fnTmp to $fn", E_USER_WARNING);
        unlink($fnTmp);
    }
}

DebugMessage('Done! Started ' . TimeDiff($startTime, ['precision'=>'second']));

function JSNow() {
    return floor(microtime(true) * 1000);
}

function FetchRegionData($region) {
    global $caughtKill;

    $region = trim(strtolower($region));

    $results = [];

    DebugMessage("Fetching realms for $region");

    $url = GetBattleNetURL($region, 'wow/realm/status');
    $jsonString = HTTP::Get($url);
    $json = json_decode($jsonString, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("Error decoding ".strlen($jsonString)." length JSON string for $region: ".json_last_error_msg(), E_USER_WARNING);
        return $results;
    }
    if (!isset($json['realms'])) {
        DebugMessage("Did not find realms in realm status JSON for $region", E_USER_WARNING);
        return $results;
    }

    $slugMap = [];

    foreach ($json['realms'] as $realmRow) {
        if ($caughtKill) {
            break;
        }
        if (!isset($realmRow['slug'])) {
            continue;
        }
        $slug = $realmRow['slug'];
        if (isset($results[$slug])) {
            $results[$slug]['name'] = $realmRow['name'];
            continue;
        }

        $resultRow = [
            'name' => $realmRow['name'],
            'canonical' => 1,
        ];

        $results[$slug] = $resultRow;
        $slugMap[$slug] = [$slug];

        if (isset($realmRow['connected_realms'])) {
            foreach ($realmRow['connected_realms'] as $connectedSlug) {
                if ($connectedSlug == $slug) {
                    continue;
                }
                $results[$connectedSlug] = [
                    'name' => '',
                ];
                $slugMap[$slug][] = $connectedSlug;
            }
        }
    }

    $curlOpts = [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 2,
        CURLOPT_TIMEOUT         => 10,
        CURLOPT_ENCODING        => 'gzip',
    ];

    $chunks = array_chunk($slugMap, 20, true);
    foreach ($chunks as $chunk) {
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, 3);

        $curls = [];
        DebugMessage("Fetching auction data for $region ".implode(', ', array_keys($chunk)));
        foreach (array_keys($chunk) as $slug) {
            $curls[$slug] = curl_init(GetBattleNetURL($region, 'wow/auction/data/' . $slug));
            curl_setopt_array($curls[$slug], $curlOpts);
            curl_multi_add_handle($mh, $curls[$slug]);
        }

        $active = false;
        $started = JSNow();

        while (CURLM_CALL_MULTI_PERFORM == ($mrc = curl_multi_exec($mh, $active)));

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                while (CURLM_CALL_MULTI_PERFORM == ($mrc = curl_multi_exec($mh, $active)));
            }
            usleep(100000);
        }

        foreach ($chunk as $slug => $slugs) {
            curl_multi_remove_handle($mh, $curls[$slug]);
            $json = json_decode(curl_multi_getcontent($curls[$slug]), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                DebugMessage("Error decoding JSON string for $region $slug: " . json_last_error_msg(), E_USER_WARNING);
                $json = [];
            }

            $modified = isset($json['files'][0]['lastModified']) ? $json['files'][0]['lastModified'] : 0;
            foreach ($slugs as $connectedSlug) {
                $results[$connectedSlug]['checked'] = $started;
                $results[$connectedSlug]['modified'] = $modified;
            }

            curl_close($curls[$slug]);
        }

        curl_multi_close($mh);
    }

    ksort($results);

    return $results;
}


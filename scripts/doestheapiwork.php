<?php

require_once __DIR__.'/../incl/incl.php';
require_once __DIR__.'/../incl/battlenet.incl.php';

define('ZOPFLI_PATH', __DIR__.'/zopfli');
define('BROTLI_PATH', __DIR__.'/brotli/bin/bro');
define('REALM_CHUNK_SIZE', 12);

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

    AtomicFilePutContents($fn, json_encode($file, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
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

    $chunks = array_chunk($slugMap, REALM_CHUNK_SIZE, true);
    foreach ($chunks as $chunk) {
        DebugMessage("Fetching auction data for $region ".implode(', ', array_keys($chunk)));
        $urls = [];
        foreach (array_keys($chunk) as $slug) {
            $urls[$slug] = GetBattleNetURL($region, 'wow/auction/data/' . $slug);
        }

        $started = JSNow();
        $dataUrls = [];
        $jsons = FetchURLBatch($urls);

        foreach ($chunk as $slug => $slugs) {
            $json = [];
            if (!isset($jsons[$slug])) {
                DebugMessage("No HTTP response for $region $slug", E_USER_WARNING);
            } else {
                $json = json_decode($jsons[$slug], true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    DebugMessage("Error decoding JSON string for $region $slug: " . json_last_error_msg(), E_USER_WARNING);
                    $json = [];
                }
            }

            $modified = isset($json['files'][0]['lastModified']) ? $json['files'][0]['lastModified'] : 0;
            $url = isset($json['files'][0]['url']) ? $json['files'][0]['url'] : '';
            if ($url) {
                $dataUrls[$slug] = $url;
            }
            foreach ($slugs as $connectedSlug) {
                $results[$connectedSlug]['checked'] = $started;
                $results[$connectedSlug]['modified'] = $modified;
            }
        }

        $dataHeads = FetchURLBatch($dataUrls, [
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true
        ]);
        foreach ($chunk as $slug => $slugs) {
            $fileDate = 0;
            if (isset($dataHeads[$slug])) {
                if (preg_match('/(?:^|\n)Last-Modified: ([^\n]+)/i', $dataHeads[$slug], $res)) {
                    $fileDate = strtotime($res[1]) * 1000;
                } else {
                    DebugMessage("Found no last-modified header for $region $slug at " . $dataUrls[$slug] . "\n" . $dataHeads[$slug], E_USER_WARNING);
                }
            } elseif (isset($dataUrls[$slug])) {
                DebugMessage("Fetched no header for $region $slug at " . $dataUrls[$slug], E_USER_WARNING);
            }
            foreach ($slugs as $connectedSlug) {
                $results[$connectedSlug]['file'] = $fileDate;
            }
        }
    }

    ksort($results);

    return $results;
}

function FetchURLBatch($urls, $curlOpts = []) {
    if (!$urls) {
        return [];
    }

    $curlOpts = [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 2,
        CURLOPT_TIMEOUT         => 10,
        CURLOPT_ENCODING        => 'gzip',
    ] + $curlOpts;

    static $mh = false;
    if ($mh === false) {
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, 3);
    }

    $results = [];
    $curls = [];

    foreach ($urls as $k => $url) {
        $curls[$k] = curl_init($url);
        curl_setopt_array($curls[$k], $curlOpts);
        curl_multi_add_handle($mh, $curls[$k]);
    }

    $active = false;
    do {
        while (CURLM_CALL_MULTI_PERFORM == ($mrc = curl_multi_exec($mh, $active)));
        if ($active) {
            usleep(100000);
        }
    } while ($active && $mrc == CURLM_OK);

    foreach ($urls as $k => $url) {
        $results[$k] = curl_multi_getcontent($curls[$k]);
        curl_multi_remove_handle($mh, $curls[$k]);
        curl_close($curls[$k]);
    }

    return $results;
}

function AtomicFilePutContents($path, $data) {
    $aPath = "$path.atomic";
    file_put_contents($aPath, $data);

    static $hasZopfli = null, $hasBrotli = null;
    if (is_null($hasZopfli)) {
        $hasZopfli = is_executable(ZOPFLI_PATH);
    }
    if (is_null($hasBrotli)) {
        $hasBrotli = is_executable(BROTLI_PATH);
    }
    $o = [];
    $ret = $retBrotli = 0;
    $zaPath = "$aPath.gz";
    $zPath = "$path.gz";
    $baPath = "$aPath.br";
    $bPath = "$path.br";

    $dataPath = $aPath;

    exec(($hasZopfli ? escapeshellcmd(ZOPFLI_PATH) : 'gzip') . ' -c ' . escapeshellarg($dataPath) . ' > ' . escapeshellarg($zaPath), $o, $ret);
    if ($hasBrotli && $ret == 0) {
        exec(escapeshellcmd(BROTLI_PATH) . ' --input ' . escapeshellarg($dataPath) . ' > ' . escapeshellarg($baPath), $o, $retBrotli);
    }

    if ($ret != 0) {
        if (file_exists($baPath)) {
            unlink($baPath);
        }
        if (file_exists($bPath)) {
            unlink($bPath);
        }
        if (file_exists($zaPath)) {
            unlink($zaPath);
        }
        if (file_exists($zPath)) {
            unlink($zPath);
        }
    } else {
        $tm = filemtime($aPath);
        touch($aPath, $tm); // wipes out fractional seconds
        touch($zaPath, $tm); // identical time to $aPath
        rename($zaPath, $zPath);
        if ($retBrotli != 0) {
            if (file_exists($baPath)) {
                unlink($baPath);
            }
            if (file_exists($bPath)) {
                unlink($bPath);
            }
        } else {
            touch($baPath, $tm); // identical time to $aPath
            rename($baPath, $bPath);
        }
    }
    rename($aPath, $path);
}
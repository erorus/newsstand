<?php

chdir(__DIR__);
$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

define('DATEFILE', 'cdnpurge.dates.json');
define('BASEPATH', '../public/');

$baseDirs = ['css', 'images', 'js'];
$purged = 0;
$prevDates = GetPrevDates();

foreach ($baseDirs as $d) {
    CheckToPurge($d);

    if ($caughtKill) {
        break;
    }
}

if (!$caughtKill) {
    SavePrevDates($prevDates);
}

if ($purged > 0) {
    DebugMessage('Done! Purged ' . $purged . ' and started ' . TimeDiff($startTime));
}

function GetPrevDates()
{
    $tr = [];

    if (file_exists(DATEFILE)) {
        if ($a = json_decode(file_get_contents(DATEFILE), true)) {
            $tr = $a;
        }
    }

    return $tr;
}

function SavePrevDates($prevDates)
{
    if (count($prevDates)) {
        file_put_contents(DATEFILE, json_encode($prevDates, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
    }
}

function CheckToPurge($bDir)
{
    global $prevDates;
    global $caughtKill;

    heartbeat();
    if ($caughtKill) {
        return;
    }

    $path = BASEPATH . $bDir;
    $files = scandir($path);
    foreach ($files as $fileName) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        if (substr($fileName, 0, 1) == '.') {
            continue;
        }

        $fullPath = "$path/$fileName";
        $localPath = "$bDir/$fileName";

        if (is_dir($fullPath)) {
            CheckToPurge($localPath, false);
        } else {
            $modTime = filemtime($fullPath);
            if (isset($prevDates[$localPath])) {
                if ($prevDates[$localPath] < $modTime) {
                    if (PurgePath($localPath)) {
                        $prevDates[$localPath] = $modTime;
                    }
                }
            } else {
                $prevDates[$localPath] = $modTime;
            }
        }
    }
}

function PurgePath($localPath)
{
    global $purged;

    $data = [
        'a'     => 'zone_file_purge',
        'tkn'   => 'ce2d32655610115c1866795590af0c3e27483', // don't bother, this key is no longer valid
        'email' => 'cloudflare@everynothing.net',
        'z'     => 'theunderminejournal.com',
        'url'   => 'https://cdn.theunderminejournal.com/' . $localPath
    ];

    $opt = [
        'timeout'        => 15,
        'connecttimeout' => 6,
        'redirect'       => 0
    ];

    $info = http_parse_message(http_post_fields('https://www.cloudflare.com/api_json.html', $data, [], $opt));

    $responseJson = json_decode($info->body, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        $responseJson = false;
    }

    if (($info->responseCode == 200) && ($responseJson['result'] == 'success')) {
        DebugMessage("Purged {$data['url']}");
        $purged++;

        return true;
    }

    if ($responseJson) {
        DebugMessage("Error " . $info->responseCode . " purging $localPath\n" . print_r($responseJson, true), E_USER_WARNING);
    } else {
        DebugMessage("Error " . $info->responseCode . " purging $localPath\n" . $info->body, E_USER_WARNING);
    }

    return false;
}




<?php

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

chdir(__DIR__);
define('DATEFILE', 'cdnpurge.dates.json');
define('BASEPATH', '../public/');

$baseDirs = ['css','images','js'];
$purged = 0;
$prevDates = GetPrevDates();

foreach ($baseDir as $d) {
    CheckToPurge($d);

    if ($caughtKill)
        break;
}

if (!$caughtKill) {
    SavePrevDates($prevDates);
}

if ($purged > 0) {
    DebugMessage('Done! Purged '.$purged.' and started '.TimeDiff($startTime));
}

function GetPrevDates() {
    $tr = [];

    if (file_exists(DATEFILE)) {
        if ($a = json_decode(file_get_contents(DATEFILE), true)) {
            $tr = $a;
        }
    }

    return $tr;
}

function SavePrevDates($prevDates) {
    if (count($prevDates)) {
        file_put_contents(DATEFILE, json_encode($prevDates, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
    }
}

function CheckToPurge($bDir) {
    global $prevDates;
    global $caughtKill;

    heartbeat();
    if ($caughtKill)
        return;

    $path = BASEPATH . $bDir;
    $files = scandir($path);
    foreach ($files as $fileName) {
        heartbeat();
        if ($caughtKill)
            return;

        if (substr($fileName, 0, 1) == '.') {
            continue;
        }

        $fullPath = "$path/$fileName";
        $localPath = "$bDir/$fileName";

        DebugMessage("Check $fullPath");

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

function PurgePath($localPath) {
    global $purged;

    DebugMessage("Purged $localPath");
    $purged++;

    return true;
}




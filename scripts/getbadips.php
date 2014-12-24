<?php

chdir(__DIR__);

require_once('../incl/incl.php');
require_once('../incl/api.incl.php');

$logFileName = isset($argv[1]) ? $argv[1] : '../logs/access.log';

if (!file_exists($logFileName)) {
    DebugMessage("Can't find log file: $logFileName\n");
    exit(1);
}

$ipHits = [];

$fh = fopen($logFileName, 'r');
if ($fh) {
    while (($line = fgets($fh, 4096)) !== false) {
        if ((strpos($line, '"GET /api/') !== false)) { //} && (strpos($line, '(X11; Ubuntu; Linux i686; rv:25.0') !== false)) {
            preg_match('/^\S+/', $line, $res);
            if (!isset($ipHits[$res[0]])) {
                $ipHits[$res[0]] = 0;
            }
            $ipHits[$res[0]]++;
        }
    }
}
fclose($fh);

arsort($ipHits, SORT_NUMERIC);

$x = 0;
foreach ($ipHits as $ip => $hits) {
    if (!IPIsBanned($ip)) {
        echo "$ip - $hits\n";
        if (++$x > 30) {
            break;
        }
    }
}
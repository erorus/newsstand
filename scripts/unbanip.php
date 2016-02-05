<?php

require_once(__DIR__.'/../incl/incl.php');
require_once(__DIR__.'/../incl/api.incl.php');

if (!isset($argv[1])) {
    DebugMessage("Enter IP to unban on command line.\n");
    exit(1);
}
$ip = trim($argv[1]);

if (!IPIsBanned($ip)) {
    DebugMessage("$ip was not banned.\n");
    exit(1);
}

if (file_exists(BANLIST_FILENAME)) {
    $lines = shell_exec('grep '.escapeshellarg("^$ip ").' '.escapeshellarg(BANLIST_FILENAME));
    if (!$lines) {
        DebugMessage('Found no lines in '.BANLIST_FILENAME."for $ip\n");
    } else {
        $other = shell_exec('grep -v '.escapeshellarg("^$ip ").' '.escapeshellarg(BANLIST_FILENAME));
        file_put_contents(BANLIST_FILENAME, $other, LOCK_EX);
    }
} else {
    DebugMessage("Could not find ".BANLIST_FILENAME."\n");
    exit(1);
}

MCDelete(BANLIST_CACHEKEY);
MCDelete(BANLIST_CACHEKEY . '_' . $ip);

DebugMessage("$ip is unbanned.\n");
<?php

require_once(__DIR__.'/../incl/incl.php');
require_once(__DIR__.'/../incl/api.incl.php');

if (!isset($argv[1])) {
    DebugMessage("Enter IP to ban on command line.\n");
    exit(1);
}
$ip = trim($argv[1]);

if ($ip == false) {
    MCDelete(BANLIST_CACHEKEY);
    DebugMessage("Cleared banlist from memcache.\n");
    exit;
}

$ret = BanIP($ip);

if ($ret) {
    DebugMessage("$ip added to ban list.\n");
} else {
    if (IPIsBanned($ip)) {
        DebugMessage("$ip already on ban list.\n");
    } else {
        DebugMessage("$ip NOT added to ban list.\n");
    }
}

<?php

require_once(__DIR__.'/../incl/incl.php');
require_once(__DIR__.'/../incl/api.incl.php');

if (!isset($argv[1])) {
    DebugMessage("Enter IP to poison on command line.\n");
    exit(1);
}
$ip = trim($argv[1]);

FlagAsPoisoned($ip, true);
DebugMessage(sprintf("%s is poisoned.", $ip));

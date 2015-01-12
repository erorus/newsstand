<?php

require_once(__DIR__.'/../incl/incl.php');
require_once(__DIR__.'/../incl/memcache.incl.php');

if (!isset($argv[1])) {
    DebugMessage('Manual API Maintenance called without time argument. Add expected completion timestamp to command line (or 0 to end maintenance)', E_USER_ERROR);
}

APIMaintenance($argv[1]);

<?php

// symlinked in / for IPN

require_once __DIR__.'/../incl/incl.php';
require_once __DIR__.'/../incl/subscription.incl.php';

$rawPost = file_get_contents('php://input');
if (!UpdateBitPayTransaction($rawPost)) {
    header($_SERVER["SERVER_PROTOCOL"] . ' 500 Internal Server Error');
}

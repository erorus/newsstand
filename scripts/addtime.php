<?php

require_once '../incl/incl.php';
require_once '../incl/subscription.incl.php';

if (count($argv) < 4) {
    fwrite(STDERR, "php addtime.php userid '+2 weeks' 'message here'\n");
    exit(1);
}

$user = intval($argv[1], 10);
$seconds = strtotime($argv[2]) - time();
if ($seconds <= time()) {
    fwrite(STDERR, "can only add time, will not add $seconds seconds\n");
    exit(2);
}

$LANG = GetLang('enus');

$message = trim($argv[3]);
if (!$message) {
    $message = $LANG['subscriptionTimeAddedMessage'];
}

$paidUntil = AddPaidTime($user, $seconds);
if ($paidUntil === false) {
    exit(3);
}

if ($paidUntil > time()) {
    $message .= "<br><br>" . sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['paidExpires']), date('Y-m-d H:i:s e', $paidUntil));
}

SendUserMessage($user, 'Subscription', $LANG['paidSubscription'], $message);

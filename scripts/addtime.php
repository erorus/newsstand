<?php

require_once '../incl/incl.php';
require_once '../incl/subscription.incl.php';

if (count($argv) < 4) {
    fwrite(STDERR, "php addtime.php userid '+2 weeks' 'message here'\n");
    exit(1);
}

$user = intval($argv[1], 10);
$seconds = strtotime($argv[2]) - time();
if ($seconds <= 0) {
    fwrite(STDERR, "can only add time, will not add $seconds seconds\n");
    exit(2);
}

$LANG = GetLang('enus');

$message = trim($argv[3]);
if (!$message) {
    $message = $LANG['subscriptionTimeAddedMessage'];
}

if ($user > 0) {
    AddTheTime($user, $seconds, $message);
} elseif ($user == -1) {
    AddToCurrentSubs($seconds, $message);
}

function AddToCurrentSubs($seconds, $message) {
    $allGood = true;

    $dt = date('Y-m-d H:i:s', time() - $seconds);

    $db = DBConnect(true);
    $stmt = $db->prepare('select id from tblUser where paiduntil > ?');
    $stmt->bind_param('s', $dt);
    $stmt->execute();
    $user = null;
    $stmt->bind_result($user);
    while ($stmt->fetch()) {
        $allGood &= AddTheTime($user, $seconds, $message);
    }
    $stmt->close();
    $db->close();

    return $allGood;
}

function AddTheTime($user, $seconds, $message) {
    global $LANG;

    $paidUntil = AddPaidTime($user, $seconds);
    if ($paidUntil === false) {
        echo "Error adding $seconds to $user\n";
        return false;
    }

    if ($paidUntil > time()) {
        $message .= "<br><br>" . sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['paidExpires']), date('Y-m-d H:i:s e', $paidUntil));
    }

    SendUserMessage($user, 'Subscription', $LANG['paidSubscription'], $message);

    echo "$user: $message\n";
    return true;
}
<?php

// symlinked in /

require_once '../incl/incl.php';
require_once '../incl/subscription.incl.php';

FullPaypalProcess();

function FullPaypalProcess() {
    if (!CheckPaypalPost()) { // expected to exit script on fail
        header('HTTP/1.0 500 Internal Server Error');
        exit;
    }
    $operation = ProcessPaypalPost(); // expected to return false on fail
    if ($operation === false) {
        header('HTTP/1.0 500 Internal Server Error');
        exit;
    }
    if (isset($operation['addTime'])) {
        $newPaidUntil = AddPaidTime($operation['addTime'], SUBSCRIPTION_PAID_ADDS_SECONDS);
        PaypalResultForUser($operation['addTime'], $newPaidUntil, false);
    }
    if (isset($operation['delTime'])) {
        $newPaidUntil = AddPaidTime($operation['delTime'], -1 * SUBSCRIPTION_PAID_ADDS_SECONDS);
        PaypalResultForUser($operation['addTime'], $newPaidUntil, true);
    }
}

function CheckPaypalPost() {
    global $PAYPAL_BUSINESSES;

    if (!isset($_POST['txn_id'])) {
        DebugPaypalMessage("Received request without txn_id at Paypal IPN. IP: ".(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'));
        header('HTTP/1.0 404 Not Found');
        exit;
    }
    if (!isset($_POST['business']) || !in_array(strtolower(trim($_POST['business'])), $PAYPAL_BUSINESSES)) {
        DebugPaypalMessage('Received invalid business from Paypal IPN: "'.(isset($_POST['business']) ? $_POST['business'] : '').'"');
        header('HTTP/1.0 420 Not Verified');
        exit;
    }

    $rawPost = file_get_contents('php://input');
    $isSandbox = isset($_POST['test_ipn']);

    if (!ValidatePaypalNotification($rawPost, $isSandbox)) {
        header('HTTP/1.0 420 Not Verified');
        exit;
    }

    if ($isSandbox) {
        DebugPaypalMessage('Ignored Paypal sandbox notification.');
        header('HTTP/1.0 420 Not Verified');
        exit;
    }

    return true;
}

function ProcessPaypalPost() {
    $db = DBConnect();

    $test_ipn = isset($_POST['test_ipn']) ? 1 : 0;
    $txn_id = $_POST['txn_id'];

    $stmt = $db->prepare('select * from tblPaypalTransactions where test_ipn = ? and txn_id = ?');
    $stmt->bind_param('is', $test_ipn, $txn_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $txnRow = $result->fetch_assoc();
    $result->close();
    $stmt->close();

    $addPaidTime = (!$txnRow) || ($txnRow['payment_status'] != 'Completed');

    $txn_type = null;
    if (isset($_POST['txn_type'])) {
        $txn_type = $_POST['txn_type'];
        $addPaidTime &= $txn_type == 'web_accept';
    } elseif (isset($_POST['payment_status'])) {
        $txn_type = $_POST['payment_status'];
        $addPaidTime = false;
    }

    $payment_date = date('Y-m-d H:i:s', isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time());
    $parent_txn_id = isset($_POST['parent_txn_id']) ? $_POST['parent_txn_id'] : null;
    $mc_currency = isset($_POST['mc_currency']) ? $_POST['mc_currency'] : null;
    $mc_fee = isset($_POST['mc_fee']) ? $_POST['mc_fee'] : null;
    $mc_gross = isset($_POST['mc_gross']) ? $_POST['mc_gross'] : null;
    $pending_reason = isset($_POST['pending_reason']) ? $_POST['pending_reason'] : null;
    $reason_code = isset($_POST['reason_code']) ? $_POST['reason_code'] : null;
    $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : null;

    $addPaidTime &= $payment_status == 'Completed';

    $user = isset($_POST['custom']) ? GetUserFromPublicHMAC($_POST['custom']) : null;

    $cols = ['test_ipn', 'txn_id',
        'txn_type', 'payment_date', 'parent_txn_id',
        'mc_currency', 'mc_fee', 'mc_gross',
        'payment_status', 'user',
        'pending_reason', 'reason_code'];

    $sql = 'insert into tblPaypalTransactions ('.implode(',', $cols).') values ('.substr(str_repeat(',?', count($cols)), 1).') on duplicate key update ';
    for ($x = 2; $x < count($cols); $x++) {
        $sql .= ($x == 2 ? '' : ', ') . sprintf('%1$s = ifnull(values(%1$s), %1$s)', $cols[$x]);
    }

    $stmt = $db->prepare($sql);
    $stmt->bind_param('isssssddsiss',
        $test_ipn, $txn_id,
        $txn_type, $payment_date, $parent_txn_id,
        $mc_currency, $mc_fee, $mc_gross,
        $payment_status, $user,
        $pending_reason, $reason_code);
    $stmt->execute();
    $affected = $db->affected_rows;
    $stmt->close();

    if (!$affected && $addPaidTime) {
        DebugPaypalMessage("Error updating Paypal transaction record");
        return false;
    }

    if ($addPaidTime) {
        return ['addTime' => $user];
    }

    if (!is_null($user) && in_array($payment_status, ['Reversed','Refunded'])) {
        $skipReverse = false;
        if (!is_null($parent_txn_id)) {
            $sql = <<<'EOF'
select count(*), max(payment_date)
from tblPaypalTransactions
where test_ipn = ?
and txn_id != ?
and parent_txn_id = ?
and payment_status in ('Reversed', 'Canceled_Reversal', 'Refunded')
EOF;

            $stmt = $db->prepare($sql);
            $stmt->bind_param('iss', $test_ipn, $txn_id, $parent_txn_id);
            $stmt->execute();
            $c = $dt = null;
            $stmt->bind_result($c, $dt);
            $stmt->fetch();
            $stmt->close();

            if ($c != 0) {
                $skipReverse = true;
                DebugPaypalMessage("Already had a reversal on $dt", "Paypal Payment Reversal Skipped - $user");
                // not fatal, already processed
            }
        }
        if (!$skipReverse) {
            $stmt = $db->prepare('select paiduntil from tblUser where id = ?');
            $stmt->bind_param('i', $user);
            $stmt->execute();
            $paidUntil = null;
            $stmt->bind_result($paidUntil);
            if (!$stmt->fetch()) {
                $paidUntil = false;
            }
            $stmt->close();

            if ($paidUntil === false) {
                DebugPaypalMessage("Could not process reversal for missing user $user", "Paypal Payment Reversal Failed - $user");
                return false;
            }

            $paidUntil = is_null($paidUntil) ? 0 : strtotime($paidUntil);
            if ($paidUntil > time()) {
                DebugPaypalMessage("", "Paypal Payment Reversed - $user");
                return ['delTime' => $user];
            } else {
                DebugPaypalMessage("", "Redundant Paypal Payment Reversal - $user");
            }
        }
    }

    return [];
}

function DebugPaypalMessage($message, $subject = 'Paypal IPN Issue') {
    global $argv;

    $pth = __DIR__ . '/../logs/paypalerrors.log';
    if ($pth) {
        $me = (PHP_SAPI == 'cli') ? ('CLI:' . realpath($argv[0])) : ('Web:' . $_SERVER['REQUEST_URI']);
        file_put_contents($pth, Date('Y-m-d H:i:s') . " $me $message\n".print_r($_POST, true), FILE_APPEND | LOCK_EX);
    }

    NewsstandMail(SUBSCRIPTION_ERRORS_EMAIL_ADDRESS, 'Paypal Manager',
        $subject, $message . "<br><br>" . str_replace("\n", '<br>', print_r($_POST, true)));
}

function ValidatePaypalNotification($rawPost, $useSandbox = false) {
    $url = sprintf('https://www%s.paypal.com/cgi-bin/webscr', $useSandbox ? '.sandbox' : '');

    $result = PostHTTP($url, 'cmd=_notify-validate&'.$rawPost);
    if (trim($result) == 'VERIFIED') {
        return true;
    }

    DebugPaypalMessage("Paypal validation returned \"$result\". IP: ".(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'));
    return false;
}

function PaypalResultForUser($user, $paidUntil, $removed) {
    $db = DBConnect();

    $stmt = $db->prepare('select locale from tblUser where id = ?');
    $stmt->bind_param('i', $user);
    $stmt->execute();
    $locale = null;
    $stmt->bind_result($locale);
    if (!$stmt->fetch()) {
        $locale = false;
    }
    $stmt->close();

    if ($locale === false) {
        DebugPaypalMessage("Could not find user $user to send result message");
        return false;
    }

    if (is_null($locale)) {
        $locale = 'enus';
    }
    $LANG = GetLang($locale);

    $message = $removed ? $LANG['subscriptionTimeRemovedMessage'] : $LANG['subscriptionTimeAddedMessage'];
    if ($paidUntil > time()) {
        $message .= "<br><br>" . sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['paidExpires']), date('Y-m-d H:i:s e', $paidUntil));
    }

    SendUserMessage($user, 'Subscription', $LANG['paidSubscription'], $message);
}
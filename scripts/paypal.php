<?php

// symlinked in / for IPN

require_once __DIR__.'/../incl/incl.php';
require_once __DIR__.'/../incl/subscription.incl.php';

LogPaypalMessage("Starting");
FullPaypalProcess();
LogPaypalMessage("Done");

function FullPaypalProcess() {
    $isIPN = $_SERVER["SCRIPT_NAME"] != '/api/paypal.php';

    $txnId = isset($_POST['txn_id']) ? $_POST['txn_id'] : 'unknown';
    LogPaypalMessage("Received ".($isIPN ? "IPN" : "API")." txn: $txnId");

    $postResult = CheckPaypalPost();
    if ($postResult === 'Duplicate') {
        if (!$isIPN) {
            LogPaypalMessage("Forwarding duplicate post to paidfinish");
            header('Location: /#subscription/paidfinish');
        }
        return;
    } elseif ($postResult !== true) {
        if (!$isIPN) {
            LogPaypalMessage("Forwarding errored post to paidpending");
            header('Location: /#subscription/paidpending');
        } elseif (!isset($_POST['txn_id'])) {
            // ignore IPN txns without IDs
        } else {
            if (!is_string($postResult)) {
                $postResult = 'HTTP/1.0 500 Internal Server Error';
            }
            LogPaypalMessage("Setting errored post header: $postResult");
            header($postResult);
        }
        return;
    }

    $operation = ProcessPaypalPost();
    if ($operation === false) {
        if (!$isIPN) {
            LogPaypalMessage("Forwarding errored process to paiderror");
            header('Location: /#subscription/paiderror');
        } else {
            LogPaypalMessage("Returning errored process header HTTP 500");
            header('HTTP/1.0 500 Internal Server Error');
        }
        return;
    }

    if (isset($operation['addTime'])) {
        $newPaidUntil = AddPaidTime($operation['addTime'], SUBSCRIPTION_PAID_ADDS_SECONDS);
        LogPaypalMessage("Added time, paid until: $newPaidUntil");
        PaypalResultForUser($operation['addTime'], $newPaidUntil, false);
    }
    if (isset($operation['delTime'])) {
        $newPaidUntil = AddPaidTime($operation['delTime'], -1 * SUBSCRIPTION_PAID_ADDS_SECONDS);
        LogPaypalMessage("Removed time, paid until: $newPaidUntil");
        PaypalResultForUser($operation['addTime'], $newPaidUntil, true);
    }
    if (!$isIPN) {
        LogPaypalMessage("Forwarding to paidfinish");
        header('Location: /#subscription/paidfinish');
    }
}

function CheckPaypalPost() {
    global $PAYPAL_BUSINESSES;

    if (!isset($_POST['txn_id'])) {
        LogPaypalError("Received request without txn_id at Paypal IPN. IP: ".(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'));
        return 'HTTP/1.0 404 Not Found';
    }
    if (!isset($_POST['business']) || !in_array(strtolower(trim($_POST['business'])), $PAYPAL_BUSINESSES)) {
        LogPaypalError('Received invalid business from Paypal IPN: "'.(isset($_POST['business']) ? $_POST['business'] : '').'"');
        return 'HTTP/1.0 420 Not Verified';
    }

    $rawPost = file_get_contents('php://input');
    $isSandbox = isset($_POST['test_ipn']);
    //$isIPN = $_SERVER["SCRIPT_NAME"] != '/api/paypal.php';
    //file_put_contents(__DIR__.'/../../logs/paypalinput.log', "IPN: " . ($isIPN ? 'yes' : 'no') . "\tSandbox: " . ($isSandbox ? 'yes' : 'no') . "\t$rawPost\n", FILE_APPEND | LOCK_EX);

    $validationResult = ValidatePaypalNotification($rawPost, $isSandbox);
    if ($validationResult !== true) {
        LogPaypalMessage("Validation failed: $validationResult");
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

        $txn_type = null;
        if (isset($_POST['txn_type'])) {
            $txn_type = $_POST['txn_type'];
        } elseif (isset($_POST['payment_status'])) {
            $txn_type = $_POST['payment_status'];
        }

        if ($txnRow &&
            (is_null($txn_type) || $txnRow['txn_type'] == $txn_type) &&
            (!isset($_POST['payment_date']) || $txnRow['payment_date'] == date('Y-m-d H:i:s', strtotime($_POST['payment_date']))) &&
            (!isset($_POST['payment_status']) || $txnRow['payment_status'] == $_POST['payment_status'])) {
            // just a duplicate, probably received from post and IPN
            LogPaypalMessage("Found duplicate row, last updated: ".$txnRow['lastupdate']);
            return 'Duplicate';
        }

        $msg = "Paypal validation returned \"$validationResult\". IP: ".(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
        $isIPN = $_SERVER["SCRIPT_NAME"] != '/api/paypal.php';
        if ($isIPN) {
            LogPaypalError($msg);
        } else {
            LogPaypalMessage($msg);
        }
        return 'HTTP/1.0 420 Not Verified';
    }

    LogPaypalMessage("Validation successful");

    if ($isSandbox) {
        LogPaypalError('Ignored Paypal sandbox notification.');
        return 'HTTP/1.0 420 Not Verified';
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

    LogPaypalMessage("Existing transaction row: ".(isset($txnRow['lastupdate']) ? $txnRow['lastupdate'] : 'no'));

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

    LogPaypalMessage("Adding paid time: ".($addPaidTime ? 'yes' : 'no'));

    $user = isset($_POST['custom']) ? GetUserFromPublicHMAC($_POST['custom']) : null;

    LogPaypalMessage("User: $user");

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
    $success = $stmt->execute();
    $stmt->close();

    if (!$success) {
        LogPaypalError("Error updating Paypal transaction record");
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
                LogPaypalError("Already had a reversal on $dt", "Paypal Payment Reversal Skipped - $user");
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
                LogPaypalError("Could not process reversal for missing user $user", "Paypal Payment Reversal Failed - $user");
                return false;
            }

            $paidUntil = is_null($paidUntil) ? 0 : strtotime($paidUntil);
            if ($paidUntil > time()) {
                LogPaypalError("", "Paypal Payment Reversed - $user");
                return ['delTime' => $user];
            } else {
                LogPaypalError("", "Redundant Paypal Payment Reversal - $user");
            }
        }
    }

    return [];
}

function LogPaypalMessage($message) {
    return;
    //LogPaypalError((isset($_SERVER['REMOTE_ADDR']) ? ($_SERVER['REMOTE_ADDR'] . " ") : '') . $message, false);
}

function LogPaypalError($message, $subject = 'Paypal IPN Issue') {
    global $argv;

    $pth = __DIR__ . '/../logs/paypalerrors.log';
    if ($pth) {
        $me = (PHP_SAPI == 'cli') ? ('CLI:' . realpath($argv[0])) : ('Web:' . $_SERVER['REQUEST_URI']);
        file_put_contents($pth, date('Y-m-d H:i:s') . " $me $message\n".($subject === false ? '' : print_r($_POST, true)), FILE_APPEND | LOCK_EX);
    }

    if ($subject !== false) {
        NewsstandMail(
            SUBSCRIPTION_ERRORS_EMAIL_ADDRESS, 'Paypal Manager',
            $subject, $message . "<br><br>" . str_replace("\n", '<br>', print_r($_POST, true))
        );
    }
}

function ValidatePaypalNotification($rawPost, $useSandbox = false) {
    $url = sprintf('https://www%s.paypal.com/cgi-bin/webscr', $useSandbox ? '.sandbox' : '');

    LogPaypalMessage("Validating $rawPost");

    $result = \Newsstand\HTTP::Post($url, 'cmd=_notify-validate&'.$rawPost);
    if (trim($result) == 'VERIFIED') {
        return true;
    }

    return $result;
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
        LogPaypalError("Could not find user $user to send result message");
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
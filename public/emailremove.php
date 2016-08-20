<?php

require_once('../incl/incl.php');
require_once('../incl/subscription.incl.php');

$status = '';
$mailId = isset($_GET['mailid']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', substr(trim($_GET['mailid']), 0, 30)) : '';

if ($mailId && isset($_POST['confirm'])) {
    $status = BlockMailId($mailId);
}

ShowBlockConfirm($mailId, $status);

function ShowBlockConfirm($mailId, $status) {
    echo '<!DOCTYPE html><html><head><title>The Undermine Journal</title></head><body style="text-align: center"><img src="/images/underminetitle.2000.png" style="width: 90%; max-width: 900px">';

    echo "<h2>Email Address Removal</h2>";

    if ($status) {
        echo "<b>$status</b><br><br>";
    }

    if ($mailId) {
        echo 'Message ID: '.$mailId.'<br><br>';
        echo '<form method="POST" action="/emailremove.php?mailid=' . $mailId . '"><input type="submit" name="confirm" value="Never Email Me Again"></form>';
    } else {
        echo '<form method="GET" action="/emailremove.php">Mail ID: <input type="text" name="mailid" value="" maxlength="30"><input type="submit" value="Next >"></form>';
    }

    echo '<br><a href="/#contact">Contact us</a> if you have any questions.';

    echo '</body></html>';
}

function BlockMailId($mailId) {
    $db = DBConnect();
    if (!$db) {
        return 'Could not connect to database, please try again later.';
    }

    $address = GetAddressByMailID($mailId);

    if ($address === false) {
        return 'Could not find an email by that ID, please try again later.';
    }

    if (!is_null($address['blocked'])) {
        return $address['address'].' was removed on '.date('Y-m-d H:i:s', $address['blocked']);
    }

    $stmt = $db->prepare('insert into tblEmailBlocked (address, added) values (?, now())');
    $stmt->bind_param('s', $address['address']);
    $stmt->execute();
    $stmt->close();

    $rowCount = $db->affected_rows;

    if ($rowCount == 0) {
        return 'Error adding row to blocked table, please try again later.';
    }

    DisableEmailAddress($address['address']);

    return $address['address'] . ' will no longer receive any mail from The Undermine Journal.';
}

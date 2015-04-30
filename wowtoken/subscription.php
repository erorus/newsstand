<?php

require_once(__DIR__.'/../incl/incl.php');

if ($_SERVER["REQUEST_METHOD"] != 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

function ReturnBadRequest() {
    header('HTTP/1.1 400 Bad Request');
    exit();
}

if (!isset($_POST['action'])) {
    ReturnBadRequest();
}

switch($_POST['action']) {
    case 'subscribe':
        ActSubscribe();
        break;
    case 'unsubscribe':
        ActUnsubscribe();
        break;
    default:
        header('HTTP/1.1 400 Bad Request');
        exit();
}

header('Content-type: application/json; charset=UTF-8');

function ActSubscribe() {
    if (!isset($_POST['id']) || !isset($_POST['endpoint'])) {
        ReturnBadRequest();
    }

    global $db;
    DBConnect();

    $oldId = 0;
    $allGood = true;

    if (isset($_POST['oldid']) && isset($_POST['oldendpoint'])) {
        $sql = 'select id from tblWowTokenSubs where subid=? and endpoint=? limit 1';

        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $_POST['oldid'], $_POST['oldendpoint']);
        $stmt->execute();
        $stmt->bind_result($oldId);
        if (!$stmt->fetch()) {
            $oldId = 0;
        }
        $stmt->close();
    }

    if ($oldId) {
        $sql = 'update tblWowTokenSubs set subid=?, endpoint=?, lastseen=now() where id=?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssi', $_POST['id'], $_POST['endpoint'], $oldId);
        $allGood &= $stmt->execute();
        $stmt->close();
    } else {
        $sql = 'insert into tblWowTokenSubs (subid, endpoint, firstseen, lastseen) values (?, ?, now(), now())';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $_POST['id'], $_POST['endpoint']);
        $allGood &= $stmt->execute();
        $stmt->close();
    }

    if ($allGood) {
        echo json_encode(['id' => $_POST['id'], 'endpoint' => $_POST['endpoint']]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
    }
}

function ActUnsubscribe() {
    if (!isset($_POST['id']) || !isset($_POST['endpoint'])) {
        ReturnBadRequest();
    }

    global $db;
    DBConnect();

    $allGood = true;

    $sql = 'delete from tblWowTokenSubs where subid=? and endpoint=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $_POST['id'], $_POST['endpoint']);
    $allGood &= $stmt->execute();
    $stmt->close();

    if ($allGood) {
        echo json_encode(['id' => $_POST['id'], 'endpoint' => $_POST['endpoint']]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
    }
}

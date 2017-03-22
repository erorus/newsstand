<?php

if ($_SERVER["REQUEST_METHOD"] != 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

function ReturnBadRequest() {
    header('HTTP/1.1 400 Bad Request');
    exit();
}

if (!isset($_POST['shown'])) {
    ReturnBadRequest();
}

$logFile = __DIR__ . '/../logs/shown.log';

$logMessage = sprintf("%s %s %s \"%s\" \"%s\"\n",
    date('Y-m-d-H:i:s'),
    $_SERVER["REMOTE_ADDR"],
    ($_POST['shown'] == '1') ? 'shown' : 'blocked',
    $_SERVER["HTTP_USER_AGENT"],
    isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

header('HTTP/1.1 201 No Content');

<?php

// wrapper for some old TUJ functions to newsstand

require_once(__DIR__.'/../../incl/incl.php');

function do_connect() {
    DBConnect();
}

function cleanup($msg = '') {
    echo $msg;
    exit;
}

function run_sql($sql) {
    global $db;

    if (!($stmt = $db->prepare($sql))) {
        echo "\n";
        DebugMessage("\nCould not parse SQL:\n$sql\n", E_USER_ERROR);
    }

    $stmt->execute();
    $stmt->close();
}

function get_single_row($sql) {
    global $db;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_assoc();
    $result->close();
    $stmt->close();

    return $tr;
}

function get_rst($sql) {
    global $db;

    $hash = md5($sql);

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $GLOBALS['_recordsets'][$hash] = $result;
    $stmt->close();

    return $hash;
}

function next_row($hash) {
    $r = $GLOBALS['_recordsets'][$hash]->fetch_assoc();
    if (!$r) {
        $GLOBALS['_recordsets'][$hash]->close();
        unset($GLOBALS['_recordsets'][$hash]);
    }

    return $r;
}

function sql_esc($str) {
    global $db;

    return $db->real_escape_string($str);
}

function get_url_old($url) {
    return FetchHTTP($url);
}
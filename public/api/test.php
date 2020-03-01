<?php

/**
 * Endpoint for heartbeat services to determine that the site is still up.
 */

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');

function heartbeatFail(string $message) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-type: text/plain;charset=UTF-8');
    header('Cache-Control: no-cache');

    echo "Error: {$message}";
    exit;
}

function heartbeatMemcache() {
    $key   = 'heartbeat-test-' . time();
    $value = time();

    MCSet($key, $value, 10);
    if (MCGet($key) !== $value) {
        heartbeatFail("Memcache is down");
    }

    if (APIMaintenance() !== false) {
        header('Content-type: text/plain;charset=UTF-8');
        header('Cache-Control: no-cache');

        echo "In maintenance mode.";
        exit;
    }
}

function heartbeatDb() {
    $db = DBConnect();
    if (!$db) {
        throw new Exception("Could not get DB");
    }

    $sql  = 'SELECT COUNT(*) FROM tblRealm';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception("Invalid SQL statement");
    }
    $stmt->execute();
    $result = 0;
    $stmt->bind_result($result);
    $stmt->fetch();
    $stmt->close();

    if ( ! ($result > 0)) {
        heartbeatFail("Could not get realms");
    }

    return $result;
}

function main() {
    try {
        heartbeatMemcache();
    } catch (Exception $e) {
        heartbeatFail("Exception testing Memcache");
        return;
    }

    try {
        $realmCount = heartbeatDb();
    } catch (Exception $e) {
        heartbeatFail("Exception testing DB");
        return;
    }

    header('Content-type: text/plain;charset=UTF-8');
    header('Cache-Control: no-cache');

    echo "Fetched {$realmCount} realms.";
}

main();

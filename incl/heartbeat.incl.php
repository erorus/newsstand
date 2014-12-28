<?php

define('HEARTBEATPATH', '/tmp/heartbeat/');

function heartbeat_shutdown($pid)
{
    if ($pid == getmypid()) {
        unlink(HEARTBEATPATH . $pid);
    }
}

function heartbeat()
{
    static $pid = -1;
    static $lastHeartbeat = 0;

    if ($lastHeartbeat + 5 > time()) {
        return false;
    }

    $lastHeartbeat = time();

    if (!is_dir(rtrim(HEARTBEATPATH, '/'))) {
        mkdir(rtrim(HEARTBEATPATH, '/'), 0777, true);
    }

    if ($pid != getmypid()) {
        $pid = getmypid();
        register_shutdown_function('heartbeat_shutdown', $pid);
    }

    touch(HEARTBEATPATH . $pid);

    return true;
}

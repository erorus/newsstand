<?php

require_once('incl.php');
require_once('memcache.incl.php');

define('SUBSCRIPTION_LOGIN_COOKIE', 'session');
define('SUBSCRIPTION_SESSION_LENGTH', 1209600); // 2 weeks

function GetLoginState($logOut = false) {
    $userInfo = [];
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return $userInfo;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return $userInfo;
    }

    $stateBytes = base64_decode(strtr($state, '-_', '+/'));

    if ($logOut) {
        MCDelete('usersession_'.$state);

        $db = DBConnect();
        $stmt = $db->prepare('DELETE FROM tblUserSession WHERE session=?');
        $stmt->bind_param('s', $stateBytes);
        $stmt->execute();
        $stmt->close();
    } else {
        $userInfo = MCGet('usersession_'.$state);
        if ($userInfo === false) {
            $db = DBConnect();

            $stmt = $db->prepare('SELECT u.id, u.name FROM tblUserSession us join tblUser u on us.user=u.id WHERE us.session=?');
            $stmt->bind_param('s', $stateBytes);
            $stmt->execute();
            $result = $stmt->get_result();
            $userInfo = DBMapArray($result);
            $stmt->close();

            if (count($userInfo) < 1) {
                $logOut = true;
            } else {
                $userInfo = array_pop($userInfo);
                MCSet('usersession_'.$state, $userInfo);

                $ip = substr($_SERVER['REMOTE_ADDR'], 0, 40);
                $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 250);

                $stmt = $db->prepare('UPDATE tblUserSession SET lastseen=NOW(), ip=?, useragent=? WHERE session=?');
                $stmt->bind_param('sss', $ip, $ua, $stateBytes);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare('UPDATE tblUser SET lastseen=NOW() WHERE id=?');
                $stmt->bind_param('i', $userInfo['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($logOut) {
        setcookie(SUBSCRIPTION_LOGIN_COOKIE, '', time() - SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);
        return [];
    }

    if (!headers_sent()) {
        setcookie(SUBSCRIPTION_LOGIN_COOKIE, $state, time() + SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);
    }

    return $userInfo;
}


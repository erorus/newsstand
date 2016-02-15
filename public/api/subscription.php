<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/battlenet.credentials.php');
require_once('../../incl/subscription.incl.php');

if (isset($_POST['loginfrom']) && isset($_POST['region'])) {
    json_return(GetLoginParams($_POST['loginfrom'], $_POST['region']));
}

if (isset($_GET['state']) && isset($_GET['code'])) {
    LoginFinish(ProcessAuthCode($_GET['state'], $_GET['code']));
}

if (isset($_POST['logout'])) {
    json_return(GetLoginState(true));
}

if (isset($_POST['settings'])) {
    $loginState = GetLoginState();
    if (!$loginState) {
        json_return(false);
    }
    json_return([
        'email' => GetSubEmail($loginState),
        'messages' => GetSubMessages($loginState),
        ]);
}

json_return([]);

///////////////////////////////

function GetLoginParams($loginFrom, $region) {
    if (GetLoginState()) {
        return [];
    }

    $loginFrom = substr($loginFrom, 0, 120);
    if ($region != 'EU') {
        $region = 'US';
    }

    $json = [
        'clientId' => BATTLE_NET_KEY,
        'authUri' => BATTLE_NET_AUTH_URI,
        'redirectUri' => 'https://' . strtolower($_SERVER["HTTP_HOST"]) . $_SERVER["SCRIPT_NAME"],
        'state' => MakeNewState([
                'from' => $loginFrom,
                'region' => $region
            ]),
    ];

    return $json;
}

function MakeNewState($stateInfo) {
    $tries = 0;
    while ($tries++ < 10) {
        $state = strtr(base64_encode(openssl_random_pseudo_bytes(18)), '+/', '-_');
        if (MCAdd('bnetstate_'.$state, $stateInfo, 3600)) {
            return $state;
        }
    }
    return false;
}

function MakeNewSession($provider, $providerId, $userName) {
    $userInfo = [ // all params here must also be created from the DB in GetLoginState
        'id' => 0,
        'name' => $userName,
    ];

    $db = DBConnect();

    $stateBytesParam = '';
    $stmt = $db->prepare('SELECT COUNT(*) FROM tblUserSession WHERE session=?');
    $stmt->bind_param('s', $stateBytesParam);

    $tries = 0;
    $state = false;
    while ($tries++ < 10) {
        $stateBytesParam = $stateBytes = openssl_random_pseudo_bytes(18);
        $state = strtr(base64_encode($stateBytes), '+/', '-_');

        $stmt->execute();
        $cntReturn = 0;
        $stmt->bind_result($cntReturn);
        $stmt->fetch();

        if ($cntReturn > 0) {
            continue;
        }

        if (!MCAdd('usersession_'.$state, [])) {
            continue;
        }

        $stmt->close();

        $userId = GetUserByProvider($provider, $providerId, $userName);
        $ip = substr($_SERVER['REMOTE_ADDR'], 0, 40);
        $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 250);

        $stmt = $db->prepare('INSERT INTO tblUserSession (session, user, firstseen, lastseen, ip, useragent) values (?, ?, NOW(), NOW(), ?, ?)');
        $stmt->bind_param('siss', $stateBytes, $userId, $ip, $ua);
        $stmt->execute();

        $userInfo['id'] = $userId;
        MCSet('usersession_'.$state, $userInfo);

        break;
    }

    $stmt->close();

    if ($tries >= 10) {
        return false;
    }

    return $state;
}

function GetUserByProvider($provider, $providerId, $userName) {
    $db = DBConnect();

    $userName = substr(trim($userName ?: ''), 0, 32);
    if (!$userName) {
        $userName = null;
    }

    $userId = false;
    $stmt = $db->prepare('SELECT user FROM tblUserAuth WHERE provider=? AND providerid=?');
    $stmt->bind_param('ss', $provider, $providerId);
    $stmt->execute();
    $stmt->bind_result($userId);
    if (!$stmt->fetch()) {
        $userId = false;
    }
    $stmt->close();

    if ($userId !== false) {
        $stmt = $db->prepare('UPDATE tblUserAuth SET lastseen=NOW() WHERE provider=? AND providerid=?');
        $stmt->bind_param('ss', $provider, $providerId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('UPDATE tblUser SET name=IFNULL(?, name), lastseen=NOW() WHERE id=?');
        $stmt->bind_param('si', $userName, $userId);
        $stmt->execute();
        $stmt->close();

        return $userId;
    }

    $stmt = $db->prepare('INSERT INTO tblUser (name, firstseen, lastseen) VALUES (IFNULL(?, \'User\'), NOW(), NOW())');
    $stmt->bind_param('s', $userName);
    $stmt->execute();
    $stmt->close();

    $userId = $db->insert_id;

    $stmt = $db->prepare('INSERT INTO tblUserAuth (provider, providerid, user, firstseen, lastseen) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('ssi', $provider, $providerId, $userId);
    $stmt->execute();
    $stmt->close();

    return $userId;
}

function ProcessAuthCode($state, $code) {
    // user auth'd to battle.net, and came back with a code we can confirm w/battle.net
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($state, 0, 24));

    if (!isset($_SERVER['HTTPS']) || ($_SERVER['HTTPS'] == '')) {
        return '#subscription/nohttps';
    }

    $stateInfo = MCGet('bnetstate_'.$state);
    if ($stateInfo === false) {
        return '#subscription/nostate';
    }

    MCDelete('bnetstate_'.$state);

    // get access token using the code
    $url = sprintf(BATTLE_NET_TOKEN_URI, strtolower($stateInfo['region']));
    $toPost = [
        'redirect_uri' => 'https://' . strtolower($_SERVER["HTTP_HOST"]) . $_SERVER["SCRIPT_NAME"],
        'scope' => '',
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => BATTLE_NET_KEY,
        'client_secret' => BATTLE_NET_SECRET,
    ];
    $outHeaders = [];
    $tokenData = PostHTTP($url, $toPost, [], $outHeaders);
    if ($tokenData === false) {
        return '#subscription/notoken';
    }
    $tokenData = json_decode($tokenData, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        return '#subscription/badtoken';
    }
    if (!isset($tokenData['access_token'])) {
        return '#subscription/missingtoken';
    }
    $token = $tokenData['access_token'];

    // get user id and battle.net tag
    $url = sprintf('https://%s.api.battle.net/account/user?access_token=%s', strtolower($stateInfo['region']), $token);
    $userData = FetchHTTP($url);
    if ($userData === false) {
        return '#subscription/nouser';
    }
    $userData = json_decode($userData, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        return '#subscription/baduser';
    }
    if (!isset($userData['id']) || !isset($userData['battletag'])) {
        return '#subscription/missinguser';
    }

    // at this point we have the battle.net user id and battletag in $userData
    $session = MakeNewSession('Battle.net', $userData['id'], $userData['battletag']);
    if ($session === false) {
        return '#subscription/nosession';
    }
    setcookie(SUBSCRIPTION_LOGIN_COOKIE, $session, time()+SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);

    return $stateInfo['from'];
}

function LoginFinish($hash = '#subscription') {
    header('Location: https://' . $_SERVER["HTTP_HOST"] . '/' . $hash);
    exit;
}

function GetSubEmail($loginState)
{
    $json = [];


    return $json;
}

function GetSubMessages($loginState)
{
    $userId = $loginState['id'];
    $messages = MCGet(SUBSCRIPTION_MESSAGES_CACHEKEY . $userId);
    if ($messages !== false) {
        return $messages;
    }

    $db = DBConnect();
    $stmt = $db->prepare('SELECT seq, unix_timestamp(created) created, type, subject FROM tblUserMessages WHERE user=?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = DBMapArray($result, null);
    $stmt->close();

    MCSet(SUBSCRIPTION_MESSAGES_CACHEKEY . $userId, $messages);

    return $messages;
}
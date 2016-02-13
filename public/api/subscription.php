<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/battlenet.credentials.php');

define('SUBSCRIPTION_STATE_STEP_GET_AUTH', 1);
define('SUBSCRIPTION_STATE_STEP_GET_TOKEN', 2);
define('SUBSCRIPTION_STATE_STEP_LOG_IN', 3);

if (isset($_POST['loginfrom']) && isset($_POST['region'])) {
    json_return(GetLoginParams($_POST['loginfrom'], $_POST['region']));
}

if (isset($_POST['loginstate'])) {
    json_return(GetLoginState($_POST['loginstate']));
}

if (isset($_GET['state']) && isset($_GET['code'])) {
    ProcessAuthCode($_GET['state'], $_GET['code']);
}

json_return([]);

///////////////////////////////

function GetLoginParams($loginFrom, $region) {
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
                'region' => $region,
                'step' => SUBSCRIPTION_STATE_STEP_GET_AUTH,
            ]),
    ];

    return $json;
}

function MakeNewState($stateInfo) {
    $stateInfo['created'] = time();
    $tries = 0;
    while ($tries++ < 10) {
        $state = strtr(base64_encode(openssl_random_pseudo_bytes(18)), '+/', '-_');
        if (MCAdd('bnetstate_'.$state, $stateInfo)) {
            return $state;
        }
    }
    return false;
}

function GetLoginState($state) {
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($state, 0, 30));

    $stateInfo = MCGet('bnetstate_'.$state);
    if ($stateInfo === false) {
        return ['step' => 0];
    }

    if ($stateInfo['step'] == SUBSCRIPTION_STATE_STEP_LOG_IN) {
        MCDelete('bnetstate_'.$state);
    }

    return $stateInfo;
}

function ProcessAuthCode($state, $code) {
    // user auth'd to battle.net, and came back with a code we can confirm w/battle.net
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($state, 0, 30));

    $stateInfo = MCGet('bnetstate_'.$state);
    if ($stateInfo === false) {
        LoginFinish('#subscription/nostate');
    }

    if ($stateInfo['step'] != SUBSCRIPTION_STATE_STEP_GET_AUTH) {
        LoginFinish('#subscription/nostate');
    }
    $stateInfo['step'] = SUBSCRIPTION_STATE_STEP_GET_TOKEN;
    MCSet('bnetstate_'.$state, $stateInfo);

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
        LoginFinish('#subscription/notoken');
    }
    $tokenData = json_decode($tokenData, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        LoginFinish('#subscription/badtoken');
    }
    if (!isset($tokenData['access_token'])) {
        LoginFinish('#subscription/missingtoken');
    }
    $token = $tokenData['access_token'];

    // get user id and battle.net tag
    $url = sprintf('https://%s.api.battle.net/account/user?access_token=%s', strtolower($stateInfo['region']), $token);
    $userData = FetchHTTP($url);
    if ($userData === false) {
        LoginFinish('#subscription/nouser');
    }
    $userData = json_decode($userData, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        LoginFinish('#subscription/baduser');
    }
    if (!isset($userData['id']) || !isset($userData['battletag'])) {
        LoginFinish('#subscription/missinguser');
    }

    $stateInfo['step'] = SUBSCRIPTION_STATE_STEP_LOG_IN;
    $stateInfo['user'] = $userData;
    MCSet('bnetstate_'.$state, $stateInfo);
    LoginFinish($stateInfo['from']);
}

function LoginFinish($hash = '#subscription') {
    header('Location: https://' . $_SERVER["HTTP_HOST"] . '/' . $hash);
    exit;
}
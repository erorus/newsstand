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

// functions from now on require a logged-in user
// TODO: CSRF check
$loginState = GetLoginState();
if (!$loginState) {
    json_return(false);
}

if (isset($_POST['settings'])) {
    json_return([
        'email' => GetSubEmail($loginState),
        'messages' => GetSubMessages($loginState),
        ]);
}

if (isset($_POST['getmessage'])) {
    $message = GetSubMessage($loginState, intval($_POST['getmessage'], 10));
    if (!$message) {
        json_return(false);
    }
    json_return(['message' => $message]);
}

if (isset($_POST['emailaddress'])) {
    json_return(SetSubEmail($loginState, $_POST['emailaddress']));
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

    // new user

    $stmt = $db->prepare('INSERT INTO tblUser (name, firstseen, lastseen) VALUES (IFNULL(?, \'User\'), NOW(), NOW())');
    $stmt->bind_param('s', $userName);
    $stmt->execute();
    $stmt->close();

    $userId = $db->insert_id;

    $stmt = $db->prepare('INSERT INTO tblUserAuth (provider, providerid, user, firstseen, lastseen) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('ssi', $provider, $providerId, $userId);
    $stmt->execute();
    $stmt->close();

    $message = <<<'EOF'
Welcome to your Subscription page at <nobr>The Undermine Journal</nobr>. Thanks for logging in.<br/><br/>
On this page, you can find all the recent messages we've sent to you, along with your notifications and other site settings.
EOF;
    SendUserMessage($userId, 'Account', 'Enjoy your subscription!', $message);

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

function SetSubEmail($loginState, $address)
{
    $userId = $loginState['id'];
    $address = trim($address);
    if ($address) {
        $filtered = filter_var($address, FILTER_VALIDATE_EMAIL);
        if ($filtered === false) {
            return ['status' => 'invalid'];
        }
        $address = $filtered;
    }

    $db = DBConnect();
    $stmt = $db->prepare('SELECT ifnull(email,\'\') address, unix_timestamp(emailset) emailset, emailverification from tblUser where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $result->close();
    $stmt->close();

    if ($row === false) {
        // could not find user
        return ['status' => 'unknown'];
    }

    if ($address == $row['address']) {
        // setting address we already have saved
        return ['status' => is_null($row['emailverification']) ? 'success' : 'verify', 'address' => $row['address']];
    }

    if ($address && !is_null($row['emailverification']) && ($row['emailset'] > (time() - 15*60))) {
        // setting a new address when we recently sent a notification email to another address
        return ['status' => 'verify', 'address' => $row['address']];
    }

    if (!$address) {
        // removing the address
        $stmt = $db->prepare('update tblUser set email=null, emailverification=null where id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        if ($db->affected_rows == 0) {
            return ['status' => 'unknown'];
        }
        ClearLoginStateCache();

        SendUserMessage($userId, 'Email', 'Email Address Removed', 'We removed your email address from our system per your request.');
        return ['status' => 'success', 'address' => $address];
    }

    // setting a new address
    $stmt = $db->prepare('update tblUser set email=null, emailverification=null where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    if ($db->affected_rows == 0) {
        return ['status' => 'unknown'];
    }
    ClearLoginStateCache();

    $verification = str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_BOTH);

    NewsstandMail($address, $loginState['name'], 'Email Address Updated', 'We received your request for us to Email you at this address. Please <a href="https://theunderminejournal.com/#subscription">log in to The Undermine Journal</a> and enter this verification code:<br><br><b>'.implode(' ', str_split($verification, 3)).'</b>');
    SendUserMessage($userId, 'Email', 'Email Address Updated', 'We updated your email address per your request.<br><br>Please check your mail at '.htmlspecialchars($address, ENT_COMPAT | ENT_HTML5).' and enter the verification code which we sent there.');

    $stmt = $db->prepare('update tblUser set email=?, emailverification=?, emailset=NOW() where id = ?');
    $stmt->bind_param('ssi', $address, $verification, $userId);
    $stmt->execute();
    $stmt->close();
    if ($db->affected_rows == 0) {
        SendUserMessage($userId, 'Email', 'Email Address Removed', 'We removed your old email address from our system, but failed to replace it with the new address you specified.');
        return ['status' => 'unknown'];
    }

    return ['status' => 'verify', 'address' => $address];
}

function GetSubEmail($loginState)
{
    $json = [];
    $userId = $loginState['id'];

    $db = DBConnect();


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

function GetSubMessage($loginState, $seq)
{

    $userId = $loginState['id'];
    $cacheKey = SUBSCRIPTION_MESSAGES_CACHEKEY . $userId . '_' . $seq;
    $message = MCGet($cacheKey);
    if ($message !== false) {
        return $message;
    }

    $db = DBConnect();
    $stmt = $db->prepare('SELECT message FROM tblUserMessages WHERE user=? and seq=?');
    $stmt->bind_param('ii', $userId, $seq);
    $stmt->execute();
    $stmt->bind_result($message);
    if (!$stmt->fetch()) {
        $message = '';
    }
    $stmt->close();

    MCSet($cacheKey, $message);

    return $message;
}
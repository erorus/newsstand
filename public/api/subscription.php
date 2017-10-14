<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/battlenet.credentials.php');
require_once('../../incl/subscription.incl.php');

if (isset($_POST['loginfrom']) && isset($_POST['region'])) {
    json_return(GetLoginParams($_POST['loginfrom'], $_POST['region'], isset($_POST['locale']) ? $_POST['locale'] : null));
}

if (isset($_GET['state']) && isset($_GET['code'])) {
    LoginFinish(ProcessAuthCode($_GET['state'], $_GET['code']));
}

if (isset($_POST['logout'])) {
    json_return(RedactLoginState(GetLoginState(true)));
}

// functions from now on require a logged-in user
$loginState = GetLoginState();
if (!$loginState) {
    json_return(false);
}
if (!ValidateCSRFProtectedRequest()) {
    json_return(false);
}

if (isset($_POST['bitpayinvoice'])) {
    json_return(CreateBitPayInvoice($loginState));
}

if (isset($_POST['newlocale'])) {
    json_return(SetSubLocale($loginState, strtolower($_POST['newlocale'])));
}

if (isset($_POST['acceptsterms'])) {
    json_return(SetAcceptedTerms($loginState));
}

if (isset($_POST['settings'])) {
    json_return([
        'email' => GetSubEmail($loginState),
        'messages' => GetSubMessages($loginState),
        'watches' => GetWatches($loginState),
        'watchlimits' => GetWatchLimits($loginState),
        'rares' => GetRareWatches($loginState),
        'reports' => GetReports($loginState),
        'paid' => GetIsPaid($loginState),
        'rss' => GetRss($loginState),
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

if (isset($_POST['verifyemail'])) {
    json_return(VerifySubEmail($loginState, $_POST['verifyemail']));
}

if (isset($_POST['setperiod'])) {
    json_return(SetWatchPeriod($loginState, $_POST['setperiod']));
}

if (isset($_POST['getitem'])) {
    json_return(GetItemWatch($loginState, $_POST['getitem']));
}

if (isset($_POST['getspecies'])) {
    json_return(GetSpeciesWatch($loginState, $_POST['getspecies']));
}

if (isset($_POST['setwatch']) && isset($_POST['id'])) {
    $result = SetWatch($loginState,
        $_POST['setwatch'],
        $_POST['id'],
        isset($_POST['subid']) ? $_POST['subid'] : -1,
        isset($_POST['region']) ? $_POST['region'] : '',
        isset($_POST['house']) ? $_POST['house'] : 0,
        isset($_POST['direction']) ? $_POST['direction'] : '',
        isset($_POST['quantity']) ? $_POST['quantity'] : 0,
        isset($_POST['price']) ? $_POST['price'] : 0);
    if (!$result) {
        json_return(false);
    }
    switch ($_POST['setwatch']) {
        case 'item':
            json_return(GetItemWatch($loginState, $_POST['id']));
            break;
        case 'species':
            json_return(GetSpeciesWatch($loginState, $_POST['id']));
            break;
    }
    json_return([]);
}

if (isset($_POST['deletewatch'])) {
    $result = DeleteWatch($loginState, $_POST['deletewatch']);
    if ($result === false) {
        json_return(false);
    }
    switch ($result['type']) {
        case 'item':
            json_return(GetItemWatch($loginState, $result['id']));
            break;
        case 'species':
            json_return(GetSpeciesWatch($loginState, $result['id']));
            break;
    }
    json_return([]);
}

if (isset($_POST['setrare'])) {
    json_return(SetRareWatch($loginState,
        $_POST['setrare'],
        isset($_POST['quality']) ? intval($_POST['quality'], 10) : 0,
        isset($_POST['itemclass']) ? intval($_POST['itemclass'], 10) : null,
        isset($_POST['minlevel']) ? intval($_POST['minlevel'], 10) : null,
        isset($_POST['maxlevel']) ? intval($_POST['maxlevel'], 10) : null,
        isset($_POST['crafted']) ? !!intval($_POST['crafted'], 10) : false,
        isset($_POST['vendor']) ? !!intval($_POST['vendor'], 10) : false,
        isset($_POST['days']) ? intval($_POST['days'], 10) : null
        ));
}

if (isset($_POST['getrare'])) {
    json_return(GetRareWatches($loginState, $_POST['getrare']));
}

if (isset($_POST['deleterare'])) {
    json_return(DeleteRareWatch($loginState, isset($_POST['house']) ? $_POST['house'] : 0, $_POST['deleterare']));
}

json_return([]);

///////////////////////////////

function GetLoginParams($loginFrom, $region, $locale) {
    global $VALID_LOCALES;

    if (GetLoginState()) {
        return [];
    }

    $loginFrom = substr($loginFrom, 0, 120);
    if ($region != 'EU') {
        $region = 'US';
    }

    if (!in_array($locale, $VALID_LOCALES)) {
        $locale = $VALID_LOCALES[0];
    }

    $json = [
        'clientId' => BATTLE_NET_KEY,
        'authUri' => BATTLE_NET_AUTH_URI,
        'redirectUri' => 'https://' . strtolower($_SERVER["HTTP_HOST"]) . $_SERVER["SCRIPT_NAME"],
        'state' => MakeNewState([
                'from' => $loginFrom,
                'locale' => $locale,
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

function MakeNewSession($provider, $providerId, $userName, $locale) {
    $userInfo = [ // all params here must also be created from the DB in GetLoginState
        'id' => 0,
        'publicid' => '',
        'name' => $userName,
        'locale' => $locale,
        'acceptedterms' => null,
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

        $userId = GetUserByProvider($provider, $providerId, $userName, $locale);
        $ip = substr($_SERVER['REMOTE_ADDR'], 0, 40);
        $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 250);

        $stmt = $db->prepare('INSERT INTO tblUserSession (session, user, firstseen, lastseen, ip, useragent) values (?, ?, NOW(), NOW(), ?, ?)');
        $stmt->bind_param('siss', $stateBytes, $userId, $ip, $ua);
        $stmt->execute();

        $userInfo['id'] = $userId;
        $savedLocale = null;

        $stmt = $db->prepare('SELECT u.locale, concat_ws(\'|\', cast(ua.provider as unsigned), ua.providerid), unix_timestamp(u.acceptedterms) FROM tblUser u join tblUserAuth ua on ua.user = u.id WHERE u.id = ? group by u.id');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($savedLocale, $userInfo['publicid'], $userInfo['acceptedterms']);
        $stmt->fetch();
        $stmt->close();

        if (is_null($savedLocale)) { // usually only when account is created
            $stmt = $db->prepare('UPDATE tblUser SET locale = ? WHERE id = ?');
            $stmt->bind_param('si', $locale, $userId);
            $stmt->execute();
            $stmt->close();
        } else { // switch client locale to what they last used when logged in
            $userInfo['locale'] = $locale = $savedLocale;
        }

        MCSet('usersession_'.$state, $userInfo);

        break;
    }

    if ($tries >= 10) {
        $stmt->close();
        return false;
    }

    return $state;
}

function GetUserByProvider($provider, $providerId, $userName, $locale = 'enus') {
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

    $period = SUBSCRIPTION_WATCH_DEFAULT_PERIOD;
    $paidUntil = SUBSCRIPTION_NEW_USERS_PAID_UNTIL;
    $paidUntil = ($paidUntil && $paidUntil > time()) ? $paidUntil : null;
    $stmt = $db->prepare('INSERT INTO tblUser (name, firstseen, lastseen, watchperiod, paiduntil) VALUES (IFNULL(?, \'User\'), NOW(), NOW(), ?, FROM_UNIXTIME(?))');
    $stmt->bind_param('sii', $userName, $period, $paidUntil);
    $stmt->execute();
    $stmt->close();

    $userId = $db->insert_id;

    $stmt = $db->prepare('INSERT INTO tblUserAuth (provider, providerid, user, firstseen, lastseen) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('ssi', $provider, $providerId, $userId);
    $stmt->execute();
    $stmt->close();

    $lang = GetLang($locale);
    SendUserMessage($userId, 'Account', $lang['subscriptionWelcomeSubject'], $lang['subscriptionWelcomeMessage']);

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
    $tokenData = \Newsstand\HTTP::Post($url, $toPost, [], $outHeaders);
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
    $userData = \Newsstand\HTTP::Get($url);
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
    $session = MakeNewSession('Battle.net', $userData['id'], $userData['battletag'], $stateInfo['locale']);
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
    $lang = GetLang($loginState['locale']);

    $address = trim($address);
    if ($address) {
        $filtered = filter_var($address, FILTER_VALIDATE_EMAIL);
        if ($filtered === false) {
            return ['status' => 'invalid'];
        }
        $address = $filtered;
    }

    $db = DBConnect();

    $cnt = 0;
    $stmt = $db->prepare('select count(*) from tblEmailBlocked where address=?');
    $stmt->bind_param('s', $address);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    if ($cnt != 0) {
        return ['status' => 'invalid'];
    }

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

    if ($address && !is_null($row['emailverification']) && ($row['emailset'] > (time() - 15*60))) {
        // setting a new address when we recently sent a notification email to another address
        return ['status' => 'verify', 'address' => $row['address']];
    }

    if ($address == $row['address']) {
        // setting address we already have saved
        if (!is_null($row['emailverification'])) {
            // resend notification email
            NewsstandMail($address, $loginState['name'], $lang['emailAddressUpdated'], sprintf($lang['emailVerificationMessage'], implode(' ', str_split($row['emailverification'], 3))), $loginState['locale']);
            SendUserMessage($userId, 'Email', $lang['emailVerificationResent'], sprintf($lang['emailVerificationResentMessage'], htmlspecialchars($address, ENT_COMPAT | ENT_HTML5)));
        }
        return ['status' => is_null($row['emailverification']) ? 'success' : 'verify', 'address' => $row['address']];
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

        SendUserMessage($userId, 'Email', $lang['emailAddressRemoved'], $lang['emailAddressRemovedMessage']);
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

    $verification = str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_BOTH);

    NewsstandMail($address, $loginState['name'], $lang['emailAddressUpdated'], sprintf($lang['emailVerificationMessage'], implode(' ', str_split($verification, 3))), $loginState['locale']);
    SendUserMessage($userId, 'Email', $lang['emailAddressUpdated'], sprintf($lang['emailAddressUpdatedMessage'], htmlspecialchars($address, ENT_COMPAT | ENT_HTML5)));

    $stmt = $db->prepare('update tblUser set email=?, emailverification=?, emailset=NOW() where id = ?');
    $stmt->bind_param('ssi', $address, $verification, $userId);
    $stmt->execute();
    $stmt->close();
    if ($db->affected_rows == 0) {
        SendUserMessage($userId, 'Email', $lang['emailAddressRemoved'], $lang['emailAddressRemovedError']);
        return ['status' => 'unknown'];
    }

    return ['status' => 'verify', 'address' => $address];
}

function VerifySubEmail($loginState, $code)
{
    $userId = $loginState['id'];
    $lang = GetLang($loginState['locale']);

    $db = DBConnect();
    $stmt = $db->prepare('SELECT ifnull(email,\'\') address, ifnull(emailverification,\'\') verification from tblUser where id = ?');
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

    if ($row['address'] == '') {
        if ($row['verification']) {
            // asking user to verify an empty address, shouldn't happen
            $stmt = $db->prepare('update tblUser set emailverification=null where ifnull(email,\'\') =\'\' and id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        return ['status' => 'unknown', 'address' => ''];
    }

    if (!$row['verification']) {
        // trying to verify an already-verified address?
        return ['status' => 'success', 'address' => $row['address']];
    }

    $code = preg_replace('/\D/', '', $code);
    if ($row['verification'] == $code) {
        $stmt = $db->prepare('update tblUser set emailverification=null, emailset=null where id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        SendUserMessage($userId, 'Email', $lang['emailAddressVerified'], sprintf($lang['emailAddressVerifiedMessage'], $row['address']));
        return ['status' => 'success', 'address' => $row['address']];
    }

    return ['status' => 'verify', 'address' => $row['address']];
}

function GetSubEmail($loginState)
{
    $json = [];
    $userId = $loginState['id'];

    $db = DBConnect();
    $stmt = $db->prepare('select ifnull(email,\'\'), ifnull(emailverification, \'\') from tblUser where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $address = '';
    $verification = '';
    $stmt->bind_result($address, $verification);
    if (!$stmt->fetch()) {
        $address = '';
        $verification = '';
    }
    $stmt->close();

    $json['address'] = $address;
    $json['needVerification'] = ($address != '') && ($verification != '');

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

function GetItemWatch($loginState, $item)
{
    return GetWatch($loginState, 'item', $item);
}

function GetSpeciesWatch($loginState, $species)
{
    return GetWatch($loginState, 'species', $species);
}

function GetWatch($loginState, $type, $id)
{
    $userId = $loginState['id'];

    $totalWatchRemaining = max(0, SUBSCRIPTION_WATCH_LIMIT_TOTAL - GetWatchCount($userId));

    $json = [];
    $id = intval($id, 10);
    if (!$id) {
        return ['maximum' => min($totalWatchRemaining, SUBSCRIPTION_WATCH_LIMIT_PER), 'watches' => $json];
    }

    $cacheKeyPrefix = defined('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') ?
        constant('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') :
        'subunknown_'.substr($type, 0, 20);

    $cacheKey = $cacheKeyPrefix . $userId . '_' . $id;
    $json = MCGet($cacheKey);
    if ($json !== false) {
        return ['maximum' => min($totalWatchRemaining + count($json), SUBSCRIPTION_WATCH_LIMIT_PER), 'watches' => $json];
    }

    $db = DBConnect();
    $stmt = $db->prepare('SELECT seq, region, if(region is null, house, null) house, item, level, species, direction, quantity, price FROM tblUserWatch WHERE user=? and '.(($type == 'species') ? 'species' : 'item').'=? and deleted is null');
    $stmt->bind_param('ii', $userId, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $json = DBMapArray($result);
    $stmt->close();

    MCSet($cacheKey, $json);

    return ['maximum' => min($totalWatchRemaining + count($json), SUBSCRIPTION_WATCH_LIMIT_PER), 'watches' => $json];
}

function SetWatch($loginState, $type, $item, $level, $region, $house, $direction, $quantity, $price)
{
    $userId = $loginState['id'];

    $type = ($type == 'species') ? 'species' : 'item';
    $subType = ($type == 'species') ? false : 'level';

    $item = intval($item, 10);
    if (!$item) {
        return false;
    }
    $level = intval($level, 10);
    if ($level < 0 || !$subType) {
        $level = null;
    }

    $house = intval($house, 10);
    if ($house <= 0) {
        if (!in_array($region, ['US','EU'])) {
            return false;
        }
        $house = null;
    } else {
        $region = null;
    }

    if (!in_array($direction, ['Under','Over'])) {
        return false;
    }

    $quantity = intval($quantity, 10);
    if ($quantity < 0) {
        $quantity = null;
    }
    $price = intval($price, 10);
    if ($price < 0) {
        $price = null;
    }
    if (!is_null($quantity)) {
        if (is_null($price)) {
            // qty available query
            if ($quantity == 0 && $direction == 'Under') {
                // qty never under 0
                return false;
            }
        } else {
            // cost to buy $quantity is $direction $price
            if ($quantity == 0) {
                // must buy at least 1
                return false;
            }
            if ($price == 0 && $direction == 'Under') {
                // price never under 0
                return false;
            }
        }
    } else {
        // market price queries
        if (is_null($price)) {
            // both qty and price null
            return false;
        }
        if ($price <= 0) {
            // price never under 0
            return false;
        }
    }

    $loops = 0;
    while (!MCAdd(SUBSCRIPTION_WATCH_LOCK_CACHEKEY . $userId, 1, 15)) {
        usleep(250000);
        if ($loops++ >= 120) { // 30 seconds
            return false;
        }
    }

    $db = DBConnect();
    $db->begin_transaction();

    $stmt = $db->prepare('select seq, region, house, direction, quantity, price from tblUserWatch where user = ? and '.$type.' = ? and ifnull('.($subType ?: 'null').',0) = ifnull(?,0) and deleted is null for update');
    $stmt->bind_param('iii', $userId, $item, $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $curWatches = DBMapArray($result);
    $stmt->close();

    $fail = false;

    $cnt = count($curWatches);
    $fail |= $cnt > SUBSCRIPTION_WATCH_LIMIT_PER;

    foreach ($curWatches as $curWatch) {
        $fail |= !is_null($curWatch['region']) && ($curWatch['region'] == $region) && ($curWatch['direction'] == $direction) && ($curWatch['quantity'] == $quantity) && ($curWatch['price'] == $price);
        $fail |= is_null($region) && is_null($curWatch['region']) && ($curWatch['house'] == $house) && ($curWatch['direction'] == $direction) && ($curWatch['quantity'] == $quantity) && ($curWatch['price'] == $price);
    }

    if ($fail) {
        $db->rollback();
        MCDelete(SUBSCRIPTION_WATCH_LOCK_CACHEKEY . $userId);
        return false;
    }

    if (GetWatchCount($userId, $db) >= SUBSCRIPTION_WATCH_LIMIT_TOTAL) {
        $db->rollback();
        MCDelete(SUBSCRIPTION_WATCH_LOCK_CACHEKEY . $userId);
        return false;
    }

    $stmt = $db->prepare('update tblUser set watchsequence = last_insert_id(watchsequence+1) where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    $seq = $db->insert_id;

    if ($subType) {
        $stmt = $db->prepare('insert into tblUserWatch (user, seq, region, house, ' . $type . ', ' . $subType . ', direction, quantity, price, created) values (?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->bind_param('iisiiisii', $userId, $seq, $region, $house, $item, $level, $direction, $quantity, $price);
    } else {
        $stmt = $db->prepare('insert into tblUserWatch (user, seq, region, house, ' . $type . ', direction, quantity, price, created) values (?,?,?,?,?,?,?,?,NOW())');
        $stmt->bind_param('iisiisii', $userId, $seq, $region, $house, $item, $direction, $quantity, $price);
    }
    $stmt->execute();
    $stmt->close();
    $cnt = $db->affected_rows;

    if ($cnt == 0) {
        $db->rollback();
        MCDelete(SUBSCRIPTION_WATCH_LOCK_CACHEKEY . $userId);
        return false;
    }

    $db->commit();
    MCDelete(SUBSCRIPTION_WATCH_LOCK_CACHEKEY . $userId);

    $cacheKeyPrefix = defined('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') ?
        constant('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') :
        'subunknown_'.substr($type, 0, 20);

    MCDelete($cacheKeyPrefix . $userId . '_' . $item);
    MCDelete($cacheKeyPrefix . $userId);
    MCDelete(SUBSCRIPTION_WATCH_COUNT_CACHEKEY . $userId);

    return true;
}

function DeleteWatch($loginState, $watch)
{
    $userId = $loginState['id'];
    $watch = intval($watch, 10);

    $db = DBConnect();
    $stmt = $db->prepare('SELECT item, species, unix_timestamp(created) created FROM tblUserWatch WHERE user=? and seq=?');
    $stmt->bind_param('ii', $userId, $watch);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $result->close();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $sql = 'update tblUserWatch set deleted=now() where user=? and seq=?';
    if ($row['created'] > (time() - 15 * 60)) {
        $sql = 'delete from tblUserWatch where user=? and seq=?';
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $userId, $watch);
    $stmt->execute();
    $stmt->close();

    $cnt = $db->affected_rows;
    if ($cnt == 0) {
        return false;
    }

    $tr = true;
    if (isset($row['item'])) {
        MCDelete(SUBSCRIPTION_ITEM_CACHEKEY . $userId . '_' . $row['item']);
        MCDelete(SUBSCRIPTION_ITEM_CACHEKEY . $userId);
        $tr = ['type' => 'item', 'id' => $row['item']];
    }
    if (isset($row['species'])) {
        MCDelete(SUBSCRIPTION_SPECIES_CACHEKEY . $userId . '_' . $row['species']);
        MCDelete(SUBSCRIPTION_SPECIES_CACHEKEY . $userId);
        $tr = ['type' => 'species', 'id' => $row['species']];
    }
    MCDelete(SUBSCRIPTION_WATCH_COUNT_CACHEKEY . $userId);

    return $tr;
}

function GetWatchCount($userId, $db = null) {
    $cnt = MCGet(SUBSCRIPTION_WATCH_COUNT_CACHEKEY . $userId);
    if ($cnt !== false) {
        return $cnt;
    }

    if (is_null($db)) {
        $db = DBConnect();
    }

    $cnt = 0;
    $stmt = $db->prepare('select count(*) from tblUserWatch where user = ? and deleted is null');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    MCSet(SUBSCRIPTION_WATCH_COUNT_CACHEKEY . $userId, $cnt);

    return $cnt;
}

function GetWatchLimits($loginState)
{
    $userId = $loginState['id'];

    return [
        'used' => GetWatchCount($userId),
        'total' => SUBSCRIPTION_WATCH_LIMIT_TOTAL,
        'per' => SUBSCRIPTION_WATCH_LIMIT_PER,
    ];
}

function GetWatches($loginState)
{
    $userId = $loginState['id'];

    $cacheKey = SUBSCRIPTION_ITEM_CACHEKEY . $userId;
    $items = MCGet($cacheKey);
    if ($items === false) {
        $sql = <<<EOF
select uw.seq, uw.region, uw.house,
    uw.item, uw.level,
    i.icon, i.class,
    uw.direction, uw.quantity, uw.price
from tblUserWatch uw
join tblDBCItem i on uw.item = i.id
where uw.user = ?
and uw.deleted is null
group by uw.seq
EOF;

        $db = DBConnect();
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = DBMapArray($result);
        $stmt->close();

        MCSet($cacheKey, $items);
    }

    PopulateLocaleCols($items, [
        ['func' => 'GetItemNames',          'key' => 'item',   'name' => 'name'],
    ], true);

    $cacheKey = SUBSCRIPTION_SPECIES_CACHEKEY . $userId;
    $battlePets = MCGet($cacheKey);
    if ($battlePets === false) {
        $sql = <<<EOF
select uw.seq, uw.region, uw.house,
    uw.species,
    p.icon, p.type, p.npc,
    uw.direction, uw.quantity, uw.price
from tblUserWatch uw
JOIN tblDBCPet p on uw.species=p.id
where uw.user = ?
and uw.deleted is null
EOF;

        $db = DBConnect();
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $battlePets = DBMapArray($result);
        $stmt->close();

        MCSet($cacheKey, $battlePets);
    }

    PopulateLocaleCols($battlePets, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']], true);

    $json = [
        'data' => $items['data'] + $battlePets['data'],
        'hydrate' => array_merge($items['hydrate'], $battlePets['hydrate']),
    ];

    return $json;
}

function GetReports($loginState)
{
    $userId = $loginState['id'];
    $isPaid = !is_null($loginState['paiduntil']) && $loginState['paiduntil'] > time();

    $cacheKey = SUBSCRIPTION_REPORTS_CACHEKEY . $userId;
    $reports = MCGet($cacheKey);
    if ($reports === false) {
        $reports = [
            'period' => null,
            'lastreport' => null,
        ];

        $db = DBConnect();
        $stmt = $db->prepare('select watchperiod, unix_timestamp(watchesreported) from tblUser where id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($reports['period'], $reports['lastreport']);
        $stmt->fetch();
        $stmt->close();

        MCSet($cacheKey, $reports);
    }

    $reports['minperiod'] = $isPaid ? SUBSCRIPTION_WATCH_MIN_PERIOD : SUBSCRIPTION_WATCH_MIN_PERIOD_FREE;
    $reports['maxperiod'] = SUBSCRIPTION_WATCH_MAX_PERIOD;

    return $reports;
}

function SetWatchPeriod($loginState, $period)
{
    $userId = $loginState['id'];
    $isPaid = !is_null($loginState['paiduntil']) && $loginState['paiduntil'] > time();

    $period = intval($period, 10);
    $period = max(min($period, SUBSCRIPTION_WATCH_MAX_PERIOD), SUBSCRIPTION_WATCH_MIN_PERIOD);
    if (!$isPaid) {
        $period = max($period, SUBSCRIPTION_WATCH_MIN_PERIOD_FREE);
    }

    $db = DBConnect();
    $stmt = $db->prepare('update tblUser set watchperiod = ? where id = ?');
    $stmt->bind_param('ii', $period, $userId);
    $stmt->execute();
    $stmt->close();

    MCDelete(SUBSCRIPTION_REPORTS_CACHEKEY . $userId);

    return GetReports($loginState);
}

function SetSubLocale($loginState, $locale)
{
    global $VALID_LOCALES;

    $userId = $loginState['id'];
    if (!in_array($locale, $VALID_LOCALES)) {
        $locale = $VALID_LOCALES[0];
    }

    $db = DBConnect();

    $stmt = $db->prepare('update tblUser set locale = ? where id = ?');
    $stmt->bind_param('si', $locale, $userId);
    $stmt->execute();
    $stmt->close();

    ClearLoginStateCache();

    return RedactLoginState(GetLoginState());
}

function SetAcceptedTerms($loginState)
{
    $userId = $loginState['id'];

    $db = DBConnect();

    $stmt = $db->prepare('update tblUser set acceptedterms = now() where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    ClearLoginStateCache();

    return RedactLoginState(GetLoginState());
}

function GetIsPaid($loginState)
{
    $json = [
        'until' => $loginState['paiduntil']
    ];

    if (isset($json['until']) && ($json['until'] < time())) {
        $json['until'] = null;
    }

    $json['accept'] = false;

    if (SUBSCRIPTION_PAID_ACCEPT_PAYMENTS && ($json['until'] < (time() + SUBSCRIPTION_PAID_RENEW_WINDOW_DAYS * 86400))) {
        $json['accept'] = [
            'button' => SUBSCRIPTION_PAID_ACCEPT_BUTTON,
            'price' => SUBSCRIPTION_PAID_PRICE,
            'days' => round(SUBSCRIPTION_PAID_ADDS_SECONDS / 86400),
            'custom' => GeneratePublicUserHMAC($loginState['publicid']),
        ];
    }

    return $json;
}

function GetRss($loginState)
{
    $cacheKey = 'subrss2_' . $loginState['id'];

    $rss = MCGet($cacheKey);
    if ($rss === false) {
        $db = DBConnect();

        $stmt = $db->prepare('select rss from tblUser WHERE id = ?');
        $stmt->bind_param('i', $loginState['id']);
        $stmt->execute();
        $stmt->bind_result($rss);
        if (!$stmt->fetch()) {
            $rss = ''; // user not found, or some other error?
        }
        $stmt->close();

        if (is_null($rss)) { // usually only when account is created
            $rss = UpdateUserRss($loginState['id'], $db);
        }
        if (!$rss) {
            $rss = ''; // can't store boolean false in memcache
        }

        MCSet($cacheKey, $rss);
    }

    if (!$rss) { // i.e. empty string
        $rss = false; 
    }

    return $rss;
}

function GetRareWatches($loginState, $house = 0)
{
    $userId = $loginState['id'];

    $house = intval($house, 10);

    $limit = SUBSCRIPTION_RARE_LIMIT_TOTAL;
    if ($house) {
        $allWatches = GetRareWatches($loginState);
        $otherHouseCount = 0;
        foreach ($allWatches['watches'] as $watch) {
            if ($watch['house'] != $house) {
                $otherHouseCount++;
            }
        }
        $limit = min(SUBSCRIPTION_RARE_LIMIT_HOUSE, SUBSCRIPTION_RARE_LIMIT_TOTAL - $otherHouseCount);
    }

    $cacheKey = SUBSCRIPTION_RARE_CACHEKEY . $userId . '_' . $house;
    $json = MCGet($cacheKey);
    if ($json !== false) {
        return ['maximum' => $limit, 'watches' => $json];
    }

    $db = DBConnect();
    $sql = 'SELECT seq, house, itemclass, minquality, minlevel, maxlevel, flags & 1 as includecrafted, flags & 2 as includevendor, days from tblUserRare where user = ?';
    if ($house) {
        $sql .= ' and house = ?';
    }
    $stmt = $db->prepare($sql);
    if ($house) {
        $stmt->bind_param('ii', $userId, $house);
    } else {
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $json = DBMapArray($result);
    $stmt->close();

    MCSet($cacheKey, $json);

    return ['maximum' => $limit, 'watches' => $json];
}

function SetRareWatch($loginState, $house, $quality, $itemClass, $minLevel, $maxLevel, $crafted, $vendor, $days) {
    $userId = $loginState['id'];

    $house = intval($house, 10);

    $fail = false;
    $fail |= $quality < 0;
    $fail |= $quality > 5;
    $fail |= $itemClass <= 0;
    $fail |= $itemClass >= 30;
    if ($maxLevel == 0 || $maxLevel == 999) {
        $maxLevel = null;
    }
    if ($minLevel == 0) {
        $minLevel = null;
    }
    if ($maxLevel < $minLevel && !is_null($maxLevel)) {
        $maxLevel = $minLevel;
    }
    $fail |= $maxLevel < 0;
    $fail |= $minLevel < 0;
    $fail |= $maxLevel > 999;
    $fail |= $minLevel > 999;
    $fail |= $days < 14;
    $fail |= $days > 730;

    if (!$fail) {
        $curWatches = GetRareWatches($loginState);
        $curWatches = $curWatches['watches'];
        $fail |= count($curWatches) >= SUBSCRIPTION_RARE_LIMIT_TOTAL;
    }
    if (!$fail) {
        $existingHouse = 0;
        foreach ($curWatches as $watch) {
            if ($watch['house'] == $house) {
                $existingHouse++;
            }
        }
        $fail |= $existingHouse >= SUBSCRIPTION_RARE_LIMIT_HOUSE;
    }

    if (!$fail) {
        $sql = <<<'EOF'
select max(s)
from (
    select min(seq) + 1 s
    from tblUserRare ur
    where ur.user = ?
    and not exists (
        select 1
        from tblUserRare ur2
        where ur2.user = ur.user
        and ur2.seq = ur.seq + 1
        )
    union
    select 1
    from (select 1) r
    where (select count(*) from tblUserRare where user = ?) = 0
) x
EOF;

        $db = DBConnect();
        $db->begin_transaction();
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $userId, $userId);
        $newId = null;
        $stmt->execute();
        $stmt->bind_result($newId);
        if (!$stmt->fetch()) {
            $newId = null;
        }
        $stmt->close();

        if (!is_null($newId)) {
            $stmt = $db->prepare('insert into tblUserRare (user, seq, house, itemclass, minquality, minlevel, maxlevel, flags, days) values (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $flags = ($crafted ? 1 : 0) | ($vendor ? 2 : 0);
            $stmt->bind_param('iiiiiiiii', $userId, $newId, $house, $itemClass, $quality, $minLevel, $maxLevel, $flags, $days);
            $stmt->execute();
            $stmt->close();
        }

        $db->commit();

        MCDelete(SUBSCRIPTION_RARE_CACHEKEY . $userId . '_' . $house);
        MCDelete(SUBSCRIPTION_RARE_CACHEKEY . $userId . '_0');
    }

    return GetRareWatches($loginState, $house);
}

function DeleteRareWatch($loginState, $house, $seq)
{
    $userId = $loginState['id'];
    $seq = intval($seq, 10);

    $db = DBConnect();
    $sql = 'delete from tblUserRare where user=? and seq=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $userId, $seq);
    $stmt->execute();
    $stmt->close();

    if ($house) {
        MCDelete(SUBSCRIPTION_RARE_CACHEKEY . $userId . '_' . $house);
    }
    MCDelete(SUBSCRIPTION_RARE_CACHEKEY . $userId . '_0');

    return GetRareWatches($loginState, $house);
}

function CreateBitPayInvoice($loginState)
{
    $paid = GetIsPaid($loginState);
    if (!$paid['accept']) {
        DebugMessage("User ".$loginState['id']." attempted to create BitPay invoice when we would not accept payments.");
        return [];
    }

    $priceAmount = preg_match('/\d+\.\d+/', SUBSCRIPTION_PAID_PRICE, $res) ? $res[0] : false;
    $priceCurrency = preg_match('/\b[A-Z]{3}\b/', SUBSCRIPTION_PAID_PRICE, $res) ? $res[0] : false;

    if (!$priceAmount || !$priceCurrency) {
        DebugMessage("Could not find price ($priceAmount) or currency ($priceCurrency) from ".SUBSCRIPTION_PAID_PRICE." when creating BitPay invoice.");
        return [];
    }

    $LANG = GetLang($loginState['locale']);

    $toPost = [
        'price' => $priceAmount,
        'currency' => $priceCurrency,
        'posData' => $paid['accept']['custom'],
        'fullNotifications' => true,
        'notificationURL' => SUBSCRIPTION_BITPAY_IPN_URL,
        'notificationEmail' => SUBSCRIPTION_ERRORS_EMAIL_ADDRESS,
        'redirectURL' => 'https://' . strtolower($_SERVER["HTTP_HOST"]) . '/#subscription',
        'itemDesc' => $LANG['paidSubscription'] . ' - ' . round(SUBSCRIPTION_PAID_ADDS_SECONDS / 86400) . ' ' . $LANG['timeDays'],
    ];

    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic '.base64_encode(SUBSCRIPTION_BITPAY_KEY.':'),
    ];

    $jsonString = \Newsstand\HTTP::Post(SUBSCRIPTION_BITPAY_INVOICE_URL, json_encode($toPost), $headers);
    if (!$jsonString) {
        DebugMessage("No response from BitPay having sent:\n" . json_encode($toPost) . "\n" . print_r($headers, true));
        return [];
    }

    $json = json_decode($jsonString, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("Invalid response from BitPay:\n$jsonString\nHaving sent:\n" . json_encode($toPost) . "\n" . print_r($headers, true));
        return [];
    }

    if (!UpdateBitPayTransaction($json, true)) {
        return [];
    }

    return ['url' => $json['url']];
}
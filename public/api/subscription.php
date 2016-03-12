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
        'watches' => GetWatches($loginState),
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
    $verificationMsg = 'We received your request for us to Email you at this address. Please <a href="https://theunderminejournal.com/#subscription">log in to The Undermine Journal</a> and enter this verification code:<br><br><b>%s</b>';

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
            NewsstandMail($address, $loginState['name'], 'Email Address Updated', sprintf($verificationMsg, implode(' ', str_split($row['emailverification'], 3))));
            SendUserMessage($userId, 'Email', 'Email Verification Resent', 'We re-sent the verification code to your email per your request.<br><br>Please check your mail at '.htmlspecialchars($address, ENT_COMPAT | ENT_HTML5).' and enter the verification code which we sent there.');
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

    NewsstandMail($address, $loginState['name'], 'Email Address Updated', sprintf($verificationMsg, implode(' ', str_split($verification, 3))));
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

function VerifySubEmail($loginState, $code)
{
    $userId = $loginState['id'];

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

        SendUserMessage($userId, 'Email', 'Email Address Verified', 'You successfully verified your control over the address '.$row['address'].', and The Undermine Journal will send all your future messages there.');
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

    $json = [];
    $id = intval($id, 10);
    if (!$id) {
        return ['maximum' => SUBSCRIPTION_WATCH_LIMIT_PER, 'watches' => $json];
    }

    $cacheKeyPrefix = defined('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') ?
        constant('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') :
        'subunknown_'.substr($type, 0, 20);

    $cacheKey = $cacheKeyPrefix . $userId . '_' . $id;
    $json = MCGet($cacheKey);
    if ($json !== false) {
        return ['maximum' => SUBSCRIPTION_WATCH_LIMIT_PER, 'watches' => $json];
    }

    $db = DBConnect();
    $stmt = $db->prepare('SELECT seq, region, if(region is null, house, null) house, item, bonusset, species, breed, direction, quantity, price FROM tblUserWatch WHERE user=? and '.(($type == 'species') ? 'species' : 'item').'=? and deleted is null');
    $stmt->bind_param('ii', $userId, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $json = DBMapArray($result);
    $stmt->close();

    MCSet($cacheKey, $json);

    return ['maximum' => SUBSCRIPTION_WATCH_LIMIT_PER, 'watches' => $json];
}

function SetWatch($loginState, $type, $item, $bonusSet, $region, $house, $direction, $quantity, $price)
{
    $userId = $loginState['id'];

    $type = ($type == 'species') ? 'species' : 'item';
    $subType = ($type == 'species') ? 'breed' : 'bonusset';

    $item = intval($item, 10);
    if (!$item) {
        return false;
    }
    $bonusSet = intval($bonusSet, 10);
    if ($bonusSet < 0) {
        $bonusSet = null;
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
    while (!MCAdd(SUBSCRIPTION_WATCH_CACHEKEY . "lock_$userId", 1, 15)) {
        usleep(250000);
        if ($loops++ >= 120) { // 30 seconds
            return false;
        }
    }

    $db = DBConnect();
    $db->begin_transaction();

    $stmt = $db->prepare('select count(*) from tblUserWatch where user = ? and '.$type.' = ? and ifnull('.$subType.',0) = ifnull(?,0) and deleted is null for update');
    $stmt->bind_param('iii', $userId, $item, $bonusSet);
    $stmt->execute();
    $cnt = 0;
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    if ($cnt > SUBSCRIPTION_WATCH_LIMIT_PER) {
        $db->rollback();
        MCDelete(SUBSCRIPTION_WATCH_CACHEKEY . "lock_$userId");
        return false;
    }

    $cnt = 0;
    $stmt = $db->prepare('select count(*) from tblUserWatch where user = ? and deleted is null');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    if ($cnt > SUBSCRIPTION_WATCH_LIMIT_TOTAL) {
        $db->rollback();
        MCDelete(SUBSCRIPTION_WATCH_CACHEKEY . "lock_$userId");
        return false;
    }

    $stmt = $db->prepare('update tblUser set watchsequence = last_insert_id(watchsequence+1) where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    $seq = $db->insert_id;

    $stmt = $db->prepare('insert into tblUserWatch (user, seq, region, house, '.$type.', '.$subType.', direction, quantity, price, created) values (?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->bind_param('iisiiisii', $userId, $seq, $region, $house, $item, $bonusSet, $direction, $quantity, $price);
    $stmt->execute();
    $stmt->close();
    $cnt = $db->affected_rows;

    if ($cnt == 0) {
        $db->rollback();
        MCDelete(SUBSCRIPTION_WATCH_CACHEKEY . "lock_$userId");
        return false;
    }

    $db->commit();
    MCDelete(SUBSCRIPTION_WATCH_CACHEKEY . "lock_$userId");

    $cacheKeyPrefix = defined('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') ?
        constant('SUBSCRIPTION_' . strtoupper($type) . '_CACHEKEY') :
        'subunknown_'.substr($type, 0, 20);

    MCDelete($cacheKeyPrefix . $userId . '_' . $item);
    MCDelete($cacheKeyPrefix . $userId);

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

    return $tr;
}

function GetWatches($loginState)
{
    global $LANG_LEVEL;

    $userId = $loginState['id'];

    $cacheKey = SUBSCRIPTION_ITEM_CACHEKEY . $userId;
    $items = MCGet($cacheKey);
    if ($items === false) {
        $itemNames = LocaleColumns('i.name');
        $bonusTags = LocaleColumns('ifnull(group_concat(ib.`tag%1$s` order by ib.tagpriority separator \' \'), if(ifnull(bs.`set`,0)=0,\'\',concat(\'__LEVEL%1$s__ \', i.level+sum(ifnull(ib.level,0))))) bonustag%1$s', true);
        $bonusTags = strtr($bonusTags, $LANG_LEVEL);

        $sql = <<<EOF
select uw.seq, uw.region, uw.house,
    uw.item, uw.bonusset, ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
    $itemNames, $bonusTags, i.icon, i.class, 
    uw.direction, uw.quantity, uw.price
from tblUserWatch uw
join tblDBCItem i on uw.item = i.id
left join tblBonusSet bs on uw.bonusset = bs.`set`
left join tblDBCItemBonus ib on ifnull(bs.bonus, i.basebonus) = ib.id
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

    $cacheKey = SUBSCRIPTION_SPECIES_CACHEKEY . $userId;
    $battlePets = MCGet($cacheKey);
    if ($battlePets === false) {
        $petNames = LocaleColumns('p.name');

        $sql = <<<EOF
select uw.seq, uw.region, uw.house,
    uw.species, uw.breed,
    $petNames, p.icon, p.type, p.npc,
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

    $json = $items + $battlePets;

    return $json;
}


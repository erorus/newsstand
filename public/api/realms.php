<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/subscription.incl.php');

header('Cache-Control: no-cache');

if (isset($_COOKIE['__cfduid'])) { // cloudflare
    setcookie('__cfduid', '', strtotime('1 year ago'), '/', '.theunderminejournal.com', false, true);
}

$loginState = ['ads' => true];
$showAds = true;
if (isset($_POST['getuser'])) {
    $loginState = GetLoginState();
    $loginState['ads'] = (!isset($loginState['paiduntil'])) || ($loginState['paiduntil'] < time());
    if (isset($loginState['publicid'])) {
        $loginState['publicHMAC'] = GeneratePublicUserHMAC($loginState['publicid']);
    }
    unset($loginState['id'], $loginState['publicid'], $loginState['paiduntil']);
    $loginState['csrfCookie'] = SUBSCRIPTION_CSRF_COOKIE;
}

json_return([
    'version' => API_VERSION,
    'language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en-US,en;q=0.5',
    'banned' => BotCheck(true),
    'user' => $loginState,
    'realms' => [GetRealms('US'),GetRealms('EU')]
    ]);

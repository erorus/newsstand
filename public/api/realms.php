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
if (isset($_POST['getuser'])) {
    $loginState = RedactLoginState(GetLoginState());
}
$loginState['ads'] = false;

json_return([
    'version' => API_VERSION,
    'language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en-US,en;q=0.5',
    'banned' => BotCheck(true),
    'user' => $loginState,
    'realms' => [GetRealms('US'),GetRealms('EU')]
    ]);

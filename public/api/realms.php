<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

header('Expires: ' . Date(DATE_RFC1123, strtotime('+3 hours')));

if (isset($_COOKIE['__cfduid'])) { // cloudflare
    setcookie('__cfduid', '', strtotime('1 year ago'), '/', '.theunderminejournal.com', false, true);
}

json_return([
    'version' => API_VERSION,
    'realms' => [GetRealms('US'),GetRealms('EU')]
    ]);

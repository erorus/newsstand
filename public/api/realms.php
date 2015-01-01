<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

header('Expires: ' . Date(DATE_RFC1123, strtotime('+3 hours')));

if (isset($_COOKIE['__cfduid'])) { // cloudflare
    setcookie('__cfduid', '', strtotime('1 year ago'), '/', '.theunderminejournal.com', false, true);
}

$region = GetSiteRegion();

json_return(array('version' => API_VERSION, 'region' => $region, 'realms' => GetRealms($region)));

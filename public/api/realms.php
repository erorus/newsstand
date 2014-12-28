<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

header('Expires: ' . Date(DATE_RFC1123, strtotime('+3 hours')));

$region = GetSiteRegion();

json_return(array('region' => $region, 'realms' => GetRealms($region)));

<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if ($json = MCGet('realms_'.$region))
    json_return($json);

DBConnect();

$stmt = $db->prepare('select * from tblRealm where region = ?');
$stmt->bind_param('s', $region);
$stmt->execute();
$result = $stmt->get_result();
$houses = DBMapArray($result);
$stmt->close();

$houses = json_encode($houses, JSON_NUMERIC_CHECK);

$memcache->set('realms_'.$region, $houses, false, 10800);

json_return($houses);
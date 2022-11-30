<?php

require_once '../incl/incl.php';
require_once '../incl/NewsstandHTTP.incl.php';

use Newsstand\HTTP;

DBConnect();
$db->real_query('delete from tblDBCItemVendorCost');

$thejson = HTTP::Get('https://nether.wowhead.com/data/vendor-items');
$itms = json_decode($thejson, true);

// exclude all herbs and elemental (motes, etc) trade goods
$sql = 'select id from tblDBCItem where class=7 and subclass in (9,10)';
$stmt = $db->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    unset($itms[$row['id']]);
}
$result->close();
$stmt->close();

$ignoreNPCs = [111838, 123124]; // beta glyph vendor

$sql = 'replace into tblDBCItemVendorCost (item, copper, npc, npccount) values (%d, %d, %d, %d)';
foreach ($itms as $itemId => $itemInfo) {
    if ($itemInfo['npccount'] == 1 && in_array($itemInfo['npc'], $ignoreNPCs)) {
        continue;
    }
    $db->real_query(sprintf($sql, $itemId, $itemInfo['price'], $itemInfo['npc'], $itemInfo['npccount']));
}

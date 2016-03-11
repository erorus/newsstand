<?php

require_once('incl/old.incl.php');

do_connect();
run_sql('delete from tblDBCItemVendorCost');

$thejson = <<<'EOF'
{"38":{"price":1,"npc":18672,"npccount":1}}
EOF;
// TODO: fetch from http://www.wowhead.com/data=vendor-items

$itms = json_decode($thejson, true);

// exclude all herbs and elemental (motes, etc) trade goods
$sql = 'select id from tblDBCItem where class=7 and subclass in (9,10)';
$rst = get_rst($sql);
while ($row = next_row($rst)) {
    unset($itms[$row['id']]);
}

$sql = 'replace into tblDBCItemVendorCost (item, copper, npc, npccount) values (%d, %d, %d, %d)';
foreach ($itms as $itemId => $itemInfo) {
    run_sql(sprintf($sql, $itemId, $itemInfo['price'], $itemInfo['npc'], $itemInfo['npccount']));
}

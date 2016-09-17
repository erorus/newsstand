<?php

require_once(__DIR__.'/../../incl/incl.php');
require_once(__DIR__.'/../DBC/db2/src/autoload.php');
use \Erorus\DB2\Reader;

$defs = BuildItemDefs();
if ($defs) {
    file_put_contents(__DIR__ . '/addon/ItemDefs.lua', $defs);
}

function BuildItemDefs() {
    $itemDB2 = new Reader(__DIR__.'/../DBC/current/enUS/Item.db2');
    $ids = $itemDB2->getIds();
    unset($itemDB2);

    $idMap = [];
    foreach ($ids as $id) {
        $idMap[$id] = $id;
    }

    DebugMessage("Item ID count: ".count($idMap));

    $sparseDB2 = new Reader(__DIR__.'/../DBC/current/enUS/Item-sparse.db2');
    $ids = $sparseDB2->getIds();
    unset($sparseDB2);

    DebugMessage("Sparse ID count: ".count($ids));

    foreach ($ids as $id) {
        unset($idMap[$id]);
    }

    DebugMessage("Missing ID count: ".count($idMap));

    if (!count($idMap)) {
        return false;
    }

    $idMap = array_reverse($idMap);
    reset($idMap);
    $lastId = current($idMap);
    DebugMessage("Last item ID: $lastId");

    $lua = "local addonName, addonTable = ...\n";
    $lua .= "addonTable.missingItems = {" . implode(',', $idMap) . "}\n";

    return $lua;
}
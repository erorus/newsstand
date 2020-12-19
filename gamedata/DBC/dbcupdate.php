<?php
require_once __DIR__ . '/../../incl/incl.php';
require_once __DIR__ . '/dbc.incl.php';

use \Erorus\DB2\Reader;

error_reporting(E_ALL);
ini_set('memory_limit', '384M');

$locale = 'enus';
$dirnm = __DIR__.'/current/enUS';

LogLine("Starting!");

DBConnect();
RunAndLogError('set session max_heap_table_size='.(1024*1024*1024));

$fileDataReader = CreateDB2Reader('ManifestInterfaceData');

LogLine("GlobalStrings");
$globalStrings = [];
$reader = CreateDB2Reader('GlobalStrings');
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $rec) {
    EchoProgress(++$x/$recordCount);
    $globalStrings[$rec['key']] = $rec['value'];
}
EchoProgress(false);
unset($reader);

LogLine("tblDBCItemSubClass");
$sql = <<<EOF
insert into tblDBCItemSubClass (class, subclass, name_$locale) values (?, ?, ?)
on duplicate key update name_$locale = ifnull(values(name_$locale), name_$locale)
EOF;
$reader = CreateDB2Reader('ItemSubClass');
RunAndLogError('truncate tblDBCItemSubClass');
$stmt = $db->prepare($sql);
$classs = $subclass = $name = null;
$stmt->bind_param('iis', $classs, $subclass, $name);
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $rec) {
    EchoProgress(++$x/$recordCount);
    $classs = $rec['class'];
    $subclass = $rec['subclass'];
    $name = $rec['plural'];
    if (is_null($name) || $name == '') {
        $name = $rec['name'];
    }
    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($reader);

$battlePetSpeciesReader = CreateDB2Reader('BattlePetSpecies');
$creatureReader = CreateDB2Reader('Creature');

LogLine("tblDBCPet");
RunAndLogError('truncate tblDBCPet');
$stmt = $db->prepare('insert into tblDBCPet (id, name_enus, type, icon, npc, category, flags) values (?, ?, ?, ?, ?, ?, ?)');
$id = $name = $type = $icon = $npc = $category = $flags = null;
$stmt->bind_param('isisiii', $id, $name, $type, $icon, $npc, $category, $flags);
$x = 0; $recordCount = count($battlePetSpeciesReader->getIds());
foreach ($battlePetSpeciesReader->generateRecords() as $recId => $rec) {
    EchoProgress(++$x/$recordCount);
    $id = $recId;

    $creatureRec = $creatureReader->getRecord($rec['npcid']);
    $name = is_null($creatureRec) ? 'NPC ' . $rec['npcid'] : $creatureRec['name'];

    $type = $rec['type'];
    $icon = GetFileDataName($rec['iconid']);
    if (is_null($icon)) {
        $icon = 'inv_misc_questionmark';
    }
    $npc = $rec['npcid'];
    $category = $rec['category'];
    $flags = $rec['flags'];

    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($creatureReader);
unset($battlePetSpeciesReader);

$stateFields = [
    18 => 'power',
    19 => 'stamina',
    20 => 'speed',
];
$reader = CreateDB2Reader('BattlePetSpeciesState');
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $rec) {
    EchoProgress(++$x/$recordCount);
    if (isset($stateFields[$rec['state']])) {
        RunAndLogError(sprintf('update tblDBCPet set `%1$s`=%2$d where id=%3$d', $stateFields[$rec['state']], $rec['amount'], $rec['species']));
    }
}
EchoProgress(false);
unset($reader);

LogLine("tblDBCItemNameDescription");
RunAndLogError('truncate tblDBCItemNameDescription');
$stmt = $db->prepare('insert into tblDBCItemNameDescription (id, desc_enus) values (?, ?)');
$tblId = $name = null;
$stmt->bind_param('is', $tblId, $name);

$tblId = SOCKET_FAKE_ITEM_NAME_DESC_ID;
$name = '+ ' . $globalStrings['EMPTY_SOCKET_PRISMATIC'];
RunAndLogError($stmt->execute());

$reader = CreateDB2Reader('ItemNameDescription');
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $rec) {
    EchoProgress(++$x/$recordCount);

    $tblId = $id;
    $name = $rec['name'];

    RunAndLogError($stmt->execute());
}
EchoProgress(false);
unset($reader);

LogLine("tblDBCItemBonus");
$reader = CreateDB2Reader('ItemBonus');
$bonusRows = [];
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $rec) {
    EchoProgress(++$x/$recordCount);
    $bonusRows[] = $rec;
}
EchoProgress(false);
unset($reader);

$bonuses = [];
foreach ($bonusRows as $row) {
    if (!isset($bonuses[$row['bonusid']])) {
        $bonuses[$row['bonusid']] = [];
    }
    switch ($row['changetype']) {
        case 1: // itemlevel boost
            if (!isset($bonuses[$row['bonusid']]['itemlevel'])) {
                $bonuses[$row['bonusid']]['itemlevel'] = 0;
            }
            $bonuses[$row['bonusid']]['itemlevel'] += $row['params'][0];
            break;
        case 2: // stats
            if (!isset($bonuses[$row['bonusid']]['statmask'])) {
                $bonuses[$row['bonusid']]['statmask'] = 0;
            }
            switch ($row['params'][0]) {
                case 22:
                    $bonuses[$row['bonusid']]['statmask'] |= BONUS_STAT_SET_CORRUPTION;
                    break;
                case 61:
                    $bonuses[$row['bonusid']]['statmask'] |= BONUS_STAT_SET_SPEED;
                    break;
                case 62:
                    $bonuses[$row['bonusid']]['statmask'] |= BONUS_STAT_SET_LEECH;
                    break;
                case 63:
                    $bonuses[$row['bonusid']]['statmask'] |= BONUS_STAT_SET_AVOIDANCE;
                    break;
                case 64:
                    $bonuses[$row['bonusid']]['statmask'] |= BONUS_STAT_SET_INDESTRUCTIBLE;
                    break;
            }
            break;
        case 3: // quality
            $bonuses[$row['bonusid']]['quality'] = $row['params'][0];
            break;
        case 4: // tag
        case 5: // rand enchant name
            $dataName = ($row['changetype'] == 4) ? 'tag' : 'name';
            if (!isset($bonuses[$row['bonusid']][$dataName]) || $bonuses[$row['bonusid']][$dataName]['prio'] > $row['params'][1]) {
                $bonuses[$row['bonusid']][$dataName] = [
                    'id' => $row['params'][0],
                    'prio' => $row['params'][1],
                ];
            }
            break;
        case 6: // socket
            if (!isset($bonuses[$row['bonusid']]['socket'])) {
                $bonuses[$row['bonusid']]['socket'] = 0;
            }
            $bonuses[$row['bonusid']]['socket'] = $bonuses[$row['bonusid']]['socket'] | pow(2, $row['params'][1] - 1);
            break;
        case 13: // itemlevel scaling distribution
            list($oldDist, $priority, $contentTuningId, $curveId) = $row['params'];
            if (isset($bonuses[$row['bonusid']]['levelcurve'])) {
                LogLine("Warning: already have curve " . $bonuses[$row['bonusid']]['levelcurve'] . ' for ' . $row['bonusid'] . ', overriding with ' . $curveId);
            }
            $bonuses[$row['bonusid']]['levelcurve'] = $curveId;
            break;
        case 14: // preview itemlevel
            if (!isset($bonuses[$row['bonusid']]['previewlevel'])) {
                $bonuses[$row['bonusid']]['previewlevel'] = 0;
            }
            $bonuses[$row['bonusid']]['previewlevel'] = max($bonuses[$row['bonusid']]['previewlevel'], $row['params'][0]);
            break;
        case 18: // required player level
            if (!isset($bonuses[$row['bonusid']]['requiredlevel'])) {
                $bonuses[$row['bonusid']]['requiredlevel'] = 0;
            }
            $bonuses[$row['bonusid']]['requiredlevel'] = max($bonuses[$row['bonusid']]['requiredlevel'], $row['params'][0]);
            break;
    }
}

RunAndLogError('truncate table tblDBCItemBonus');
$sql = <<<'SQL'
insert into tblDBCItemBonus (
    id, quality, level, previewlevel, levelcurve, requiredlevel,
    tagid, tagpriority, nameid, namepriority,
    socketmask, statmask
) values (
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?
)
SQL;
$stmt = $db->prepare($sql);
$id = $quality = $level = $previewLevel = $levelCurve = $requiredLevel = $tagPriority = $tagId = $nameId = $namePriority = $socketMask = $statMask = null;
$stmt->bind_param('iiiiiiiiiiii',
    $id, $quality, $level, $previewLevel, $levelCurve, $requiredLevel,
    $tagId, $tagPriority, $nameId, $namePriority,
    $socketMask, $statMask
);
foreach ($bonuses as $bonusId => $bonusData) {
    $id = $bonusId;
    $quality = isset($bonusData['quality']) ? $bonusData['quality'] : null;
    $level = isset($bonusData['itemlevel']) ? $bonusData['itemlevel'] : null;
    $previewLevel = isset($bonusData['previewlevel']) ? $bonusData['previewlevel'] : null;
    $levelCurve = isset($bonusData['levelcurve']) ? $bonusData['levelcurve'] : null;
    $requiredLevel = isset($bonusData['requiredlevel']) ? $bonusData['requiredlevel'] : null;
    $tagId = isset($bonusData['tag']) ? $bonusData['tag']['id'] : null;
    $tagPriority = isset($bonusData['tag']) ? $bonusData['tag']['prio'] : null;
    $nameId = isset($bonusData['name']) ? $bonusData['name']['id'] : null;
    $namePriority = isset($bonusData['name']) ? $bonusData['name']['prio'] : null;
    $socketMask = (isset($bonusData['socket']) && ($bonusData['socket'] != 0)) ? $bonusData['socket'] : null;
    if (isset($socketMask) && ($socketMask & 0x7F) && !isset($tagId)) {
        $tagId = SOCKET_FAKE_ITEM_NAME_DESC_ID;
        $tagPriority = 250;
    }
    $statMask = isset($bonusData['statmask']) ? $bonusData['statmask'] : 0;
    RunAndLogError($stmt->execute());
}
$stmt->close();
unset($bonuses, $bonusRows);

LogLine("tblDBCCurvePoint");

$reader = CreateDB2Reader('CurvePoint');

RunAndLogError('truncate table tblDBCCurvePoint');
$stmt = $db->prepare("replace into tblDBCCurvePoint (curve, step, `key`, `value`) values (?, ?, ?, ?)");
$curve = $step = $key = $value = null;
$stmt->bind_param('iidd', $curve, $step, $key, $value);

$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $rec) {
    EchoProgress(++$x/$recordCount);
    $curve = $rec['curve'];
    $step = $rec['step'];
    $key = $rec['pair'][0];
    $value = $rec['pair'][1];
    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($reader);

LogLine("tblDBCItem");
$itemReader = CreateDB2Reader('Item');
$itemSparseReader = CreateDB2Reader('ItemSparse');
$dbCacheReader = false;
if (file_exists($dirnm . '/DBCache.bin')) {
    try {
        $dbCacheReader = $itemSparseReader->loadDBCache($dirnm . '/DBCache.bin');
    } catch (Exception $e) {
        LogLine("Warning: could not open $dirnm/DBCache.bin: " .$e->getMessage());
        $dbCacheReader = false;
    }
}

// keyed by flags[1] & 0x3
$requiredSideMap = [
    0x0 => 3, // any
    0x1 => 2, // horde
    0x2 => 1, // alliance
    0x3 => 3, // any (but shouldn't have any item with both flags set)
];

//RunAndLogError('truncate table tblDBCItem');
$sql = <<<'EOF'
replace into tblDBCItem (
    id, name_enus, quality, level, class, subclass, icon,
    stacksize, binds, buyfromvendor, selltovendor, auctionable,
    type, requiredlevel, requiredskill, flags, othersideitem, requiredside) VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOF;
$stmt = $db->prepare($sql);
$id = $name = $quality = $level = $classId = $subclass = $icon = null;
$stackSize = $binds = $buyFromVendor = $sellToVendor = $auctionable = null;
$type = $requiredLevel = $requiredSkill = $flags = $otherSideItem = $requiredSide = null;
$stmt->bind_param('isiiiisiiiiiiiiiii',
    $id, $name, $quality, $level, $classId, $subclass, $icon,
    $stackSize, $binds, $buyFromVendor, $sellToVendor, $auctionable,
    $type, $requiredLevel, $requiredSkill, $flags, $otherSideItem, $requiredSide
    );
$x = 0; $recordCount = count($itemReader->getIds());
foreach ($itemReader->generateRecords() as $recId => $rec) {
    EchoProgress(++$x / $recordCount);
    $sniffed = false;
    $sparseRec = $itemSparseReader->getRecord($recId);
    $cacheRec = $dbCacheReader ? $dbCacheReader->getRecord($recId) : null;
    if (!is_null($cacheRec)) {
        $sniffed = is_null($sparseRec);
        $sparseRec = $cacheRec;
    }
    if (is_null($sparseRec)) {
        continue;
    }

    $id = $recId;
    $name = $sparseRec['name'];
    $quality = $sparseRec['quality'];
    $level = $sparseRec['level'];
    $classId = $rec['class'];
    $subclass = $rec['subclass'];
    $icon = GetFileDataName($rec['iconfiledata']) ?: '';
    $stackSize = $sparseRec['stacksize'];
    $binds = $sparseRec['binds'];
    $buyFromVendor = $sparseRec['buyprice'];
    $sellToVendor = $sparseRec['sellprice'];
    $auctionable = in_array($sparseRec['binds'], [0,2,3]) ? 1 : 0;
    $type = $sparseRec['type'];
    $requiredLevel = $sparseRec['requiredlevel'];
    $requiredSkill = $sparseRec['requiredskill'];
    $otherSideItem = $sparseRec['oppositesideitem'];
    $requiredSide = $requiredSideMap[$sparseRec['flags'][1] & 0x3];

    $noTransmogFlag = ($sparseRec['flags'][1] & 0x400000) ? 2 : 0;
    $sniffedFlag = $sniffed ? 4 : 0;

    $flags = $noTransmogFlag | $sniffedFlag;

    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($itemReader);
unset($dbCacheReader);
unset($itemSparseReader);

$appearanceReader = CreateDB2Reader('ItemAppearance');
$modifiedAppearanceReader = CreateDB2Reader('ItemModifiedAppearance');

$sorted = [];
$x = 0; $recordCount = count($modifiedAppearanceReader->getIds());
foreach ($modifiedAppearanceReader->generateRecords() as $recId => $rec) {
    EchoProgress(++$x / $recordCount);
    $sorted[] = $rec;
}
EchoProgress(false);

// we want the first index to appear last, so it's the update that doesn't get overwritten
usort($sorted, function($a, $b){
    $s = ($b['bonustype'] == 0 ? 0 : 1) - ($a['bonustype'] == 0 ? 0 : 1);
    if ($s != 0) {
        return $s;
    }
    return $b['index'] - $a['index'];
});

$stmt = $db->prepare('update tblDBCItem set icon = ? where id = ? and icon = \'\'');
$id = $icon = null;
$stmt->bind_param('si', $icon, $id);
$x = 0;
foreach ($sorted as $rec) {
    EchoProgress(++$x / $recordCount);
    $appearance = $appearanceReader->getRecord($rec['appearance']);
    if (!is_null($appearance)) {
        $icon = GetFileDataName($appearance['iconfiledata']);
    }
    if (is_null($icon)) {
        continue;
    }
    $id = $rec['item'];
    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);

$stmt = $db->prepare('update tblDBCItem set display = ? where id = ? and display is null');
$id = $display = null;
$stmt->bind_param('ii', $display, $id);
$x = 0;
foreach ($sorted as $rec) {
    EchoProgress(++$x / $recordCount);
    $appearance = $appearanceReader->getRecord($rec['appearance']);
    if (is_null($appearance)) {
        continue;
    }

    $display = $appearance['display'];
    $id = $rec['item'];
    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($sorted, $appearanceReader, $modifiedAppearanceReader);

LogLine("tblDBCItemSpell");
$reader = CreateDB2Reader('ItemEffect');
RunAndLogError('truncate table tblDBCItemSpell');
$sql = 'insert ignore into tblDBCItemSpell (item, spell) values (?, ?)';
$stmt = $db->prepare($sql);
$item = $spell = null;
$stmt->bind_param('ii', $item, $spell);
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $rec) {
    EchoProgress(++$x / $recordCount);
    $item = $rec['item'];
    $spell = $rec['spell'];
    if ($item <= 0 || $spell <= 0) {
        continue;
    }
    RunAndLogError($stmt->execute());
}
EchoProgress(false);
$stmt->close();
unset($reader);

/*
LogLine("tblDBCRandEnchants");
$reader = CreateDB2Reader('ItemRandomProperties');
$stmt = $db->prepare("insert into tblDBCRandEnchants (id, name_$locale) values (?, ?) on duplicate key update name_$locale = values(name_$locale)");
$enchId = $name = null;
$stmt->bind_param('is', $enchId, $name);
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $rec) {
    EchoProgress(++$x/$recordCount);
    $enchId = $id;
    $name = $rec['name'];
    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($reader);

RunAndLogError('truncate table tblDBCItemRandomSuffix');
$stmt = $db->prepare("insert ignore into tblDBCItemRandomSuffix (locale, suffix) (select distinct '$locale', name_$locale from tblDBCRandEnchants where trim(name_$locale) != '' and id < 0)");
RunAndLogError($stmt->execute());
$stmt->close();
*/

LogLine("Making spell temp tables..");

DB2TempTable('SpellEffect'); //effect type id 24 = create item, 53 = enchant, 157 = create tradeskill item
DB2TempTable('Spell');
DB2TempTable('SpellCooldowns');
DB2TempTable('SpellCategories');
DB2TempTable('SpellCategory');

RunAndLogError('create temporary table ttblSpellCategory2 select * from ttblSpellCategory');

DB2TempTable('SpellMisc');
DB2TempTable('SpellName');
DB2TempTable('SpellReagents');
DB2TempTable('SkillLine');
DB2TempTable('SkillLineAbility');
DB2TempTable('TradeSkillCategory');

RunAndLogError('truncate table tblDBCTradeSkillCategory');

$sql = <<<'SQL'
INSERT INTO tblDBCTradeSkillCategory
    (id, name, parent, skillline, `order`)
(SELECT id, name, parentid, skillline, `order` FROM ttblTradeSkillCategory)
SQL;
RunAndLogError($sql);

$sql = <<<'SQL'
CREATE temporary TABLE ttblDBCSkillLines (
    id smallint unsigned NOT NULL,
    name char(50) NOT NULL,
    mainid smallint unsigned NOT NULL,
    PRIMARY KEY (id)
) ENGINE=memory
SQL;
RunAndLogError($sql);

$sql = <<<'SQL'
insert into ttblDBCSkillLines (
    select id, linename, if(linecatid=9, 185, if(mainlineid=0, id, mainlineid))
    from ttblSkillLine
    where
    (
        (linecatid=11) or
        (linecatid=9 and
            (linename='Cooking' or linename like 'Way of %')
        )
    )
)
SQL;
RunAndLogError($sql);

LogLine('Getting trades..');
RunAndLogError('truncate tblDBCSpell');
$sql = <<<EOF
insert ignore into tblDBCSpell (id,name,description,cooldown,qtymade,skillline,tradeskillcategory,replacesspell,requiredside)
(select sn.id, sn.spellname, ifnull(s.longdescription, ''),
    greatest(
        ifnull(cd.categorycooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cd.individualcooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cc.chargecooldown,0)) / 1000,
    if(se.itemcreated=0,0,if(se.diesides=0,if(se.qtymadeFloat=0,1,se.qtymadeFloat),(se.qtymadeFloat * 2 + se.diesides + 1)/2)),
    min(sl.mainid),
    sla.tradeskillcategory & 0xFFFF,
    nullif(sla.replacesspell, 0),
    case
        when sla.racemask64 is null then 3
        when (sla.racemask64 & 0x7FF) ^ 0x44D = 0 then 1
        when (sla.racemask64 & 0x7FF) ^ 0x3B2 = 0 then 2
        else 3
    end
from ttblSpellName sn
left join ttblSpell s on s.id = sn.id
left join ttblSpellMisc sm on sn.id=sm.spellid
left join ttblSpellCooldowns cd on cd.spellid = sn.id
left join ttblSpellCategories cs on cs.spellid = sn.id
left join ttblSpellCategory c on c.id = cs.categoryid
left join ttblSpellCategory2 cc on cc.id = cs.chargecategoryid
join ttblSpellEffect se on sn.id=se.spellid
join ttblSkillLineAbility sla on sn.id=sla.spellid
join ttblDBCSkillLines sl on sl.id=sla.lineid
where se.effecttypeid in (24,53,157)
group by sn.id)
EOF;
RunAndLogError($sql);

RunAndLogError('update tblDBCSpell set tradeskillcategory=1449 where id=314960 and tradeskillcategory=0');

RunAndLogError('truncate tblDBCSpellCrafts');
$sql = <<<EOF
insert ignore into tblDBCSpellCrafts (spell, item)
(
    select s.id, se.itemcreated
    from tblDBCSpell s
    join ttblSpellEffect se on s.id = se.spellid
    where se.effecttypeid in (24,53,157)
    and se.itemcreated > 0
)
EOF;
RunAndLogError($sql);

// Insert for non-side-specific spells that create side-specific items
$sql = <<<EOF
insert into tblDBCSpellCrafts (
    select sc.spell, i.othersideitem
    from tblDBCSpellCrafts sc
    join tblDBCItem i on sc.item = i.id
    left join tblDBCSpellCrafts sc2 on i.othersideitem = sc2.item
    where sc2.item is null
    and nullif(i.othersideitem, 0) is not null
)
EOF;
RunAndLogError($sql);

$sql = <<<EOF
insert ignore into tblDBCSpell (id,name,description) (
    select distinct sn.id, sn.spellname, ifnull(s.longdescription,'')
    from ttblSpellName sn
    left join ttblSpell s on s.id = sn.id
    left join ttblSpellMisc sm on sn.id=sm.spellid
    join tblDBCItemSpell dis on dis.spell=sn.id
)
EOF;
RunAndLogError($sql);

RunAndLogError('truncate tblDBCSpellReagents');
$sql = <<<'EOF'
insert into tblDBCSpellReagents (spell, item, qty)
(select spell, reagent%1$d, reagentcount%1$d from ttblSpellReagents
where reagent%1$d != 0 and reagentcount%1$d != 0)
on duplicate key update qty = qty + values(qty)
EOF;
for ($x = 1; $x <= 8; $x++) {
    RunAndLogError(sprintf($sql, $x));
}

LogLine('Getting spell expansion IDs..');
$sql = <<<'SQL'
select tsc.id,
if(tsc.parent=0, tsc.`order`,
if(tsc2.parent=0, tsc2.`order`,
if(tsc3.parent=0, tsc3.`order`,
if(tsc4.parent=0, tsc4.`order`,
if(tsc5.parent=0, tsc5.`order`, null))))) mainorder
from tblDBCTradeSkillCategory tsc
left join tblDBCTradeSkillCategory tsc2 on tsc.parent = tsc2.id
left join tblDBCTradeSkillCategory tsc3 on tsc2.parent = tsc3.id
left join tblDBCTradeSkillCategory tsc4 on tsc3.parent = tsc4.id
left join tblDBCTradeSkillCategory tsc5 on tsc4.parent = tsc5.id
SQL;

$mainOrders = [];
$stmt = $db->prepare($sql);
RunAndLogError($stmt->execute());
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $mainOrders[$row['mainorder']][] = $row['id'];
}

$orderToExpansion = [
    930 => 7, // bfa
    940 => 6, // legion
    950 => 5, // wod
    960 => 4, // mop
    970 => 3, // cata
    980 => 2, // wotlk
    990 => 1, // bc
    999 => 0, // classic
    1000 => 0, // classic (old)
];

$sql = 'update tblDBCSpell set expansion = %d where tradeskillcategory in (%s)';
foreach ($orderToExpansion as $order => $exp) {
    if (!isset($mainOrders[$order])) {
        continue;
    }
    RunAndLogError(sprintf($sql, $exp, implode(',', $mainOrders[$order])));
}


/* */
LogLine("Done.\n ");

function RunAndLogError($sql) {
    global $db;
    $ok = is_bool($sql) ? $sql : $db->real_query($sql);
    if (!$ok) {
        LogLine("Error: ".$db->error."\n".$sql);
        exit(1);
    }
}

function GetFileDataName($id) {
    global $fileDataReader;
    $row = $fileDataReader->getRecord($id);
    if (is_null($row)) {
        return null;
    }
    return preg_replace('/\.blp$/', '', strtolower($row['name']));
}

function DB2TempTable($baseFile) {
    global $db, $dbLayout;

    LogLine("ttbl$baseFile");
    $reader = CreateDB2Reader($baseFile);
    $columns = $dbLayout[$baseFile]['names'];

    $fieldTypes = $reader->getFieldTypes();
    $fieldCounts = [];
    foreach ($reader->generateRecords() as $id => $rec) {
        foreach ($columns as $colName) {
            $fieldCounts[$colName] = is_array($rec[$colName]) ? count($rec[$colName]) : 1;
        }
        break;
    }

    $maxLengths = [];
    foreach ($columns as $colName) {
        if ($fieldTypes[$colName] == Reader::FIELD_TYPE_STRING) {
            $maxLengths[$colName] = 1;
        }
    }

    if (count($maxLengths)) {
        $x = 0; $recordCount = count($reader->getIds());
        foreach ($reader->generateRecords() as $id => $rec) {
            EchoProgress(++$x / $recordCount);
            foreach ($maxLengths as $colName => &$maxLength) {
                if (is_array($rec[$colName])) {
                    foreach ($rec[$colName] as $recVal) {
                        $maxLength = max($maxLength, strlen($recVal));
                    }
                } else {
                    $maxLength = max($maxLength, strlen($rec[$colName]));
                }
            }
            unset($maxLength);
        }
        EchoProgress(false);
    }

    $sql = 'create temporary table `ttbl'.$baseFile.'` (`id` int,';
    $y = 0;
    $tableCols = [];
    $indexFields = ['id'];
    $paramTypes = 'i';
    foreach ($columns as $colName) {
        if ($y++ > 0) {
            $sql .= ',';
        }
        for ($x = 1; $x <= $fieldCounts[$colName]; $x++) {
            $tableColName = $colName . ($fieldCounts[$colName] > 1 ? $x : '');
            $sql .= ($x > 1 ? ',' : '') . "`$tableColName` ";
            $tableCols[] = $tableColName;
            if (strtolower(substr($colName,-2)) == 'id') {
                $indexFields[] = $tableColName;
            }
            switch ($fieldTypes[$colName]) {
                case Reader::FIELD_TYPE_INT:
                    if (substr($colName, -5) == 'Float') {
                        $sql .= 'float';
                        $paramTypes .= 'd';
                    } else {
                        $sql .= (substr($colName, -2) == '64') ? 'bigint' : 'int';
                        $paramTypes .= 'i';
                    }
                    break;
                case Reader::FIELD_TYPE_FLOAT:
                    $sql .= 'float';
                    $paramTypes .= 'd';
                    break;
                case Reader::FIELD_TYPE_STRING:
                    $sql .= 'varchar(' . $maxLengths[$colName] . ')';
                    $paramTypes .= 's';
                    break;
                default:
                    $sql .= 'int';
                    $paramTypes .= 'i';
                    break;
            }
        }
    }
    foreach ($indexFields as $idx) {
        $sql .= ", index using hash (`$idx`)";
    }
    $sql .= ') engine=memory;';

    RunAndLogError($sql);

    $sql = sprintf('insert into `%s` (`id`,`%s`) values (?,%s)', "ttbl$baseFile", implode('`,`', $tableCols), substr(str_repeat('?,', count($tableCols)), 0, -1));
    $stmt = $db->prepare($sql);
    $row = [];
    $idCol = 0;
    $params = [$paramTypes, &$idCol];
    foreach ($columns as $colName) {
        for ($x = 1; $x <= $fieldCounts[$colName]; $x++) {
            $tableColName = $colName . ($fieldCounts[$colName] > 1 ? $x : '');
            $row[$tableColName] = null;
            $params[] = &$row[$tableColName];
        }
    }
    call_user_func_array([$stmt, 'bind_param'], $params);

    $x = 0; $recordCount = count($reader->getIds());
    foreach ($reader->generateRecords() as $id => $rec) {
        EchoProgress(++$x/$recordCount);
        $idCol = $id;
        foreach ($columns as $colName) {
            $forceFloat = substr($colName, -5) == 'Float';
            if ($fieldCounts[$colName] > 1) {
                for ($z = 1; $z <= $fieldCounts[$colName]; $z++) {
                    if ($forceFloat) {
                        $row[$colName . $z] = current(unpack('f', pack('V', $rec[$colName][$z - 1])));
                    } else {
                        $row[$colName . $z] = $rec[$colName][$z - 1];
                    }
                }
            } else {
                if ($forceFloat) {
                    $row[$colName] = current(unpack('f', pack('V', $rec[$colName])));
                } else {
                    $row[$colName] = $rec[$colName];
                }
            }
        }
        RunAndLogError($stmt->execute());
    }
    EchoProgress(false);
    unset($reader);
}

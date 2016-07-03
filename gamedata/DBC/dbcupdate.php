<?php
require_once('../incl/old.incl.php');
require_once('dbcdecode.php');

require_once '../../incl/incl.php';
require_once 'db2/src/autoload.php';

use \Erorus\DB2\Reader;

error_reporting(E_ALL);

$locale = 'enus';
$dirnm = 'current/enUS';

DBConnect();

$fileDataReader = new Reader($dirnm . '/FileData.dbc');
$fileDataReader->setFieldNames(['id', 'name']);

LogLine("tblDBCItemSubClass");
$sql = <<<EOF
insert into tblDBCItemSubClass (class, subclass, name_$locale) values (?, ?, ?)
on duplicate key update name_$locale = ifnull(values(name_$locale), name_$locale)
EOF;
$reader = new Reader($dirnm . '/ItemSubClass.dbc');
$reader->setFieldNames([1=>'class', 2=>'subclass', 11=>'name', 12=>'plural']);
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

$battlePetSpeciesReader = new Reader($dirnm . '/BattlePetSpecies.db2');
$battlePetSpeciesReader->setFieldNames([0=>'id', 1=>'npcid', 2=>'iconid', 4=>'type', 5=>'category', 6=>'flags']);

$creatureReader = new Reader($dirnm . '/Creature.db2');
$creatureReader->setFieldNames([0=>'id', 14=>'name']);

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
    $name = is_null($creatureRec) ? null : $creatureRec['name'];
    
    $type = $rec['type'];
    $icon = GetFileDataName($rec['iconid']);
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
$reader = new Reader($dirnm . '/BattlePetSpeciesState.db2');
$reader->setFieldNames([0=>'id', 1=>'species', 2=>'state', 3=>'amount']);
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $rec) {
    EchoProgress(++$x/$recordCount);
    if (isset($stateFields[$rec['state']])) {
        RunAndLogError(sprintf('update tblDBCPet set `%1$s`=%2$d where id=%3$d', $stateFields[$rec['state']], $rec['amount'], $rec['species']));
    }
}
EchoProgress(false);
unset($reader);

LogLine("tblDBCItemBonus");

$reader = new Reader($dirnm . '/ItemNameDescription.dbc');
$reader->setFieldNames([1 =>'name']);
$bonusNames = [];
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $row) {
    EchoProgress(++$x/$recordCount);
    $bonusNames[$id] = $row['name'];
}
EchoProgress(false);
unset($reader);

$reader = new Reader($dirnm . '/ItemBonus.db2');
$reader->setFieldNames([1=>'bonusid', 2=>'changetype', 3=>'param1', 4=>'param2', 5=>'prio']);
$bonusRows = [];
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $row) {
    EchoProgress(++$x/$recordCount);
    $bonusRows[] = $row;
}
EchoProgress(false);
unset($reader);

$bonuses = [];
foreach ($bonusRows as $row) {
    if (!isset($bonuses[$row['bonusid']])) {
        $bonuses[$row['bonusid']] = [];
    }
    switch ($row['changetype']) {
        case 1: // itemlevel
            if (!isset($bonuses[$row['bonusid']]['itemlevel'])) {
                $bonuses[$row['bonusid']]['itemlevel'] = 0;
            }
            $bonuses[$row['bonusid']]['itemlevel'] += $row['param1'];
            break;
        case 3: // quality
            $bonuses[$row['bonusid']]['quality'] = $row['param1'];
            break;
        case 4: // nametag
            if (!isset($bonuses[$row['bonusid']]['nametag'])) {
                $bonuses[$row['bonusid']]['nametag'] = ['name' => '', 'prio' => -1];
            }
            if ($bonuses[$row['bonusid']]['nametag']['prio'] < $row['param2']) {
                $bonuses[$row['bonusid']]['nametag'] = ['name' => isset($bonusNames[$row['param1']]) ? $bonusNames[$row['param1']] : $row['param1'], 'prio' => $row['param2']];
            }
            break;
        case 5: // rand enchant name
            if (!isset($bonuses[$row['bonusid']]['randname'])) {
                $bonuses[$row['bonusid']]['randname'] = ['name' => '', 'prio' => -1];
            }
            if ($bonuses[$row['bonusid']]['randname']['prio'] < $row['param2']) {
                $bonuses[$row['bonusid']]['randname'] = ['name' => isset($bonusNames[$row['param1']]) ? $bonusNames[$row['param1']] : $row['param1'], 'prio' => $row['param2']];
            }
            break;
    }
}

RunAndLogError('truncate table tblDBCItemBonus');
$stmt = $db->prepare("insert into tblDBCItemBonus (id, quality, level, tag_$locale, tagpriority, name_$locale, namepriority) values (?, ?, ?, ?, ?, ?, ?)");
$id = $quality = $level = $tag = $tagPriority = $name = $namePriority = null;
$stmt->bind_param('iiisisi', $id, $quality, $level, $tag, $tagPriority, $name, $namePriority);
foreach ($bonuses as $bonusId => $bonusData) {
    $id = $bonusId;
    $quality = isset($bonusData['quality']) ? $bonusData['quality'] : null;
    $level = isset($bonusData['itemlevel']) ? $bonusData['itemlevel'] : null;
    $tag = isset($bonusData['nametag']) ? $bonusData['nametag']['name'] : null;
    $tagPriority = isset($bonusData['nametag']) ? $bonusData['nametag']['prio'] : null;
    $name = isset($bonusData['randname']) ? $bonusData['randname']['name'] : null;
    $namePriority = isset($bonusData['randname']) ? $bonusData['randname']['prio'] : null;
    $stmt->execute();
}
$stmt->close();
unset($bonuses, $bonusRows, $bonusNames);
RunAndLogError('update tblDBCItemBonus set flags = flags | 1 where ifnull(level,0) != 0');

LogLine("tblDBCItem");
$itemReader = new Reader($dirnm . '/Item.db2');
$itemReader->setFieldNames([0=>'id', 1=>'class', 2=>'subclass', 7=>'iconfiledata']);
$itemSparseReader = new Reader($dirnm . '/Item-sparse.db2');
$itemSparseReader->setFieldNames([
    0=>'id',
    1=>'quality',
    3=>'flags2',
    7=>'buycount',
    8=>'buyprice',
    9=>'sellprice',
    10=>'type',
    13=>'level',
    14=>'requiredlevel',
    15=>'requiredskill',
    23=>'stacksize',
    25=>'stat1',
    26=>'stat2',
    27=>'stat3',
    28=>'stat4',
    29=>'stat5',
    30=>'stat6',
    31=>'stat7',
    32=>'stat8',
    35=>'stat9',
    34=>'stat10',
    69=>'binds',
    70=>'name'
]);

$pvpStatIds = [35,57];

RunAndLogError('truncate table tblDBCItem');
$sql = <<<'EOF'
insert into tblDBCItem (
    id, name_enus, quality, level, class, subclass, icon, 
    stacksize, binds, buyfromvendor, selltovendor, auctionable, 
    type, requiredlevel, requiredskill, flags) VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOF;
$stmt = $db->prepare($sql);
$id = $name = $quality = $level = $classId = $subclass = $icon = null;
$stackSize = $binds = $buyFromVendor = $sellToVendor = $auctionable = null;
$type = $requiredLevel = $requiredSkill = $flags = null;
$stmt->bind_param('isiiiisiiiiiiiii',
    $id, $name, $quality, $level, $classId, $subclass, $icon,
    $stackSize, $binds, $buyFromVendor, $sellToVendor, $auctionable,
    $type, $requiredLevel, $requiredSkill, $flags
    );
$x = 0; $recordCount = count($itemReader->getIds());
foreach ($itemReader->generateRecords() as $recId => $rec) {
    EchoProgress(++$x / $recordCount);
    $sparseRec = $itemSparseReader->getRecord($recId);
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

    $pvpFlag = 0;
    for ($i = 1; $i <= 10; $i++) {
        if (in_array($sparseRec["stat$i"], $pvpStatIds)) {
            $pvpFlag = 1;
            break;
        }
    }

    $noTransmogFlag = ($sparseRec['flags2'] & 0x400000) ? 2 : 0;

    $flags = $pvpFlag | $noTransmogFlag;

    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($itemReader);
unset($itemSparseReader);

$appearanceReader = new Reader($dirnm . '/ItemAppearance.db2');
$appearanceReader->setFieldNames([0=>'id', 1=>'display', 2=>'iconfiledata']);
$modifiedAppearanceReader = new Reader($dirnm . '/ItemModifiedAppearance.db2');
$modifiedAppearanceReader->setFieldNames([1=>'item', 2=>'bonustype', 3=>'appearance', 4=>'iconoverride', 5=>'index']);

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
    $icon = GetFileDataName($rec['iconoverride']);
    if (is_null($icon)) {
        $appearance = $appearanceReader->getRecord($rec['appearance']);
        if (!is_null($appearance)) {
            $icon = GetFileDataName($appearance['iconfiledata']);
        }
    }
    if (is_null($icon)) {
        continue;
    }
    $id = $rec['item'];
    $stmt->execute();
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
    $stmt->execute();
}
$stmt->close();
EchoProgress(false);
unset($sorted, $appearanceReader, $modifiedAppearanceReader);

// this bonus tree node stuff probably isn't quite right
$bonusTreeNodeReader = new Reader($dirnm . '/ItemBonusTreeNode.db2');
$bonusTreeNodeReader->setFieldNames([1=>'node', 4=>'bonus']);
$nodeLookup = [];
$x = 0; $recordCount = count($bonusTreeNodeReader->getIds());
foreach ($bonusTreeNodeReader->generateRecords() as $rec) {
    EchoProgress(++$x / $recordCount);
    if (!$rec['bonus']) {
        continue;
    }
    $nodeLookup[$rec['node']][] = $rec['bonus'];
}
unset($bonusTreeNodeReader);

$itemXBonusTreeReader = new Reader($dirnm . '/ItemXBonusTree.db2');
$itemXBonusTreeReader->setFieldNames([1=>'item', 2=>'node']);

$sql = <<<'EOF'
update tblDBCItem 
set basebonus = (
    select ib.id
    from tblDBCItemBonus ib
    where ib.level is null
    and ib.tag_enus is not null
    and ib.id in (%s)
    order by ib.tagpriority desc
    limit 1)
where id = %d    
EOF;

$x = 0; $recordCount = count($itemXBonusTreeReader->getIds());
foreach ($itemXBonusTreeReader->generateRecords() as $recId => $rec) {
    EchoProgress(++$x / $recordCount);

    if (!isset($nodeLookup[$rec['node']])) {
        continue;
    }
    RunAndLogError(sprintf($sql, implode(',', $nodeLookup[$rec['node']]), $rec['item']));
}

/* */

// TODO: rest of this script

LogLine(dbcdecode('ItemEffect', array(2=>'itemid', 4=>'spellid')));
RunAndLogError('truncate table tblDBCItemSpell');
RunAndLogError('insert ignore into tblDBCItemSpell (select * from ttblItemEffect where itemid > 0 and spellid > 0)');

LogLine(dbcdecode('ItemRandomSuffix', array(1=>'suffixid', 2=>'name1', 3=>'name2')));
LogLine(dbcdecode('ItemRandomProperties', array(1=>'suffixid', 2=>'name1', 8=>'name2')));
RunAndLogError('truncate table tblDBCRandEnchants');
RunAndLogError('insert into tblDBCRandEnchants (id, name_enus) (select suffixid, ifnull(name1, name2) from ttblItemRandomProperties)');
RunAndLogError('insert into tblDBCRandEnchants (id, name_enus) (select suffixid * -1, ifnull(name1, name2) from ttblItemRandomSuffix)');
RunAndLogError('truncate table tblDBCItemRandomSuffix');
RunAndLogError('insert into tblDBCItemRandomSuffix (locale, suffix) (select distinct \'enus\', name from tblDBCRandEnchants where trim(name) like \'of %\')');

LogLine(dbcdecode('SpellIcon', array(1=>'iconid',2=>'iconpath')));
RunAndLogError('update ttblSpellIcon set iconpath = substring_index(iconpath,\'\\\\\',-1) where instr(iconpath,\'\\\\\') > 0');

LogLine(dbcdecode('SpellEffect', array(
	1=>'effectid',
	3=>'effecttypeid', //24 = create item, 53 = enchant, 157 = create tradeskill item
	7=>'qtymade',
	11=>'diesides',
	12=>'itemcreated',
	28=>'spellid',
	29=>'effectorder'
	)));

LogLine(dbcdecode('Spell', array(
	1=>'spellid',
	2=>'spellname',
	4=>'longdescription',
	24=>'miscid',
	19=>'reagentsid',
    15=>'cooldownsid',
    13=>'categoriesid',
	)));

LogLine(dbcdecode('SpellCooldowns', array(
            1=>'id',
            4=>'categorycooldown',
            5=>'individualcooldown',
        )));

LogLine(dbcdecode('SpellCategories', array(
            1=>'id',
            4=>'categoryid',
            10=>'chargecategoryid',
        )));

LogLine(dbcdecode('SpellCategory', array(
            1=>'id',
            2=>'flags',
            6=>'chargecooldown',
        )));

RunAndLogError('create temporary table ttblSpellCategory2 select * from ttblSpellCategory');
$tables[] = 'SpellCategory2';

LogLine(dbcdecode('SpellMisc', array(
	1=>'miscid',
	2=>'spellid',
	22=>'iconid'
	)));


LogLine(dbcdecode('SpellReagents', array(
	1=>'reagentsid',
	2=>'reagent1',
	3=>'reagent2',
	4=>'reagent3',
	5=>'reagent4',
	6=>'reagent5',
	7=>'reagent6',
	8=>'reagent7',
	9=>'reagent8',
	10=>'reagentcount1',
	11=>'reagentcount2',
	12=>'reagentcount3',
	13=>'reagentcount4',
	14=>'reagentcount5',
	15=>'reagentcount6',
	16=>'reagentcount7',
	17=>'reagentcount8'
	)));


LogLine(dbcdecode('SkillLine', array(1=>'lineid',2=>'linecatid',3=>'linename')));
LogLine(dbcdecode('SkillLineAbility', array(1=>'slaid',2=>'lineid',3=>'spellid',9=>'greyat',10=>'yellowat')));

RunAndLogError('CREATE temporary TABLE `ttblDBCSkillLines` (`id` smallint unsigned NOT NULL, `name` char(50) NOT NULL, PRIMARY KEY (`id`)) ENGINE=memory');
RunAndLogError('insert into ttblDBCSkillLines (select lineid, linename from ttblSkillLine where ((linecatid=11) or (linecatid=9 and (linename=\'Cooking\' or linename like \'Way of %\'))))');

LogLine('Getting trades..');
RunAndLogError('truncate tblDBCItemReagents');
for ($x = 1; $x <= 8; $x++) {
    $sql = 'insert into tblDBCItemReagents (select itemcreated, sl.id, sr.reagent'.$x.', sr.reagentcount'.$x.'/if(se.diesides=0,if(se.qtymade=0,1,se.qtymade),(se.qtymade * 2 + se.diesides + 1)/2), s.spellid from ttblSpell s, ttblSpellReagents sr, ttblSpellEffect se, ttblSkillLineAbility sla, ttblDBCSkillLines sl where sla.lineid=sl.id and sla.spellid=s.spellid and s.reagentsid=sr.reagentsid and s.spellid=se.spellid and se.itemcreated != 0 and sr.reagent'.$x.' != 0)';
    $sr = run_sql($sql);
    if ($sr != '') LogLine($sql."\n".$sr);
}

RunAndLogError('truncate tblDBCSpell');
$sql = <<<EOF
insert into tblDBCSpell (id,name,icon,description,cooldown,qtymade,yellow,skillline,crafteditem)
(select distinct s.spellid, s.spellname, si.iconpath, s.longdescription,
    greatest(
        ifnull(cd.categorycooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cd.individualcooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cc.chargecooldown,0)) / 1000,
    if(se.itemcreated=0,0,if(se.diesides=0,if(se.qtymade=0,1,se.qtymade),(se.qtymade * 2 + se.diesides + 1)/2)),
    sla.yellowat,sla.lineid,if(se.itemcreated=0,null,se.itemcreated)
from ttblSpell s
left join ttblSpellMisc sm on s.miscid=sm.miscid
left join ttblSpellIcon si on si.iconid=sm.iconid
left join ttblSpellCooldowns cd on cd.id = s.cooldownsid
left join ttblSpellCategories cs on cs.id = s.categoriesid
left join ttblSpellCategory c on c.id = cs.categoryid
left join ttblSpellCategory2 cc on cc.id = cs.chargecategoryid
join tblDBCItemReagents ir on s.spellid=ir.spell
join ttblSpellEffect se on s.spellid=se.spellid
join ttblSkillLineAbility sla on s.spellid=sla.spellid
where se.effecttypeid in (24,53,157))
EOF;
RunAndLogError($sql);

$sql = 'insert ignore into tblDBCSpell (id,name,icon,description) ';
$sql .= ' (select distinct s.spellid, s.spellname, si.iconpath, s.longdescription ';
$sql .= ' from ttblSpell s left join ttblSpellMisc sm on s.miscid=sm.miscid left join ttblSpellIcon si on si.iconid=sm.iconid ';
$sql .= ' join tblDBCItemSpell dis on dis.spell=s.spellid) ';
RunAndLogError($sql);


$sql = <<<EOF
replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell)
select ic.id, ir.skillline, ir.reagent, ir.quantity, ir.spell
from tblDBCItem ic, tblDBCItem ic2, tblDBCItemReagents ir
where ic.class=3 and ic.quality=2 and ic.name like 'Perfect %'
and ic2.class=3 and ic2.name = substr(ic.name,9)
and ic2.id=ir.item
EOF;
RunAndLogError($sql);

/*
$sql = 'insert into tblDBCItemReagents (select 45850, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
$sql = 'insert into tblDBCItemReagents (select 45851, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
$sql = 'insert into tblDBCItemReagents (select 45852, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
$sql = 'insert into tblDBCItemReagents (select 45853, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
*/
$sql = 'update tblDBCItemReagents set quantity=quantity*1000 where item in (select id from tblDBCItem where class=6)';
run_sql($sql);

//$sql = 'replace INTO tblItemVendorCost (itemid, copper) VALUES (52078, 0)';
//run_sql($sql);

/* arctic fur */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (44128,0,38425,10,-32515)';
run_sql($sql);

/* frozen orb swaps NPC 40160 */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (47556,0,43102,6,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (45087,0,43102,4,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35623,0,43102,1,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35624,0,43102,1,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (36860,0,43102,1,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35625,0,43102,1,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35627,0,43102,1,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35622,0,43102,1,-40160)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (36908,0,43102,1,-40160)';
run_sql($sql);

/* spirit of harmony */
$sql = <<<EOF
replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values 
(72092,0,76061,0.05,-66678),
(72093,0,76061,0.05,-66678),
(72094,0,76061,0.2,-66678),
(72103,0,76061,0.2,-66678),
(72120,0,76061,0.05,-66678),
(72238,0,76061,0.5,-66678),
(72988,0,76061,0.05,-66678),
(74247,0,76061,1,-66678),
(74249,0,76061,0.05,-66678),
(74250,0,76061,0.2,-66678),
(76734,0,76061,1,-66678),
(79101,0,76061,0.05,-66678),
(79255,0,76061,1,-66678)
EOF;
run_sql($sql);

/* ink trader - currency is ink of dreams */
/* starlight ink uncommon */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (79255,0,79254,10,-33027)';
run_sql($sql);

/* inferno ink uncommon */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (61981,0,79254,10,-33027)';
run_sql($sql);

/* snowfall ink uncommon */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43127,0,79254,10,-33027)';
run_sql($sql);

/* blackfallow ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (61978,0,79254,1,-33027)';
run_sql($sql);

/* celestial ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43120,0,79254,1,-33027)';
run_sql($sql);

/* ethereal ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43124,0,79254,1,-33027)';
run_sql($sql);

/* ink of the sea */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43126,0,79254,1,-33027)';
run_sql($sql);

/* ivory ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (37101,0,79254,1,-33027)';
run_sql($sql);

/* jadefire ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43118,0,79254,1,-33027)';
run_sql($sql);

/* lions ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43116,0,79254,1,-33027)';
run_sql($sql);

/* midnight ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (39774,0,79254,1,-33027)';
run_sql($sql);

/* moonglow ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (39469,0,79254,1,-33027)';
run_sql($sql);

/* shimmering ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43122,0,79254,1,-33027)';
run_sql($sql);

/* pristine hide */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (52980,0,56516,10,-50381)';
run_sql($sql);

/* imperial silk cooldown */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) VALUES (82447, 197, 82441, 8, 125557)';
run_sql($sql);

/* spells deleted from game */
$sql = 'delete from tblDBCItemReagents where spell=74493 and item=52976 and skillline=165 and reagent=52977 and quantity=4';
run_sql($sql);
$sql = 'delete from tblDBCItemReagents where spell=28021 and item=22445 and skillline=333 and reagent=12363';
run_sql($sql);
run_sql('delete FROM tblDBCItemReagents WHERE spell in (102366,140040,140041)');

LogLine('Getting spell expansion IDs..');
$sql = <<<EOF
SELECT s.id, max(ic.level) mx, min(ic.level) mn
FROM tblDBCItemReagents ir, tblDBCItem ic, tblDBCSpell s
WHERE ir.spell=s.id
and ir.reagent=ic.id
and ic.level <= 100
and ic.id not in (select item from tblDBCItemVendorCost)
and s.expansion is null
group by s.id
EOF;

$rst = get_rst($sql);
while ($row = next_row($rst))
{
    $exp = 0;

    if (is_null($row['mx']))
        $exp = 'null';
    elseif ($row['mx'] > 90)
        $exp = 5; // wod
    elseif ($row['mx'] > 85)
        $exp = 4; // mop
    elseif ($row['mx'] > 80)
        $exp = 3; // cata
    elseif ($row['mx'] > 70)
        $exp = 2; // wotlk
    elseif ($row['mx'] > 60)
        $exp = 1; // bc
    elseif ($row['mn'] == 60)
        $exp = 1;

    run_sql(sprintf('update tblDBCSpell set expansion=%s where id=%d', $exp, $row['id']));
}


/* */
LogLine("Done.\n ");

function getIconById($iconid) {
	static $iconcache = array();
	if (isset($iconcache[$iconid])) return $iconcache[$iconid];

	$spellicon = '';
	$irow = get_single_row('select iconpath from ttblSpellIcon where iconid=\''.$iconid.'\'');
	if (($irow) && (nvl($irow['iconpath'],'~') != '~')) {
		$spellicon = $irow['iconpath'];
		$spellicon = substr($spellicon, strrpos($spellicon, '\\')+1);
	}
	$iconcache[$iconid] = $spellicon;
	return $spellicon;
}

function LogLine($msg) {
	if ($msg == '') return;
	if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
	echo "\n".Date('H:i:s').' '.$msg;
}

function RunAndLogError($sql) {
    global $db;
    $ok = is_bool($sql) ? $sql : $db->real_query($sql);
    if (!$ok) {
        LogLine("Error: ".$db->error."\n".$sql);
        exit(1);
    }
}

function EchoProgress($frac) {
    static $lastStr = false;
    if ($frac === false) {
        $lastStr = false;
        return;
    }

    $str = str_pad(number_format($frac * 100, 1) . '%', 6, ' ', STR_PAD_LEFT);
    if ($str === $lastStr) {
        return;
    }

    echo ($lastStr === false) ? " " : str_repeat(chr(8), strlen($lastStr)), $lastStr = $str;
}

function GetFileDataName($id) {
    global $fileDataReader;
    $row = $fileDataReader->getRecord($id);
    if (is_null($row)) {
        return null;
    }
    return preg_replace('/\.blp$/', '', strtolower($row['name']));
}
<?php
require_once '../../incl/incl.php';
require_once 'db2/src/autoload.php';

use \Erorus\DB2\Reader;

error_reporting(E_ALL);

$locale = 'enus';
$dirnm = 'current/enUS';

LogLine("Starting!");

DBConnect();
RunAndLogError('set session max_heap_table_size='.(1024*1024*1024));

$fileDataReader = new Reader($dirnm . '/ManifestInterfaceData.db2');
$fileDataReader->setFieldNames(['path', 'name']);

LogLine("tblDBCItemSubClass");
$sql = <<<EOF
insert into tblDBCItemSubClass (class, subclass, name_$locale) values (?, ?, ?)
on duplicate key update name_$locale = ifnull(values(name_$locale), name_$locale)
EOF;
$reader = new Reader($dirnm . '/ItemSubClass.db2');
$reader->setFieldNames([0=>'name', 1=>'plural', 3=>'class', 4=>'subclass']);
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
$battlePetSpeciesReader->setFieldNames([0=>'npcid', 1=>'iconid', 5=>'flags', 6=>'type', 7=>'category']);

$creatureReader = new Reader($dirnm . '/Creature.db2');
$creatureReader->setFieldNames([4=>'name']);

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
$reader->setFieldNames([0=>'amount', 1=>'species', 2=>'state']);
$reader->setFieldsSigned([0=>true]);
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

$reader = new Reader($dirnm . '/ItemNameDescription.db2');
$reader->setFieldNames(['name']);
$bonusNames = [];
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $rec) {
    EchoProgress(++$x/$recordCount);
    $bonusNames[$id] = $rec['name'];
}
EchoProgress(false);
unset($reader);

$reader = new Reader($dirnm . '/ItemBonus.db2');
$reader->setFieldNames(['params', 'bonusid', 'changetype', 'prio']);
$reader->setFieldsSigned([true]);
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
        case 3: // quality
            $bonuses[$row['bonusid']]['quality'] = $row['params'][0];
            break;
        case 4: // nametag
            if (!isset($bonuses[$row['bonusid']]['nametag'])) {
                $bonuses[$row['bonusid']]['nametag'] = ['name' => '', 'prio' => -1];
            }
            if ($bonuses[$row['bonusid']]['nametag']['prio'] < $row['params'][1]) {
                $bonuses[$row['bonusid']]['nametag'] = ['name' => isset($bonusNames[$row['params'][0]]) ? $bonusNames[$row['params'][0]] : $row['params'][0], 'prio' => $row['params'][1]];
            }
            break;
        case 5: // rand enchant name
            if (!isset($bonuses[$row['bonusid']]['randname'])) {
                $bonuses[$row['bonusid']]['randname'] = ['name' => '', 'prio' => -1];
            }
            if ($bonuses[$row['bonusid']]['randname']['prio'] < $row['params'][1]) {
                $bonuses[$row['bonusid']]['randname'] = ['name' => isset($bonusNames[$row['params'][0]]) ? $bonusNames[$row['params'][0]] : $row['params'][0], 'prio' => $row['params'][1]];
            }
            break;
        case 14: // min itemlevel
            if (!isset($bonuses[$row['bonusid']]['minitemlevel'])) {
                $bonuses[$row['bonusid']]['minitemlevel'] = 0;
            }
            $bonuses[$row['bonusid']]['minitemlevel'] = max($bonuses[$row['bonusid']]['minitemlevel'], $row['params'][0]);
            break;
    }
}

RunAndLogError('truncate table tblDBCItemBonus');
$stmt = $db->prepare("insert into tblDBCItemBonus (id, quality, level, minlevel, tag_$locale, tagpriority, name_$locale, namepriority) values (?, ?, ?, ?, ?, ?, ?, ?)");
$id = $quality = $level = $minLevel = $tag = $tagPriority = $name = $namePriority = null;
$stmt->bind_param('iiiisisi', $id, $quality, $level, $minLevel, $tag, $tagPriority, $name, $namePriority);
foreach ($bonuses as $bonusId => $bonusData) {
    $id = $bonusId;
    $quality = isset($bonusData['quality']) ? $bonusData['quality'] : null;
    $level = isset($bonusData['itemlevel']) ? $bonusData['itemlevel'] : null;
    $minLevel = isset($bonusData['minitemlevel']) ? $bonusData['minitemlevel'] : null;
    $tag = isset($bonusData['nametag']) ? $bonusData['nametag']['name'] : null;
    $tagPriority = isset($bonusData['nametag']) ? $bonusData['nametag']['prio'] : null;
    $name = isset($bonusData['randname']) ? $bonusData['randname']['name'] : null;
    $namePriority = isset($bonusData['randname']) ? $bonusData['randname']['prio'] : null;
    RunAndLogError($stmt->execute());
}
$stmt->close();
unset($bonuses, $bonusRows, $bonusNames);
RunAndLogError('update tblDBCItemBonus set flags = flags | 1 where (ifnull(level,0) != 0 or ifnull(minlevel,0) != 0)');

LogLine("tblDBCItem");
$itemReader = new Reader($dirnm . '/Item.db2');
$itemReader->setFieldNames(['iconfiledata', 'class', 'subclass']);
$itemSparseReader = new Reader($dirnm . '/Item-sparse.db2');
$itemSparseReader->setFieldNames([
    0=>'flags',
    3=>'buyprice',
    4=>'sellprice',
    9=>'stacksize',
    13=>'name',
    22=>'level',
    23=>'requiredskill',
    42=>'quality',
    43=>'buycount',
    44=>'type',
    45=>'requiredlevel',
    52=>'binds',
]);

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

    $noTransmogFlag = ($sparseRec['flags'][1] & 0x400000) ? 2 : 0;

    $flags = $noTransmogFlag;

    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($itemReader);
unset($itemSparseReader);

$appearanceReader = new Reader($dirnm . '/ItemAppearance.db2');
$appearanceReader->setFieldNames(['display', 'iconfiledata']);
$modifiedAppearanceReader = new Reader($dirnm . '/ItemModifiedAppearance.db2');
$modifiedAppearanceReader->setFieldNames(['item', 'appearance', 'bonustype', 'index']);

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

// this bonus tree node stuff probably isn't quite right
$bonusTreeNodeReader = new Reader($dirnm . '/ItemBonusTreeNode.db2');
$bonusTreeNodeReader->setFieldNames(['node', 2=>'bonus']);
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
$itemXBonusTreeReader->setFieldNames(['item', 'node']);

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
unset($itemXBonusTreeReader);
EchoProgress(false);

LogLine("tblDBCItemSpell");
$reader = new Reader($dirnm . '/ItemEffect.db2');
$reader->setFieldNames(['item', 'spell']);
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

LogLine("tblDBCRandEnchants");
$reader = new Reader($dirnm . '/ItemRandomSuffix.db2');
$reader->setFieldNames(['name']);
RunAndLogError('truncate table tblDBCRandEnchants');
$stmt = $db->prepare("insert into tblDBCRandEnchants (id, name_$locale) values (?, ?) on duplicate key update name_$locale = values(name_$locale)");
$enchId = $name = null;
$stmt->bind_param('is', $enchId, $name);
$x = 0; $recordCount = count($reader->getIds());
foreach ($reader->generateRecords() as $id => $rec) {
    EchoProgress(++$x/$recordCount);
    $enchId = $id * -1;
    $name = $rec['name'];
    RunAndLogError($stmt->execute());
}
$stmt->close();
EchoProgress(false);
unset($reader);

$reader = new Reader($dirnm . '/ItemRandomProperties.db2');
$reader->setFieldNames(['name']);
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

LogLine("Making spell temp tables..");

DB2TempTable('SpellIcon', ['iconpath']);
RunAndLogError('update ttblSpellIcon set iconpath = substring_index(iconpath,\'\\\\\',-1) where instr(iconpath,\'\\\\\') > 0');

DB2TempTable('SpellEffect', [
	10=>'effecttypeid', //24 = create item, 53 = enchant, 157 = create tradeskill item
	13=>'qtymade',
	15=>'diesides',
	16=>'itemcreated',
	22=>'spellid',
	23=>'effectorder',
	]);

DB2TempTable('Spell', [
	0=>'spellname',
	2=>'longdescription',
    4=>'miscid',
	]);

DB2TempTable('SpellCooldowns', [
    0=>'spell',
    1=>'categorycooldown',
    2=>'individualcooldown',
]);

DB2TempTable('SpellCategories', [
    0=>'spell',
    2=>'categoryid',
    8=>'chargecategoryid',
]);

DB2TempTable('SpellCategory', [
    1=>'chargecooldown',
    2=>'flags',
]);

RunAndLogError('create temporary table ttblSpellCategory2 select * from ttblSpellCategory');

DB2TempTable('SpellMisc', [
    6=>'iconid',
]);

DB2TempTable('SpellReagents', [
	0=>'spell',
	1=>'reagent',
	2=>'reagentcount',
]);

DB2TempTable('SkillLine', [0=>'linename',5=>'linecatid']);
DB2TempTable('SkillLineAbility', [0=>'spellid',4=>'lineid',6=>'greyat',7=>'yellowat']);

RunAndLogError('CREATE temporary TABLE `ttblDBCSkillLines` (`id` smallint unsigned NOT NULL, `name` char(50) NOT NULL, PRIMARY KEY (`id`)) ENGINE=memory');
RunAndLogError('insert into ttblDBCSkillLines (select id, linename from ttblSkillLine where ((linecatid=11) or (linecatid=9 and (linename=\'Cooking\' or linename like \'Way of %\'))))');

LogLine('Getting trades..');
RunAndLogError('truncate tblDBCItemReagents');
for ($x = 1; $x <= 8; $x++) {
    $sql = <<<'EOF'
insert into tblDBCItemReagents (item, skillline, reagent, quantity, spell) (
    select itemcreated, 
        sl.id, 
        sr.reagent%1$d, 
        sr.reagentcount%1$d/if(se.diesides=0,if(se.qtymade=0,1,se.qtymade),(se.qtymade * 2 + se.diesides + 1)/2), 
        s.id 
    from ttblSpell s
    join ttblSpellReagents sr on sr.spell = s.id 
    join ttblSpellEffect se on se.spellid = s.id 
    join ttblSkillLineAbility sla on sla.spellid = s.id
    join ttblDBCSkillLines sl on sl.id = sla.lineid
    where se.itemcreated != 0 and sr.reagent%1$d != 0
)
EOF;
    RunAndLogError(sprintf($sql, $x));
}

RunAndLogError('truncate tblDBCSpell');
$sql = <<<EOF
insert into tblDBCSpell (id,name,icon,description,cooldown,qtymade,yellow,skillline,crafteditem)
(select distinct s.id, s.spellname, si.iconpath, s.longdescription,
    greatest(
        ifnull(cd.categorycooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cd.individualcooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cc.chargecooldown,0)) / 1000,
    if(se.itemcreated=0,0,if(se.diesides=0,if(se.qtymade=0,1,se.qtymade),(se.qtymade * 2 + se.diesides + 1)/2)),
    sla.yellowat,sla.lineid,if(se.itemcreated=0,null,se.itemcreated)
from ttblSpell s
left join ttblSpellMisc sm on s.miscid=sm.id
left join ttblSpellIcon si on si.id=sm.iconid
left join ttblSpellCooldowns cd on cd.spell = s.id
left join ttblSpellCategories cs on cs.spell = s.id
left join ttblSpellCategory c on c.id = cs.categoryid
left join ttblSpellCategory2 cc on cc.id = cs.chargecategoryid
join tblDBCItemReagents ir on s.id=ir.spell
join ttblSpellEffect se on s.id=se.spellid
join ttblSkillLineAbility sla on s.id=sla.spellid
where se.effecttypeid in (24,53,157))
EOF;
RunAndLogError($sql);

$sql = 'insert ignore into tblDBCSpell (id,name,icon,description) ';
$sql .= ' (select distinct s.id, s.spellname, si.iconpath, s.longdescription ';
$sql .= ' from ttblSpell s left join ttblSpellMisc sm on s.miscid=sm.id left join ttblSpellIcon si on si.id=sm.iconid ';
$sql .= ' join tblDBCItemSpell dis on dis.spell=s.id) ';
RunAndLogError($sql);

$sql = <<<EOF
replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell)
select ic.id, ir.skillline, ir.reagent, ir.quantity, ir.spell
from tblDBCItem ic, tblDBCItem ic2, tblDBCItemReagents ir
where ic.class=3 and ic.quality=2 and ic.name_enus like 'Perfect %'
and ic2.class=3 and ic2.name_enus = substr(ic.name_enus,9)
and ic2.id=ir.item
EOF;
RunAndLogError($sql);

/* arctic fur */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (44128,0,38425,10,-32515)');

/* frozen orb swaps NPC 40160 */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (47556,0,43102,6,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (45087,0,43102,4,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35623,0,43102,1,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35624,0,43102,1,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (36860,0,43102,1,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35625,0,43102,1,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35627,0,43102,1,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (35622,0,43102,1,-40160)');
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (36908,0,43102,1,-40160)');

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
RunAndLogError($sql);

/* ink trader - currency is ink of dreams */
/* starlight ink uncommon */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (79255,0,79254,10,-33027)');

/* inferno ink uncommon */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (61981,0,79254,10,-33027)');

/* snowfall ink uncommon */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43127,0,79254,10,-33027)');

/* blackfallow ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (61978,0,79254,1,-33027)');

/* celestial ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43120,0,79254,1,-33027)');

/* ethereal ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43124,0,79254,1,-33027)');

/* ink of the sea */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43126,0,79254,1,-33027)');

/* ivory ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (37101,0,79254,1,-33027)');

/* jadefire ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43118,0,79254,1,-33027)');

/* lions ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43116,0,79254,1,-33027)');

/* midnight ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (39774,0,79254,1,-33027)');

/* moonglow ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (39469,0,79254,1,-33027)');

/* shimmering ink */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (43122,0,79254,1,-33027)');

/* pristine hide */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) values (52980,0,56516,10,-50381)');

/* imperial silk cooldown */
RunAndLogError('replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell) VALUES (82447, 197, 82441, 8, 125557)');

/* spells deleted from game */
RunAndLogError('delete from tblDBCItemReagents where spell=74493 and item=52976 and skillline=165 and reagent=52977 and quantity=4');
RunAndLogError('delete from tblDBCItemReagents where spell=28021 and item=22445 and skillline=333 and reagent=12363');
RunAndLogError('delete FROM tblDBCItemReagents WHERE spell in (102366,140040,140041)');

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

$stmt = $db->prepare($sql);
RunAndLogError($stmt->execute());
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $exp = 0;

    if (is_null($row['mx']))
        $exp = 'null';
    elseif ($row['mx'] > 100)
        $exp = 6; // legion
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

    RunAndLogError(sprintf('update tblDBCSpell set expansion=%s where id=%d', $exp, $row['id']));
}
$result->close();
$stmt->close();


/* */
LogLine("Done.\n ");

function LogLine($msg) {
	if ($msg == '') return;
	if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
	echo "\n".date('H:i:s').' '.$msg;
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

function DB2TempTable($baseFile, $columns, $signedCols=[]) {
    global $dirnm, $db;

    $filePath = "$dirnm/$baseFile.db2";
    if (!file_exists($filePath)) {
        $filePath = "$dirnm/$baseFile.dbc";
    }
    if (!file_exists($filePath)) {
        LogLine("Could not find $filePath\n ");
        exit(1);
    }

    LogLine("ttbl$baseFile");
    $reader = new Reader($filePath);
    $reader->setFieldNames($columns);
    $reader->setFieldsSigned($signedCols);
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
                    $sql .= 'int';
                    $paramTypes .= 'i';
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
            if ($fieldCounts[$colName] > 1) {
                for ($z = 1; $z <= $fieldCounts[$colName]; $z++) {
                    $row[$colName.$z] = $rec[$colName][$z-1];
                }
            } else {
                $row[$colName] = $rec[$colName];
            }
        }
        RunAndLogError($stmt->execute());
    }
    EchoProgress(false);
    unset($reader);
}

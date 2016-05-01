<?php
require_once('../incl/old.incl.php');
require_once('dbcdecode.php');

$LOCALES = ['dede','eses','frfr','itit','ptbr','ruru'];

header('Content-type: text/plain');
error_reporting(E_ALL);

DBConnect();

$tables = array();
dtecho(run_sql('set session max_heap_table_size='.(1024*1024*1024)));

foreach ($LOCALES as $locale) {
    dtecho("Starting locale $locale");

    foreach ($tables as $tbl) {
        run_sql('drop temporary table `ttbl'.$tbl.'`');
    }
    $tables = array();

    $dirnm = 'current/' . substr($locale, 0, 2) . strtoupper(substr($locale, 2, 2));

    /* */

    // DBCItemSubClass
    dtecho(dbcdecode('ItemSubClass', array(1=>'id', 2=>'class', 3=>'subclass', 12=>'name', 13=>'plural')));
    dtecho(run_sql("insert ignore into tblDBCItemSubClass (class, subclass, name_$locale) (select class, subclass, if(ifnull(plural,'')='',name,plural) from ttblItemSubClass) on duplicate key update tblDBCItemSubClass.name_$locale=ifnull(values(name_$locale),tblDBCItemSubClass.name_$locale)"));

    // DBCItem
    dtecho(dbcdecode('Item-sparse', array(1=>'id',71=>'name')));
    dtecho(run_sql('delete from `ttblItem-sparse` where id not in (select id from tblDBCItem)'));
    dtecho(run_sql("insert into tblDBCItem (id, name_$locale) (select id, name from `ttblItem-sparse`) on duplicate key update tblDBCItem.name_$locale=ifnull(values(name_$locale),tblDBCItem.name_$locale)"));

    //DBCItemBonus
    dtecho(dbcdecode('ItemBonus', array(2=>'bonusid', 3=>'changetype', 4=>'param1', 5=>'param2', 6=>'prio')));
    dtecho(dbcdecode('ItemNameDescription', array(1=>'id', 2=>'name')));

    $bonuses = [];
    $bonusNames = [];
    $rst = get_rst('select * from ttblItemNameDescription');
    while ($row = next_row($rst)) {
        $bonusNames[$row['id']] = $row['name'];
    }
    $rst = get_rst('select * from ttblItemBonus order by bonusid, prio');
    while ($row = next_row($rst)) {
        if (!isset($bonuses[$row['bonusid']])) {
            $bonuses[$row['bonusid']] = [];
        }
        switch ($row['changetype']) {
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

    dtecho(run_sql('CREATE temporary TABLE `ttblDBCItemBonus` LIKE `tblDBCItemBonus`'));
    $tables[] = 'DBCItemBonus';

    foreach ($bonuses as $bonusId => $bonusData) {
        $sql = "insert into ttblDBCItemBonus (id, tag_$locale, `name_$locale`) values ($bonusId";
        if (isset($bonusData['nametag'])) {
            $sql .= ', \'' . sql_esc($bonusData['nametag']['name']) . '\'';
        } else {
            $sql .= ', null';
        }
        if (isset($bonusData['randname'])) {
            $sql .= ', \'' . sql_esc($bonusData['randname']['name']) . '\'';
        } else {
            $sql .= ', null';
        }
        $sql .= ')';
        dtecho(run_sql($sql));
    }

    dtecho(run_sql('delete from ttblDBCItemBonus where id not in (select id from tblDBCItemBonus)'));
    dtecho(run_sql("insert into tblDBCItemBonus (id, tag_$locale, name_$locale) (select id, tag_$locale, name_$locale from ttblDBCItemBonus) on duplicate key update tag_$locale = values(tag_$locale), name_$locale = values(name_$locale)"));

    // DBCItemRandomSuffix
    dtecho(dbcdecode('ItemRandomSuffix', array(1=>'suffixid', 2=>'name1', 3=>'name2')));
    dtecho(dbcdecode('ItemRandomProperties', array(1=>'suffixid', 2=>'name1', 8=>'name2')));
    dtecho(run_sql('delete from ttblItemRandomProperties where suffixid not in (select id from tblDBCRandEnchants)'));
    dtecho(run_sql('delete from ttblItemRandomSuffix where suffixid not in (select id * -1 from tblDBCRandEnchants)'));
    dtecho(run_sql('insert into tblDBCRandEnchants (id, name_'.$locale.') (select suffixid, ifnull(name1, name2) from ttblItemRandomProperties) on duplicate key update name_'.$locale.'=values(name_'.$locale.')'));
    dtecho(run_sql('insert into tblDBCRandEnchants (id, name_'.$locale.') (select suffixid * -1, ifnull(name1, name2) from ttblItemRandomSuffix) on duplicate key update name_'.$locale.'=values(name_'.$locale.')'));
    dtecho(run_sql('insert ignore into tblDBCItemRandomSuffix (locale, suffix) (select distinct \''.$locale.'\', name_'.$locale.' from tblDBCRandEnchants where trim(name_'.$locale.') != \'\' and id < 0)'));

    // DBCPet
    dtecho(dbcdecode('BattlePetSpecies', array(1=>'id', 2=>'npcid')));
    dtecho(dbcdecode('Creature', array(1=>'id', 15=>'name')));
    dtecho(run_sql('delete from ttblBattlePetSpecies where id not in (select id from tblDBCPet)'));
    dtecho(run_sql("insert into tblDBCPet (id, name_$locale) (select bps.id, c.name from ttblBattlePetSpecies bps join ttblCreature c on bps.npcid = c.id) on duplicate key update name_$locale=values(name_$locale)"));


    /* */
}

/* */
dtecho("Done.\n ");

function dtecho($msg) {
	if ($msg == '') return;
	if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
	echo "\n".Date('H:i:s').' '.$msg;
}

foreach ($tables as $tbl) {
	run_sql('drop temporary table `ttbl'.$tbl.'`');
}
cleanup('');

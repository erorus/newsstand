<?php

require_once '../../incl/incl.php';
require_once 'db2/src/autoload.php';

use \Erorus\DB2\Reader;

$LOCALES = ['dede','eses','frfr','itit','ptbr','ruru'];

$newLocales = [];
for ($x = 1; $x < count($argv); $x++) {
    if (in_array($argv[$x], $LOCALES)) {
        $newLocales[] = $argv[$x];
    } else {
        fwrite(STDERR, "Could not find locale ".$argv[$x]."\n");
    }
}
if (count($newLocales)) {
    $LOCALES = $newLocales;
}

error_reporting(E_ALL);

$db = DBConnect();

foreach ($LOCALES as $locale) {
    $dirnm = 'current/' . substr($locale, 0, 2) . strtoupper(substr($locale, 2, 2));

    LogLine("$locale tblDBCItemSubClass");
    $sql = <<<EOF
insert into tblDBCItemSubClass (class, subclass, name_$locale) values (?, ?, ?)
on duplicate key update name_$locale = ifnull(values(name_$locale), name_$locale)
EOF;
    $reader = new Reader($dirnm . '/ItemSubClass.dbc');
    $reader->setFieldNames([0=>'name', 1=>'plural', 3=>'class', 4=>'subclass']);
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
        $stmt->execute();
    }
    $stmt->close();
    EchoProgress(false);
    unset($reader);

    LogLine("$locale tblDBCItem");
    $reader = new Reader($dirnm . '/Item-sparse.db2');
    $reader->setFieldNames([13 => 'name']);
    $stmt = $db->prepare("insert into tblDBCItem (id, name_$locale) values (?, ?) on duplicate key update name_$locale = ifnull(values(name_$locale), name_$locale)");
    $id = $name = null;
    $stmt->bind_param('is', $id, $name);
    $x = 0; $recordCount = count($reader->getIds());
    foreach ($reader->generateRecords() as $recId => $rec) {
        EchoProgress(++$x/$recordCount);
        $id = $recId;
        $name = $rec['name'];
        $stmt->execute();
    }
    $stmt->close();
    EchoProgress(false);
    unset($reader);

    LogLine("$locale tblDBCItemBonus");

    $reader = new Reader($dirnm . '/ItemNameDescription.dbc');
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
        }
    }

    $sql = <<<EOF
insert into tblDBCItemBonus (id, tag_$locale, name_$locale) values (?, ?, ?)
on duplicate key update tag_$locale = values(tag_$locale), name_$locale = values(name_$locale)
EOF;
    $stmt = $db->prepare($sql);
    $id = $tag = $name = null;
    $stmt->bind_param('iss', $id, $tag, $name);
    foreach ($bonuses as $bonusId => $bonusData) {
        $id = $bonusId;
        $tag = isset($bonusData['nametag']) ? $bonusData['nametag']['name'] : null;
        $name = isset($bonusData['randname']) ? $bonusData['randname']['name'] : null;
        $stmt->execute();
    }
    $stmt->close();
    unset($bonuses, $bonusRows, $bonusNames);
    
    
    LogLine("$locale tblDBCRandEnchants");

    $reader = new Reader($dirnm . '/ItemRandomSuffix.db2');
    $reader->setFieldNames(['name']);
    $stmt = $db->prepare("insert into tblDBCRandEnchants (id, name_$locale) values (?, ?) on duplicate key update name_$locale = ifnull(values(name_$locale), name_enus)");
    $enchId = $name = null;
    $stmt->bind_param('is', $enchId, $name);
    $x = 0; $recordCount = count($reader->getIds());
    foreach ($reader->generateRecords() as $id => $rec) {
        EchoProgress(++$x/$recordCount);
        $enchId = $id * -1;
        $name = $rec['name'];
        if (!$name) {
            $name = null;
        }
        $stmt->execute();
    }
    $stmt->close();
    EchoProgress(false);
    unset($reader);

    $reader = new Reader($dirnm . '/ItemRandomProperties.db2');
    $reader->setFieldNames(['name']);
    $stmt = $db->prepare("insert into tblDBCRandEnchants (id, name_$locale) values (?, ?) on duplicate key update name_$locale = ifnull(values(name_$locale), name_enus)");
    $enchId = $name = null;
    $stmt->bind_param('is', $enchId, $name);
    $x = 0; $recordCount = count($reader->getIds());
    foreach ($reader->generateRecords() as $id => $rec) {
        EchoProgress(++$x/$recordCount);
        $enchId = $id;
        $name = $rec['name'];
        if (!$name) {
            $name = null;
        }
        $stmt->execute();
    }
    $stmt->close();
    EchoProgress(false);
    unset($reader);

    $stmt = $db->prepare("insert ignore into tblDBCItemRandomSuffix (locale, suffix) (select distinct '$locale', name_$locale from tblDBCRandEnchants where trim(name_$locale) != '' and id < 0)");
    $stmt->execute();
    $stmt->close();

    LogLine("$locale tblDBCPet");

    $battlePetReader = new Reader($dirnm . '/BattlePetSpecies.db2');
    $battlePetReader->setFieldNames(['npc']);
    $creatureReader = new Reader($dirnm . '/Creature.db2');
    $creatureReader->setFieldNames([4=>'name']);
    $stmt = $db->prepare("insert into tblDBCPet (id, name_$locale) values (?, ?) on duplicate key update name_$locale = values(name_$locale)");
    $species = $name = null;
    $stmt->bind_param('is', $species, $name);
    $x = 0; $recordCount = count($battlePetReader->getIds());
    foreach ($battlePetReader->generateRecords() as $id => $rec) {
        EchoProgress(++$x/$recordCount);
        $species = $id;
        $creature = $creatureReader->getRecord($rec['npc']);
        if (is_null($creature)) {
            continue;
        }
        $name = $creature['name'];
        $stmt->execute();
    }
    $stmt->close();
    EchoProgress(false);
    unset($creatureReader);
    unset($battlePetReader);


    /* */
}

/* */
LogLine("Done.\n");

function LogLine($msg) {
	if ($msg == '') return;
	if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
	echo "\n".Date('H:i:s').' '.$msg;
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

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

    LogLine("$locale GlobalStrings");
    $globalStrings = [];
    $reader = new Reader($dirnm . '/GlobalStrings.db2');
    $reader->setFieldNames(['key','value']);
    foreach ($reader->generateRecords() as $rec) {
        $globalStrings[$rec['key']] = $rec['value'];
    }
    unset($reader);

    LogLine("$locale tblDBCItemNameDescription");
    $stmt = $db->prepare("insert into tblDBCItemNameDescription (id, desc_$locale) values (?, ?) on duplicate key update desc_$locale = values(desc_$locale)");
    $tblId = $name = null;
    $stmt->bind_param('is', $tblId, $name);

    $tblId = SOCKET_FAKE_ITEM_NAME_DESC_ID;
    $name = '+ ' . $globalStrings['EMPTY_SOCKET_PRISMATIC'];
    $stmt->execute();

    $reader = new Reader($dirnm . '/ItemNameDescription.db2');
    $reader->setFieldNames(['name']);
    $x = 0; $recordCount = count($reader->getIds());
    foreach ($reader->generateRecords() as $id => $rec) {
        EchoProgress(++$x/$recordCount);

        $tblId = $id;
        $name = $rec['name'];

        $stmt->execute();
    }
    EchoProgress(false);
    unset($reader);

    LogLine("$locale tblDBCItemSubClass");
    $sql = <<<EOF
insert into tblDBCItemSubClass (class, subclass, name_$locale) values (?, ?, ?)
on duplicate key update name_$locale = ifnull(values(name_$locale), name_$locale)
EOF;
    $reader = new Reader($dirnm . '/ItemSubClass.db2');
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
	echo "\n".date('H:i:s').' '.$msg;
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

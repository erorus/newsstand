<?php

require_once __DIR__ . '/../../incl/incl.php';
require_once __DIR__ . '/dbc.incl.php';

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
    $reader = CreateDB2Reader('GlobalStrings');
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

    $reader = CreateDB2Reader('ItemNameDescription');
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
    $reader = CreateDB2Reader('ItemSubClass');
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
    $reader = CreateDB2Reader('ItemSparse');
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

    $stmt = $db->prepare("update tblDBCItem set name_$locale = name_enus where name_$locale is null");
    $stmt->execute();
    $stmt->close();

    LogLine("$locale tblDBCRandEnchants");
    $reader = CreateDB2Reader('ItemRandomSuffix');
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

    $reader = CreateDB2Reader('ItemRandomProperties');
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

    $battlePetReader = CreateDB2Reader('BattlePetSpecies');
    $creatureReader = CreateDB2Reader('Creature');
    $stmt = $db->prepare("insert into tblDBCPet (id, name_$locale) values (?, ?) on duplicate key update name_$locale = values(name_$locale)");
    $species = $name = null;
    $stmt->bind_param('is', $species, $name);
    $x = 0; $recordCount = count($battlePetReader->getIds());
    foreach ($battlePetReader->generateRecords() as $id => $rec) {
        EchoProgress(++$x/$recordCount);
        $species = $id;
        $creature = $creatureReader->getRecord($rec['npcid']);
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

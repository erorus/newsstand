<?php
require_once(__DIR__.'/../incl/incl.php');

ini_set('memory_limit','256M');

DBConnect();
$db->query('set session transaction isolation level read uncommitted');

GetDataTables();

function GetDataTables() {
    global $db, $argv;

    $sql = 'SELECT house from tblRealm where region=\'US\' and slug IN (\'medivh\', \'commodities\')';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result, null);
    $stmt->close();

    $sql = 'SELECT id from tblRealm where house = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result, null);
    $stmt->close();

    $tables = [
        'tblAuction' => 'house IN (' . implode(',', $houses) . ')',
        'tblAuctionBonus' => 'house IN (' . implode(',', $houses) . ')',
        'tblAuctionExtra' => 'house IN (' . implode(',', $houses) . ')',
        'tblAuctionPet' => 'house IN (' . implode(',', $houses) . ')',
        'tblAuctionRare' => 'house IN (' . implode(',', $houses) . ')',
        'tblBuilding' => '1=1',
        'tblHouseCheck' => '1=1',
        'tblItemBonusesSeen' => '1=1',
        'tblItemGlobal' => '1=1',
        'tblItemHistoryDaily' => 'house IN (' . implode(',', $houses) . ')',
        'tblItemHistoryHourly' => 'house IN (' . implode(',', $houses) . ')',
        //'tblItemHistoryMonthly' => 'item in (select id from tblDBCItem where auctionable=1) and house IN (' . implode(',', $houses) . ')',
        'tblItemSummary' => 'house IN (' . implode(',', $houses) . ')',
        'tblPet' => '1=1',
        'tblPetGlobal' => '1=1',
        'tblPetHistoryHourly' => 'house IN (' . implode(',', $houses) . ')',
        'tblPetSummary' => 'house IN (' . implode(',', $houses) . ')',
        'tblRealm' => '1=1',
        'tblSnapshot' => 'house IN (' . implode(',', $houses) . ')',
        'tblWowToken' => '1=1',
    ];

    if (count($argv) > 1) {
        $toRemove = array_diff(array_keys($tables), array_slice($argv, 1));;
        foreach ($toRemove as $tblName) {
            unset($tables[$tblName]);
        }
    }

    if (count($tables) == 0) {
        echo "No tables marked for export.\n";
        exit(1);
    }

    $sqlFile = __DIR__.'/../testdata.sql.gz';

    if (!(touch($sqlFile) && ($sqlFile = realpath($sqlFile)))) {
        echo "Could not create testdata.sql\n";
        exit(1);
    }
    $sqlResource = gzopen($sqlFile, 'w');

    $tmpFile = tempnam('/tmp', 'testdata');

    $cmd = 'mysqldump --verbose --allow-keywords --skip-opt --quick --create-options --add-drop-table --add-locks --extended-insert --no-autocommit --result-file=%s --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' --host='.escapeshellarg(DATABASE_HOST).' --where=%s '.escapeshellarg(DATABASE_SCHEMA)." %s\n";
    foreach ($tables as $table => $where) {
        file_put_contents($tmpFile, '');
        $trash = [];
        $ret = 0;
        exec(sprintf($cmd, escapeshellarg($tmpFile), escapeshellarg($where), escapeshellarg($table)), $trash, $ret);
        if ($ret != 0) {
            echo 'Error: '.implode("\n", $trash);
            break;
        }
        gzwrite($sqlResource, file_get_contents($tmpFile));
    }
    gzclose($sqlResource);

    unlink($tmpFile);
}

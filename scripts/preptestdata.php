<?php
require_once(__DIR__.'/../incl/incl.php');

ini_set('memory_limit','256M');

DBConnect();
$db->query('set session transaction isolation level read uncommitted');

GetDataTables();

function GetDataTables() {
    global $db, $argv;

    $sql = 'SELECT house from tblRealm where region=\'US\' and slug=\'medivh\'';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $house = 0;
    $stmt->bind_result($house);
    $stmt->fetch();
    $stmt->close();

    $sql = 'SELECT id from tblRealm where house = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result, null);
    $stmt->close();

    $sql = sprintf('SELECT id from tblSeller where realm in (%s) order by lastseen desc limit 20', implode(',', $realms));
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $sellers = DBMapArray($result, null);
    $stmt->close();

    $tables = [
        'tblAuction' => 'house='.$house,
        'tblAuctionExtra' => 'house='.$house,
        'tblAuctionPet' => 'house='.$house,
        'tblAuctionRare' => 'house='.$house,
        'tblBonusSet' => '1=1',
        'tblHouseCheck' => '1=1',
        'tblItemBonusesSeen' => '1=1',
        'tblItemExpired' => 'house='.$house,
        'tblItemGlobal' => '1=1',
        'tblItemHistoryDaily' => 'house='.$house,
        'tblItemHistoryHourly' => 'house='.$house,
        'tblItemHistoryMonthly' => 'item in (select id from tblDBCItem where auctionable=1) and house='.$house,
        'tblItemSummary' => 'house='.$house,
        'tblPet' => '1=1',
        'tblPetHistoryHourly' => 'house='.$house,
        'tblPetSummary' => 'house='.$house,
        'tblRealm' => '1=1',
        'tblRealmGuidHouse' => '1=1',
        'tblSeller' => 'realm in ('.implode(',',$realms).')',
        'tblSellerHistoryHourly' => 'seller in ('.implode(',',$sellers).')',
        'tblSellerItemHistory' => 'seller in ('.implode(',',$sellers).')',
        'tblSnapshot' => 'house='.$house,
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

    $cmd = 'mysqldump --verbose --allow-keywords --skip-opt --quick --create-options --add-drop-table --add-locks --extended-insert --no-autocommit --result-file=%s --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' --where=%s '.escapeshellarg(DATABASE_SCHEMA)." %s\n";
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
<?php
require_once('../incl/incl.php');

DBConnect();

GetDataTables();


function GetDataTables() {
    global $db;

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

    $sql = sprintf('SELECT id from tblSeller where realm in (%s) order by lastseen desc limit 10', implode(',', $realms));
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $sellers = DBMapArray($result, null);
    $stmt->close();

    $tables = [
        'tblAuction' => 'house='.$house,
        'tblAuctionPet' => 'house='.$house,
        'tblAuctionRare' => 'house='.$house,
        'tblHouseCheck' => '1=1',
        'tblItemGlobal' => '1=1',
        'tblItemHistory' => 'house='.$house,
        'tblItemHistoryDaily' => 'item in (select id from tblDBCItem where auctionable=1) and house='.$house,
        'tblItemHistoryMonthly' => 'item in (select id from tblDBCItem where auctionable=1) and house='.$house,
        'tblItemSummary' => 'house='.$house,
        'tblPetHistory' => 'house='.$house,
        'tblPetSummary' => 'house='.$house,
        'tblRealm' => '1=1',
        'tblSeller' => 'realm in ('.implode(',',$realms).')',
        'tblSellerHistory' => 'seller in ('.implode(',',$sellers).')',
        'tblSnapshot' => 'house='.$house,
    ];

    $sqlFile = __DIR__.'/../testdata.sql.gz';

    if (!(touch($sqlFile) && ($sqlFile = realpath($sqlFile)))) {
        echo "Could not create testdata.sql\n";
        exit(1);
    }
    $sqlResource = gzopen($sqlFile, 'w');

    $tmpFile = tempnam('/tmp', 'testdata');

    $cmd = 'mysqldump --verbose --allow-keywords --skip-opt --add-drop-table --add-locks --extended-insert --no-autocommit --result-file=%s --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' --where=%s '.escapeshellarg(DATABASE_SCHEMA)." %s\n";
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
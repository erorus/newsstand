<?php

require_once(__DIR__.'/../incl/incl.php');

$tables = [
    'tblBonusSet' => '1=1',
    'tblHouseCheck' => '1=1',
    'tblItemGlobal' => '1=1',
    'tblItemHistoryDaily' => 'item in (select id from tblDBCItem where auctionable=1)',
    'tblItemHistoryMonthly' => 'item in (select id from tblDBCItem where auctionable=1)',
    'tblItemSummary' => '1=1',
    'tblPet' => '1=1',
    'tblPetSummary' => '1=1',
    'tblRealm' => '1=1',
    'tblSnapshot' => '1=1',
];

$sqlFile = __DIR__.'/../backup/backupdata.'.Date('Ymd').'.sql.gz';

if (!(touch($sqlFile) && ($sqlFile = realpath($sqlFile)))) {
    echo "Could not create backupdata.sql.gz\n";
    exit(1);
}
file_put_contents($sqlFile, '');

$tmpFile = tempnam('/tmp', 'backupdata');

$cmd = 'mysqldump --verbose --quick --allow-keywords --skip-opt --create-options --add-drop-table --add-locks --extended-insert --single-transaction --result-file=%s --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' --where=%s '.escapeshellarg(DATABASE_SCHEMA)." %s\n";
foreach ($tables as $table => $where) {
    echo "Starting $table on ".Date("Y-m-d H:i:s")."\n";
    file_put_contents($tmpFile, '');
    $trash = [];
    $ret = 0;
    exec(sprintf($cmd, escapeshellarg($tmpFile), escapeshellarg($where), escapeshellarg($table)), $trash, $ret);
    if ($ret != 0) {
        echo 'Error: '.implode("\n", $trash);
        break;
    }
    exec(sprintf('gzip -c %s >> %s', escapeshellarg($tmpFile), escapeshellarg($sqlFile)), $trash, $ret);
    if ($ret != 0) {
        echo 'Error: '.implode("\n", $trash);
        break;
    }
}

unlink($tmpFile);

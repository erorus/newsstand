<?php

$startTime = time();

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
    DebugMessage("Could not create backupdata.sql.gz", E_USER_ERROR);
    exit(1);
}
file_put_contents($sqlFile, '');

$cmd = 'mysqldump --verbose --quick --allow-keywords --skip-opt --create-options --add-drop-table --add-locks --extended-insert --single-transaction --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' --where=%s '.escapeshellarg(DATABASE_SCHEMA)." %s | gzip -c >> %s\n";
foreach ($tables as $table => $where) {
    DebugMessage("Starting $table");
    $trash = [];
    $ret = 0;
    exec(sprintf($cmd, escapeshellarg($where), escapeshellarg($table), escapeshellarg($sqlFile)), $trash, $ret);
    if ($ret != 0) {
        echo 'Error: '.implode("\n", $trash);
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

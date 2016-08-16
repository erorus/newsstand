<?php

$startTime = time();

require_once(__DIR__.'/../incl/incl.php');
require_once(__DIR__.'/../incl/memcache.incl.php');

$backupSet = (isset($argv[1]) && ($argv[1] == 'user')) ? 'user' : 'data';

DebugMessage("Starting $backupSet backup");

$tables = [
    'data' => [
        'tblBonusSet'           => '1=1',
        'tblHouseCheck'         => '1=1',
        'tblItemGlobal'         => '1=1',
        'tblItemHistoryDaily'   => 'item in (select id from tblDBCItem where auctionable=1)',
        'tblItemHistoryMonthly' => 'item in (select id from tblDBCItem where auctionable=1)',
        'tblItemSummary'        => '1=1',
        'tblPet'                => '1=1',
        'tblPetSummary'         => '1=1',
        'tblRealm'              => '1=1',
        'tblSnapshot'           => '1=1',
        'tblWowToken'           => '1=1',
    ],
    'user' => [
        'tblEmailBlocked'       => '1=1',
        'tblEmailLog'           => '1=1',
        'tblPaypalTransactions' => '1=1',
        'tblUser'               => '1=1',
        'tblUserAuth'           => '1=1',
        'tblUserMessages'       => '1=1',
        'tblUserRare'           => '1=1',
        'tblUserSession'        => '1=1',
        'tblUserWatch'          => '1=1',
    ]
];

$sqlFile = __DIR__.'/../backup/backup'.$backupSet.'.'.date('Ymd').'.sql.gz';

if (!(touch($sqlFile) && ($sqlFile = realpath($sqlFile)))) {
    DebugMessage("Could not create backup$backupSet.sql.gz", E_USER_ERROR);
    exit(1);
}
file_put_contents($sqlFile, '');

APIMaintenance($backupSet == 'data' ? '+45 minutes' : '+5 minutes');

$cmd = 'mysqldump --verbose --skip-opt --quick --allow-keywords --create-options --add-drop-table --add-locks --extended-insert --single-transaction --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' --where=%s '.escapeshellarg(DATABASE_SCHEMA)." %s | gzip -c >> %s\n";
foreach ($tables[$backupSet] as $table => $where) {
    DebugMessage("Starting $table");
    if (($zp = gzopen($sqlFile, 'ab')) === false) {
        DebugMessage("Could not write table message for $table to $sqlFile");
    } else {
        gzwrite($zp, "select concat(now(), ' Inserting into $table');\n");
        gzclose($zp);
    }

    $trash = [];
    $ret = 0;
    exec(sprintf($cmd, escapeshellarg($where), escapeshellarg($table), escapeshellarg($sqlFile)), $trash, $ret);
    if ($ret != 0) {
        echo 'Error: '.implode("\n", $trash);
        break;
    }
}

if ($backupSet == 'data') {
    APIMaintenance('+2 minutes', '+2 minutes');
} else {
    APIMaintenance(0);
}

DebugMessage('Done! Started ' . TimeDiff($startTime));

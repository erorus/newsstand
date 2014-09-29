<?php
require_once('../incl/incl.php');

DBConnect();

$sql = sprintf('SELECT table_name FROM information_schema.tables where table_name like \'tblDBC%%\' and table_schema=\'%s\'', DATABASE_SCHEMA);
$stmt = $db->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$tables = DBMapArray($result, null);
$stmt->close();

$sqlFile = __DIR__.'/datatables.sql';

if (!(touch($sqlFile) && ($sqlFile = realpath($sqlFile)))) {
    echo "Could not create datatables.sql\n";
    exit(1);
}

$cmd = 'mysqldump --verbose --allow-keywords --result-file='.escapeshellarg($sqlFile).' --user='.escapeshellarg(DATABASE_USERNAME_CLI).' --password='.escapeshellarg(DATABASE_PASSWORD_CLI).' '.escapeshellarg(DATABASE_SCHEMA);
foreach ($tables as $table)
    $cmd .= ' ' . escapeshellarg($table);

passthru($cmd);

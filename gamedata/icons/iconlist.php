<?php
require_once __DIR__.'/../DBC/db2/src/autoload.php';

use \Erorus\DB2\Reader;

$manifest = new Reader(__DIR__.'/../DBC/current/enUS/ManifestInterfaceData.db2');
$manifest->setFieldNames(['path','name']);
foreach ($manifest->generateRecords() as $record) {
    if ((strtolower($record['path']) == 'interface\\icons\\') && (strtolower(substr($record['name'], -4)) == '.blp')) {
        echo strtolower($record['path']), $record['name'], "\n";
    }
}

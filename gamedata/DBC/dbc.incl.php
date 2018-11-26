<?php

require_once __DIR__ . '/db2/src/autoload.php';

use \Erorus\DB2\Reader;
use \Erorus\DB2\HotfixedReader;

$dbLayout = json_decode(file_get_contents(__DIR__ . '/layout.json'), true);

function LogLine($msg) {
    if ($msg == '') return;
    if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
    fwrite(STDERR, "\n".date('H:i:s').' '.$msg);
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

function CreateDB2Reader($name) {
    global $dbLayout, $dirnm;

    if (!isset($dbLayout[$name])) {
        LogLine(sprintf('Could not find %s in db layout', $name));
        exit(1);
    }

    $layout = $dbLayout[$name];

    $extension = 'db2';

    $filePath = "$dirnm/$name.$extension";
    if (!file_exists($filePath)) {
        LogLine("Could not find $filePath\n ");
        exit(1);
    }

    if (isset($layout['strings'])) {
        $reader = new Reader($filePath, $layout['strings']);
    } elseif (file_exists("$dirnm/DBCache.bin")) {
        $reader = new HotfixedReader($filePath, "$dirnm/DBCache.bin");
    } else {
        $reader = new Reader($filePath);
    }

    if (isset($layout['hash']) && $reader->getLayoutHash() != $layout['hash']) {
        LogLine("Warning: Expected $name hash " . str_pad(dechex($layout['hash']), 8, '0', STR_PAD_LEFT) . " but found " . str_pad(dechex($reader->getLayoutHash()), 8, '0', STR_PAD_LEFT));
        //exit(1);
    }

    if (isset($layout['names'])) {
        $reader->setFieldNames($layout['names']);
    }

    if (isset($layout['signed'])) {
        $reader->setFieldsSigned($layout['signed']);
    }

    return $reader;
}


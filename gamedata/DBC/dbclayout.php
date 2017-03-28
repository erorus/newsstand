<?php
require_once __DIR__ . '/../../incl/incl.php';
require_once __DIR__ . '/dbc.incl.php';

use \Erorus\DB2\Reader;

define('MAX_ROWS_CHECKED', 1000);

error_reporting(E_ALL);
ini_set('memory_limit', '384M');

$newLayout = [];
$files = array_keys($dbLayout);
$failed = false;
foreach ($files as $filenm) {
    foreach (['old','current'] as $d) {
        if (!file_exists(__DIR__ . "/$d/enUS/$filenm.db2")) {
            LogLine(sprintf('Could not find %s %s', $d, $filenm));
            $failed = true;
        }
    }
}
if ($failed) {
    LogLine("Quitting.\n ");
    exit(1);
}
foreach ($files as $filenm) {
    if (false === ($newLayout[$filenm] = CheckLayout($filenm))) {
        $newLayout = [];
        break;
    }
}

if ($newLayout) {
    LogLine("Done.\n ");
    echo json_encode($newLayout, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_FORCE_OBJECT);
}

function CheckLayout($filenm) {
    global $dbLayout;

    LogLine(sprintf('Loading %s (old)', $filenm));
    $oldReader = new Reader(__DIR__.'/old/enUS/'.$filenm.'.db2',
        isset($dbLayout[$filenm]['strings']) ? $dbLayout[$filenm]['strings'] : null);

    $fieldsToCheck = array_keys($dbLayout[$filenm]['names']);
    $vals = [];
    $keys = $oldReader->getIds();
    shuffle($keys);
    for ($x = 0; $x < count($keys) && $x < MAX_ROWS_CHECKED; $x++) {
        $rec = $oldReader->getRecord($keys[$x]);

        foreach ($fieldsToCheck as $field) {
            $vals[$field][$keys[$x]] = ValueHash($rec[$field]);
        }
    }

    unset($oldReader);

    LogLine(sprintf('Loading %s (current)', $filenm));
    $newReader = new Reader(__DIR__.'/current/enUS/'.$filenm.'.db2');

    $map = [];
    $newFieldCount = $newReader->getFieldCount();
    foreach ($vals as $oldCol => $pairs) {
        $matchedCols = [];
        foreach ($pairs as $id => $oldVal) {
            $rec = $newReader->getRecord($id);
            if (is_null($rec)) {
                continue;
            }
            for ($x = 0; $x < $newFieldCount; $x++) {
                $newVal = ValueHash($rec[$x]);
                if ($newVal === $oldVal) {
                    if (!isset($matchedCols[$x])) {
                        $matchedCols[$x] = 0;
                    }
                    $matchedCols[$x]++;
                }
            }
        }
        if (count($matchedCols) == 0) {
            LogLine(sprintf('--- ERROR: Could not find match for column %d in %s', $oldCol, $filenm));
            $map = false;
            break;
        }
        arsort($matchedCols);
        reset($matchedCols);
        $bestScore = current($matchedCols);
        if (isset($matchedCols[$oldCol]) && $matchedCols[$oldCol] == $bestScore) {
            $map[$oldCol] = $oldCol;
            continue;
        }
        foreach ($matchedCols as $newCol => $score) {
            if (!in_array($newCol, $map)) {
                if ($score != $bestScore) {
                    LogLine(sprintf('--- WARNING: column %d mapping to %d with score of %d (best was %d)', $oldCol, $newCol, $score, $bestScore));
                }
                $map[$oldCol] = $newCol;
                break;
            }
        }
        if (!isset($map[$oldCol])) {
            LogLine(sprintf('--- ERROR: Could not find an unused column for %d in %s (matched %s)', $oldCol, $filenm, implode(',', array_keys($matchedCols))));
            $map = false;
            break;
        }
    }
    if (!$map) {
        return false;
    }

    asort($map);

    $result = [];
    $result['hash'] = $newReader->getLayoutHash();
    foreach ($map as $oldCol => $newCol) {
        foreach (['names','signed'] as $param) {
            if (isset($dbLayout[$filenm][$param][$oldCol])) {
                $result[$param][$newCol] = $dbLayout[$filenm][$param][$oldCol];
            }
        }
    }
    if (isset($dbLayout[$filenm]['strings'])) {
        $types = $newReader->getFieldTypes(false);
        foreach ($types as $field => $type) {
            if ($type == Reader::FIELD_TYPE_STRING) {
                $result['strings'][] = $field;
            }
        }
    }
    ksort($result);
    return $result;
}

function ValueHash($v) {
    if (is_array($v)) {
        $v = json_encode($v);
    }
    if (is_string($v)) {
        $v = md5($v, true);
    }
    return $v;
}
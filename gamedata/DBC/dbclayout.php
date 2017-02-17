<?php
require_once __DIR__ . '/../../incl/incl.php';
require_once __DIR__ . '/dbc.incl.php';

define('DIFFERENT_VALUES', 5);
define('MAX_ROWS_CHECKED', 100);

error_reporting(E_ALL);
ini_set('memory_limit', '384M');

$newLayout = [];
$files = array_keys($dbLayout);
$failed = false;
foreach ($files as $filenm) {
    foreach (['current','new'] as $d) {
        if (!file_exists(__DIR__ . "/$d/enUS/$filenm.db2")) {
            LogLine(sprintf('Could not find %s %s', $d, $filenm));
            $failed = true;
        }
    }
}
if ($failed) {
    fwrite(STDERR, "\n");
    exit(1);
}
foreach ($files as $filenm) {
    if (false === ($newLayout[$filenm] = CheckLayout($filenm))) {
        $newLayout = [];
        break;
    }
}

if ($newLayout) {
    fwrite(STDERR, "\n");
    echo json_encode($newLayout, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_FORCE_OBJECT);
}

function CheckLayout($filenm) {
    global $dirnm, $dbLayout;

    LogLine(sprintf('Loading %s (current)', $filenm));
    $dirnm = __DIR__.'/current/enUS';
    $currentReader = CreateDB2Reader($filenm, true);

    LogLine(sprintf('Loading %s (new)', $filenm));
    $dirnm = __DIR__.'/new/enUS';
    $newReader = CreateDB2Reader($filenm, true);

    $fieldsToCheck = array_keys($dbLayout[$filenm]['names']);
    if (isset($dbLayout[$filenm]['strings'])) {
        $fieldsToCheck = array_unique(array_merge($fieldsToCheck, $dbLayout[$filenm]['strings']));
    }
    $vals = [];
    $keys = $currentReader->getIds();
    shuffle($keys);
    for ($x = 0; $x < count($keys) && $x < MAX_ROWS_CHECKED; $x++) {
        $rec = $currentReader->getRecord($keys[$x]);

        foreach ($fieldsToCheck as $field) {
            $value = is_array($rec[$field]) ? json_encode($rec[$field]) : $rec[$field];
            if (!isset($vals[$field]) || !in_array($value, $vals[$field])) {
                $vals[$field][$keys[$x]] = $value;
            }
        }

        $needAnother = false;
        foreach ($vals as $field => $set) {
            if (count($set) < DIFFERENT_VALUES) {
                $needAnother = true;
                break;
            }
        }
        if (!$needAnother) {
            break;
        }
    }

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
                $newVal = is_array($rec[$x]) ? json_encode($rec[$x]) : $rec[$x];
                if ($newVal == $oldVal) {
                    if (!isset($matchedCols[$x])) {
                        $matchedCols[$x] = 0;
                    }
                    $matchedCols[$x]++;
                }
            }
        }
        if (count($matchedCols) == 0) {
            LogLine(sprintf('Could not find match for column %d in %s', $oldCol, $filenm));
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
                    LogLine(sprintf('Warning: column %d mapping to %d with score of %d (best was %d)', $oldCol, $newCol, $score, $bestScore));
                }
                $map[$oldCol] = $newCol;
                break;
            }
        }
        if (!isset($map[$oldCol])) {
            LogLine(sprintf('Could not find an unused column for %d in %s (matched %s)', $oldCol, $filenm, implode(',', array_keys($matchedCols))));
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
        if (isset($dbLayout[$filenm]['strings']) && in_array($oldCol, $dbLayout[$filenm]['strings'])) {
            $result['strings'][] = $newCol;
        }
    }
    ksort($result);
    return $result;
}
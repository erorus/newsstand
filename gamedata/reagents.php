<?php
require_once('../incl/incl.php');

DBConnect();

if (!file_exists(__DIR__.'/reagents.cache')) {
    mkdir(__DIR__.'/reagents.cache');
}

$spells = GetTradeSpells();
UpdateTradeSpells($spells);

function GetTradeSpells() {
    global $db;

    $stmt = $db->prepare('SELECT id, skillline, qtymade, crafteditem FROM `tblDBCSpell` WHERE crafteditem is not null');
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = DBMapArray($result);
    $stmt->close();

    return $rows;
}

function UpdateTradeSpells($spells) {
    global $db;
    $total = count($spells);
    $sofar = 0;
    foreach ($spells as $spellId => $spellRow) {
        DebugMessage('Updating spell '.$spellId.' ('.(++$sofar).' of '.$total.')');

        $reagents = GetReagents($spellId);

        if ($reagents === false) {
            continue;
        }

        $db->begin_transaction();

        $queryOk = $db->query(sprintf('delete from tblDBCItemReagents where spell=%d', $spellId));
        if ($queryOk && count($reagents)) {
            $sql = 'insert into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values ';
            $c = 0;
            foreach ($reagents as $itemId => $qty) {
                $sql .= ($c++ > 0 ? ',' : '') . sprintf('(%d,%d,%d,%F,%d,1)', $spellRow['crafteditem'], $spellRow['skillline'], $itemId, $qty/$spellRow['qtymade'], $spellId);
            }
            $queryOk = $db->query($sql);
        }

        if ($queryOk) {
            $db->commit();
        } else {
            DebugMessage('Error updating reagents for spell '.$spellId);
            $db->rollback();
        }
    }
}

function GetReagents($spell) {
    $cacheFile = __DIR__.'/reagents.cache/'.$spell.'.json';
    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $power = FetchHTTP(sprintf('http://www.wowhead.com/spell=%d&power', $spell));
    if (!$power) {
        DebugMessage("Spell $spell could not be fetched from Wowhead.", E_USER_NOTICE);
        return false;
    }

    $reagents = [];
    $c = preg_match('/Reagents:<br[^>]*><div\b[^>]*>([\w\W]+?)<\/div>/', $power, $res);
    if ($c > 0) {
        $itemsHtml = $res[1];

        $c = preg_match_all('/<a href="\/item=(\d+)">[^<]*<\/a>(?:&nbsp;\((\d+)\))?/', $itemsHtml, $res);
        for ($x = 0; $x < $c; $x++) {
            $itemId = $res[1][$x];
            $qty = intval($res[2][$x]);
            if (!$qty) {
                $qty = 1;
            }
            if (!isset($reagents[$itemId])) {
                $reagents[$itemId] = 0;
            }
            $reagents[$itemId] += $qty;
        }
    } else {
        DebugMessage("Spell $spell has no reagents.", E_USER_NOTICE);
    }

    file_put_contents($cacheFile, json_encode($reagents, JSON_NUMERIC_CHECK));

    return $reagents;
}
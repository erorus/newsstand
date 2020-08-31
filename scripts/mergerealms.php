<?php

/**
 * Updates realm canonicals and houses to match blizz connections. Run after realmupdates.php with supervision.
 *
 * Add a command line param to save changes.
 */

require_once __DIR__ . '/../incl/incl.php';
require_once __DIR__ . '/../incl/memcache.incl.php';

$saveChanges = isset($argv[1]);

echo $saveChanges ? "SAVING CHANGES\n" : "DRY RUN\n";

$db = DBConnect();
$sql = <<<EOF
SELECT id, region, slug, house, canonical, blizzId, blizzConnection
FROM tblRealm
WHERE blizzConnection IS NOT NULL
EOF;

$stmt = $db->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$byConnection = DBMapArray($result, ['blizzConnection', false]);
$stmt->close();

$houseMoves = [];

foreach ($byConnection as $realms) {
    // They all share the same connection. Pick the first.
    $connectionId = $realms[0]['blizzConnection'];

    // Sort by slug, so we can fall back to the alphabetical slug if we don't find a matching blizz ID.
    usort($realms, function ($a, $b) {
        return strcmp($a['slug'], $b['slug']);
    });
    $canonicalSlug = $realms[0]['slug'];
    $canonicalHouse = $realms[0]['house'];

    // We want our canonical to be the realm whose blizzId matches the blizzConnection, if it exists.
    foreach ($realms as $realm) {
        if ($realm['blizzId'] === $connectionId) {
            $canonicalSlug = $realm['slug'];
            $canonicalHouse = $realm['house'];
        }
    }

    // Lock all the houses.
    $locked = [];
    foreach ($realms as $realm) {
        $house = $realm['house'];
        if (isset($locked[$house])) {
            continue;
        }
        if (!MCHouseLock($house)) {
            echo "--- Could not lock house {$house}!\n";
            foreach (array_keys($locked) as $lockedHouse) {
                MCHouseUnlock($lockedHouse);
            }
            continue 2;
        }
        $locked[$house] = true;
    }

    if ($saveChanges) {
        $db->begin_transaction();
    }

    // Loop through all realms, make sure their cols are set correctly.
    foreach ($realms as $realm) {
        $realmUpdates = [];
        if ($realm['slug'] === $canonicalSlug) {
            // This is the canonical realm.
            if ($realm['canonical'] !== $canonicalSlug) {
                // But it doesn't know it yet.
                $realmUpdates['canonical'] = $canonicalSlug;
            }
        } else {
            // This not the canonical realm.
            if (!is_null($realm['canonical'])) {
                // But it thinks it is.
                $realmUpdates['canonical'] = null;
            }
        }

        if ($realm['house'] !== $canonicalHouse) {
            // This realm has a different house than its canonical parent for this connection.

            // Remember which houses we moved for later.
            $houseMoves[$realm['house']] = $canonicalHouse;

            $realmUpdates['house'] = $canonicalHouse;
        }

        if ($realmUpdates) {
            $message = "{$realm['region']} {$connectionId} {$realm['slug']} -";
            if (array_key_exists('canonical', $realmUpdates)) {
                $message .= sprintf(
                    ' Canonical: %s->%s',
                    $realm['canonical'] ?? 'NULL',
                    $realmUpdates['canonical'] ?? 'NULL'
                );
            }
            if (isset($realmUpdates['house'])) {
                $message .= sprintf(' House: %d->%d', $realm['house'], $realmUpdates['house']);
            }
            echo "{$message}\n";

            if ($saveChanges) {
                // do updates
                if (array_key_exists('canonical', $realmUpdates)) {
                    $sql = 'UPDATE tblRealm SET canonical = ? WHERE id = ?';
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('si', $realmUpdates['canonical'], $realm['id']);
                    $stmt->execute();
                    $stmt->close();
                }

                if (isset($realmUpdates['house'])) {
                    $sql = 'UPDATE tblRealm SET house = ? WHERE id = ?';
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('ii', $realmUpdates['house'], $realm['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    if ($saveChanges) {
        $db->commit();
        MCDelete('realms_' . $realm['region']);
    }

    foreach (array_keys($locked) as $lockedHouse) {
        MCHouseUnlock($lockedHouse);
    }
}

if ($saveChanges) {
    $db->begin_transaction();
}

foreach ($houseMoves as $fromHouse => $toHouse) {
    echo sprintf("Moving house %d to %d\n", $fromHouse, $toHouse);

    if (!MCHouseLock($fromHouse)) {
        echo "--- Could not lock house {$fromHouse}!\n";
        continue;
    }
    if (!MCHouseLock($toHouse)) {
        echo "--- Could not lock house {$fromHouse}!\n";
        continue;
    }

    if ($saveChanges) {
        // do updates
        $sql = 'UPDATE tblUserWatch SET house = ? WHERE house = ?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $toHouse, $fromHouse);
        $stmt->execute();
        $stmt->close();
        echo sprintf("  %d tblUserWatch rows updated.\n", $db->affected_rows);

        $sql = 'UPDATE tblUserRare SET house = ? WHERE house = ?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $toHouse, $fromHouse);
        $stmt->execute();
        $stmt->close();
        echo sprintf("  %d tblUserRare rows updated.\n", $db->affected_rows);

        $sql = 'DELETE FROM tblUserRareReport WHERE house = ?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $fromHouse);
        $stmt->execute();
        $stmt->close();
        echo sprintf("  %d tblUserRareReport rows deleted.\n", $db->affected_rows);
    }

    MCHouseUnlock($toHouse);
    MCHouseUnlock($fromHouse);
}

if ($saveChanges) {
    $db->commit();
    echo "Restart all fetchers and parsers.\n";
}

<?php

    require_once __DIR__ . '/../../incl/incl.php';
    require_once __DIR__ . '/../../incl/memcache.incl.php';

    function GetUSRealms() {
        $cacheKey = 'extra:multirealm:realms2';

        $realms = MCGet($cacheKey);
        if ($realms !== false) {
            return $realms;
        }

        $sql = 'select id, slug, name, house, canonical from tblRealm where region = \'US\' and locale is not null';
        $db = DBConnect();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $realmList = DBMapArray($result);
        $stmt->close();

        uasort($realmList, function($a,$b){
            return strcmp($a['name'], $b['name']);
        });

        $realms = [
            'id' => $realmList,
            'house' => [],
        ];

        foreach ($realmList as $id => $row) {
            if (!is_null($row['canonical'])) {
                $realms['house'][$row['house']] = $id;
            }
        }

        MCSet($cacheKey, $realms, 86400);

        return $realms;
    }

    function GetSellerList() {
        $cacheKey = 'extra:multirealm:sellers3';

        $sellers = MCGet($cacheKey);
        if ($sellers !== false) {
            return $sellers;
        }

        $sellers = [];

        $sql = <<<'EOF'
        select z2.cnt, s.name sellername, r.name realmname, s.firstseen, s.lastseen, r.house, r.slug
        from (
            select seller, count(distinct `snapshot`) cnt
            from (
                SELECT seller, item, `snapshot`
                FROM `tblSellerItemHistory` 
                where item in (128159, 127736, 127738, 127732, 127731, 127737, 127735, 127730, 127734, 127733, 127718, 128158, 127720, 127714, 127713, 127719, 127717, 127712, 127716, 127715)
            ) z1
            group by seller
            having count(distinct item) = 2
        ) z2
        left join tblSellerItemHistory h on h.seller = z2.seller and h.item not in (128159, 127736, 127738, 127732, 127731, 127737, 127735, 127730, 127734, 127733, 127718, 128158, 127720, 127714, 127713, 127719, 127717, 127712, 127716, 127715)
        join tblSeller s on s.id = z2.seller
        join tblRealm r on s.realm = r.id
        where h.seller is null
        and r.region = 'US'
        and s.firstseen > '2016-04-01'
        and s.lastseen > timestampadd(hour, -36, now())
        order by r.house, s.firstseen, z2.cnt desc, s.lastseen desc
EOF;

        $db = DBConnect();
        $db->query('set transaction isolation level read uncommitted, read only');
        $db->begin_transaction();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sellers[$row['house']][] = $row;
        }
        $result->close();
        $stmt->close();
        $db->commit(); // end transaction

        MCSet($cacheKey, $sellers);

        return $sellers;
    }

    function GenerateList() {
        $realms = GetUSRealms();
        $sellers = GetSellerList();

        foreach ($realms['id'] as $id => $row) {
            $canonicalId = isset($realms['house'][$row['house']]) ? $realms['house'][$row['house']] : $id;
            echo '<a name="', $row['slug'], '"></a><h3>', $row['name'], '</h3>';
            if ($canonicalId != $id) {
                echo '<i>See <a href="#', $realms['id'][$canonicalId]['slug'], '">', $realms['id'][$canonicalId]['name'], '</a></i><br>';
            } else {
                if (!isset($sellers[$row['house']])) {
                    echo 'None detected!<br>';
                } else {
                    echo '<table><tr><th>Seller</th><th>First Seen</th><th>Last Seen</th><th>Confidence</th></tr>';
                    foreach ($sellers[$row['house']] as $sellerRow) {
                        echo '<tr><td><a href="/#us/', $sellerRow['slug'], '/seller/', mb_strtolower($sellerRow['sellername']), '">';
                        echo $sellerRow['sellername'], '-', $sellerRow['realmname'], '</a></td>';
                        echo '<td>', FormatDate($sellerRow['firstseen']), '</td>';
                        echo '<td>', FormatDate($sellerRow['lastseen']), '</td>';
                        echo '<td>';
                        if ($sellerRow['cnt'] < 3) {
                            echo 'Low';
                        } elseif ($sellerRow['cnt'] < 8) {
                            echo 'Medium';
                        } else {
                            echo 'High';
                        }
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }
            echo '<br>';
        }
    }

    function FormatDate($dt) {
        $dt = strtotime($dt);
        if ($dt < strtotime('Jan 1 2016')) {
            return date('M j, Y', $dt);
        }
        if ($dt > (time() - 86400)) {
            $hours = round((time() - $dt) / 3600);
            return "$hours hour".($hours == 1 ? '' : 's')." ago";
        }
        return date('M j', $dt);
    }

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=560">
    <title>Extra: The Undermine Journal</title>

    <link rel="stylesheet" href="https://theunderminejournal.com/css/wowhead/basic.css?1">
    <script type="text/javascript" src="https://theunderminejournal.com/js/wowhead/power.js?2"></script>
    <script type="text/javascript" src="https://theunderminejournal.com/js/wowhead/basic.js"></script>

    <style type="text/css">
        body{min-width:40em;font-size:14px;color:#333;margin:0;font-family:sans-serif}
        .main {padding: 2em}
        .story {line-height:1.6;max-width: 45em;font-size:16px;text-align:justify}
        h1,h2,h3{line-height:1.2;margin-top:0;margin-bottom:0.75em}
        h1{font-size: 150%}
        table { width: 100%; border-spacing: 0 }
        table th {padding: 0.4em 0.2em 0; border-bottom: 1px solid #CCC; font-weight: normal}
        table td {padding: 0.2em 0.4em}
        tr:hover td {background-color: #EEE}
    </style>
</head>
<body>
<div style="margin: 2em">
    <a href="/"><img src="../images/underminetitle.2000.trans.png" style="border: 0; background-image: url('../images/underminetitle.2000.png'); background-size: contain; background-repeat: no-repeat; max-width: 100%; max-height: 4em"></a>
    <div style="font-size: 2em; padding-left: 1em">Extra: Multirealm Corporation</div>
</div>
<div class="main">
    <div class="story">
        <h1>Multirealm Corporation Sells Expensive Trade Goods Across Most US Realms</h1>
        <i>Published April 30, 2016</i><br>

        <p>A single player, or a small group of players, employs hundreds of sellers for suspicious coordinated activity observed on most US realms.
            By what could be described as automated undercutting, auctions of expensive crafted trade goods
            with prices reaching 5,000g to 15,000g are repeatedly posted up to twice per hour per seller by this group.</p>

        <p>The typical behavior of these level 1 sellers include using a standard randomly-generated name to <b>post one each of two expensive trade items, and nothing else</b>. When undercut, the seller will cancel and repost affected auctions, sometimes within one half hour.</p>

        <p>Sellers of this corporation have been observed to sell the following pairs of items:
        <ul>
            <li><a href="http://www.wowhead.com/item=127719">Advanced Muzzlesprocket</a> + <a href="http://www.wowhead.com/item=127713">Mighty Steelforged Essence</a></li>
            <li><a href="http://www.wowhead.com/item=127720">Bi-Directional Fizzle Reducer</a> + <a href="http://www.wowhead.com/item=127714">Mighty Truesteel Essence</a></li>
            <li><a href="http://www.wowhead.com/item=128159">Elemental Distillate</a> + <a href="http://www.wowhead.com/item=127736">Savage Ensorcelled Tarot</a></li>
            <li><a href="http://www.wowhead.com/item=127738">Infrablue-Blocker Lenses</a> + <a href="http://www.wowhead.com/item=127732">Savage Truesteel Essence</a></li>
            <li><a href="http://www.wowhead.com/item=127712">Mighty Burnished Essence</a> + <a href="http://www.wowhead.com/item=127717">Mighty Weapon Crystal</a></li>
            <li><a href="http://www.wowhead.com/item=127718">Mighty Ensorcelled Tarot</a> + <a href="http://www.wowhead.com/item=128158">Wildswater</a></li>
            <li><a href="http://www.wowhead.com/item=127715">Mighty Hexweave Essence</a> + <a href="http://www.wowhead.com/item=127716">Mighty Taladite Amplifier</a></li>
            <li><a href="http://www.wowhead.com/item=127730">Savage Burnished Essence</a> + <a href="http://www.wowhead.com/item=127735">Savage Weapon Crystal</a></li>
            <li><a href="http://www.wowhead.com/item=127733">Savage Hexweave Essence</a> + <a href="http://www.wowhead.com/item=127734">Savage Taladite Amplifier</a></li>
            <li><a href="http://www.wowhead.com/item=127731">Savage Steelforged Essence</a> + <a href="http://www.wowhead.com/item=127737">Taladite Firing Pin</a></li>
        </ul></p>

        <p>We credit <a href="https://www.reddit.com/r/woweconomy/comments/4fd5l6/notice_the_multiserver_bot_army_has_now_moved_to/"><b>Sweetblingz</b> on Reddit</a> for first calling our attention to this phenomenon. Visit that Reddit thread for more information and discussion.</p>

        <h2>List of Sellers</h2>
        <p>Here is a list of sellers we suspect may be acting on behalf of this corporation. If you sell the items listed above, be aware of these sellers, and others like them, on your realm.</p>

        <?php GenerateList(); ?>
    </div>
</div>
</body></html>

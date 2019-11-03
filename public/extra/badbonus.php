<?php

    require_once __DIR__ . '/../../incl/incl.php';
    require_once __DIR__ . '/../../incl/memcache.incl.php';

    function GetExtraData() {
        $cacheKey = 'extra:badbonus';

        $data = MCGet($cacheKey);
        if ($data !== false) {
            return $data;
        }

        $data = [
            'now' => time(),
            'rows' => [],
        ];

        $sql = <<<'EOF'
SELECT a.id auctionid, a.timeleft, concat_ws(' ', i.name_enus, re.name_enus,
(select group_concat(ind.desc_enus separator ' ')
 from tblDBCItemNameDescription ind
 join tblDBCItemBonus ib on ib.nameid=ind.id
 where ib.id in (abb.bonus1, abb.bonus2, abb.bonus3, abb.bonus4, abb.bonus5, abb.bonus6))
                ) itemname,
i.id itemid, ae.rand, ae.seed, 
concat_ws(':', abb.bonus1, abb.bonus2, abb.bonus3, abb.bonus4, abb.bonus5, abb.bonus6) bonuses,
a.bid, a.buy, r.region, r.name realmname, if(s.lastseen > timestampadd(day, -30, now()), s.name, '???') sellername, ibs.observed,
abb.firstseen, abb.lastseen
FROM tblAuction a
join tblAuctionBadBonus abb on abb.house = a.house and abb.id = a.id
left join tblAuctionExtra ae on ae.house = a.house and ae.id = a.id
join tblDBCItem i on a.item = i.id
join tblSeller s on s.id = a.seller
join tblRealm r on s.realm = r.id
join tblItemBonusesSeen ibs on ibs.item = a.item and ibs.bonus1=0
left join tblDBCRandEnchants re on ae.rand = re.id
order by r.region, r.name, i.name_enus, a.id
EOF;

        $db = DBConnect();
        $db->query('set transaction isolation level read uncommitted, read only');
        $db->begin_transaction();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data['rows'][] = $row;
        }
        $result->close();
        $stmt->close();
        $db->commit(); // end transaction

        MCSet($cacheKey, $data, 10*60);

        return $data;
    }

    function GenerateList() {
        $data = GetExtraData();

        echo sprintf('Updated <b>%s</b> (%s)<br><br>', TimeDiff($data['now']), date('Y-m-d H:i:s', $data['now']));

        echo '<table>';
        echo <<<'EOF'
<tr>
    <th>Realm</th>
    <th class="r">Auc ID</th>
    <th>Item</th>
    <th>Bonuses</th>
    <th>Seller</th>
    <th class="r">Bid</th>
    <th class="r">Buy</th>
    <th>First Seen</th>
    <th>Last Seen</th>
</tr>
EOF;

        $rowFormat = <<<'EOF'
<tr>
    <td><span class="mobile">Realm: </span>%s</td>
    <td class="r"><span class="mobile">Auc ID: </span>%s</td>
    <td><span class="mobile">Item: </span>%s</td>
    <td><span class="mobile">Bonuses: </span>%s</td>
    <td><span class="mobile">Seller: </span>%s</td>
    <td class="r"><span class="mobile">Bid: </span>%s</td>
    <td class="r"><span class="mobile">Buy: </span>%s</td>
    <td><span class="mobile">First: </span>%s</td>
    <td><span class="mobile">Last: </span>%s</td>
</tr>
EOF;

        foreach ($data['rows'] as $row) {
            echo sprintf($rowFormat,
                $row['region'] . '&nbsp;' . $row['realmname'],
                $row['auctionid'],
                sprintf('<a href="http://www.wowhead.com/item=%d?bonus=%s%s">%s</a>',
                    $row['itemid'], $row['bonuses'],
                    !is_null($row['rand']) ? '&rand=' . $row['rand'] : '',
                    htmlspecialchars($row['itemname'])
                    ),
                $row['bonuses'],
                htmlspecialchars($row['sellername']),
                gsc($row['bid']),
                gsc($row['buy']),
                is_null($row['firstseen']) ? '' : FormatDate($row['firstseen']),
                is_null($row['lastseen']) ? '' : FormatDate($row['lastseen'])
                );
        }

        echo '</table>';
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

    function gsc($c) {
        $gold = floor($c / 10000);
        $c -= $gold * 10000;
        $silver = floor($c / 100);
        $c -= $silver * 100;

        return sprintf('%sg&nbsp;%ss&nbsp;%sc',
            number_format($gold),
            str_pad($silver, 2, '0', STR_PAD_LEFT),
            str_pad($c, 2, '0', STR_PAD_LEFT));
    }

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=560">
    <title>Extra: The Undermine Journal</title>

    <script src="https://wow.zamimg.com/widgets/power.js"></script>

    <style type="text/css">
        body{min-width:40em;font-size:14px;color:#333;margin:0;font-family:sans-serif}
        .main {padding: 2em}
        .story {line-height:1.6;max-width: 45em;font-size:16px;text-align:justify}
        h1,h2,h3{line-height:1.2;margin-top:0;margin-bottom:0.75em}
        h1{font-size: 150%}
        table { border-spacing: 0; font-size: 75% }
        table th {padding: 0.4em 0.2em 0; border-bottom: 1px solid #CCC; text-align: left}
        table td {padding: 0.2em 0.4em; vertical-align: top}
        td.r, th.r { text-align: right }
        tr:hover td {background-color: #EEE}

        .mobile { display: none }

        @media all and (max-width: 800px) {
            span.mobile { display: inline; font-weight: bold }
            .maintable th { display: none }
            .maintable td { display: block; padding: 2px 0 }
            .maintable td.r { text-align: left }
            .maintable tr { display: block; margin: 15px 0 }
            .maintable table { display: block; font-size: 100% }
        }

    </style>
</head>
<body>
<div style="margin: 2em">
    <a href="/"><img src="../images/underminetitle.2000.trans.png" style="border: 0; background-image: url('../images/underminetitle.2000.png'); background-size: contain; background-repeat: no-repeat; max-width: 100%; max-height: 4em"></a>
    <div style="font-size: 2em; padding-left: 1em">Extra: Bad Bonus Data</div>
</div>
<div class="main">
    <div class="story">
        <h1>Extra: Bad Bonus Data in Battle.net API, Armory</h1>
        <i>Published March 18, 2017</i><br>

        <p>Some auctions are shown with incorrect bonuses in the Battle.net API data, the armory auction house, and probably also the mobile auction house.</p>

        <p>Below is a listing of some current auctions we observed with bad bonuses. These auctions show items with titles, tags, or levels which do not appear in-game.</p>
    </div>

    <div class="maintable">
        <?php GenerateList(); ?>
    </div>
</div>
</body></html>

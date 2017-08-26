<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/subscription.incl.php');

RunMeNTimes(1);
CatchKill();

ini_set('memory_limit', '128M');

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not reporting watches!', E_USER_NOTICE);
    exit;
}

$itemClassOrder = [2, 9, 6, 4, 7, 3, 14, 1, 15, 8, 16, 10, 12, 13, 17, 18, 5, 11];
$itemClassOrderSql = '';
foreach ($itemClassOrder as $idx => $classId) {
    $itemClassOrderSql .= "when $classId then $idx ";
}

$stmt = $db->prepare('SELECT house, group_concat(concat_ws(\' \', region, name) order by 1 separator \', \') names, min(region) region, min(slug) slug from tblRealm where locale is not null group by house');
$stmt->execute();
$result = $stmt->get_result();
$houseNameCache = DBMapArray($result);
$stmt->close();

$loopStart = time();
$toSleep = 0;
while ((!CatchKill()) && (time() < ($loopStart + 60 * 30 - 25))) {
    heartbeat();
    sleep(min($toSleep, 20));
    if (CatchKill() || APIMaintenance()) {
        break;
    }
    ob_start();
    $toSleep = CheckNextUser();
    ob_end_flush();
    if ($toSleep === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function CheckNextUser()
{
    $db = DBConnect();

    $sql = <<<'EOF'
select *
from tblUser u
where watchesobserved > ifnull(watchesreported, '2000-01-01')
and timestampadd(minute, greatest(if(paiduntil is null or paiduntil < now(), ?, ?), watchperiod), ifnull(watchesreported, '2000-01-01')) < now()
order by ifnull(watchesreported, '2000-01-01'), watchesobserved
limit 1
for update
EOF;

    $db->begin_transaction();

    $stmt = $db->prepare($sql);
    $freeFreq = SUBSCRIPTION_WATCH_MIN_PERIOD_FREE;
    $paidFreq = SUBSCRIPTION_WATCH_MIN_PERIOD;
    $stmt->bind_param('ii', $freeFreq, $paidFreq);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $result->close();
    $stmt->close();

    if (is_null($row)) {
        $db->rollback();
        return 10;
    }

    $stmt = $db->prepare('update tblUser set watchesreported = ? where id = ?');
    $userId = $row['id'];
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param('si', $now, $userId);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    $expectedReport = strtotime($row['watchesobserved']);
    if (!is_null($row['watchesreported'])) {
        $expectedReport = max($expectedReport, max((is_null($row['paiduntil']) || strtotime($row['paiduntil']) < time()) ? SUBSCRIPTION_WATCH_MIN_PERIOD_FREE : SUBSCRIPTION_WATCH_MIN_PERIOD, $row['watchperiod']) * 60 + strtotime($row['watchesreported']));
    }
    DebugMessage("User " . str_pad($row['id'], 7, ' ', STR_PAD_LEFT) . " (" . $row['name'] . ') checking for new watches/rares, overdue by ' . TimeDiff(
            $expectedReport, array(
                'parts'     => 2,
                'precision' => 'second'
            )
        )
    );

    $subjects = [];
    $messages = [];
    $houseSubjects = [];
    $ret = ReportUserWatches($now, $row);
    if ($ret !== false) {
        $subjects[] = $ret[0];
        $messages[] = $ret[1];
        if ($ret[2]) {
            $houseSubjects[] = $ret[2];
        }
    }
    $ret = ReportUserRares($now, $row);
    if ($ret !== false) {
        $subjects[] = $ret[0];
        $messages[] = $ret[1];
        if ($ret[2]) {
            $houseSubjects[] = $ret[2];
        }
    }

    if (!count($messages)) {
        return 0;
    }

    $locale = $row['locale'];
    $LANG = GetLang($locale);

    $message = $row['name'].',<br>' . implode('<hr>', $messages) . '<br><hr>' . $LANG['notificationsMessage'] . '<br><br>';
    $subject = implode(', ', $subjects);
    if (count($houseSubjects) == count($subjects)) {
        $houseSubjects = array_unique($houseSubjects);
        if (count($houseSubjects) == 1) {
            $subject .= ' - ' . $houseSubjects[0];
        }
    }

    if (is_null($row['paiduntil']) || (strtotime($row['paiduntil']) < time())) {
        $message .= $LANG['freeSubscriptionAccount'];
        $hoursNext = round((max(intval($row['watchperiod'],10), SUBSCRIPTION_WATCH_MIN_PERIOD_FREE)+5)/60, 1);
    } else {
        $message .= sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['paidExpires']), date('Y-m-d H:i:s e', strtotime($row['paiduntil'])));
        $hoursNext = round((max(intval($row['watchperiod'],10), SUBSCRIPTION_WATCH_MIN_PERIOD)+5)/60, 1);
    }

    if ($hoursNext > 0.3) {
        $hoursNext = sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['timeFuture']), $hoursNext . ' ' . ($hoursNext == 1 ? $LANG['timeHour'] : $LANG['timeHours']));
        $message .= ' ' . sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['notificationPeriodNext']), $hoursNext);
    }

    SendUserMessage($userId, 'marketnotification', $subject, $message);

    return 0;
}

function ReportUserWatches($now, $userRow)
{
    global $houseNameCache, $itemClassOrderSql;

    $locale = $userRow['locale'];
    $LANG = GetLang($locale);

    $message = '';

    $db = DBConnect();

    $sql = <<<'EOF'
select 0 ispet, uw.seq, uw.region, uw.house,
    uw.item, uw.bonusset, ifnull(GROUP_CONCAT(distinct bs.`tagid` ORDER BY 1 SEPARATOR '.'), '') tagurl,
    i.name_%1$s name,
    ifnull(group_concat(distinct ind.`desc_%1$s` separator ' '),'') bonustag,
    case i.class %2$s else 999 end classorder,
    uw.direction, uw.quantity, uw.price, uw.currently
from tblUserWatch uw
join tblDBCItem i on uw.item = i.id
left join tblBonusSet bs on uw.bonusset = bs.`set`
left join tblDBCItemNameDescription ind on ind.id = bs.tagid
where uw.user = ?
and uw.deleted is null
and uw.observed > ifnull(uw.reported, '2000-01-01')
group by uw.seq
union
select 1 ispet, uw.seq, uw.region, uw.house,
    uw.species, null breed, '' breedurl,
    p.name_%1$s name,
    null bonustag,
    p.type classorder,
    uw.direction, uw.quantity, uw.price, uw.currently
from tblUserWatch uw
JOIN tblDBCPet p on uw.species=p.id
where uw.user = ?
and uw.deleted is null
and uw.observed > ifnull(uw.reported, '2000-01-01')
order by if(region is null, 0, 1), house, region, ispet, classorder, name, bonustag, seq
EOF;

    $sql = sprintf($sql, $locale, $itemClassOrderSql);

    $prevHouse = false;
    $houseCount = 0;
    $updateSeq = [];
    $lastItem = '';

    $userId = $userRow['id'];
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $updateSeq[] = $row['seq'];
        if ($prevHouse !== $row['house']) {
            $houseCount++;
            $prevHouse = $row['house'];
            $message .= '<br><b>' . $houseNameCache[$prevHouse]['names'] . '</b><br><br>';
        }

        $url = sprintf('https://theunderminejournal.com/#%s/%s/%s/%s%s',
            strtolower($houseNameCache[$prevHouse]['region']),
            $houseNameCache[$prevHouse]['slug'],
            $row['ispet'] ? 'battlepet' : 'item',
            $row['item'],
            $row['tagurl'] ? ('.' . $row['tagurl']) : ''
        );

        $bonusTag = $row['bonustag'];
        if ($bonusTag) {
            $bonusTag = ' ' . $bonusTag;
        }

        $lastItem = sprintf('[%s]%s', $row['name'], $bonusTag);
        $message .= sprintf('<a href="%s">[%s]%s</a>', $url, $row['name'], $bonusTag);

        $direction = $LANG[strtolower($row['direction'])];

        if (!is_null($row['price'])) {
            $value = FormatPrice($row['price'], $LANG);
            $currently = FormatPrice($row['currently'], $LANG);
            if (!is_null($row['quantity'])) {
                $condition = $LANG['priceToBuy'] . ' ' . $row['quantity'];
            } else {
                $condition = $LANG['marketPrice'];
            }
        } else {
            $value = $row['quantity'];
            $currently = $row['currently'];
            $condition = $LANG['availableQuantity'];
        }

        $message .= sprintf(' %s %s %s: %s <b>%s</b><br>', $condition, $direction, $value, $LANG['now'], $currently);
    }
    $result->close();
    $stmt->close();

    if (!count($updateSeq)) {
        return false;
    }

    if (count($updateSeq) == 1) {
        $subject = $lastItem;
    } else {
        $subject = '' . count($updateSeq) . ' ' . $LANG['marketNotifications'];
    }
    $houseSubject = '';
    if ($houseCount == 1) {
        $houseSubject = $houseNameCache[$prevHouse]['names'];
    }

    $sql = 'update tblUserWatch set reported = \'%s\' where user = %d and seq in (%s)';
    $chunks = array_chunk($updateSeq, 200);
    foreach ($chunks as $seqs) {
        DBQueryWithError($db, sprintf($sql, $now, $userId, implode(',', $seqs)));
    }

    return [$subject, $message, $houseSubject];
}

function ReportUserRares($now, $userRow)
{
    global $houseNameCache, $itemClassOrderSql;

    $locale = $userRow['locale'];
    $LANG = GetLang($locale);

    $message = '';

    $db = DBConnect();
    $db->begin_transaction();

    $sql = <<<'EOF'
select z.house, z.item, z.bonusset, max(z.snapshot) snapshot,
    ifnull(group_concat(distinct bs.`tagid` ORDER BY 1 SEPARATOR '.'), '') tagurl,
    z.name, z.class, z.level, z.quality,
    ifnull(group_concat(distinct ind.`desc_%1$s` separator ' '), '') bonustag,
    z.prevseen,
    z.price, z.median, z.mean, z.stddev, z.region,
    case z.class %2$s else 999 end classorder
from (
    SELECT rr.house, rr.item, rr.bonusset, rr.prevseen, rr.price, unix_timestamp(rr.snapshot) snapshot,
    i.name_%1$s name, i.class, i.level, i.quality,
    ig.median, ig.mean, ig.stddev, ig.region
    FROM tblUserRareReport rr
    join tblDBCItem i on i.id = rr.item
    join tblRealm r on r.house = rr.house and r.canonical is not null
    left join tblItemGlobal ig on ig.item = rr.item and ig.bonusset = rr.bonusset and ig.region = r.region 
    where rr.user = ?
    ) z
left join tblBonusSet bs on z.bonusset = bs.`set`
left join tblDBCItemNameDescription ind on bs.tagid = ind.id
group by z.house, z.item, z.bonusset
order by house, prevseen, classorder, name, bonustag;
EOF;

    $sql = sprintf($sql, $locale, $itemClassOrderSql);

    $prevHouse = false;
    $houseCount = 0;
    $rowCount = 0;
    $lastItem = '';
    $maxSnapshot = 0;

    $userId = $userRow['id'];
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rowCount++;
        $maxSnapshot = max($maxSnapshot, $row['snapshot']);
        if ($prevHouse !== $row['house']) {
            $houseCount++;
            $prevHouse = $row['house'];
            $message .= '<br><b>' . $houseNameCache[$prevHouse]['names'] . '</b><br><br>';
        }

        $url = sprintf('https://theunderminejournal.com/#%s/%s/%s/%s%s',
            strtolower($houseNameCache[$prevHouse]['region']),
            $houseNameCache[$prevHouse]['slug'],
            'item',
            $row['item'],
            $row['tagurl'] ? ('.' . $row['tagurl']) : ''
        );

        $bonusTag = $row['bonustag'];
        if ($bonusTag) {
            $bonusTag = ' ' . $bonusTag;
        }

        $lastItem = sprintf('[%s]%s', $row['name'], $bonusTag);
        $message .= sprintf('<a href="%s">[%s]%s</a>', $url, $row['name'], $bonusTag);
        $message .= sprintf(' (%s %d %s %s) - ',
            $LANG['level'], $row['level'],
            isset($LANG['qualities'][$row['quality']]) ? $LANG['qualities'][$row['quality']] : '',
            isset($LANG['itemClasses'][$row['class']]) ? $LANG['itemClasses'][$row['class']] : '');
        $message .= sprintf(' %s <b>%s</b>, %s <b>%s</b>.<br>',
            $LANG['lastSeen'],
            is_null($row['prevseen']) ? '?' : sprintf(str_replace('{1}', '%s', $LANG['timePast']), round((time() - strtotime($row['prevseen'])) / 86400) . ' ' . $LANG['timeDays']),
            $LANG['now'],
            FormatPrice($row['price'], $LANG));
        $message .= sprintf('%s %s: %s, %s: %s, %s: %s<br><br>',
            $row['region'], $LANG['medianPrice'], FormatPrice($row['median'], $LANG),
            $LANG['mean'], FormatPrice($row['mean'], $LANG),
            $LANG['standardDeviation'], FormatPrice($row['stddev'], $LANG)
            );
    }
    $result->close();
    $stmt->close();

    if (!$rowCount) {
        return false;
    }

    $maxSnapshot = date('Y-m-d H:i:s', $maxSnapshot);
    $stmt = $db->prepare('delete from tblUserRareReport where user = ? and snapshot <= ?');
    $stmt->bind_param('is', $userId, $maxSnapshot);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    if ($rowCount == 1) {
        $subject = $LANG['unusuals'] . ': ' . $lastItem;
    } else {
        $subject = '' . $rowCount . ' ' . $LANG['unusualItems'];
    }
    $houseSubject = '';
    if ($houseCount == 1) {
        $houseSubject = $houseNameCache[$prevHouse]['names'];
    }

    return [$subject, $message, $houseSubject];
}

function FormatPrice($amt, &$LANG) {
    $amt = round($amt);
    if ($amt >= 100) {// 1s
        $g = number_format($amt / 10000, $amt % 10000 == 0 ? 0 : 2, $LANG['decimalPoint'], $LANG['thousandsSep']);
        $v = '' . $g . $LANG['suffixGold'];
    } else {
        $c = $amt;
        $v = '' . $c . $LANG['suffixCopper'];
    }
    return $v;
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = $db->query($sql);
    if (!$queryOk) {
        DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}
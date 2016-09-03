<?php

require_once('incl.php');
require_once('memcache.incl.php');
require_once('subscription.credentials.php');

define('SUBSCRIPTION_LOGIN_COOKIE', 'session');
define('SUBSCRIPTION_CSRF_COOKIE', 'csrf');
define('SUBSCRIPTION_SESSION_LENGTH', 1209600); // 2 weeks

define('SUBSCRIPTION_TERMS_UPDATED', 1460851200); // timestamp of last material change to terms/policy
define('SUBSCRIPTION_NEW_USERS_PAID_UNTIL', 1462665600); // timestamp when new users are automatically considered paid until (May 8)

define('SUBSCRIPTION_PAID_ADDS_SECONDS', 5184000); // 60 days
define('SUBSCRIPTION_PAID_RENEW_WINDOW_DAYS', 10); // when subscription has this or fewer days remaining, allow them to renew 
define('SUBSCRIPTION_PAID_ACCEPT_PAYMENTS', true); // set to false to disable paypal
define('SUBSCRIPTION_PAID_ACCEPT_BUTTON', 'BBL426DYP3BTC');
define('SUBSCRIPTION_PAID_PRICE', '$5.00 USD'); // must be formatted: "$##.## XXX"

define('SUBSCRIPTION_BITPAY_INVOICE_URL', 'https://bitpay.com/api/invoice');

define('SUBSCRIPTION_MESSAGES_CACHEKEY', 'submessage_');
define('SUBSCRIPTION_MESSAGES_MAX', 50);
define('SUBSCRIPTION_RSS_PATH', __DIR__.'/../rss/rss');

define('SUBSCRIPTION_ITEM_CACHEKEY', 'subitem_b_');
define('SUBSCRIPTION_SPECIES_CACHEKEY', 'subspecies2_');
define('SUBSCRIPTION_WATCH_CACHEKEY', 'subwatch_');
define('SUBSCRIPTION_RARE_CACHEKEY', 'subrare_');
define('SUBSCRIPTION_REPORTS_CACHEKEY', 'subreports_');
define('SUBSCRIPTION_PAID_CACHEKEY', 'subpaid_');
define('SUBSCRIPTION_SESSION_CACHEKEY', 'usersession_');

define('SUBSCRIPTION_WATCH_LIMIT_PER', 5);
define('SUBSCRIPTION_WATCH_LIMIT_TOTAL', 1000);

define('SUBSCRIPTION_RARE_LIMIT_HOUSE', 20); // max number of rare watches per house
define('SUBSCRIPTION_RARE_LIMIT_TOTAL', 50); // max number of rare watches

define('SUBSCRIPTION_WATCH_MAX_PERIOD', 1440); // minutes
define('SUBSCRIPTION_WATCH_MIN_PERIOD', 2); // minutes
define('SUBSCRIPTION_WATCH_MIN_PERIOD_FREE', 480); // lowest period for free subs
define('SUBSCRIPTION_WATCH_DEFAULT_PERIOD', 720); // minutes
define('SUBSCRIPTION_WATCH_FREE_LAST_LOGIN_DAYS', 30); // max number of days since we've seen this free sub and we still trigger notifications

function GetLoginState($logOut = false) {
    $userInfo = [];
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return $userInfo;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return $userInfo;
    }

    $stateBytes = base64_decode(strtr($state, '-_', '+/'));
    $cacheKey = SUBSCRIPTION_SESSION_CACHEKEY.$state;

    if ($logOut) {
        MCDelete($cacheKey);

        $db = DBConnect();
        $stmt = $db->prepare('DELETE FROM tblUserSession WHERE session=?');
        $stmt->bind_param('s', $stateBytes);
        $stmt->execute();
        $stmt->close();
    } else {
        $userInfo = MCGet($cacheKey);
        if ($userInfo === false) {
            $db = DBConnect();

            // see also MakeNewSession in api/subscription.php
            $stmt = $db->prepare('SELECT u.id, concat_ws(\'|\', cast(ua.provider as unsigned), ua.providerid) as publicid, u.name, u.locale, unix_timestamp(u.acceptedterms) acceptedterms FROM tblUserSession us join tblUser u on us.user=u.id join tblUserAuth ua on ua.user=u.id WHERE us.session=? group by u.id');
            $stmt->bind_param('s', $stateBytes);
            $stmt->execute();
            $result = $stmt->get_result();
            $userInfo = DBMapArray($result);
            $stmt->close();

            if (count($userInfo) < 1) {
                $logOut = true;
            } else {
                $userInfo = array_pop($userInfo);
                MCSet($cacheKey, $userInfo);

                $ip = substr($_SERVER['REMOTE_ADDR'], 0, 40);
                $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 250);

                $stmt = $db->prepare('UPDATE tblUserSession SET lastseen=NOW(), ip=?, useragent=? WHERE session=?');
                $stmt->bind_param('sss', $ip, $ua, $stateBytes);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare('UPDATE tblUser SET lastseen=NOW() WHERE id=?');
                $stmt->bind_param('i', $userInfo['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
        if (isset($userInfo['id'])) {
            $userInfo['paiduntil'] = GetUserPaidUntil($userInfo['id']);
        }
    }

    if ($logOut) {
        setcookie(SUBSCRIPTION_LOGIN_COOKIE, '', time() - SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);
        setcookie(SUBSCRIPTION_CSRF_COOKIE, '', 0, '/api/csrf.txt', '', true, false);
        return [];
    }

    if (!headers_sent()) {
        setcookie(SUBSCRIPTION_LOGIN_COOKIE, $state, time() + SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);
        setcookie(SUBSCRIPTION_CSRF_COOKIE, strtr(base64_encode(hash_hmac('sha256', $stateBytes, SUBSCRIPTION_CSRF_HMAC_KEY, true)), '+/=', '-_.'), 0, '/api/csrf.txt', '', true, false);
    }

    return $userInfo;
}

function RedactLoginState($loginState) {
    $loginState['ads'] = (!isset($loginState['paiduntil'])) || ($loginState['paiduntil'] < time());
    unset($loginState['id'], $loginState['publicid'], $loginState['paiduntil']);
    $loginState['acceptedterms'] = isset($loginState['acceptedterms']) && ($loginState['acceptedterms'] > SUBSCRIPTION_TERMS_UPDATED);
    $loginState['csrfCookie'] = SUBSCRIPTION_CSRF_COOKIE;

    return $loginState;
}

function ValidateCSRFProtectedRequest()
{
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return false;
    }
    if (strlen($_SERVER['HTTP_X_CSRF_TOKEN']) > 100) {
        return false;
    }
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return false;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return false;
    }

    $stateBytes = base64_decode(strtr($state, '-_', '+/'));
    $correctToken = strtr(base64_encode(hash_hmac('sha256', $stateBytes, SUBSCRIPTION_CSRF_HMAC_KEY, true)), '+/=', '-_.');

    if (function_exists('hash_equals')) {
        return hash_equals($correctToken, $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
    return (sha1($correctToken) === sha1($_SERVER['HTTP_X_CSRF_TOKEN'])); // sha1 on both sides to mitigate timing attacks
}

function ClearLoginStateCache()
{
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return false;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return false;
    }

    MCDelete(SUBSCRIPTION_SESSION_CACHEKEY.$state);
    return true;
}

function SendUserMessage($userId, $messageType, $subject, $message)
{
    $loops = 0;
    while (!MCAdd(SUBSCRIPTION_MESSAGES_CACHEKEY . "lock_$userId", 1, 15)) {
        usleep(250000);
        if ($loops++ >= 120) { // 30 seconds
            return false;
        }
    }

    $db = DBConnect(true);
    $seq = 0;
    $cnt = 0;
    $stmt = $db->prepare('select ifnull(max(seq),0)+1, count(*) from tblUserMessages where user = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($seq, $cnt);
    $stmt->fetch();
    $stmt->close();

    $stmt = $db->prepare('INSERT INTO tblUserMessages (user, seq, created, type, subject, message) VALUES (?, ?, NOW(), ?, ?, ?)');
    $stmt->bind_param('iisss', $userId, $seq, $messageType, $subject, $message);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        $cnt++;
    }
    if ($cnt > SUBSCRIPTION_MESSAGES_MAX) {
        $stmt = $db->prepare('delete from tblUserMessages where user = ? order by seq asc limit ?');
        $cnt = $cnt - SUBSCRIPTION_MESSAGES_MAX;
        $stmt->bind_param('ii', $userId, $cnt);
        $stmt->execute();
        $stmt->close();
    }

    MCDelete(SUBSCRIPTION_MESSAGES_CACHEKEY . "lock_$userId");
    if (!$success) {
        DebugMessage("Error adding user message: ".$db->error);
        $db->close();
        return false;
    }

    MCDelete(SUBSCRIPTION_MESSAGES_CACHEKEY . $userId);
    MCDelete(SUBSCRIPTION_MESSAGES_CACHEKEY . $userId . '_' . $seq);

    $stmt = $db->prepare('select name, email, locale from tblUser where id = ? and email is not null and emailverification is null');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $name = $address = $locale = '';
    $stmt->bind_result($name, $address, $locale);
    if (!$stmt->fetch()) {
        $address = false;
    }
    $stmt->close();
    if ($address) {
        NewsstandMail($address, $name, $subject, $message, $locale);
    }

    UpdateUserRss($userId, $db);

    $db->close();
    return $seq;
}

function UpdateUserRss($userId, &$db) {
    $stmt = $db->prepare('select rss, name from tblUser where id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $name = $rss = '';
    $stmt->bind_result($rss, $name);
    if (!$stmt->fetch()) {
        $rss = false;
    }
    $stmt->close();
    if ($rss === false) {
        DebugMessage("Asked to update RSS for user $userId which could not be found.", E_USER_WARNING);
        return false;
    }

    if (is_null($rss)) {
        $randomPool = '0123456789bcdfghjklmnpqrstvwxyz';
        $poolMaxIdx = strlen($randomPool) - 1;
        $tries = 0;
        $rss = '';

        while (!$rss && ($tries++ < 10)) {
            for ($x = 0; $x < 24; $x++) {
                $rss .= substr($randomPool, mt_rand(0, $poolMaxIdx), 1);
            }

            $stmt = $db->prepare('select count(*) from tblUser WHERE rss = ?');
            $rssCheck = $rss;
            $stmt->bind_param('s', $rssCheck);
            $stmt->execute();
            $c = 0;
            $stmt->bind_result($c);
            $stmt->fetch();
            $stmt->close();
            if ($c > 0) {
                $rss = ''; // try again
            }
        }
        $success = false;
        if ($rss) {
            $stmt = $db->prepare('UPDATE tblUser SET rss = ? WHERE id = ?');
            $stmt->bind_param('si', $rss, $userId);
            $success = $stmt->execute();
            $stmt->close();
        }
        if (!$success) {
            DebugMessage("Could not set RSS key \"$rss\" for $userId", E_USER_WARNING);
            return false;
        }
    }

    $rssTempNam = tempnam(SUBSCRIPTION_RSS_PATH, 'rssworking');
    if ($rssTempNam === false) {
        DebugMessage("Could not create temp rss file in ".SUBSCRIPTION_RSS_PATH, E_USER_WARNING);
        return false;
    }
    $rssFile = fopen($rssTempNam, 'w');
    if ($rssFile === false) {
        DebugMessage("Could not open temp rss file $rssTempNam", E_USER_WARNING);
        unlink($rssTempNam);
        return false;
    }

    $MakeCData = function($text) {
        return '<![CDATA[' . str_replace(']]', ']]>]]<![CDATA[', $text) . ']]>';
    };
    $dt = date(DATE_RSS);
    $rssSubDir = substr($rss,0,1) . '/' . substr($rss,1,1);

    $rssXml = <<<EOF
<?xml version="1.0"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>The Undermine Journal - $name</title>
        <link>https://theunderminejournal.com/#subscription</link>
        <description>Subscription messages for $name</description>
        <pubDate>$dt</pubDate>
        <lastBuildDate>$dt</lastBuildDate>
        <docs>http://blogs.law.harvard.edu/tech/rss</docs>
        <atom:link href="https://sub.theunderminejournal.com/rss/$rssSubDir/$rss.rss" rel="self" type="application/rss+xml" />
EOF;
    fwrite($rssFile, $rssXml);

    $itemFormat = <<<'EOF'

        <item>
            <title>%s</title>
            <link>https://theunderminejournal.com/#subscription</link>
            <guid isPermaLink="false">%s</guid>
            <description>%s</description>
            <pubDate>%s</pubDate>
        </item>
EOF;

    $stmt = $db->prepare('select seq, unix_timestamp(created) as created, subject, message from tblUserMessages where user = ? order by seq desc limit 15');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        fwrite($rssFile, sprintf($itemFormat,
            $MakeCData($row['subject']),
            md5($name . 'x' . $row['seq'] . 'x' . $row['created']),
            $MakeCData($row['message']),
            date(DATE_RSS, $row['created'])));
    }
    $result->close();
    $stmt->close();

    $rssXml = <<<EOF

    </channel>
</rss>
EOF;
    fwrite($rssFile, $rssXml);
    fclose($rssFile);

    $rssSubParts = explode('/', $rssSubDir);
    $rssPath = SUBSCRIPTION_RSS_PATH;
    for ($x = 0; $x < count($rssSubParts); $x++) {
        $rssPath .= "/" . $rssSubParts[$x];
        if (!is_dir($rssPath)) {
            $success = mkdir($rssPath, 0777);
            if (!$success) {
                DebugMessage("Could not create rss path $rssPath", E_USER_WARNING);
                unlink($rssTempNam);
                return false;
            }
            chmod($rssPath, 0777);
        }
    }

    chmod($rssTempNam, 0644);
    $rssFileName = $rssPath . "/$rss.rss";
    $success = rename($rssTempNam, $rssFileName);
    if ($success === false) {
        DebugMessage("Could not rename temp rss file $rssTempNam to $rssFileName", E_USER_WARNING);
        if (file_exists($rssTempNam)) {
            unlink($rssTempNam);
        }
        return false;
    }

    return $rss;
}

function DisableEmailAddress($address) {
    // Note: this is not permanent, a user can re-add it later

    $db = DBConnect(true);
    $stmt = $db->prepare('select id, locale from tblUser where email = ?');
    $stmt->bind_param('s', $address);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = DBMapArray($result);
    $stmt->close();

    $stmt = $db->prepare('update tblUser set email=null, emailverification=null where id = ?');
    $userIdParam = 0;
    $stmt->bind_param('i', $userIdParam);
    foreach ($ids as $userId => $userRow) {
        $lang = GetLang($userRow['locale']);
        $userIdParam = $userId;
        $stmt->execute();
        SendUserMessage($userId, 'Email', $lang['emailAddressRemoved'], sprintf($lang['emailAddressRemovedBounce'], htmlspecialchars($address, ENT_COMPAT | ENT_HTML5)));
    }
    $stmt->close();

    $db->close();
    return count($ids);
}

// seconds can be negative!
function AddPaidTime($userId, $seconds) {
    $db = DBConnect();

    $db->begin_transaction();

    $stmt = $db->prepare('select paiduntil from tblUser where id = ? for update');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $paidUntil = null;
    $stmt->bind_result($paidUntil);
    if (!$stmt->fetch()) {
        $paidUntil = false;
    }
    $stmt->close();

    if ($paidUntil === false) {
        $db->rollback();
        DebugMessage("Could not find $userId when adding $seconds paid time");
        return false;
    }

    if (is_null($paidUntil)) {
        $paidUntil = time();
    } else {
        $paidUntil = strtotime($paidUntil);
        if ($paidUntil <= time()) {
            $paidUntil = time();
        }
    }
    $paidUntil += $seconds;

    $stmt = $db->prepare('update tblUser set paiduntil = from_unixtime(?) where id = ?');
    $stmt->bind_param('ii', $paidUntil, $userId);
    $stmt->execute();
    $affected = $db->affected_rows;
    $stmt->close();

    $db->commit();

    MCDelete(SUBSCRIPTION_PAID_CACHEKEY . $userId);

    if (!$affected) {
        DebugMessage("0 rows affected when adding $seconds paid time to user $userId");
        return false;
    }

    return $paidUntil;
}

function GetUserPaidUntil($userId) {
    $cacheKey = SUBSCRIPTION_PAID_CACHEKEY . $userId;

    $ts = MCGet($cacheKey);
    if ($ts === false) {
        $db = DBConnect();

        $stmt = $db->prepare('SELECT ifnull(unix_timestamp(u.paiduntil),0) FROM tblUser u WHERE u.id=?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($ts);
        if (!$stmt->fetch()) {
            $ts = 0;
        }
        $stmt->close();
        MCSet($cacheKey, $ts);
    }

    if (!$ts) {
        $ts = null;
    }

    return $ts;
}

function GeneratePublicUserHMAC($publicId) {
    $msg = time().":$publicId";
    $hmac = strtr(base64_encode(hash_hmac('sha256', $msg, SUBSCRIPTION_PUBLIC_ID_HMAC_KEY, true)), '+/=', '-_.');
    return "$hmac:$msg";
}

function ValidatePublicUserHMAC($fullMsg, $timestamp = false) {
    $parts = explode(":", $fullMsg);
    if (count($parts) < 2) {
        return false;
    }
    $givenHMAC = array_shift($parts);
    $msg = implode(":", $parts);
    $realHMAC = strtr(base64_encode(hash_hmac('sha256', $msg, SUBSCRIPTION_PUBLIC_ID_HMAC_KEY, true)), '+/=', '-_.');

    if (function_exists('hash_equals')) {
        $good = hash_equals($realHMAC, $givenHMAC);
    } else {
        $good = (sha1($realHMAC) === sha1($givenHMAC)); // sha1 on both sides to mitigate timing attack
    }

    if (!$good) {
        return false;
    }

    $created = intval(array_shift($parts), 10);

    $diff = ($timestamp ?: time()) - $created;
    if ($diff < -600) {
        return false;
    }
    if ($diff > 86400) {
        return false;
    }

    return $parts;
}

function GetUserFromPublicHMAC($msg, $timestamp = false) {
    $result = ValidatePublicUserHMAC($msg, $timestamp);
    if (!$result) {
        return null;
    }

    $providerParts = explode('|', $result[0]);
    if (count($providerParts) != 2) {
        return null;
    }

    $db = DBConnect();
    $stmt = $db->prepare('select user from tblUserAuth where provider = ? and providerid = ?');
    $stmt->bind_param('is', $providerParts[0], $providerParts[1]);
    $stmt->execute();
    $user = null;
    $stmt->bind_result($user);
    if (!$stmt->fetch()) {
        $user = null;
    }
    $stmt->close();

    return $user;
}

function UpdateBitPayTransaction($bitPayJson, $isNew = false) {
    $isNew = !!$isNew; // force boolean

    if (!is_array($bitPayJson)) {
        $jsonString = $bitPayJson;
        $bitPayJson = json_decode($jsonString, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            DebugMessage("Invalid BitPay JSON\n" . print_r($jsonString, true), E_USER_ERROR);
            return false;
        }
    }

    $requiredFields = ['btcDue','btcPaid','btcPrice','currency','currentTime',
        'exceptionStatus','expirationTime','id','invoiceTime','posData','price',
        'rate','status','url'];

    foreach ($requiredFields as $fieldName) {
        if (!array_key_exists($fieldName, $bitPayJson)) {
            DebugMessage("BitPay JSON missing required field: $fieldName\n" . print_r($bitPayJson, true), E_USER_ERROR);
            return false;
        }
    }

    $invoiced = floor($bitPayJson['invoiceTime'] / 1000);
    $expired = floor($bitPayJson['expirationTime'] / 1000);

    $db = DBConnect();
    $stmt = $db->prepare('select id, user, subextended from tblBitPayTransactions where id = ?');
    $stmt->bind_param('s', $bitPayJson['id']);
    $stmt->execute();
    $txnId = $userId = $subExtended = null;
    $stmt->bind_result($txnId, $userId, $subExtended);
    if (!$stmt->fetch()) {
        $txnId = $userId = $subExtended = null;
    }
    $stmt->close();

    if (is_null($txnId) != $isNew) {
        if ($isNew) {
            DebugMessage("New BitPay transaction already found in database:\n" . print_r($bitPayJson, true), E_USER_ERROR);
        } else {
            DebugMessage("Existing BitPay transaction not found in database:\n" . print_r($bitPayJson, true), E_USER_ERROR);
        }
        return false;
    }

    if ($isNew) {
        $userId = GetUserFromPublicHMAC($bitPayJson['posData'], $invoiced);
        if (is_null($userId)) {
            DebugMessage("Could not get valid user from BitPay posData:\n" . print_r($bitPayJson, true), E_USER_ERROR);
            return false;
        }
    }

    $sql = <<<'EOF'
insert into tblBitPayTransactions (
    id, user, price, currency, rate, 
    btcprice, btcpaid, btcdue, status, 
    exception, url, posdata, 
    invoiced, expired, updated
) values (
    ?, ?, ?, ?, ?, 
    ?, ?, ?, ?, 
    ?, ?, ?, 
    from_unixtime(?), from_unixtime(?), now())
on duplicate key update 
user=ifnull(user,values(user)), price=values(price), currency=values(currency), rate=values(rate),
btcprice=values(btcprice), btcpaid=values(btcpaid), btcdue=values(btcdue), status=values(status), 
exception=values(exception), url=values(url), posdata=values(posdata), 
invoiced=values(invoiced), expired=values(expired), updated=values(updated)
EOF;

    $stmt = $db->prepare($sql);
    $exceptionStatus = $bitPayJson['exceptionStatus'] ?: null;

    $stmt->bind_param('sissssssssssii',
        $bitPayJson['id'], $userId, $bitPayJson['price'], $bitPayJson['currency'], $bitPayJson['rate'],
        $bitPayJson['btcPrice'], $bitPayJson['btcPaid'], $bitPayJson['btcDue'], $bitPayJson['status'],
        $exceptionStatus, $bitPayJson['url'], $bitPayJson['posData']
        , $invoiced, $expired);

    if (!$stmt->execute()) {
        DebugMessage("Error updating BitPay transaction record: " . print_r($bitPayJson, true), E_USER_ERROR);
        return false;
    }

    $stmt->close();

    if ($userId && !$subExtended) {
        $stmt = $db->prepare('select locale from tblUser where id = ?');
        $locale = null;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($locale);
        if (!$stmt->fetch()) {
            $locale = 'enus';
        }
        $stmt->close();

        $LANG = GetLang($locale);

        if (in_array($bitPayJson['status'], ['confirmed', 'complete'])) {
            $paidUntil = AddPaidTime($userId, SUBSCRIPTION_PAID_ADDS_SECONDS);
            if ($paidUntil === false) {
                LogBitPayError($bitPayJson, "Failed to add paid time to $userId");
                SendUserMessage($userId, 'Subscription', $LANG['paidSubscription'], $LANG['SubscriptionErrors']['paiderror']);
                return false;
            } else {
                $stmt = $db->prepare('update tblBitPayTransactions set subextended=1 where id=?');
                $stmt->bind_param('s', $bitPayJson['id']);
                if (!$stmt->execute()) {
                    LogBitPayError($bitPayJson, "Failed to mark transaction as extending the sub for $userId");
                } else {
                    $message = $LANG['subscriptionTimeAddedMessage'];
                    $message .= "<br><br>" . sprintf(preg_replace('/\{(\d+)\}/', '%$1$s', $LANG['paidExpires']), date('Y-m-d H:i:s e', $paidUntil));

                    SendUserMessage($userId, 'Subscription', $LANG['paidSubscription'], $message);
                }
                $stmt->close();
            }
        } elseif (in_array($bitPayJson['status'], ['invalid']) || $bitPayJson['exceptionStatus']) {
            LogBitPayError($bitPayJson, "BitPay exception for $userId");
            SendUserMessage($userId, 'Subscription', $LANG['paidSubscription'], $LANG['SubscriptionErrors']['paiderror']);
        }
    }

    return true;
}

function LogBitPayError($bitPayJson, $message, $subject = 'BitPay Transaction Issue') {
    global $argv;

    $pth = __DIR__ . '/../logs/paypalerrors.log';
    if ($pth) {
        $me = (PHP_SAPI == 'cli') ? ('CLI:' . realpath($argv[0])) : ('Web:' . $_SERVER['REQUEST_URI']);
        file_put_contents($pth, date('Y-m-d H:i:s') . " $me $message\n".($subject === false ? '' : print_r($bitPayJson, true)), FILE_APPEND | LOCK_EX);
    }

    if ($subject !== false) {
        NewsstandMail(
            SUBSCRIPTION_ERRORS_EMAIL_ADDRESS, 'BitPay Manager',
            $subject, $message . "<br><br>" . str_replace("\n", '<br>', print_r($bitPayJson, true))
        );
    }
}


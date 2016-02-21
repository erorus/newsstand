<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/subscription.incl.php');

RunMeNTimes(1);
CatchKill();

define('BOUNCEBACK_PATH', '/var/newsstand/notifications.inbox/');

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not checking bouncebacks!', E_USER_NOTICE);
    exit;
}

$loopStart = time();
while ((!$caughtKill) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    if ($caughtKill || APIMaintenance()) {
        break;
    }
    $hadFile = NextMailFile();
    if ($hadFile === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function NextMailFile()
{
    $dir = scandir(substr(BOUNCEBACK_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $lockFail = false;
    $gotFile = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)\./', $fileName, $res)) {
            if (($handle = fopen(BOUNCEBACK_PATH . $fileName, 'rb')) === false) {
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                $lockFail = true;
                fclose($handle);
                continue;
            }

            if (feof($handle)) {
                fclose($handle);
                unlink(BOUNCEBACK_PATH . $fileName);
                continue;
            }

            $ts = intval($res[1], 10);

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile) {
        if ($lockFail) {
            sleep(3);
            return true;
        }
        return false;
    }

    DebugMessage('Found message received at '.Date('Y-m-d H:i:s', $ts).', '.TimeDiff($ts));
    $message = fread($handle, min(filesize(BOUNCEBACK_PATH . $fileName), 4194304));

    ftruncate($handle, 0);
    fclose($handle);
    unlink(BOUNCEBACK_PATH . $fileName);

    $mailId = false;
    if (preg_match('/X-Undermine-MailID:\s*([a-zA-Z0-9_-]{27})/', $message, $res)) {
        $mailId = $res[1];
    } elseif (preg_match('/[Mm]essage ID: ([a-zA-Z0-9_-]{27})/', $message, $res)) {
        $mailId = $res[1];
    }

    if (!$mailId) {
        DebugMessage('Could not find message ID, forwarding to editor');
        NewsstandMail('editor@theunderminejournal.com', 'The Editor', 'Unparsed notification reply', $message);
    } else {
        $address = GetAddressByMailID($mailId);
        if (!$address) {
            DebugMessage('Could not find address for mail ID '.$mailId);
        } else {
            $cnt = DisableEmailAddress($address['address']);
            DebugMessage('Address '.$address['address'].' removed from '.$cnt.' account'.($cnt==1?'':'s').'.');
        }
    }

    return true;
}
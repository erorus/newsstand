<?php

require_once __DIR__ . '/../../incl/theapi.incl.php';
require_once __DIR__ . '/../../incl/NewsstandHTTP.incl.php';
require_once __DIR__ . '/../../incl/database.credentials.php';
require_once __DIR__ . '/../../incl/memcache.incl.php';

define('COOKIE_LIFE', 7 * 24 * 60 * 60);
define('DATE_FORMAT', 'Y-m-d h:i:s A');
define('GUESS_CACHE_KEY', 'theapiguesses');

$user = false;
$guessUpdate = '';
$redirect_uri = sprintf('https://%s%s', $_SERVER['HTTP_HOST'], substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/') + 1));

if (isset($_GET['logout'])) {
    SetUserCookie();
} else {
    GetUser();
    if (isset($_POST['guessdate'])) {
        $guessUpdate = SubmitGuess();
    }
}

function GetUser() {
    global $user, $redirect_uri;

    if (isset($_GET['code'])) {
        $url = 'https://us.battle.net/oauth/token';
        $toPost = [
            'redirect_uri' => $redirect_uri,
            'scope' => '',
            'grant_type' => 'authorization_code',
            'code' => $_GET['code'],
            'client_id' => BATTLE_NET_KEY,
            'client_secret' => BATTLE_NET_SECRET,
        ];
        $outHeaders = [];
        $tokenData = \Newsstand\HTTP::Post($url, $toPost, [], $outHeaders);
        if ($tokenData === false) {
            return 'notoken';
        }
        $tokenData = json_decode($tokenData, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return 'badtoken';
        }
        if (!isset($tokenData['access_token'])) {
            return 'missingtoken';
        }
        $token = $tokenData['access_token'];

        // get user id and battle.net tag
        $url = sprintf('https://us.api.battle.net/account/user?access_token=%s', $token);
        $userData = \Newsstand\HTTP::Get($url);
        if ($userData === false) {
            return 'nouser';
        }
        $userData = json_decode($userData, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return 'baduser';
        }
        if (!isset($userData['id']) || !isset($userData['battletag'])) {
            return 'missinguser';
        }

        $user = [
            'id' => $userData['id'],
            'tag' => $userData['battletag'],
            'exp' => time() + COOKIE_LIFE,
        ];

        SetUserCookie();

        return true;
    }

    $userHashed = $_POST['user'] ?? $_COOKIE['user'] ?? '';
    if (strpos($userHashed, '.') !== false) {
        list($hmac, $json) = explode('.', $userHashed, 2);
        $expectedHmac = base64_encode(hash_hmac('sha256', $json, HMAC_KEY, true));
        if (!hash_equals($hmac, $expectedHmac)) {
            SetUserCookie();

            return false;
        }

        $user = json_decode($json, true);
        if ($user['exp'] < time()) {
            $user = false;
        } else {
            $user['exp'] = time() + COOKIE_LIFE;
        }

        SetUserCookie();
    }

    return true;
}

function GenerateUserHash() {
    global $user;

    if (!$user) {
        return '';
    }

    $json = json_encode($user, JSON_BIGINT_AS_STRING);
    $hmac = base64_encode(hash_hmac('sha256', $json, HMAC_KEY, true));

    return "$hmac.$json";
}

function SetUserCookie() {
    global $user;

    if (!$user) {
        setcookie('user', '', time() - COOKIE_LIFE, '/contest/', $_SERVER['HTTP_HOST'], true, true);
    } else {
        setcookie('user', GenerateUserHash(), time() + COOKIE_LIFE, '/contest/', $_SERVER['HTTP_HOST'], true, true);
    }
}

function ShowLoginForm() {
    global $redirect_uri;

    $key = BATTLE_NET_KEY;

    echo <<<EOF
<form method="GET" action="https://us.battle.net/oauth/authorize">
<input type="hidden" name="client_id" value="$key">
<input type="hidden" name="scope" value="">
<input type="hidden" name="redirect_uri" value="$redirect_uri">
<input type="hidden" name="response_type" value="code">
<input type="submit" value="Log In with Battle.net">
</form>
EOF;
}

function ShowGuessForm() {
    global $user, $guessUpdate;

    $userHash = htmlspecialchars(GenerateUserHash());

    echo <<<EOF
    <p>Hello, <b>${user['tag']}</b>! <input type="button" value="Log Out" onclick="location.href='?logout=1';" style="margin-left: 5em"></p>
EOF;

    if ($guessUpdate) {
        echo sprintf('<div class="guess-update">%s</div>', $guessUpdate);
    }

    $db = GetDB();
    if (!$db) {
        echo '<p>The database is unavailable right now, please try again later!</p>';
        return;
    }

    $dt = GetUserLastGuessDate();

    if (!is_null($dt) && $dt > (time() - 60 * 60)) {
        echo "<p>You submitted a guess " . floor((time() - $dt) / 60) . " minutes ago. You can guess again one hour after your last guess.</p>";
        return;
    }

    $time = date(DATE_FORMAT);

echo <<<EOF
    <form method="POST" action="./">
        <input type="hidden" name="user" value="$userHash">
        The current time is $time UTC.<br>
        Please enter your guess for when the API will come back online:<br>
        Date: <input type="date" name="guessdate" required><br>
        Time: <input type="time" name="guesstime" step="1" required> UTC<br>
        <input type="submit" value="Submit Guess">
    </form>
EOF;

}

function SubmitGuess() {
    global $user;

    if (!$user) {
        return 'No user logged in!';
    }

    $dt = GetUserLastGuessDate();
    if (!is_null($dt) && $dt > (time() - 60 * 60)) {
        return 'You guessed recently! Come back later.';
    }

    if (!isset($_POST['guessdate']) || !$_POST['guessdate'] || !isset($_POST['guesstime']) || !$_POST['guesstime']) {
        return 'Form elements missing!';
    }

    $tm = strtotime(sprintf('%s %s', $_POST['guessdate'], $_POST['guesstime']));
    if (!$tm) {
        return 'Bad time format!';
    }
    if ($tm < time()) {
        return 'Date entered is in the past, please pick a future date.';
    }
    if ($tm >= strtotime('2019-01-01')) {
        return 'Date entered is too far in the future (we hope). Please pick a sooner date.';
    }

    $db = GetDB();
    if (!$db) {
        return 'Database unavailable, please try again.';
    }

    $stmt = $db->prepare('insert into tblAPIBets (userid, name, placed, bet) values (?, ?, now(), from_unixtime(?))');
    $stmt->bind_param('sss', $user['id'], $user['tag'], $tm);
    if (!$stmt->execute()) {
        return 'Error saving guess to database!';
    }
    $stmt->close();

    MCDelete(GUESS_CACHE_KEY);

    return 'Saved guess for ' . date(DATE_FORMAT, $tm);
}

function GetUserLastGuessDate() {
    global $user;

    $db = GetDB();

    $stmt = $db->prepare('select unix_timestamp(max(placed)) from tblAPIBets where userid=?');
    $stmt->bind_param('s', $user['id']);
    $stmt->execute();
    $dt = null;
    $stmt->bind_result($dt);
    $stmt->fetch();
    $stmt->close();

    return $dt;
}

function GetGuesses() {
    $guesses = MCGet(GUESS_CACHE_KEY);
    if ($guesses !== false) {
        return $guesses;
    }

    $db = GetDB();
    if (!$db) {
        return [];
    }

    $data = [];

    $sql = <<<'EOF'
select b.name, unix_timestamp(b.bet) bet from tblAPIBets b
join (SELECT userid, max(placed) placed FROM `tblAPIBets` group by userid) maxes
on b.userid = maxes.userid and b.placed = maxes.placed
order by b.bet asc, b.placed asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = [$row['name'], date(DATE_FORMAT, $row['bet'])];
    }
    $result->close();
    $stmt->close();

    MCSet(GUESS_CACHE_KEY, $data);

    return $data;
}

function PrintGuesses() {
    $guesses = GetGuesses();
    foreach ($guesses as $row) {
        echo sprintf('<tr><td>%s</td><td align="right">%s</td></tr>', htmlspecialchars($row[0]), htmlspecialchars($row[1]));
    }
}

function GetDB() {
    static $db = null;

    if (!is_null($db)) {
        return $db;
    }

    $host = 'localhost';
    $user = DATABASE_USERNAME_CLI;
    $pass = DATABASE_PASSWORD_CLI;
    $database = DATABASE_SCHEMA;

    $db = new mysqli($host, $user, $pass, $database);
    if ($db->connect_error) {
        $db = false;
    } else {
        $db->set_charset("utf8");
        $db->query('SET time_zone=\'+0:00\'');
    }

    return $db;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Does the API work? Contest</title>
    <script>
        (function(){
            if (location.search && location.search.length > 1) {
                window.history.replaceState({}, '', location.href.substr(0, location.href.indexOf('?')));
            }
        })();
    </script>
    <style type="text/css">
        body{font-family: sans-serif; margin:40px auto;max-width:650px;line-height:1.6;font-size:18px;color:#444;padding:0 10px}
        h1,h2,h3{line-height:1.2}
        .guess-update {
            border: 1px dashed red;
            margin: 1em;
            padding: 1em;
        }
        .guess-table th, .guess-table td {
            padding: 4px 1em;
            border-bottom: 1px solid #666;
        }
    </style>
</head>
<body>
    <h1>Does the API work? Contest</h1>
    <h2>for the <a href="https://dev.battle.net">Battle.net Auction House API</a></h2>

    <p>The Battle.net Auction House API has been down for.. a long time. How about we have a friendly contest to guess when it will come back?</p>

    <p>Log in with Battle.Net, then pick the date and time when you think the first auction house updates will return, for US and for EU. Each guess is valid for both regions. You can change your guess once per hour.</p>

    <p>The two people (one for US realms, one for EU realms) who first come closest to the correct time <i>without going over</i> can log back in here to receive a $20 Battle.net Balance code, just to keep things interesting.</p>

    <h3>Submit A Guess</h3>
    <div id="guessforms">
    <?php
        if (!$user) {
            ShowLoginForm();
        } else {
            ShowGuessForm();
        }
    ?>
    </div>

    <h3>Current Guesses</h3>
    <table class="guess-table" cellspacing="0">
        <tr><th>Player</th><th>Guess</th></tr>
        <?php PrintGuesses(); ?>
    </table>


    <h3>Brought to you by <a href="https://theunderminejournal.com">The Undermine Journal</a></h3>
</body>
</html>
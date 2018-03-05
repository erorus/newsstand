<?php

require_once '../incl/incl.php';

if (count($argv) < 4) {
    fwrite(STDERR, "php promocodes.php '+30 days' X-codes Y-uses-per-code\n");
    exit(1);
}

$seconds = strtotime($argv[1]) - time();
if ($seconds <= 0) {
    fwrite(STDERR, "can only add time, will not add $seconds seconds\n");
    exit(2);
}

$codeCount = intval($argv[2], 10);
if ($codeCount > 100 || $codeCount < 1) {
    fwrite(STDERR, "Invalid code count $codeCount\n");
    exit(2);
}

$usesPerCode = intval($argv[3], 10);
if ($usesPerCode > 100 || $usesPerCode < 1) {
    fwrite(STDERR, "Invalid uses per code $usesPerCode\n");
    exit(2);
}

for ($x = 0; $x < $codeCount; $x++) {
    $code = AddNewCode($seconds, $usesPerCode);
    if ($code === false) {
        break;
    }
    echo sprintf("%s (%d use%s): %s\n", $argv[1], $usesPerCode, $usesPerCode == 1 ? '' : 's', $code);
}

function AddNewCode($seconds, $usesPerCode) {
    $db = DBConnect();

    $code = false;

    for ($tries = 0; $tries < 10; $tries++) {
        $c = null;
        $code = GeneratePromoCode();

        $stmt = $db->prepare('select count(*) from tblPromoCode where `code` = ?');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->bind_result($c);
        if (!$stmt->fetch()) {
            $c = null;
        }
        $stmt->close();

        if ($c == 0) {
            break;
        }
        if (is_null($c)) {
            fwrite(STDERR, "Could not fetch count of used code\n");
            return false;
        }
    }
    if ($c != 0) {
        fwrite(STDERR, "Could not generate unused code\n");
        return false;
    }

    $stmt = $db->prepare('insert into tblPromoCode (code, maxuses, addseconds, created) values (?, ?, ?, NOW())');
    $stmt->bind_param('sii', $code, $usesPerCode, $seconds);
    $success = $stmt->execute();
    $stmt->close();
    if (!$success) {
        fwrite(STDERR, "Could not save new code\n");
        return false;
    }

    return $code;
}

function GeneratePromoCode($minEntropyBits=24) {
    $WORDLIST = <<<'EOF'
Z
ak
rt
ik
um
fr
bl
zz
ap
un
ek
eet
paf
gak
erk
gip
nap
kik
bap
ikk
grk
tiga
moof
bitz
akak
ripl
foop
keek
errk
apap
rakr
fibit
shibl
nebit
ababl
iklik
nubop
krikl
zibit
amama
apfap
ripdip
skoopl
bapalu
oggnog
yipyip
kaklak
ikripl
bipfiz
kiklix
nufazl
igglepop
bakfazl
rapnukl
fizbikl
lapadap
biglkip
nibbipl
fuzlpop
gipfizy
babbada
ibbityip
etiggara
saklpapp
ukklnukl
bendippl
ikerfafl
ikspindl
baksnazl
kerpoppl
hopskopl
hapkranky
skippykik
nogglefrap
rapnakskappypappl
rripdipskiplip
napfazzyboggin
kikklpipkikkl
nibbityfuzhips
bobnobblepapkap
hikkitybippl
EOF;

    $words = explode("\n", $WORDLIST);

    $bits = log(count($words), 2);
    $wordCount = ceil($minEntropyBits / $bits);

    $result = [];
    for ($x = 0; $x < $wordCount; $x++) {
        $result[] = $words[random_int(0, count($words) - 1)];
    }

    return implode(' ', $result);
}


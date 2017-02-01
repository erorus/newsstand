<?php

require_once __DIR__.'/../incl/incl.php';
require_once __DIR__.'/../incl/battlenet.incl.php';

define('ZOPFLI_PATH', __DIR__.'/zopfli');
define('BROTLI_PATH', __DIR__.'/brotli/bin/bro');
define('REALM_CHUNK_SIZE', 12);

define('REALM_LIST_JSON', <<<'EOF'
{"us":{
    "aegwynn":"Aegwynn",
    "aerie-peak":"Aerie Peak",
    "agamaggan":"Agamaggan",
    "aggramar":"Aggramar",
    "akama":"Akama",
    "alexstrasza":"Alexstrasza",
    "alleria":"Alleria",
    "altar-of-storms":"Altar of Storms",
    "alterac-mountains":"Alterac Mountains",
    "amanthul":"Aman'Thul",
    "andorhal":"Andorhal",
    "anetheron":"Anetheron",
    "antonidas":"Antonidas",
    "anubarak":"Anub'arak",
    "anvilmar":"Anvilmar",
    "arathor":"Arathor",
    "archimonde":"Archimonde",
    "area-52":"Area 52",
    "argent-dawn":"Argent Dawn",
    "arthas":"Arthas",
    "arygos":"Arygos",
    "auchindoun":"Auchindoun",
    "azgalor":"Azgalor",
    "azjolnerub":"Azjol-Nerub",
    "azralon":"Azralon",
    "azshara":"Azshara",
    "azuremyst":"Azuremyst",
    "baelgun":"Baelgun",
    "balnazzar":"Balnazzar",
    "barthilas":"Barthilas",
    "black-dragonflight":"Black Dragonflight",
    "blackhand":"Blackhand",
    "blackrock":"Blackrock",
    "blackwater-raiders":"Blackwater Raiders",
    "blackwing-lair":"Blackwing Lair",
    "bladefist":"Bladefist",
    "blades-edge":"Blade's Edge",
    "bleeding-hollow":"Bleeding Hollow",
    "blood-furnace":"Blood Furnace",
    "bloodhoof":"Bloodhoof",
    "bloodscalp":"Bloodscalp",
    "bonechewer":"Bonechewer",
    "borean-tundra":"Borean Tundra",
    "boulderfist":"Boulderfist",
    "bronzebeard":"Bronzebeard",
    "burning-blade":"Burning Blade",
    "burning-legion":"Burning Legion",
    "caelestrasz":"Caelestrasz",
    "cairne":"Cairne",
    "cenarion-circle":"Cenarion Circle",
    "cenarius":"Cenarius",
    "chogall":"Cho'gall",
    "chromaggus":"Chromaggus",
    "coilfang":"Coilfang",
    "crushridge":"Crushridge",
    "daggerspine":"Daggerspine",
    "dalaran":"Dalaran",
    "dalvengyr":"Dalvengyr",
    "dark-iron":"Dark Iron",
    "darkspear":"Darkspear",
    "darrowmere":"Darrowmere",
    "dathremar":"Dath'Remar",
    "dawnbringer":"Dawnbringer",
    "deathwing":"Deathwing",
    "demon-soul":"Demon Soul",
    "dentarg":"Dentarg",
    "destromath":"Destromath",
    "dethecus":"Dethecus",
    "detheroc":"Detheroc",
    "doomhammer":"Doomhammer",
    "draenor":"Draenor",
    "dragonblight":"Dragonblight",
    "dragonmaw":"Dragonmaw",
    "draka":"Draka",
    "drakkari":"Drakkari",
    "draktharon":"Drak'Tharon",
    "drakthul":"Drak'thul",
    "dreadmaul":"Dreadmaul",
    "drenden":"Drenden",
    "dunemaul":"Dunemaul",
    "durotan":"Durotan",
    "duskwood":"Duskwood",
    "earthen-ring":"Earthen Ring",
    "echo-isles":"Echo Isles",
    "eitrigg":"Eitrigg",
    "eldrethalas":"Eldre'Thalas",
    "elune":"Elune",
    "emerald-dream":"Emerald Dream",
    "eonar":"Eonar",
    "eredar":"Eredar",
    "executus":"Executus",
    "exodar":"Exodar",
    "farstriders":"Farstriders",
    "feathermoon":"Feathermoon",
    "fenris":"Fenris",
    "firetree":"Firetree",
    "fizzcrank":"Fizzcrank",
    "frostmane":"Frostmane",
    "frostmourne":"Frostmourne",
    "frostwolf":"Frostwolf",
    "galakrond":"Galakrond",
    "gallywix":"Gallywix",
    "garithos":"Garithos",
    "garona":"Garona",
    "garrosh":"Garrosh",
    "ghostlands":"Ghostlands",
    "gilneas":"Gilneas",
    "gnomeregan":"Gnomeregan",
    "goldrinn":"Goldrinn",
    "gorefiend":"Gorefiend",
    "gorgonnash":"Gorgonnash",
    "greymane":"Greymane",
    "grizzly-hills":"Grizzly Hills",
    "guldan":"Gul'dan",
    "gundrak":"Gundrak",
    "gurubashi":"Gurubashi",
    "hakkar":"Hakkar",
    "haomarush":"Haomarush",
    "hellscream":"Hellscream",
    "hydraxis":"Hydraxis",
    "hyjal":"Hyjal",
    "icecrown":"Icecrown",
    "illidan":"Illidan",
    "jaedenar":"Jaedenar",
    "jubeithos":"Jubei'Thos",
    "kaelthas":"Kael'thas",
    "kalecgos":"Kalecgos",
    "kargath":"Kargath",
    "kelthuzad":"Kel'Thuzad",
    "khadgar":"Khadgar",
    "khaz-modan":"Khaz Modan",
    "khazgoroth":"Khaz'goroth",
    "kiljaeden":"Kil'jaeden",
    "kilrogg":"Kilrogg",
    "kirin-tor":"Kirin Tor",
    "korgath":"Korgath",
    "korialstrasz":"Korialstrasz",
    "kul-tiras":"Kul Tiras",
    "laughing-skull":"Laughing Skull",
    "lethon":"Lethon",
    "lightbringer":"Lightbringer",
    "lightninghoof":"Lightninghoof",
    "lightnings-blade":"Lightning's Blade",
    "llane":"Llane",
    "lothar":"Lothar",
    "madoran":"Madoran",
    "maelstrom":"Maelstrom",
    "magtheridon":"Magtheridon",
    "maiev":"Maiev",
    "malfurion":"Malfurion",
    "malganis":"Mal'Ganis",
    "malorne":"Malorne",
    "malygos":"Malygos",
    "mannoroth":"Mannoroth",
    "medivh":"Medivh",
    "misha":"Misha",
    "moknathal":"Mok'Nathal",
    "moon-guard":"Moon Guard",
    "moonrunner":"Moonrunner",
    "mugthol":"Mug'thol",
    "muradin":"Muradin",
    "nagrand":"Nagrand",
    "nathrezim":"Nathrezim",
    "nazgrel":"Nazgrel",
    "nazjatar":"Nazjatar",
    "nemesis":"Nemesis",
    "nerzhul":"Ner'zhul",
    "nesingwary":"Nesingwary",
    "nordrassil":"Nordrassil",
    "norgannon":"Norgannon",
    "onyxia":"Onyxia",
    "perenolde":"Perenolde",
    "proudmoore":"Proudmoore",
    "queldorei":"Quel'dorei",
    "quelthalas":"Quel'Thalas",
    "ragnaros":"Ragnaros",
    "ravencrest":"Ravencrest",
    "ravenholdt":"Ravenholdt",
    "rexxar":"Rexxar",
    "rivendare":"Rivendare",
    "runetotem":"Runetotem",
    "sargeras":"Sargeras",
    "saurfang":"Saurfang",
    "scarlet-crusade":"Scarlet Crusade",
    "scilla":"Scilla",
    "senjin":"Sen'jin",
    "sentinels":"Sentinels",
    "shadow-council":"Shadow Council",
    "shadowmoon":"Shadowmoon",
    "shadowsong":"Shadowsong",
    "shandris":"Shandris",
    "shattered-halls":"Shattered Halls",
    "shattered-hand":"Shattered Hand",
    "shuhalo":"Shu'halo",
    "silver-hand":"Silver Hand",
    "silvermoon":"Silvermoon",
    "sisters-of-elune":"Sisters of Elune",
    "skullcrusher":"Skullcrusher",
    "skywall":"Skywall",
    "smolderthorn":"Smolderthorn",
    "spinebreaker":"Spinebreaker",
    "spirestone":"Spirestone",
    "staghelm":"Staghelm",
    "steamwheedle-cartel":"Steamwheedle Cartel",
    "stonemaul":"Stonemaul",
    "stormrage":"Stormrage",
    "stormreaver":"Stormreaver",
    "stormscale":"Stormscale",
    "suramar":"Suramar",
    "tanaris":"Tanaris",
    "terenas":"Terenas",
    "terokkar":"Terokkar",
    "thaurissan":"Thaurissan",
    "the-forgotten-coast":"The Forgotten Coast",
    "the-scryers":"The Scryers",
    "the-underbog":"The Underbog",
    "the-venture-co":"The Venture Co",
    "thorium-brotherhood":"Thorium Brotherhood",
    "thrall":"Thrall",
    "thunderhorn":"Thunderhorn",
    "thunderlord":"Thunderlord",
    "tichondrius":"Tichondrius",
    "tol-barad":"Tol Barad",
    "tortheldrin":"Tortheldrin",
    "trollbane":"Trollbane",
    "turalyon":"Turalyon",
    "twisting-nether":"Twisting Nether",
    "uldaman":"Uldaman",
    "uldum":"Uldum",
    "undermine":"Undermine",
    "ursin":"Ursin",
    "uther":"Uther",
    "vashj":"Vashj",
    "veknilash":"Vek'nilash",
    "velen":"Velen",
    "warsong":"Warsong",
    "whisperwind":"Whisperwind",
    "wildhammer":"Wildhammer",
    "windrunner":"Windrunner",
    "winterhoof":"Winterhoof",
    "wyrmrest-accord":"Wyrmrest Accord",
    "ysera":"Ysera",
    "ysondre":"Ysondre",
    "zangarmarsh":"Zangarmarsh",
    "zuljin":"Zul'jin",
    "zuluhed":"Zuluhed"
},
"eu": {
    "aegwynn":"Aegwynn",
    "aerie-peak":"Aerie Peak",
    "agamaggan":"Agamaggan",
    "aggra-portugues":"Aggra (Portugu\u00eas)",
    "aggramar":"Aggramar",
    "ahnqiraj":"Ahn'Qiraj",
    "alakir":"Al'Akir",
    "alexstrasza":"Alexstrasza",
    "alleria":"Alleria",
    "alonsus":"Alonsus",
    "amanthul":"Aman'Thul",
    "ambossar":"Ambossar",
    "anachronos":"Anachronos",
    "anetheron":"Anetheron",
    "antonidas":"Antonidas",
    "anubarak":"Anub'arak",
    "arakarahm":"Arak-arahm",
    "arathi":"Arathi",
    "arathor":"Arathor",
    "archimonde":"Archimonde",
    "area-52":"Area 52",
    "argent-dawn":"Argent Dawn",
    "arthas":"Arthas",
    "arygos":"Arygos",
    "ashenvale":"Ashenvale",
    "aszune":"Aszune",
    "auchindoun":"Auchindoun",
    "azjolnerub":"Azjol-Nerub",
    "azshara":"Azshara",
    "azuregos":"Azuregos",
    "azuremyst":"Azuremyst",
    "baelgun":"Baelgun",
    "balnazzar":"Balnazzar",
    "blackhand":"Blackhand",
    "blackmoore":"Blackmoore",
    "blackrock":"Blackrock",
    "blackscar":"Blackscar",
    "bladefist":"Bladefist",
    "blades-edge":"Blade's Edge",
    "bloodfeather":"Bloodfeather",
    "bloodhoof":"Bloodhoof",
    "bloodscalp":"Bloodscalp",
    "blutkessel":"Blutkessel",
    "booty-bay":"Booty Bay",
    "borean-tundra":"Borean Tundra",
    "boulderfist":"Boulderfist",
    "bronze-dragonflight":"Bronze Dragonflight",
    "bronzebeard":"Bronzebeard",
    "burning-blade":"Burning Blade",
    "burning-legion":"Burning Legion",
    "burning-steppes":"Burning Steppes",
    "chamber-of-aspects":"Chamber of Aspects",
    "chants-eternels":"Chants \u00e9ternels",
    "chogall":"Cho'gall",
    "chromaggus":"Chromaggus",
    "colinas-pardas":"Colinas Pardas",
    "confrerie-du-thorium":"Confr\u00e9rie du Thorium",
    "conseil-des-ombres":"Conseil des Ombres",
    "crushridge":"Crushridge",
    "cthun":"C'Thun",
    "culte-de-la-rive-noire":"Culte de la Rive noire",
    "daggerspine":"Daggerspine",
    "dalaran":"Dalaran",
    "dalvengyr":"Dalvengyr",
    "darkmoon-faire":"Darkmoon Faire",
    "darksorrow":"Darksorrow",
    "darkspear":"Darkspear",
    "das-konsortium":"Das Konsortium",
    "das-syndikat":"Das Syndikat",
    "deathguard":"Deathguard",
    "deathweaver":"Deathweaver",
    "deathwing":"Deathwing",
    "deepholm":"Deepholm",
    "defias-brotherhood":"Defias Brotherhood",
    "dentarg":"Dentarg",
    "der-abyssische-rat":"Der abyssische Rat",
    "der-mithrilorden":"Der Mithrilorden",
    "der-rat-von-dalaran":"Der Rat von Dalaran",
    "destromath":"Destromath",
    "dethecus":"Dethecus",
    "die-aldor":"Die Aldor",
    "die-arguswacht":"Die Arguswacht",
    "die-ewige-wacht":"Die ewige Wacht",
    "die-nachtwache":"Die Nachtwache",
    "die-silberne-hand":"Die Silberne Hand",
    "die-todeskrallen":"Die Todeskrallen",
    "doomhammer":"Doomhammer",
    "draenor":"Draenor",
    "dragonblight":"Dragonblight",
    "dragonmaw":"Dragonmaw",
    "drakthul":"Drak'thul",
    "drekthar":"Drek'Thar",
    "dun-modr":"Dun Modr",
    "dun-morogh":"Dun Morogh",
    "dunemaul":"Dunemaul",
    "durotan":"Durotan",
    "earthen-ring":"Earthen Ring",
    "echsenkessel":"Echsenkessel",
    "eitrigg":"Eitrigg",
    "eldrethalas":"Eldre'Thalas",
    "elune":"Elune",
    "emerald-dream":"Emerald Dream",
    "emeriss":"Emeriss",
    "eonar":"Eonar",
    "eredar":"Eredar",
    "eversong":"Eversong",
    "executus":"Executus",
    "exodar":"Exodar",
    "festung-der-sturme":"Festung der St\u00fcrme",
    "fordragon":"Fordragon",
    "forscherliga":"Forscherliga",
    "frostmane":"Frostmane",
    "frostmourne":"Frostmourne",
    "frostwhisper":"Frostwhisper",
    "frostwolf":"Frostwolf",
    "galakrond":"Galakrond",
    "garona":"Garona",
    "garrosh":"Garrosh",
    "genjuros":"Genjuros",
    "ghostlands":"Ghostlands",
    "gilneas":"Gilneas",
    "goldrinn":"Goldrinn",
    "gordunni":"Gordunni",
    "gorgonnash":"Gorgonnash",
    "greymane":"Greymane",
    "grim-batol":"Grim Batol",
    "grom":"Grom",
    "guldan":"Gul'dan",
    "hakkar":"Hakkar",
    "haomarush":"Haomarush",
    "hellfire":"Hellfire",
    "hellscream":"Hellscream",
    "howling-fjord":"Howling Fjord",
    "hyjal":"Hyjal",
    "illidan":"Illidan",
    "jaedenar":"Jaedenar",
    "kaelthas":"Kael'thas",
    "karazhan":"Karazhan",
    "kargath":"Kargath",
    "kazzak":"Kazzak",
    "kelthuzad":"Kel'Thuzad",
    "khadgar":"Khadgar",
    "khaz-modan":"Khaz Modan",
    "khazgoroth":"Khaz'goroth",
    "kiljaeden":"Kil'jaeden",
    "kilrogg":"Kilrogg",
    "kirin-tor":"Kirin Tor",
    "korgall":"Kor'gall",
    "kragjin":"Krag'jin",
    "krasus":"Krasus",
    "kul-tiras":"Kul Tiras",
    "kult-der-verdammten":"Kult der Verdammten",
    "la-croisade-ecarlate":"La Croisade \u00e9carlate",
    "laughing-skull":"Laughing Skull",
    "les-clairvoyants":"Les Clairvoyants",
    "les-sentinelles":"Les Sentinelles",
    "lich-king":"Lich King",
    "lightbringer":"Lightbringer",
    "lightnings-blade":"Lightning's Blade",
    "lordaeron":"Lordaeron",
    "los-errantes":"Los Errantes",
    "lothar":"Lothar",
    "madmortem":"Madmortem",
    "magtheridon":"Magtheridon",
    "malfurion":"Malfurion",
    "malganis":"Mal'Ganis",
    "malorne":"Malorne",
    "malygos":"Malygos",
    "mannoroth":"Mannoroth",
    "marecage-de-zangar":"Mar\u00e9cage de Zangar",
    "mazrigos":"Mazrigos",
    "medivh":"Medivh",
    "minahonda":"Minahonda",
    "moonglade":"Moonglade",
    "mugthol":"Mug'thol",
    "nagrand":"Nagrand",
    "nathrezim":"Nathrezim",
    "naxxramas":"Naxxramas",
    "nazjatar":"Nazjatar",
    "nefarian":"Nefarian",
    "nemesis":"Nemesis",
    "neptulon":"Neptulon",
    "nerathor":"Nera'thor",
    "nerzhul":"Ner'zhul",
    "nethersturm":"Nethersturm",
    "nordrassil":"Nordrassil",
    "norgannon":"Norgannon",
    "nozdormu":"Nozdormu",
    "onyxia":"Onyxia",
    "outland":"Outland",
    "perenolde":"Perenolde",
    "pozzo-delleternita":"Pozzo dell'Eternit\u00e0",
    "proudmoore":"Proudmoore",
    "quelthalas":"Quel'Thalas",
    "ragnaros":"Ragnaros",
    "rajaxx":"Rajaxx",
    "rashgarroth":"Rashgarroth",
    "ravencrest":"Ravencrest",
    "ravenholdt":"Ravenholdt",
    "razuvious":"Razuvious",
    "rexxar":"Rexxar",
    "runetotem":"Runetotem",
    "sanguino":"Sanguino",
    "sargeras":"Sargeras",
    "saurfang":"Saurfang",
    "scarshield-legion":"Scarshield Legion",
    "senjin":"Sen'jin",
    "shadowsong":"Shadowsong",
    "shattered-halls":"Shattered Halls",
    "shattered-hand":"Shattered Hand",
    "shattrath":"Shattrath",
    "shendralar":"Shen'dralar",
    "silvermoon":"Silvermoon",
    "sinstralis":"Sinstralis",
    "skullcrusher":"Skullcrusher",
    "soulflayer":"Soulflayer",
    "spinebreaker":"Spinebreaker",
    "sporeggar":"Sporeggar",
    "steamwheedle-cartel":"Steamwheedle Cartel",
    "stormrage":"Stormrage",
    "stormreaver":"Stormreaver",
    "stormscale":"Stormscale",
    "sunstrider":"Sunstrider",
    "suramar":"Suramar",
    "sylvanas":"Sylvanas",
    "taerar":"Taerar",
    "talnivarr":"Talnivarr",
    "tarren-mill":"Tarren Mill",
    "teldrassil":"Teldrassil",
    "temple-noir":"Temple noir",
    "terenas":"Terenas",
    "terokkar":"Terokkar",
    "terrordar":"Terrordar",
    "the-maelstrom":"The Maelstrom",
    "the-shatar":"The Sha'tar",
    "the-venture-co":"The Venture Co",
    "theradras":"Theradras",
    "thermaplugg":"Thermaplugg",
    "thrall":"Thrall",
    "throkferoth":"Throk'Feroth",
    "thunderhorn":"Thunderhorn",
    "tichondrius":"Tichondrius",
    "tirion":"Tirion",
    "todeswache":"Todeswache",
    "trollbane":"Trollbane",
    "turalyon":"Turalyon",
    "twilights-hammer":"Twilight's Hammer",
    "twisting-nether":"Twisting Nether",
    "tyrande":"Tyrande",
    "uldaman":"Uldaman",
    "ulduar":"Ulduar",
    "uldum":"Uldum",
    "ungoro":"Un'Goro",
    "varimathras":"Varimathras",
    "vashj":"Vashj",
    "veklor":"Vek'lor",
    "veknilash":"Vek'nilash",
    "voljin":"Vol'jin",
    "wildhammer":"Wildhammer",
    "wrathbringer":"Wrathbringer",
    "xavius":"Xavius",
    "ysera":"Ysera",
    "ysondre":"Ysondre",
    "zenedar":"Zenedar",
    "zirkel-des-cenarius":"Zirkel des Cenarius",
    "zuljin":"Zul'jin",
    "zuluhed":"Zuluhed"
}}
EOF
);

use \Newsstand\HTTP;

RunMeNTimes(1);
CatchKill();

$startTime = time();

$connectionTracking = [
    'created' => 0,
    'requests' => 0,
];

$file = [];
$file['note'] = 'Brought to you by https://does.theapi.work/';
$file['started'] = JSNow();
foreach (['us','eu'] as $region) {
    $file['regions'][$region] = FetchRegionData($region);
    if ($caughtKill) {
        break;
    }
}
$file['finished'] = JSNow();

if (!$caughtKill) {
    $fn = isset($argv[1]) ? $argv[1] : __DIR__.'/../theapi.work/times.json';

    AtomicFilePutContents($fn, json_encode($file, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
}

DebugMessage("Opened {$connectionTracking['created']} connections to service {$connectionTracking['requests']} requests.");
DebugMessage('Done! Started ' . TimeDiff($startTime, ['precision'=>'second']));

function JSNow() {
    return floor(microtime(true) * 1000);
}

function FetchRegionData($region) {
    global $caughtKill;

    $region = trim(strtolower($region));

    $results = [];

    DebugMessage("Fetching realms for $region");

    $url = GetBattleNetURL($region, 'wow/realm/status');
    $jsonString = HTTP::Get($url);
    $json = json_decode($jsonString, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("Error decoding ".strlen($jsonString)." length JSON string for $region: ".json_last_error_msg(), E_USER_WARNING);
        return $results;
    }
    $showWhenMissing = true;
    if (!isset($json['realms'])) {
        DebugMessage("Did not find realms in realm status JSON for $region", E_USER_WARNING);
        $json['realms'] = [];
        $showWhenMissing = false;
    }

    $ourList = json_decode(REALM_LIST_JSON, true);
    $ourList = $ourList[$region];
    foreach ($json['realms'] as $realmRow) {
        if (isset($realmRow['slug'])) {
            unset($ourList[$realmRow['slug']]);
        }
    }
    foreach ($ourList as $slug => $name) {
        $json['realms'][] = [
            'slug' => $slug,
            'name' => $name,
            'missing' => $showWhenMissing,
        ];
    }

    $slugMap = [];

    foreach ($json['realms'] as $realmRow) {
        if ($caughtKill) {
            break;
        }
        if (!isset($realmRow['slug'])) {
            continue;
        }
        $slug = $realmRow['slug'];
        if (isset($results[$slug])) {
            $results[$slug]['name'] = $realmRow['name'];
            continue;
        }

        $resultRow = [
            'name' => $realmRow['name'],
            'canonical' => 1,
        ];
        if (isset($realmRow['missing']) && $realmRow['missing']) {
            $resultRow['realmmissing'] = true;
        }

        $results[$slug] = $resultRow;
        $slugMap[$slug] = [$slug];

        if (isset($realmRow['connected_realms'])) {
            foreach ($realmRow['connected_realms'] as $connectedSlug) {
                if ($connectedSlug == $slug) {
                    continue;
                }
                $results[$connectedSlug] = [
                    'name' => '',
                ];
                $slugMap[$slug][] = $connectedSlug;
            }
        }
    }

    $chunks = array_chunk($slugMap, REALM_CHUNK_SIZE, true);
    foreach ($chunks as $chunk) {
        DebugMessage("Fetching auction data for $region ".implode(', ', array_keys($chunk)));
        $urls = [];
        foreach (array_keys($chunk) as $slug) {
            $urls[$slug] = GetBattleNetURL($region, 'wow/auction/data/' . $slug);
        }

        $started = JSNow();
        $dataUrls = [];
        $jsons = FetchURLBatch($urls);

        foreach ($chunk as $slug => $slugs) {
            $json = [];
            if (!isset($jsons[$slug])) {
                DebugMessage("No HTTP response for $region $slug", E_USER_WARNING);
            } else {
                $json = json_decode($jsons[$slug], true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    DebugMessage("Error decoding JSON string for $region $slug: " . json_last_error_msg(), E_USER_WARNING);
                    $json = [];
                }
            }

            $error = false;
            if (isset($json['status']) && $json['status'] == 'nok' && isset($json['reason'])) {
                $error = $json['reason'];
            }

            $modified = isset($json['files'][0]['lastModified']) ? $json['files'][0]['lastModified'] : 0;
            $url = isset($json['files'][0]['url']) ? $json['files'][0]['url'] : '';
            if ($url) {
                $dataUrls[$slug] = $url;
            }
            foreach ($slugs as $connectedSlug) {
                $results[$connectedSlug]['checked'] = $started;
                $results[$connectedSlug]['modified'] = $modified;
                if ($error) {
                    $results[$connectedSlug]['statuserror'] = $error;
                }
            }
        }

        $dataHeads = FetchURLBatch($dataUrls, [
            CURLOPT_HEADER => true,
            CURLOPT_RANGE => '0-2048',
        ]);
        foreach ($chunk as $slug => $slugs) {
            $fileDate = 0;
            $found = [];
            if (isset($dataHeads[$slug])) {
                $header = substr($dataHeads[$slug], 0, strpos($dataHeads[$slug], "\r\n\r\n"));
                $body = substr($dataHeads[$slug], strlen($header) + 4);

                if (preg_match('/(?:^|\n)Last-Modified: ([^\n\r]+)/i', $header, $res)) {
                    $fileDate = strtotime($res[1]) * 1000;
                } elseif ($header) {
                    DebugMessage("Found no last-modified header for $region $slug at " . $dataUrls[$slug] . "\n" . $header, E_USER_WARNING);
                }

                if (preg_match('/"realms":\s*(\[[^\]]*\])/', $body, $res)) {
                    $dataRealms = json_decode($res[1], true);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        DebugMessage("JSON error decoding realms from $region $slug data file\n$body", E_USER_WARNING);
                    } else {
                        foreach ($dataRealms as $dataRealm) {
                            if (isset($dataRealm['slug'])) {
                                $found[$dataRealm['slug']] = $dataRealm;
                            }
                        }
                    }
                } else {
                    DebugMessage("Found no realms section in data file for $region $slug\n$body", E_USER_WARNING);
                }
            } elseif (isset($dataUrls[$slug])) {
                DebugMessage("Fetched no data file for $region $slug at " . $dataUrls[$slug], E_USER_WARNING);
            }
            foreach ($slugs as $connectedSlug) {
                $results[$connectedSlug]['file'] = $fileDate;
                if (!isset($found[$connectedSlug])) {
                    $results[$connectedSlug]['datamissing'] = count($found) ? current(array_keys($found)) : $slug;
                }
            }
        }
    }

    ksort($results);

    return $results;
}

function FetchURLBatch($urls, $curlOpts = []) {
    if (!$urls) {
        return [];
    }

    global $connectionTracking;

    $curlOpts = [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 2,
        CURLOPT_TIMEOUT         => 10,
    ] + $curlOpts;

    if (!isset($curlOpts[CURLOPT_RANGE]) && !isset($curlOpts[CURLOPT_ENCODING])) {
        $curlOpts[CURLOPT_ENCODING] = 'gzip';
    }

    static $mh = false;
    if ($mh === false) {
        $mh = curl_multi_init();
        // old curl forces pipelining on one connection if we ask for it and the server supports it
        // this is slower than just opening multiple connections like we want to with curl_multi
        // also, old curl doesn't interpret the http2 flag properly, and thinks we want pipelining if we just set "2" here
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, 0);
    }

    $results = [];
    $curls = [];

    foreach ($urls as $k => $url) {
        $curls[$k] = curl_init($url);
        curl_setopt_array($curls[$k], $curlOpts);
        curl_multi_add_handle($mh, $curls[$k]);
    }

    $active = false;
    do {
        while (CURLM_CALL_MULTI_PERFORM == ($mrc = curl_multi_exec($mh, $active)));
        if ($active) {
            usleep(100000);
        }
    } while ($active && $mrc == CURLM_OK);

    foreach ($urls as $k => $url) {
        $results[$k] = curl_multi_getcontent($curls[$k]);
        $connectionTracking['created'] += curl_getinfo($curls[$k], CURLINFO_NUM_CONNECTS);
        $connectionTracking['requests']++;

        curl_multi_remove_handle($mh, $curls[$k]);
        curl_close($curls[$k]);
    }

    return $results;
}

function AtomicFilePutContents($path, $data) {
    $aPath = "$path.atomic";
    file_put_contents($aPath, $data);

    static $hasZopfli = null, $hasBrotli = null;
    if (is_null($hasZopfli)) {
        $hasZopfli = is_executable(ZOPFLI_PATH);
    }
    if (is_null($hasBrotli)) {
        $hasBrotli = is_executable(BROTLI_PATH);
    }
    $o = [];
    $ret = $retBrotli = 0;
    $zaPath = "$aPath.gz";
    $zPath = "$path.gz";
    $baPath = "$aPath.br";
    $bPath = "$path.br";

    $dataPath = $aPath;

    exec(($hasZopfli ? escapeshellcmd(ZOPFLI_PATH) : 'gzip') . ' -c ' . escapeshellarg($dataPath) . ' > ' . escapeshellarg($zaPath), $o, $ret);
    if ($hasBrotli && $ret == 0) {
        exec(escapeshellcmd(BROTLI_PATH) . ' --input ' . escapeshellarg($dataPath) . ' > ' . escapeshellarg($baPath), $o, $retBrotli);
    }

    if ($ret != 0) {
        if (file_exists($baPath)) {
            unlink($baPath);
        }
        if (file_exists($bPath)) {
            unlink($bPath);
        }
        if (file_exists($zaPath)) {
            unlink($zaPath);
        }
        if (file_exists($zPath)) {
            unlink($zPath);
        }
    } else {
        $tm = filemtime($aPath);
        touch($aPath, $tm); // wipes out fractional seconds
        touch($zaPath, $tm); // identical time to $aPath
        rename($zaPath, $zPath);
        if ($retBrotli != 0) {
            if (file_exists($baPath)) {
                unlink($baPath);
            }
            if (file_exists($bPath)) {
                unlink($bPath);
            }
        } else {
            touch($baPath, $tm); // identical time to $aPath
            rename($baPath, $bPath);
        }
    }
    rename($aPath, $path);
}
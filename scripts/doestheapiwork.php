<?php

require_once __DIR__.'/../incl/incl.php';
require_once __DIR__.'/../incl/battlenet.incl.php';

define('ZOPFLI_PATH', __DIR__.'/zopfli');
define('BROTLI_PATH', __DIR__.'/brotli/bin/bro');
define('REALM_CHUNK_SIZE', 12);
define('FETCH_REALM_LIST', false);

define('REALM_LIST_JSON', <<<'EOF'
{"us":{
    "1": {
        "connectedId": 3694,
        "slug": "lightbringer",
        "name": "Lightbringer"
    },
    "2": {
        "connectedId": 1168,
        "slug": "cenarius",
        "name": "Cenarius"
    },
    "3": {
        "connectedId": 151,
        "slug": "uther",
        "name": "Uther"
    },
    "4": {
        "connectedId": 4,
        "slug": "kilrogg",
        "name": "Kilrogg"
    },
    "5": {
        "connectedId": 5,
        "slug": "proudmoore",
        "name": "Proudmoore"
    },
    "6": {
        "connectedId": 3661,
        "slug": "hyjal",
        "name": "Hyjal"
    },
    "7": {
        "connectedId": 7,
        "slug": "frostwolf",
        "name": "Frostwolf"
    },
    "8": {
        "connectedId": 128,
        "slug": "nerzhul",
        "name": "Ner'zhul"
    },
    "9": {
        "connectedId": 9,
        "slug": "kiljaeden",
        "name": "Kil'jaeden"
    },
    "10": {
        "connectedId": 10,
        "slug": "blackrock",
        "name": "Blackrock"
    },
    "11": {
        "connectedId": 11,
        "slug": "tichondrius",
        "name": "Tichondrius"
    },
    "12": {
        "connectedId": 12,
        "slug": "silver-hand",
        "name": "Silver Hand"
    },
    "13": {
        "connectedId": 1190,
        "slug": "doomhammer",
        "name": "Doomhammer"
    },
    "14": {
        "connectedId": 104,
        "slug": "icecrown",
        "name": "Icecrown"
    },
    "15": {
        "connectedId": 155,
        "slug": "deathwing",
        "name": "Deathwing"
    },
    "16": {
        "connectedId": 3693,
        "slug": "kelthuzad",
        "name": "Kel'Thuzad"
    },
    "47": {
        "connectedId": 47,
        "slug": "eitrigg",
        "name": "Eitrigg"
    },
    "51": {
        "connectedId": 51,
        "slug": "garona",
        "name": "Garona"
    },
    "52": {
        "connectedId": 52,
        "slug": "alleria",
        "name": "Alleria"
    },
    "53": {
        "connectedId": 53,
        "slug": "hellscream",
        "name": "Hellscream"
    },
    "54": {
        "connectedId": 54,
        "slug": "blackhand",
        "name": "Blackhand"
    },
    "55": {
        "connectedId": 55,
        "slug": "whisperwind",
        "name": "Whisperwind"
    },
    "56": {
        "connectedId": 1129,
        "slug": "archimonde",
        "name": "Archimonde"
    },
    "57": {
        "connectedId": 57,
        "slug": "illidan",
        "name": "Illidan"
    },
    "58": {
        "connectedId": 58,
        "slug": "stormreaver",
        "name": "Stormreaver"
    },
    "59": {
        "connectedId": 3684,
        "slug": "malganis",
        "name": "Mal'Ganis"
    },
    "60": {
        "connectedId": 60,
        "slug": "stormrage",
        "name": "Stormrage"
    },
    "61": {
        "connectedId": 61,
        "slug": "zuljin",
        "name": "Zul'jin"
    },
    "62": {
        "connectedId": 62,
        "slug": "medivh",
        "name": "Medivh"
    },
    "63": {
        "connectedId": 63,
        "slug": "durotan",
        "name": "Durotan"
    },
    "64": {
        "connectedId": 64,
        "slug": "bloodhoof",
        "name": "Bloodhoof"
    },
    "65": {
        "connectedId": 52,
        "slug": "khadgar",
        "name": "Khadgar"
    },
    "66": {
        "connectedId": 3683,
        "slug": "dalaran",
        "name": "Dalaran"
    },
    "67": {
        "connectedId": 67,
        "slug": "elune",
        "name": "Elune"
    },
    "68": {
        "connectedId": 68,
        "slug": "lothar",
        "name": "Lothar"
    },
    "69": {
        "connectedId": 69,
        "slug": "arthas",
        "name": "Arthas"
    },
    "70": {
        "connectedId": 70,
        "slug": "mannoroth",
        "name": "Mannoroth"
    },
    "71": {
        "connectedId": 71,
        "slug": "warsong",
        "name": "Warsong"
    },
    "72": {
        "connectedId": 157,
        "slug": "shattered-hand",
        "name": "Shattered Hand"
    },
    "73": {
        "connectedId": 73,
        "slug": "bleeding-hollow",
        "name": "Bleeding Hollow"
    },
    "74": {
        "connectedId": 74,
        "slug": "skullcrusher",
        "name": "Skullcrusher"
    },
    "75": {
        "connectedId": 75,
        "slug": "argent-dawn",
        "name": "Argent Dawn"
    },
    "76": {
        "connectedId": 76,
        "slug": "sargeras",
        "name": "Sargeras"
    },
    "77": {
        "connectedId": 77,
        "slug": "azgalor",
        "name": "Azgalor"
    },
    "78": {
        "connectedId": 78,
        "slug": "magtheridon",
        "name": "Magtheridon"
    },
    "79": {
        "connectedId": 77,
        "slug": "destromath",
        "name": "Destromath"
    },
    "80": {
        "connectedId": 71,
        "slug": "gorgonnash",
        "name": "Gorgonnash"
    },
    "81": {
        "connectedId": 154,
        "slug": "dethecus",
        "name": "Dethecus"
    },
    "82": {
        "connectedId": 159,
        "slug": "spinebreaker",
        "name": "Spinebreaker"
    },
    "83": {
        "connectedId": 1136,
        "slug": "bonechewer",
        "name": "Bonechewer"
    },
    "84": {
        "connectedId": 84,
        "slug": "dragonmaw",
        "name": "Dragonmaw"
    },
    "85": {
        "connectedId": 85,
        "slug": "shadowsong",
        "name": "Shadowsong"
    },
    "86": {
        "connectedId": 86,
        "slug": "silvermoon",
        "name": "Silvermoon"
    },
    "87": {
        "connectedId": 87,
        "slug": "windrunner",
        "name": "Windrunner"
    },
    "88": {
        "connectedId": 1169,
        "slug": "cenarion-circle",
        "name": "Cenarion Circle"
    },
    "89": {
        "connectedId": 1138,
        "slug": "nathrezim",
        "name": "Nathrezim"
    },
    "90": {
        "connectedId": 90,
        "slug": "terenas",
        "name": "Terenas"
    },
    "91": {
        "connectedId": 91,
        "slug": "burning-blade",
        "name": "Burning Blade"
    },
    "92": {
        "connectedId": 159,
        "slug": "gorefiend",
        "name": "Gorefiend"
    },
    "93": {
        "connectedId": 159,
        "slug": "eredar",
        "name": "Eredar"
    },
    "94": {
        "connectedId": 154,
        "slug": "shadowmoon",
        "name": "Shadowmoon"
    },
    "95": {
        "connectedId": 91,
        "slug": "lightnings-blade",
        "name": "Lightning's Blade"
    },
    "96": {
        "connectedId": 96,
        "slug": "eonar",
        "name": "Eonar"
    },
    "97": {
        "connectedId": 67,
        "slug": "gilneas",
        "name": "Gilneas"
    },
    "98": {
        "connectedId": 98,
        "slug": "kargath",
        "name": "Kargath"
    },
    "99": {
        "connectedId": 99,
        "slug": "llane",
        "name": "Llane"
    },
    "100": {
        "connectedId": 100,
        "slug": "earthen-ring",
        "name": "Earthen Ring"
    },
    "101": {
        "connectedId": 101,
        "slug": "laughing-skull",
        "name": "Laughing Skull"
    },
    "102": {
        "connectedId": 1129,
        "slug": "burning-legion",
        "name": "Burning Legion"
    },
    "103": {
        "connectedId": 77,
        "slug": "thunderlord",
        "name": "Thunderlord"
    },
    "104": {
        "connectedId": 104,
        "slug": "malygos",
        "name": "Malygos"
    },
    "105": {
        "connectedId": 105,
        "slug": "thunderhorn",
        "name": "Thunderhorn"
    },
    "106": {
        "connectedId": 106,
        "slug": "aggramar",
        "name": "Aggramar"
    },
    "107": {
        "connectedId": 1138,
        "slug": "crushridge",
        "name": "Crushridge"
    },
    "108": {
        "connectedId": 119,
        "slug": "stonemaul",
        "name": "Stonemaul"
    },
    "109": {
        "connectedId": 1136,
        "slug": "daggerspine",
        "name": "Daggerspine"
    },
    "110": {
        "connectedId": 127,
        "slug": "stormscale",
        "name": "Stormscale"
    },
    "111": {
        "connectedId": 119,
        "slug": "dunemaul",
        "name": "Dunemaul"
    },
    "112": {
        "connectedId": 119,
        "slug": "boulderfist",
        "name": "Boulderfist"
    },
    "113": {
        "connectedId": 113,
        "slug": "suramar",
        "name": "Suramar"
    },
    "114": {
        "connectedId": 114,
        "slug": "dragonblight",
        "name": "Dragonblight"
    },
    "115": {
        "connectedId": 115,
        "slug": "draenor",
        "name": "Draenor"
    },
    "116": {
        "connectedId": 116,
        "slug": "uldum",
        "name": "Uldum"
    },
    "117": {
        "connectedId": 117,
        "slug": "bronzebeard",
        "name": "Bronzebeard"
    },
    "118": {
        "connectedId": 118,
        "slug": "feathermoon",
        "name": "Feathermoon"
    },
    "119": {
        "connectedId": 119,
        "slug": "bloodscalp",
        "name": "Bloodscalp"
    },
    "120": {
        "connectedId": 120,
        "slug": "darkspear",
        "name": "Darkspear"
    },
    "121": {
        "connectedId": 121,
        "slug": "azjolnerub",
        "name": "Azjol-Nerub"
    },
    "122": {
        "connectedId": 122,
        "slug": "perenolde",
        "name": "Perenolde"
    },
    "123": {
        "connectedId": 123,
        "slug": "eldrethalas",
        "name": "Eldre'Thalas"
    },
    "124": {
        "connectedId": 127,
        "slug": "spirestone",
        "name": "Spirestone"
    },
    "125": {
        "connectedId": 125,
        "slug": "shadow-council",
        "name": "Shadow Council"
    },
    "126": {
        "connectedId": 118,
        "slug": "scarlet-crusade",
        "name": "Scarlet Crusade"
    },
    "127": {
        "connectedId": 127,
        "slug": "firetree",
        "name": "Firetree"
    },
    "128": {
        "connectedId": 128,
        "slug": "frostmane",
        "name": "Frostmane"
    },
    "129": {
        "connectedId": 1136,
        "slug": "gurubashi",
        "name": "Gurubashi"
    },
    "130": {
        "connectedId": 1138,
        "slug": "smolderthorn",
        "name": "Smolderthorn"
    },
    "131": {
        "connectedId": 131,
        "slug": "skywall",
        "name": "Skywall"
    },
    "151": {
        "connectedId": 151,
        "slug": "runetotem",
        "name": "Runetotem"
    },
    "153": {
        "connectedId": 153,
        "slug": "moonrunner",
        "name": "Moonrunner"
    },
    "154": {
        "connectedId": 154,
        "slug": "detheroc",
        "name": "Detheroc"
    },
    "155": {
        "connectedId": 155,
        "slug": "kalecgos",
        "name": "Kalecgos"
    },
    "156": {
        "connectedId": 156,
        "slug": "ursin",
        "name": "Ursin"
    },
    "157": {
        "connectedId": 157,
        "slug": "dark-iron",
        "name": "Dark Iron"
    },
    "158": {
        "connectedId": 158,
        "slug": "greymane",
        "name": "Greymane"
    },
    "159": {
        "connectedId": 159,
        "slug": "wildhammer",
        "name": "Wildhammer"
    },
    "160": {
        "connectedId": 160,
        "slug": "staghelm",
        "name": "Staghelm"
    },
    "162": {
        "connectedId": 162,
        "slug": "emerald-dream",
        "name": "Emerald Dream"
    },
    "163": {
        "connectedId": 163,
        "slug": "maelstrom",
        "name": "Maelstrom"
    },
    "164": {
        "connectedId": 164,
        "slug": "twisting-nether",
        "name": "Twisting Nether"
    },
    "1067": {
        "connectedId": 101,
        "slug": "chogall",
        "name": "Cho'gall"
    },
    "1068": {
        "connectedId": 74,
        "slug": "guldan",
        "name": "Gul'dan"
    },
    "1069": {
        "connectedId": 1069,
        "slug": "kaelthas",
        "name": "Kael'thas"
    },
    "1070": {
        "connectedId": 1070,
        "slug": "alexstrasza",
        "name": "Alexstrasza"
    },
    "1071": {
        "connectedId": 1071,
        "slug": "kirin-tor",
        "name": "Kirin Tor"
    },
    "1072": {
        "connectedId": 1072,
        "slug": "ravencrest",
        "name": "Ravencrest"
    },
    "1075": {
        "connectedId": 71,
        "slug": "balnazzar",
        "name": "Balnazzar"
    },
    "1128": {
        "connectedId": 77,
        "slug": "azshara",
        "name": "Azshara"
    },
    "1129": {
        "connectedId": 1129,
        "slug": "agamaggan",
        "name": "Agamaggan"
    },
    "1130": {
        "connectedId": 163,
        "slug": "lightninghoof",
        "name": "Lightninghoof"
    },
    "1131": {
        "connectedId": 70,
        "slug": "nazjatar",
        "name": "Nazjatar"
    },
    "1132": {
        "connectedId": 1175,
        "slug": "malfurion",
        "name": "Malfurion"
    },
    "1136": {
        "connectedId": 1136,
        "slug": "aegwynn",
        "name": "Aegwynn"
    },
    "1137": {
        "connectedId": 84,
        "slug": "akama",
        "name": "Akama"
    },
    "1138": {
        "connectedId": 1138,
        "slug": "chromaggus",
        "name": "Chromaggus"
    },
    "1139": {
        "connectedId": 113,
        "slug": "draka",
        "name": "Draka"
    },
    "1140": {
        "connectedId": 131,
        "slug": "drakthul",
        "name": "Drak'thul"
    },
    "1141": {
        "connectedId": 1138,
        "slug": "garithos",
        "name": "Garithos"
    },
    "1142": {
        "connectedId": 1136,
        "slug": "hakkar",
        "name": "Hakkar"
    },
    "1143": {
        "connectedId": 121,
        "slug": "khaz-modan",
        "name": "Khaz Modan"
    },
    "1145": {
        "connectedId": 84,
        "slug": "mugthol",
        "name": "Mug'thol"
    },
    "1146": {
        "connectedId": 1146,
        "slug": "korgath",
        "name": "Korgath"
    },
    "1147": {
        "connectedId": 1147,
        "slug": "kul-tiras",
        "name": "Kul Tiras"
    },
    "1148": {
        "connectedId": 127,
        "slug": "malorne",
        "name": "Malorne"
    },
    "1151": {
        "connectedId": 1151,
        "slug": "rexxar",
        "name": "Rexxar"
    },
    "1154": {
        "connectedId": 12,
        "slug": "thorium-brotherhood",
        "name": "Thorium Brotherhood"
    },
    "1165": {
        "connectedId": 1165,
        "slug": "arathor",
        "name": "Arathor"
    },
    "1173": {
        "connectedId": 1173,
        "slug": "madoran",
        "name": "Madoran"
    },
    "1175": {
        "connectedId": 1175,
        "slug": "trollbane",
        "name": "Trollbane"
    },
    "1182": {
        "connectedId": 1182,
        "slug": "muradin",
        "name": "Muradin"
    },
    "1184": {
        "connectedId": 1184,
        "slug": "veknilash",
        "name": "Vek'nilash"
    },
    "1185": {
        "connectedId": 1185,
        "slug": "senjin",
        "name": "Sen'jin"
    },
    "1190": {
        "connectedId": 1190,
        "slug": "baelgun",
        "name": "Baelgun"
    },
    "1258": {
        "connectedId": 64,
        "slug": "duskwood",
        "name": "Duskwood"
    },
    "1259": {
        "connectedId": 156,
        "slug": "zuluhed",
        "name": "Zuluhed"
    },
    "1260": {
        "connectedId": 1071,
        "slug": "steamwheedle-cartel",
        "name": "Steamwheedle Cartel"
    },
    "1262": {
        "connectedId": 98,
        "slug": "norgannon",
        "name": "Norgannon"
    },
    "1263": {
        "connectedId": 3678,
        "slug": "thrall",
        "name": "Thrall"
    },
    "1264": {
        "connectedId": 78,
        "slug": "anetheron",
        "name": "Anetheron"
    },
    "1265": {
        "connectedId": 3685,
        "slug": "turalyon",
        "name": "Turalyon"
    },
    "1266": {
        "connectedId": 154,
        "slug": "haomarush",
        "name": "Haomarush"
    },
    "1267": {
        "connectedId": 156,
        "slug": "scilla",
        "name": "Scilla"
    },
    "1268": {
        "connectedId": 78,
        "slug": "ysondre",
        "name": "Ysondre"
    },
    "1270": {
        "connectedId": 63,
        "slug": "ysera",
        "name": "Ysera"
    },
    "1271": {
        "connectedId": 55,
        "slug": "dentarg",
        "name": "Dentarg"
    },
    "1276": {
        "connectedId": 156,
        "slug": "andorhal",
        "name": "Andorhal"
    },
    "1277": {
        "connectedId": 155,
        "slug": "executus",
        "name": "Executus"
    },
    "1278": {
        "connectedId": 157,
        "slug": "dalvengyr",
        "name": "Dalvengyr"
    },
    "1280": {
        "connectedId": 74,
        "slug": "black-dragonflight",
        "name": "Black Dragonflight"
    },
    "1282": {
        "connectedId": 78,
        "slug": "altar-of-storms",
        "name": "Altar of Storms"
    },
    "1283": {
        "connectedId": 1072,
        "slug": "uldaman",
        "name": "Uldaman"
    },
    "1284": {
        "connectedId": 1426,
        "slug": "aerie-peak",
        "name": "Aerie Peak"
    },
    "1285": {
        "connectedId": 91,
        "slug": "onyxia",
        "name": "Onyxia"
    },
    "1286": {
        "connectedId": 157,
        "slug": "demon-soul",
        "name": "Demon Soul"
    },
    "1287": {
        "connectedId": 153,
        "slug": "gnomeregan",
        "name": "Gnomeregan"
    },
    "1288": {
        "connectedId": 1174,
        "slug": "anvilmar",
        "name": "Anvilmar"
    },
    "1289": {
        "connectedId": 163,
        "slug": "the-venture-co",
        "name": "The Venture Co"
    },
    "1290": {
        "connectedId": 1071,
        "slug": "sentinels",
        "name": "Sentinels"
    },
    "1291": {
        "connectedId": 1129,
        "slug": "jaedenar",
        "name": "Jaedenar"
    },
    "1292": {
        "connectedId": 158,
        "slug": "tanaris",
        "name": "Tanaris"
    },
    "1293": {
        "connectedId": 71,
        "slug": "alterac-mountains",
        "name": "Alterac Mountains"
    },
    "1294": {
        "connectedId": 1174,
        "slug": "undermine",
        "name": "Undermine"
    },
    "1295": {
        "connectedId": 154,
        "slug": "lethon",
        "name": "Lethon"
    },
    "1296": {
        "connectedId": 154,
        "slug": "blackwing-lair",
        "name": "Blackwing Lair"
    },
    "1297": {
        "connectedId": 99,
        "slug": "arygos",
        "name": "Arygos"
    },
    "1342": {
        "connectedId": 115,
        "slug": "echo-isles",
        "name": "Echo Isles"
    },
    "1344": {
        "connectedId": 71,
        "slug": "the-forgotten-coast",
        "name": "The Forgotten Coast"
    },
    "1345": {
        "connectedId": 114,
        "slug": "fenris",
        "name": "Fenris"
    },
    "1346": {
        "connectedId": 1138,
        "slug": "anubarak",
        "name": "Anub'arak"
    },
    "1347": {
        "connectedId": 125,
        "slug": "blackwater-raiders",
        "name": "Blackwater Raiders"
    },
    "1348": {
        "connectedId": 7,
        "slug": "vashj",
        "name": "Vashj"
    },
    "1349": {
        "connectedId": 123,
        "slug": "korialstrasz",
        "name": "Korialstrasz"
    },
    "1350": {
        "connectedId": 1151,
        "slug": "misha",
        "name": "Misha"
    },
    "1351": {
        "connectedId": 87,
        "slug": "darrowmere",
        "name": "Darrowmere"
    },
    "1352": {
        "connectedId": 164,
        "slug": "ravenholdt",
        "name": "Ravenholdt"
    },
    "1353": {
        "connectedId": 1147,
        "slug": "bladefist",
        "name": "Bladefist"
    },
    "1354": {
        "connectedId": 47,
        "slug": "shuhalo",
        "name": "Shu'halo"
    },
    "1355": {
        "connectedId": 4,
        "slug": "winterhoof",
        "name": "Winterhoof"
    },
    "1356": {
        "connectedId": 1169,
        "slug": "sisters-of-elune",
        "name": "Sisters of Elune"
    },
    "1357": {
        "connectedId": 119,
        "slug": "maiev",
        "name": "Maiev"
    },
    "1358": {
        "connectedId": 127,
        "slug": "rivendare",
        "name": "Rivendare"
    },
    "1359": {
        "connectedId": 1182,
        "slug": "nordrassil",
        "name": "Nordrassil"
    },
    "1360": {
        "connectedId": 128,
        "slug": "tortheldrin",
        "name": "Tortheldrin"
    },
    "1361": {
        "connectedId": 122,
        "slug": "cairne",
        "name": "Cairne"
    },
    "1362": {
        "connectedId": 127,
        "slug": "draktharon",
        "name": "Drak'Tharon"
    },
    "1363": {
        "connectedId": 116,
        "slug": "antonidas",
        "name": "Antonidas"
    },
    "1364": {
        "connectedId": 117,
        "slug": "shandris",
        "name": "Shandris"
    },
    "1365": {
        "connectedId": 3675,
        "slug": "moon-guard",
        "name": "Moon Guard"
    },
    "1367": {
        "connectedId": 1184,
        "slug": "nazgrel",
        "name": "Nazgrel"
    },
    "1368": {
        "connectedId": 90,
        "slug": "hydraxis",
        "name": "Hydraxis"
    },
    "1369": {
        "connectedId": 1171,
        "slug": "wyrmrest-accord",
        "name": "Wyrmrest Accord"
    },
    "1370": {
        "connectedId": 12,
        "slug": "farstriders",
        "name": "Farstriders"
    },
    "1371": {
        "connectedId": 85,
        "slug": "borean-tundra",
        "name": "Borean Tundra"
    },
    "1372": {
        "connectedId": 1185,
        "slug": "queldorei",
        "name": "Quel'dorei"
    },
    "1373": {
        "connectedId": 3677,
        "slug": "garrosh",
        "name": "Garrosh"
    },
    "1374": {
        "connectedId": 86,
        "slug": "moknathal",
        "name": "Mok'Nathal"
    },
    "1375": {
        "connectedId": 1184,
        "slug": "nesingwary",
        "name": "Nesingwary"
    },
    "1377": {
        "connectedId": 1165,
        "slug": "drenden",
        "name": "Drenden"
    },
    "1425": {
        "connectedId": 1425,
        "slug": "drakkari",
        "name": "Drakkari"
    },
    "1427": {
        "connectedId": 1427,
        "slug": "ragnaros",
        "name": "Ragnaros"
    },
    "1428": {
        "connectedId": 1428,
        "slug": "quelthalas",
        "name": "Quel'Thalas"
    },
    "1549": {
        "connectedId": 160,
        "slug": "azuremyst",
        "name": "Azuremyst"
    },
    "1555": {
        "connectedId": 101,
        "slug": "auchindoun",
        "name": "Auchindoun"
    },
    "1556": {
        "connectedId": 157,
        "slug": "coilfang",
        "name": "Coilfang"
    },
    "1557": {
        "connectedId": 155,
        "slug": "shattered-halls",
        "name": "Shattered Halls"
    },
    "1558": {
        "connectedId": 70,
        "slug": "blood-furnace",
        "name": "Blood Furnace"
    },
    "1559": {
        "connectedId": 1129,
        "slug": "the-underbog",
        "name": "The Underbog"
    },
    "1563": {
        "connectedId": 1070,
        "slug": "terokkar",
        "name": "Terokkar"
    },
    "1564": {
        "connectedId": 105,
        "slug": "blades-edge",
        "name": "Blade's Edge"
    },
    "1565": {
        "connectedId": 62,
        "slug": "exodar",
        "name": "Exodar"
    },
    "1566": {
        "connectedId": 3676,
        "slug": "area-52",
        "name": "Area 52"
    },
    "1567": {
        "connectedId": 96,
        "slug": "velen",
        "name": "Velen"
    },
    "1570": {
        "connectedId": 75,
        "slug": "the-scryers",
        "name": "The Scryers"
    },
    "1572": {
        "connectedId": 53,
        "slug": "zangarmarsh",
        "name": "Zangarmarsh"
    },
    "1576": {
        "connectedId": 106,
        "slug": "fizzcrank",
        "name": "Fizzcrank"
    },
    "1578": {
        "connectedId": 1069,
        "slug": "ghostlands",
        "name": "Ghostlands"
    },
    "1579": {
        "connectedId": 68,
        "slug": "grizzly-hills",
        "name": "Grizzly Hills"
    },
    "1581": {
        "connectedId": 54,
        "slug": "galakrond",
        "name": "Galakrond"
    },
    "1582": {
        "connectedId": 1173,
        "slug": "dawnbringer",
        "name": "Dawnbringer"
    },
    "3207": {
        "connectedId": 3207,
        "slug": "goldrinn",
        "name": "Goldrinn"
    },
    "3208": {
        "connectedId": 3208,
        "slug": "nemesis",
        "name": "Nemesis"
    },
    "3209": {
        "connectedId": 3209,
        "slug": "azralon",
        "name": "Azralon"
    },
    "3210": {
        "connectedId": 3208,
        "slug": "tol-barad",
        "name": "Tol Barad"
    },
    "3234": {
        "connectedId": 3234,
        "slug": "gallywix",
        "name": "Gallywix"
    },
    "3721": {
        "connectedId": 3721,
        "slug": "caelestrasz",
        "name": "Caelestrasz"
    },
    "3722": {
        "connectedId": 3722,
        "slug": "amanthul",
        "name": "Aman'Thul"
    },
    "3723": {
        "connectedId": 3723,
        "slug": "barthilas",
        "name": "Barthilas"
    },
    "3724": {
        "connectedId": 3724,
        "slug": "thaurissan",
        "name": "Thaurissan"
    },
    "3725": {
        "connectedId": 3725,
        "slug": "frostmourne",
        "name": "Frostmourne"
    },
    "3726": {
        "connectedId": 3726,
        "slug": "khazgoroth",
        "name": "Khaz'goroth"
    },
    "3733": {
        "connectedId": 3724,
        "slug": "dreadmaul",
        "name": "Dreadmaul"
    },
    "3734": {
        "connectedId": 3721,
        "slug": "nagrand",
        "name": "Nagrand"
    },
    "3735": {
        "connectedId": 3726,
        "slug": "dathremar",
        "name": "Dath'Remar"
    },
    "3736": {
        "connectedId": 3728,
        "slug": "jubeithos",
        "name": "Jubei'Thos"
    },
    "3737": {
        "connectedId": 3728,
        "slug": "gundrak",
        "name": "Gundrak"
    },
    "3738": {
        "connectedId": 3729,
        "slug": "saurfang",
        "name": "Saurfang"
    }
},
"eu": {
    "500": {
        "connectedId": 1325,
        "slug": "aggramar",
        "name": "Aggramar"
    },
    "501": {
        "connectedId": 1587,
        "slug": "arathor",
        "name": "Arathor"
    },
    "502": {
        "connectedId": 3666,
        "slug": "aszune",
        "name": "Aszune"
    },
    "503": {
        "connectedId": 1396,
        "slug": "azjolnerub",
        "name": "Azjol-Nerub"
    },
    "504": {
        "connectedId": 1080,
        "slug": "bloodhoof",
        "name": "Bloodhoof"
    },
    "505": {
        "connectedId": 1402,
        "slug": "doomhammer",
        "name": "Doomhammer"
    },
    "506": {
        "connectedId": 1403,
        "slug": "draenor",
        "name": "Draenor"
    },
    "507": {
        "connectedId": 1588,
        "slug": "dragonblight",
        "name": "Dragonblight"
    },
    "508": {
        "connectedId": 2074,
        "slug": "emerald-dream",
        "name": "Emerald Dream"
    },
    "509": {
        "connectedId": 509,
        "slug": "garona",
        "name": "Garona"
    },
    "510": {
        "connectedId": 510,
        "slug": "voljin",
        "name": "Vol'jin"
    },
    "511": {
        "connectedId": 1598,
        "slug": "sunstrider",
        "name": "Sunstrider"
    },
    "512": {
        "connectedId": 512,
        "slug": "arakarahm",
        "name": "Arak-arahm"
    },
    "513": {
        "connectedId": 1091,
        "slug": "twilights-hammer",
        "name": "Twilight's Hammer"
    },
    "515": {
        "connectedId": 3657,
        "slug": "zenedar",
        "name": "Zenedar"
    },
    "516": {
        "connectedId": 516,
        "slug": "forscherliga",
        "name": "Forscherliga"
    },
    "517": {
        "connectedId": 1331,
        "slug": "medivh",
        "name": "Medivh"
    },
    "518": {
        "connectedId": 1091,
        "slug": "agamaggan",
        "name": "Agamaggan"
    },
    "519": {
        "connectedId": 639,
        "slug": "alakir",
        "name": "Al'Akir"
    },
    "521": {
        "connectedId": 3657,
        "slug": "bladefist",
        "name": "Bladefist"
    },
    "522": {
        "connectedId": 1091,
        "slug": "bloodscalp",
        "name": "Bloodscalp"
    },
    "523": {
        "connectedId": 1092,
        "slug": "burning-blade",
        "name": "Burning Blade"
    },
    "524": {
        "connectedId": 3713,
        "slug": "burning-legion",
        "name": "Burning Legion"
    },
    "525": {
        "connectedId": 1091,
        "slug": "crushridge",
        "name": "Crushridge"
    },
    "526": {
        "connectedId": 1598,
        "slug": "daggerspine",
        "name": "Daggerspine"
    },
    "527": {
        "connectedId": 1596,
        "slug": "deathwing",
        "name": "Deathwing"
    },
    "528": {
        "connectedId": 3656,
        "slug": "dragonmaw",
        "name": "Dragonmaw"
    },
    "529": {
        "connectedId": 1597,
        "slug": "dunemaul",
        "name": "Dunemaul"
    },
    "531": {
        "connectedId": 531,
        "slug": "dethecus",
        "name": "Dethecus"
    },
    "533": {
        "connectedId": 1336,
        "slug": "sinstralis",
        "name": "Sinstralis"
    },
    "535": {
        "connectedId": 535,
        "slug": "durotan",
        "name": "Durotan"
    },
    "536": {
        "connectedId": 3702,
        "slug": "argent-dawn",
        "name": "Argent Dawn"
    },
    "537": {
        "connectedId": 3714,
        "slug": "kirin-tor",
        "name": "Kirin Tor"
    },
    "538": {
        "connectedId": 1621,
        "slug": "dalaran",
        "name": "Dalaran"
    },
    "539": {
        "connectedId": 1302,
        "slug": "archimonde",
        "name": "Archimonde"
    },
    "540": {
        "connectedId": 1315,
        "slug": "elune",
        "name": "Elune"
    },
    "541": {
        "connectedId": 1624,
        "slug": "illidan",
        "name": "Illidan"
    },
    "542": {
        "connectedId": 1390,
        "slug": "hyjal",
        "name": "Hyjal"
    },
    "543": {
        "connectedId": 512,
        "slug": "kaelthas",
        "name": "Kael'thas"
    },
    "544": {
        "connectedId": 509,
        "slug": "nerzhul",
        "name": "Ner'zhul"
    },
    "545": {
        "connectedId": 1336,
        "slug": "chogall",
        "name": "Cho'gall"
    },
    "546": {
        "connectedId": 509,
        "slug": "sargeras",
        "name": "Sargeras"
    },
    "547": {
        "connectedId": 1311,
        "slug": "runetotem",
        "name": "Runetotem"
    },
    "548": {
        "connectedId": 3666,
        "slug": "shadowsong",
        "name": "Shadowsong"
    },
    "549": {
        "connectedId": 3391,
        "slug": "silvermoon",
        "name": "Silvermoon"
    },
    "550": {
        "connectedId": 1417,
        "slug": "stormrage",
        "name": "Stormrage"
    },
    "551": {
        "connectedId": 2074,
        "slug": "terenas",
        "name": "Terenas"
    },
    "552": {
        "connectedId": 1313,
        "slug": "thunderhorn",
        "name": "Thunderhorn"
    },
    "553": {
        "connectedId": 1402,
        "slug": "turalyon",
        "name": "Turalyon"
    },
    "554": {
        "connectedId": 1329,
        "slug": "ravencrest",
        "name": "Ravencrest"
    },
    "556": {
        "connectedId": 633,
        "slug": "shattered-hand",
        "name": "Shattered Hand"
    },
    "557": {
        "connectedId": 639,
        "slug": "skullcrusher",
        "name": "Skullcrusher"
    },
    "558": {
        "connectedId": 3656,
        "slug": "spinebreaker",
        "name": "Spinebreaker"
    },
    "559": {
        "connectedId": 3656,
        "slug": "stormreaver",
        "name": "Stormreaver"
    },
    "560": {
        "connectedId": 2073,
        "slug": "stormscale",
        "name": "Stormscale"
    },
    "561": {
        "connectedId": 1317,
        "slug": "earthen-ring",
        "name": "Earthen Ring"
    },
    "562": {
        "connectedId": 1607,
        "slug": "alexstrasza",
        "name": "Alexstrasza"
    },
    "563": {
        "connectedId": 1099,
        "slug": "alleria",
        "name": "Alleria"
    },
    "564": {
        "connectedId": 3686,
        "slug": "antonidas",
        "name": "Antonidas"
    },
    "565": {
        "connectedId": 570,
        "slug": "baelgun",
        "name": "Baelgun"
    },
    "566": {
        "connectedId": 3691,
        "slug": "blackhand",
        "name": "Blackhand"
    },
    "567": {
        "connectedId": 567,
        "slug": "gilneas",
        "name": "Gilneas"
    },
    "568": {
        "connectedId": 568,
        "slug": "kargath",
        "name": "Kargath"
    },
    "569": {
        "connectedId": 1406,
        "slug": "khazgoroth",
        "name": "Khaz'goroth"
    },
    "570": {
        "connectedId": 570,
        "slug": "lothar",
        "name": "Lothar"
    },
    "571": {
        "connectedId": 3696,
        "slug": "madmortem",
        "name": "Madmortem"
    },
    "572": {
        "connectedId": 1098,
        "slug": "malfurion",
        "name": "Malfurion"
    },
    "573": {
        "connectedId": 1105,
        "slug": "zuluhed",
        "name": "Zuluhed"
    },
    "574": {
        "connectedId": 1401,
        "slug": "nozdormu",
        "name": "Nozdormu"
    },
    "575": {
        "connectedId": 1407,
        "slug": "perenolde",
        "name": "Perenolde"
    },
    "576": {
        "connectedId": 1118,
        "slug": "die-silberne-hand",
        "name": "Die Silberne Hand"
    },
    "577": {
        "connectedId": 3679,
        "slug": "aegwynn",
        "name": "Aegwynn"
    },
    "578": {
        "connectedId": 578,
        "slug": "arthas",
        "name": "Arthas"
    },
    "579": {
        "connectedId": 579,
        "slug": "azshara",
        "name": "Azshara"
    },
    "580": {
        "connectedId": 580,
        "slug": "blackmoore",
        "name": "Blackmoore"
    },
    "581": {
        "connectedId": 581,
        "slug": "blackrock",
        "name": "Blackrock"
    },
    "582": {
        "connectedId": 612,
        "slug": "destromath",
        "name": "Destromath"
    },
    "583": {
        "connectedId": 3692,
        "slug": "eredar",
        "name": "Eredar"
    },
    "584": {
        "connectedId": 1105,
        "slug": "frostmourne",
        "name": "Frostmourne"
    },
    "585": {
        "connectedId": 3703,
        "slug": "frostwolf",
        "name": "Frostwolf"
    },
    "586": {
        "connectedId": 612,
        "slug": "gorgonnash",
        "name": "Gorgonnash"
    },
    "587": {
        "connectedId": 1104,
        "slug": "guldan",
        "name": "Gul'dan"
    },
    "588": {
        "connectedId": 578,
        "slug": "kelthuzad",
        "name": "Kel'Thuzad"
    },
    "589": {
        "connectedId": 1104,
        "slug": "kiljaeden",
        "name": "Kil'jaeden"
    },
    "590": {
        "connectedId": 1612,
        "slug": "malganis",
        "name": "Mal'Ganis"
    },
    "591": {
        "connectedId": 612,
        "slug": "mannoroth",
        "name": "Mannoroth"
    },
    "592": {
        "connectedId": 1405,
        "slug": "zirkel-des-cenarius",
        "name": "Zirkel des Cenarius"
    },
    "593": {
        "connectedId": 3696,
        "slug": "proudmoore",
        "name": "Proudmoore"
    },
    "594": {
        "connectedId": 1104,
        "slug": "nathrezim",
        "name": "Nathrezim"
    },
    "600": {
        "connectedId": 1408,
        "slug": "dun-morogh",
        "name": "Dun Morogh"
    },
    "601": {
        "connectedId": 3680,
        "slug": "amanthul",
        "name": "Aman'Thul"
    },
    "602": {
        "connectedId": 1400,
        "slug": "senjin",
        "name": "Sen'jin"
    },
    "604": {
        "connectedId": 604,
        "slug": "thrall",
        "name": "Thrall"
    },
    "605": {
        "connectedId": 531,
        "slug": "theradras",
        "name": "Theradras"
    },
    "606": {
        "connectedId": 3660,
        "slug": "genjuros",
        "name": "Genjuros"
    },
    "607": {
        "connectedId": 1598,
        "slug": "balnazzar",
        "name": "Balnazzar"
    },
    "608": {
        "connectedId": 1105,
        "slug": "anubarak",
        "name": "Anub'arak"
    },
    "609": {
        "connectedId": 578,
        "slug": "wrathbringer",
        "name": "Wrathbringer"
    },
    "610": {
        "connectedId": 531,
        "slug": "onyxia",
        "name": "Onyxia"
    },
    "611": {
        "connectedId": 612,
        "slug": "nerathor",
        "name": "Nera'thor"
    },
    "612": {
        "connectedId": 612,
        "slug": "nefarian",
        "name": "Nefarian"
    },
    "613": {
        "connectedId": 1121,
        "slug": "kult-der-verdammten",
        "name": "Kult der Verdammten"
    },
    "614": {
        "connectedId": 1121,
        "slug": "das-syndikat",
        "name": "Das Syndikat"
    },
    "615": {
        "connectedId": 531,
        "slug": "terrordar",
        "name": "Terrordar"
    },
    "616": {
        "connectedId": 579,
        "slug": "kragjin",
        "name": "Krag'jin"
    },
    "617": {
        "connectedId": 1327,
        "slug": "der-rat-von-dalaran",
        "name": "Der Rat von Dalaran"
    },
    "618": {
        "connectedId": 1393,
        "slug": "nordrassil",
        "name": "Nordrassil"
    },
    "619": {
        "connectedId": 1325,
        "slug": "hellscream",
        "name": "Hellscream"
    },
    "621": {
        "connectedId": 1598,
        "slug": "laughing-skull",
        "name": "Laughing Skull"
    },
    "622": {
        "connectedId": 3681,
        "slug": "magtheridon",
        "name": "Magtheridon"
    },
    "623": {
        "connectedId": 1396,
        "slug": "quelthalas",
        "name": "Quel'Thalas"
    },
    "624": {
        "connectedId": 3660,
        "slug": "neptulon",
        "name": "Neptulon"
    },
    "625": {
        "connectedId": 3674,
        "slug": "twisting-nether",
        "name": "Twisting Nether"
    },
    "626": {
        "connectedId": 3682,
        "slug": "ragnaros",
        "name": "Ragnaros"
    },
    "627": {
        "connectedId": 1596,
        "slug": "the-maelstrom",
        "name": "The Maelstrom"
    },
    "628": {
        "connectedId": 3687,
        "slug": "sylvanas",
        "name": "Sylvanas"
    },
    "629": {
        "connectedId": 3656,
        "slug": "vashj",
        "name": "Vashj"
    },
    "630": {
        "connectedId": 633,
        "slug": "bloodfeather",
        "name": "Bloodfeather"
    },
    "631": {
        "connectedId": 3660,
        "slug": "darksorrow",
        "name": "Darksorrow"
    },
    "632": {
        "connectedId": 3657,
        "slug": "frostwhisper",
        "name": "Frostwhisper"
    },
    "633": {
        "connectedId": 633,
        "slug": "korgall",
        "name": "Kor'gall"
    },
    "635": {
        "connectedId": 1096,
        "slug": "defias-brotherhood",
        "name": "Defias Brotherhood"
    },
    "636": {
        "connectedId": 1096,
        "slug": "the-venture-co",
        "name": "The Venture Co"
    },
    "637": {
        "connectedId": 1596,
        "slug": "lightnings-blade",
        "name": "Lightning's Blade"
    },
    "638": {
        "connectedId": 3656,
        "slug": "haomarush",
        "name": "Haomarush"
    },
    "639": {
        "connectedId": 639,
        "slug": "xavius",
        "name": "Xavius"
    },
    "640": {
        "connectedId": 3690,
        "slug": "khaz-modan",
        "name": "Khaz Modan"
    },
    "641": {
        "connectedId": 1122,
        "slug": "drekthar",
        "name": "Drek'Thar"
    },
    "642": {
        "connectedId": 512,
        "slug": "rashgarroth",
        "name": "Rashgarroth"
    },
    "643": {
        "connectedId": 512,
        "slug": "throkferoth",
        "name": "Throk'Feroth"
    },
    "644": {
        "connectedId": 1086,
        "slug": "conseil-des-ombres",
        "name": "Conseil des Ombres"
    },
    "645": {
        "connectedId": 1315,
        "slug": "varimathras",
        "name": "Varimathras"
    },
    "646": {
        "connectedId": 1091,
        "slug": "hakkar",
        "name": "Hakkar"
    },
    "647": {
        "connectedId": 1127,
        "slug": "les-sentinelles",
        "name": "Les Sentinelles"
    },
    "1080": {
        "connectedId": 1080,
        "slug": "khadgar",
        "name": "Khadgar"
    },
    "1081": {
        "connectedId": 1081,
        "slug": "bronzebeard",
        "name": "Bronzebeard"
    },
    "1082": {
        "connectedId": 1082,
        "slug": "kul-tiras",
        "name": "Kul Tiras"
    },
    "1083": {
        "connectedId": 1598,
        "slug": "chromaggus",
        "name": "Chromaggus"
    },
    "1084": {
        "connectedId": 1084,
        "slug": "dentarg",
        "name": "Dentarg"
    },
    "1085": {
        "connectedId": 1085,
        "slug": "moonglade",
        "name": "Moonglade"
    },
    "1086": {
        "connectedId": 1086,
        "slug": "la-croisade-\u00e9carlate",
        "name": "La Croisade \u00e9carlate"
    },
    "1087": {
        "connectedId": 633,
        "slug": "executus",
        "name": "Executus"
    },
    "1088": {
        "connectedId": 1598,
        "slug": "trollbane",
        "name": "Trollbane"
    },
    "1089": {
        "connectedId": 1388,
        "slug": "mazrigos",
        "name": "Mazrigos"
    },
    "1090": {
        "connectedId": 1598,
        "slug": "talnivarr",
        "name": "Talnivarr"
    },
    "1091": {
        "connectedId": 1091,
        "slug": "emeriss",
        "name": "Emeriss"
    },
    "1092": {
        "connectedId": 1092,
        "slug": "drakthul",
        "name": "Drak'thul"
    },
    "1093": {
        "connectedId": 1598,
        "slug": "ahnqiraj",
        "name": "Ahn'Qiraj"
    },
    "1096": {
        "connectedId": 1096,
        "slug": "scarshield-legion",
        "name": "Scarshield Legion"
    },
    "1097": {
        "connectedId": 1097,
        "slug": "ysera",
        "name": "Ysera"
    },
    "1098": {
        "connectedId": 1098,
        "slug": "malygos",
        "name": "Malygos"
    },
    "1099": {
        "connectedId": 1099,
        "slug": "rexxar",
        "name": "Rexxar"
    },
    "1104": {
        "connectedId": 1104,
        "slug": "anetheron",
        "name": "Anetheron"
    },
    "1105": {
        "connectedId": 1105,
        "slug": "nazjatar",
        "name": "Nazjatar"
    },
    "1106": {
        "connectedId": 1106,
        "slug": "tichondrius",
        "name": "Tichondrius"
    },
    "1117": {
        "connectedId": 1085,
        "slug": "steamwheedle-cartel",
        "name": "Steamwheedle Cartel"
    },
    "1118": {
        "connectedId": 1118,
        "slug": "die-ewige-wacht",
        "name": "Die ewige Wacht"
    },
    "1119": {
        "connectedId": 1121,
        "slug": "die-todeskrallen",
        "name": "Die Todeskrallen"
    },
    "1121": {
        "connectedId": 1121,
        "slug": "die-arguswacht",
        "name": "Die Arguswacht"
    },
    "1122": {
        "connectedId": 1122,
        "slug": "uldaman",
        "name": "Uldaman"
    },
    "1123": {
        "connectedId": 1123,
        "slug": "eitrigg",
        "name": "Eitrigg"
    },
    "1127": {
        "connectedId": 1127,
        "slug": "confr\u00e9rie-du-thorium",
        "name": "Confr\u00e9rie du Thorium"
    },
    "1298": {
        "connectedId": 1416,
        "slug": "veknilash",
        "name": "Vek'nilash"
    },
    "1299": {
        "connectedId": 1598,
        "slug": "boulderfist",
        "name": "Boulderfist"
    },
    "1300": {
        "connectedId": 1300,
        "slug": "frostmane",
        "name": "Frostmane"
    },
    "1301": {
        "connectedId": 1301,
        "slug": "outland",
        "name": "Outland"
    },
    "1303": {
        "connectedId": 1303,
        "slug": "grim-batol",
        "name": "Grim Batol"
    },
    "1304": {
        "connectedId": 1597,
        "slug": "jaedenar",
        "name": "Jaedenar"
    },
    "1305": {
        "connectedId": 1305,
        "slug": "kazzak",
        "name": "Kazzak"
    },
    "1306": {
        "connectedId": 1084,
        "slug": "tarren-mill",
        "name": "Tarren Mill"
    },
    "1307": {
        "connectedId": 1307,
        "slug": "chamber-of-aspects",
        "name": "Chamber of Aspects"
    },
    "1308": {
        "connectedId": 1096,
        "slug": "ravenholdt",
        "name": "Ravenholdt"
    },
    "1309": {
        "connectedId": 1309,
        "slug": "pozzo-delleternit\u00e0",
        "name": "Pozzo dell'Eternit\u00e0"
    },
    "1310": {
        "connectedId": 1416,
        "slug": "eonar",
        "name": "Eonar"
    },
    "1311": {
        "connectedId": 1311,
        "slug": "kilrogg",
        "name": "Kilrogg"
    },
    "1312": {
        "connectedId": 1081,
        "slug": "aerie-peak",
        "name": "Aerie Peak"
    },
    "1313": {
        "connectedId": 1313,
        "slug": "wildhammer",
        "name": "Wildhammer"
    },
    "1314": {
        "connectedId": 1389,
        "slug": "saurfang",
        "name": "Saurfang"
    },
    "1316": {
        "connectedId": 1316,
        "slug": "nemesis",
        "name": "Nemesis"
    },
    "1317": {
        "connectedId": 1317,
        "slug": "darkmoon-faire",
        "name": "Darkmoon Faire"
    },
    "1318": {
        "connectedId": 578,
        "slug": "veklor",
        "name": "Vek'lor"
    },
    "1319": {
        "connectedId": 531,
        "slug": "mugthol",
        "name": "Mug'thol"
    },
    "1320": {
        "connectedId": 1612,
        "slug": "taerar",
        "name": "Taerar"
    },
    "1321": {
        "connectedId": 1105,
        "slug": "dalvengyr",
        "name": "Dalvengyr"
    },
    "1322": {
        "connectedId": 1104,
        "slug": "rajaxx",
        "name": "Rajaxx"
    },
    "1323": {
        "connectedId": 567,
        "slug": "ulduar",
        "name": "Ulduar"
    },
    "1324": {
        "connectedId": 1097,
        "slug": "malorne",
        "name": "Malorne"
    },
    "1326": {
        "connectedId": 1121,
        "slug": "der-abyssische-rat",
        "name": "Der abyssische Rat"
    },
    "1327": {
        "connectedId": 1327,
        "slug": "der-mithrilorden",
        "name": "Der Mithrilorden"
    },
    "1328": {
        "connectedId": 535,
        "slug": "tirion",
        "name": "Tirion"
    },
    "1330": {
        "connectedId": 568,
        "slug": "ambossar",
        "name": "Ambossar"
    },
    "1331": {
        "connectedId": 1331,
        "slug": "suramar",
        "name": "Suramar"
    },
    "1332": {
        "connectedId": 1123,
        "slug": "krasus",
        "name": "Krasus"
    },
    "1333": {
        "connectedId": 516,
        "slug": "die-nachtwache",
        "name": "Die Nachtwache"
    },
    "1334": {
        "connectedId": 1624,
        "slug": "arathi",
        "name": "Arathi"
    },
    "1335": {
        "connectedId": 1335,
        "slug": "ysondre",
        "name": "Ysondre"
    },
    "1336": {
        "connectedId": 1336,
        "slug": "eldrethalas",
        "name": "Eldre'Thalas"
    },
    "1337": {
        "connectedId": 1086,
        "slug": "culte-de-la-rive-noire",
        "name": "Culte de la Rive noire"
    },
    "1378": {
        "connectedId": 1378,
        "slug": "dun-modr",
        "name": "Dun Modr"
    },
    "1379": {
        "connectedId": 1379,
        "slug": "zuljin",
        "name": "Zul'jin"
    },
    "1380": {
        "connectedId": 1379,
        "slug": "uldum",
        "name": "Uldum"
    },
    "1381": {
        "connectedId": 1381,
        "slug": "cthun",
        "name": "C'Thun"
    },
    "1382": {
        "connectedId": 1379,
        "slug": "sanguino",
        "name": "Sanguino"
    },
    "1383": {
        "connectedId": 1379,
        "slug": "shendralar",
        "name": "Shen'dralar"
    },
    "1384": {
        "connectedId": 1384,
        "slug": "tyrande",
        "name": "Tyrande"
    },
    "1385": {
        "connectedId": 1385,
        "slug": "exodar",
        "name": "Exodar"
    },
    "1386": {
        "connectedId": 1385,
        "slug": "minahonda",
        "name": "Minahonda"
    },
    "1387": {
        "connectedId": 1384,
        "slug": "los-errantes",
        "name": "Los Errantes"
    },
    "1388": {
        "connectedId": 1388,
        "slug": "lightbringer",
        "name": "Lightbringer"
    },
    "1389": {
        "connectedId": 1389,
        "slug": "darkspear",
        "name": "Darkspear"
    },
    "1391": {
        "connectedId": 1082,
        "slug": "alonsus",
        "name": "Alonsus"
    },
    "1392": {
        "connectedId": 633,
        "slug": "burning-steppes",
        "name": "Burning Steppes"
    },
    "1393": {
        "connectedId": 1393,
        "slug": "bronze-dragonflight",
        "name": "Bronze Dragonflight"
    },
    "1394": {
        "connectedId": 1082,
        "slug": "anachronos",
        "name": "Anachronos"
    },
    "1395": {
        "connectedId": 1384,
        "slug": "colinas-pardas",
        "name": "Colinas Pardas"
    },
    "1400": {
        "connectedId": 1400,
        "slug": "ungoro",
        "name": "Un'Goro"
    },
    "1401": {
        "connectedId": 1401,
        "slug": "garrosh",
        "name": "Garrosh"
    },
    "1404": {
        "connectedId": 1400,
        "slug": "area-52",
        "name": "Area 52"
    },
    "1405": {
        "connectedId": 1405,
        "slug": "todeswache",
        "name": "Todeswache"
    },
    "1406": {
        "connectedId": 1406,
        "slug": "arygos",
        "name": "Arygos"
    },
    "1407": {
        "connectedId": 1407,
        "slug": "teldrassil",
        "name": "Teldrassil"
    },
    "1408": {
        "connectedId": 1408,
        "slug": "norgannon",
        "name": "Norgannon"
    },
    "1409": {
        "connectedId": 1106,
        "slug": "lordaeron",
        "name": "Lordaeron"
    },
    "1413": {
        "connectedId": 1303,
        "slug": "aggra-portugu\u00eas",
        "name": "Aggra (Portugu\u00eas)"
    },
    "1415": {
        "connectedId": 1389,
        "slug": "terokkar",
        "name": "Terokkar"
    },
    "1416": {
        "connectedId": 1416,
        "slug": "blades-edge",
        "name": "Blade's Edge"
    },
    "1417": {
        "connectedId": 1417,
        "slug": "azuremyst",
        "name": "Azuremyst"
    },
    "1587": {
        "connectedId": 1587,
        "slug": "hellfire",
        "name": "Hellfire"
    },
    "1588": {
        "connectedId": 1588,
        "slug": "ghostlands",
        "name": "Ghostlands"
    },
    "1589": {
        "connectedId": 1311,
        "slug": "nagrand",
        "name": "Nagrand"
    },
    "1595": {
        "connectedId": 1085,
        "slug": "the-shatar",
        "name": "The Sha'tar"
    },
    "1596": {
        "connectedId": 1596,
        "slug": "karazhan",
        "name": "Karazhan"
    },
    "1597": {
        "connectedId": 1597,
        "slug": "auchindoun",
        "name": "Auchindoun"
    },
    "1598": {
        "connectedId": 1598,
        "slug": "shattered-halls",
        "name": "Shattered Halls"
    },
    "1602": {
        "connectedId": 1602,
        "slug": "gordunni",
        "name": "Gordunni"
    },
    "1603": {
        "connectedId": 1603,
        "slug": "lich-king",
        "name": "Lich King"
    },
    "1604": {
        "connectedId": 1604,
        "slug": "soulflayer",
        "name": "Soulflayer"
    },
    "1605": {
        "connectedId": 1605,
        "slug": "deathguard",
        "name": "Deathguard"
    },
    "1606": {
        "connectedId": 1096,
        "slug": "sporeggar",
        "name": "Sporeggar"
    },
    "1607": {
        "connectedId": 1607,
        "slug": "nethersturm",
        "name": "Nethersturm"
    },
    "1608": {
        "connectedId": 1401,
        "slug": "shattrath",
        "name": "Shattrath"
    },
    "1609": {
        "connectedId": 1609,
        "slug": "deepholm",
        "name": "Deepholm"
    },
    "1610": {
        "connectedId": 1603,
        "slug": "greymane",
        "name": "Greymane"
    },
    "1611": {
        "connectedId": 1104,
        "slug": "festung-der-st\u00fcrme",
        "name": "Festung der St\u00fcrme"
    },
    "1612": {
        "connectedId": 1612,
        "slug": "echsenkessel",
        "name": "Echsenkessel"
    },
    "1613": {
        "connectedId": 578,
        "slug": "blutkessel",
        "name": "Blutkessel"
    },
    "1614": {
        "connectedId": 1614,
        "slug": "galakrond",
        "name": "Galakrond"
    },
    "1615": {
        "connectedId": 1615,
        "slug": "howling-fjord",
        "name": "Howling Fjord"
    },
    "1616": {
        "connectedId": 1609,
        "slug": "razuvious",
        "name": "Razuvious"
    },
    "1617": {
        "connectedId": 1924,
        "slug": "deathweaver",
        "name": "Deathweaver"
    },
    "1618": {
        "connectedId": 1618,
        "slug": "die-aldor",
        "name": "Die Aldor"
    },
    "1619": {
        "connectedId": 1121,
        "slug": "das-konsortium",
        "name": "Das Konsortium"
    },
    "1620": {
        "connectedId": 510,
        "slug": "chants-\u00e9ternels",
        "name": "Chants \u00e9ternels"
    },
    "1621": {
        "connectedId": 1621,
        "slug": "mar\u00e9cage-de-zangar",
        "name": "Mar\u00e9cage de Zangar"
    },
    "1622": {
        "connectedId": 1624,
        "slug": "temple-noir",
        "name": "Temple noir"
    },
    "1623": {
        "connectedId": 1623,
        "slug": "fordragon",
        "name": "Fordragon"
    },
    "1624": {
        "connectedId": 1624,
        "slug": "naxxramas",
        "name": "Naxxramas"
    },
    "1625": {
        "connectedId": 1625,
        "slug": "borean-tundra",
        "name": "Borean Tundra"
    },
    "1626": {
        "connectedId": 1127,
        "slug": "les-clairvoyants",
        "name": "Les Clairvoyants"
    },
    "1922": {
        "connectedId": 1922,
        "slug": "azuregos",
        "name": "Azuregos"
    },
    "1923": {
        "connectedId": 1923,
        "slug": "ashenvale",
        "name": "Ashenvale"
    },
    "1924": {
        "connectedId": 1924,
        "slug": "booty-bay",
        "name": "Booty Bay"
    },
    "1925": {
        "connectedId": 1925,
        "slug": "eversong",
        "name": "Eversong"
    },
    "1926": {
        "connectedId": 1927,
        "slug": "thermaplugg",
        "name": "Thermaplugg"
    },
    "1927": {
        "connectedId": 1927,
        "slug": "grom",
        "name": "Grom"
    },
    "1928": {
        "connectedId": 1928,
        "slug": "goldrinn",
        "name": "Goldrinn"
    },
    "1929": {
        "connectedId": 1929,
        "slug": "blackscar",
        "name": "Blackscar"
    }
},
"tw": {
    "963": {
        "connectedId": 963,
        "slug": "shadowmoon",
        "name": "Shadowmoon"
    },
    "964": {
        "connectedId": 964,
        "slug": "spirestone",
        "name": "Spirestone"
    },
    "965": {
        "connectedId": 966,
        "slug": "stormscale",
        "name": "Stormscale"
    },
    "966": {
        "connectedId": 966,
        "slug": "dragonmaw",
        "name": "Dragonmaw"
    },
    "977": {
        "connectedId": 977,
        "slug": "frostmane",
        "name": "Frostmane"
    },
    "978": {
        "connectedId": 978,
        "slug": "sundown-marsh",
        "name": "Sundown Marsh"
    },
    "979": {
        "connectedId": 999,
        "slug": "hellscream",
        "name": "Hellscream"
    },
    "980": {
        "connectedId": 980,
        "slug": "skywall",
        "name": "Skywall"
    },
    "982": {
        "connectedId": 3663,
        "slug": "world-tree",
        "name": "World Tree"
    },
    "985": {
        "connectedId": 985,
        "slug": "crystalpine-stinger",
        "name": "Crystalpine Stinger"
    },
    "999": {
        "connectedId": 999,
        "slug": "zealot-blade",
        "name": "Zealot Blade"
    },
    "1001": {
        "connectedId": 964,
        "slug": "chillwind-point",
        "name": "Chillwind Point"
    },
    "1006": {
        "connectedId": 977,
        "slug": "menethil",
        "name": "Menethil"
    },
    "1023": {
        "connectedId": 978,
        "slug": "demon-fall-canyon",
        "name": "Demon Fall Canyon"
    },
    "1033": {
        "connectedId": 963,
        "slug": "whisperwind",
        "name": "Whisperwind"
    },
    "1037": {
        "connectedId": 977,
        "slug": "bleeding-hollow",
        "name": "Bleeding Hollow"
    },
    "1038": {
        "connectedId": 3663,
        "slug": "arygos",
        "name": "Arygos"
    },
    "1043": {
        "connectedId": 966,
        "slug": "nightsong",
        "name": "Nightsong"
    },
    "1046": {
        "connectedId": 980,
        "slug": "lights-hope",
        "name": "Light's Hope"
    },
    "1048": {
        "connectedId": 978,
        "slug": "silverwing-hold",
        "name": "Silverwing Hold"
    },
    "1049": {
        "connectedId": 985,
        "slug": "wrathbringer",
        "name": "Wrathbringer"
    },
    "1054": {
        "connectedId": 999,
        "slug": "arthas",
        "name": "Arthas"
    },
    "1056": {
        "connectedId": 963,
        "slug": "queldorei",
        "name": "Quel'dorei"
    },
    "1057": {
        "connectedId": 964,
        "slug": "icecrown",
        "name": "Icecrown"
    },
    "2075": {
        "connectedId": 3663,
        "slug": "order-of-the-cloud-serpent",
        "name": "Order of the Cloud Serpent"
    }
},
"kr": {
    "201": {
        "connectedId": 201,
        "slug": "burning-legion",
        "name": "Burning Legion"
    },
    "205": {
        "connectedId": 205,
        "slug": "azshara",
        "name": "Azshara"
    },
    "207": {
        "connectedId": 2110,
        "slug": "dalaran",
        "name": "Dalaran"
    },
    "210": {
        "connectedId": 210,
        "slug": "durotan",
        "name": "Durotan"
    },
    "211": {
        "connectedId": 2110,
        "slug": "norgannon",
        "name": "Norgannon"
    },
    "212": {
        "connectedId": 2116,
        "slug": "garona",
        "name": "Garona"
    },
    "214": {
        "connectedId": 214,
        "slug": "windrunner",
        "name": "Windrunner"
    },
    "215": {
        "connectedId": 2116,
        "slug": "guldan",
        "name": "Gul'dan"
    },
    "258": {
        "connectedId": 2108,
        "slug": "alexstrasza",
        "name": "Alexstrasza"
    },
    "264": {
        "connectedId": 2110,
        "slug": "malfurion",
        "name": "Malfurion"
    },
    "293": {
        "connectedId": 293,
        "slug": "hellscream",
        "name": "Hellscream"
    },
    "2079": {
        "connectedId": 214,
        "slug": "wildhammer",
        "name": "Wildhammer"
    },
    "2106": {
        "connectedId": 214,
        "slug": "rexxar",
        "name": "Rexxar"
    },
    "2107": {
        "connectedId": 2107,
        "slug": "hyjal",
        "name": "Hyjal"
    },
    "2108": {
        "connectedId": 2108,
        "slug": "deathwing",
        "name": "Deathwing"
    },
    "2110": {
        "connectedId": 2110,
        "slug": "cenarius",
        "name": "Cenarius"
    },
    "2111": {
        "connectedId": 201,
        "slug": "stormrage",
        "name": "Stormrage"
    },
    "2116": {
        "connectedId": 2116,
        "slug": "zuljin",
        "name": "Zul'jin"
    }
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
foreach (['us','eu','tw','kr'] as $region) {
    $file['regions'][$region] = FetchRegionData($region);
    if (CatchKill()) {
        break;
    }
}
foreach (['us','eu','tw','kr','cn'] as $region) {
    if (CatchKill()) {
        break;
    }
    $file['tokens'][$region] = FetchTokenData($region);
}
$file['finished'] = JSNow();

if (!CatchKill()) {
    $fn = isset($argv[1]) ? $argv[1] : __DIR__.'/../theapi.work/times.json';

    AtomicFilePutContents($fn, json_encode($file, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
}

DebugMessage("Opened {$connectionTracking['created']} connections to service {$connectionTracking['requests']} requests.");
DebugMessage('Done! Started ' . TimeDiff($startTime, ['precision'=>'second']));

function JSNow() {
    return floor(microtime(true) * 1000);
}

function FetchTokenData($region) {
    $result = [
        'checked' => JSNow()
    ];

    $outHeaders = [];
    $requestInfo = GetBattleNetUrl($region, '/data/wow/token/');
    $json = $requestInfo ? \Newsstand\HTTP::Get($requestInfo[0], $requestInfo[1], $outHeaders) : '';
    if (!$json) {
        if (isset($outHeaders['curlError'])) {
            $result['status'] = $outHeaders['curlError'];
        }
        if (isset($outHeaders['responseCode'])) {
            $result['status'] = 'HTTP ' . $outHeaders['responseCode'];
        }
        if (isset($outHeaders['X-Mashery-Error-Code'])) {
            $result['status'] = 'Mashery: ' . $outHeaders['X-Mashery-Error-Code'];
        }
        if (isset($outHeaders['Content-Type']) && ($outHeaders['Content-Type'] == 'application/json;charset=UTF-8')) {
            $data = json_decode($outHeaders['body'], true);
            if (json_last_error() != JSON_ERROR_NONE) {
                if (isset($data['type'])) {
                    $result['status'] = 'Blizzard: ' . $data['type'];
                    if (isset($data['detail'])) {
                        $result['status'] .= ' (' . $data['detail'] . ')';
                    }
                }
            }
        }
        return $result;
    }

    $data = json_decode($json, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        $result['status'] = 'Invalid JSON response';
        return $result;
    }

    if (isset($data['last_updated_timestamp'])) {
        $data['last_updated'] = floor($data['last_updated_timestamp'] / 1000);
    }

    if (!isset($data['last_updated']) || !isset($data['price'])) {
        $result['status'] = 'Missing fields in JSON response';
        return $result;
    }

    $result['modified'] = $data['last_updated_timestamp'] ?? ($data['last_updated'] * 1000);
    return $result;
}

function FetchRegionData($region) {
    $region = trim(strtolower($region));

    $results = [];

    if (FETCH_REALM_LIST) {
        DebugMessage("Fetching realms for $region");

        $requestInfo = GetBattleNetURL($region, 'data/wow/connected-realm/index');
        $jsonString  = $requestInfo ? HTTP::Get($requestInfo[0], $requestInfo[1]) : '';
        $realmIndex  = json_decode($jsonString, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            DebugMessage("Error decoding " . strlen($jsonString) . " length JSON string for $region: " . json_last_error_msg());

            return $results;
        }
        if ( ! isset($realmIndex['connected_realms'])) {
            DebugMessage("Did not find connected_realms in connected realm index JSON for $region");

            return $results;
        }

        $checkList = json_decode(REALM_LIST_JSON, true)[$region];

        $realms       = [];
        $connectedIds = [];
        foreach ($realmIndex['connected_realms'] as $info) {
            if (preg_match('/connected-realm\/(\d+)/', $info['href'] ?? '', $res)) {
                $connectedIds[] = intval($res[1]);
            }
        }

        $chunks = array_chunk($connectedIds, REALM_CHUNK_SIZE);
        foreach ($chunks as $chunk) {
            DebugMessage("Fetching realm for $region connected realms " . implode(', ', $chunk));
            $urls = [];
            foreach ($chunk as $connectedId) {
                $urls[$connectedId] = GetBattleNetURL($region, 'data/wow/connected-realm/' . $connectedId, false);
            }
            $jsons = FetchURLBatch($urls);

            foreach ($chunk as $connectedId) {
                $json = [];
                if ( ! isset($jsons[$connectedId]) || ! $jsons[$connectedId]) {
                    DebugMessage("No HTTP response for $region $connectedId");
                } else {
                    $json = json_decode($jsons[$connectedId], true);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        DebugMessage("Error decoding JSON string for $region $connectedId: " . json_last_error_msg());
                        $json = [];
                    }
                }

                if ( ! isset($json['realms'])) {
                    $json['realms'] = [];
                }
                usort($json['realms'], function ($a, $b) {
                    return $a['id'] - $b['id'];
                });

                $setCanonical = false;
                foreach ($json['realms'] ?? [] as $realm) {
                    $realm = [
                        'id'          => $realm['id'],
                        'connectedId' => $connectedId,
                        'slug'        => $realm['slug'] ?? null,
                        'name'        => $realm['name'] ?? null,
                    ];
                    if ( ! $setCanonical) {
                        $realm['canonical'] = true;
                        $setCanonical       = true;
                    }
                    $realms[] = $realm;
                    unset($checkList[$realm['id']]);
                }
            }
        }

        $connectedIds = array_fill_keys($connectedIds, true);
        foreach ($checkList as $realmId => $realm) {
            $realm['id']      = $realmId;
            $realm['missing'] = true;
            if ( ! isset($connectedIds[$realm['connectedId']])) {
                $realm['canonical']                  = true;
                $connectedIds[$realm['connectedId']] = true;
            }
            $realms[] = $realm;
        }
        unset($checkList);
    } else {
        $checkList = json_decode(REALM_LIST_JSON, true)[$region];
        $connectedIds = [];
        $realms = [];

        foreach ($checkList as $realmId => $realm) {
            $realm['id']      = $realmId;
            if ( ! isset($connectedIds[$realm['connectedId']])) {
                $realm['canonical']                  = true;
                $connectedIds[$realm['connectedId']] = true;
            }
            $realms[] = $realm;
        }
        unset($checkList);
    }

    usort($realms, function ($a, $b) {
        return $a['id'] - $b['id'];
    });

    $connectedIds = array_keys($connectedIds);

    $chunks = array_chunk($connectedIds, REALM_CHUNK_SIZE);
    foreach ($chunks as $chunk) {
        DebugMessage("Fetching auction data for $region ".implode(', ', $chunk));
        $urls = [];
        foreach ($chunk as $connectedId) {
            $urls[$connectedId] = GetBattleNetURL($region, 'data/wow/connected-realm/' . $connectedId . '/auctions', false);
        }

        $started = JSNow();
        $dataUrls = [];
        $dataHeads = FetchURLBatch($urls, [
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['If-Modified-Since: ' . date(DATE_RFC7231, time() + 24 * 60 * 60)],
        ]);

        foreach ($chunk as $connectedId) {
            $fileDate = 0;
            $noResponse = true;
            $errorCode = 0;

            if (isset($dataHeads[$connectedId])) {
                $header = substr($dataHeads[$connectedId], 0, strpos($dataHeads[$connectedId], "\r\n\r\n"));
                if (preg_match('/^HTTP\/[\d\.]+ (\d{3})/', $header, $res)) {
                    $errorCode = intval($res[1]);
                }

                if (preg_match('/(?:^|\n)Last-Modified: ([^\n\r]+)/i', $header, $res)) {
                    $fileDate = strtotime($res[1]) * 1000;
                } elseif ($header && $errorCode < 400) {
                    DebugMessage("Found no last-modified header for $region $connectedId\n" . $header);
                }

                $noResponse = false;
            } elseif (isset($dataUrls[$connectedId])) {
                DebugMessage("Fetched no data file for $region $connectedId");
            }

            foreach ($realms as $realm) {
                if ($realm['connectedId'] === $connectedId) {
                    $results[$realm['slug']] = $realm;
                    $results[$realm['slug']]['checked'] = $started;
                    $results[$realm['slug']]['modified'] = $fileDate;
                    $results[$realm['slug']]['errorCode'] = $errorCode;
                    if ($noResponse) {
                        $results[$realm['slug']]['noResponse'] = true;
                    }
                }
            }
        }
    }

    ksort($results);

    return $results;
}

function FetchURLBatch($urls, $origCurlOpts = []) {
    if (!$urls) {
        return [];
    }

    global $connectionTracking;

    $curlOpts = [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 2,
        CURLOPT_TIMEOUT         => 6,
    ] + $origCurlOpts;

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

    static $isRetrying = false;
    if (!$isRetrying) {
        $toRetry = [];
        foreach ($results as $k => $v) {
            if (!$v) {
                $toRetry[$k] = $urls[$k];
            }
        }
        if ($toRetry) {
            $isRetrying = true;
            DebugMessage('Retrying ' . implode(', ', array_keys($toRetry)));
            $more = FetchURLBatch($toRetry, $origCurlOpts);
            foreach ($more as $k => $v) {
                $results[$k] = $v;
            }
            $isRetrying = false;
        }
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

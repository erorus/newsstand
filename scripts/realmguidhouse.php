<?php

$startTime = time();

require_once('../incl/incl.php');

RunMeNTimes(1);

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

BuildRealmGuidHouse();

DebugMessage('Done! Started '.TimeDiff($startTime));

function BuildRealmGuidHouse()
{
    global $db;

    $guids = GetRealmInfo();

    $db->begin_transaction();

    $stmt = $db->prepare('SELECT region, name, house FROM tblRealm');
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result, ['region', 'name']);
    $stmt->close();

    $db->query('delete from tblRealmGuidHouse');

    $stmt = $db->prepare('replace into tblRealmGuidHouse (realmguid, house) values (?, ?)');
    $realmGuid = 0;
    $house = 0;
    $stmt->bind_param('ii', $realmGuid, $house);

    foreach ($guids as $guid => $realmInfo) {
        $found = false;
        for ($x = 1; $x < count($realmInfo); $x++) {
            if (isset($houses[$realmInfo[0]][$realmInfo[$x]])) {
                $found = true;
                $realmGuid = $guid;
                $house = $houses[$realmInfo[0]][$realmInfo[$x]]['house'];
                $stmt->execute();
            }
        }
        if (!$found) {
            echo "Could not find house for ".implode(',', $realmInfo)."\n";
        }
    }

    $stmt->close();

    $db->commit();

}

function GetRealmInfo()
{
    // https://github.com/Phanx/LibRealmInfo/blob/master/LibRealmInfo.lua

    $lua = <<<'EOF'
    [1136] = "Aegwynn,PVP,enUS,Vengeance,US,CST",
	[1284] = "Aerie Peak,PVE,enUS,Vindication,US,PST",
	[1129] = "Agamaggan,PVP,enUS,Shadowburn,US,CST",
	[106]  = "Aggramar,PVE,enUS,Vindication,US,CST",
	[1137] = "Akama,PVP,enUS,Reckoning,US,CST",
	[1070] = "Alexstrasza,PVE,enUS,Rampage,US,CST",
	[52]   = "Alleria,PVE,enUS,Rampage,US,CST",
	[1282] = "Altar of Storms,PVP,enUS,Ruin,US,EST",
	[1293] = "Alterac Mountains,PVP,enUS,Ruin,US,EST",
	[3722] = "Aman'Thul,PVE,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1418] = "US", -- Aman'Thul / old US datacenter
	[1276] = "Andorhal,PVP,enUS,Shadowburn,US,EST",
	[1264] = "Anetheron,PVP,enUS,Ruin,US,EST",
	[1363] = "Antonidas,PVE,enUS,Cyclone,US,PST",
	[1346] = "Anub'arak,PVP,enUS,Vengeance,US,EST",
	[1288] = "Anvilmar,PVE,enUS,Ruin,US,PST",
	[1165] = "Arathor,PVE,enUS,Reckoning,US,PST",
	[56]   = "Archimonde,PVP,enUS,Shadowburn,US,CST",
	[1566] = "Area 52,PVE,enUS,Vindication,US,EST",
	[75]   = "Argent Dawn,RP,enUS,Ruin,US,EST",
	[69]   = "Arthas,PVP,enUS,Ruin,US,EST",
	[1297] = "Arygos,PVE,enUS,Vindication,US,EST",
	[1555] = "Auchindoun,PVP,enUS,Vindication,US,EST",
	[77]   = "Azgalor,PVP,enUS,Ruin,US,CST",
	[121]  = "Azjol-Nerub,PVE,enUS,Cyclone,US,MST",
	[3209] = "Azralon,PVP,ptBR,Shadowburn,US,US",
	[1128] = "Azshara,PVP,enUS,Ruin,US,EST",
	[1549] = "Azuremyst,PVE,enUS,Shadowburn,US,PST",
	[1190] = "Baelgun,PVE,enUS,Shadowburn,US,PST",
	[1075] = "Balnazzar,PVP,enUS,Ruin,US,CST",
	[3723] = "Barthilas,PVP,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1419] = "US", -- Barthilas / old US datacenter
	[1280] = "Black Dragonflight,PVP,enUS,Ruin,US,EST",
	[54]   = "Blackhand,PVE,enUS,Rampage,US,CST",
	[1168] = "US", -- Blackmoore
	[10]   = "Blackrock,PVP,enUS,Bloodlust,US,PST",
	[1347] = "Blackwater Raiders,RP,enUS,Reckoning,US,PST",
	[1296] = "Blackwing Lair,PVP,enUS,Shadowburn,US,PST",
	[1564] = "Blade's Edge,PVE,enUS,Vindication,US,PST",
	[1353] = "Bladefist,PVE,enUS,Vengeance,US,PST",
	[73]   = "Bleeding Hollow,PVP,enUS,Ruin,US,EST",
	[1558] = "Blood Furnace,PVP,enUS,Ruin,US,CST",
	[64]   = "Bloodhoof,PVE,enUS,Ruin,US,EST",
	[119]  = "Bloodscalp,PVP,enUS,Cyclone,US,MST",
	[83]   = "Bonechewer,PVP,enUS,Vengeance,US,PST",
	[1371] = "Borean Tundra,PVE,enUS,Reckoning,US,CST",
	[112]  = "Boulderfist,PVP,enUS,Cyclone,US,PST",
	[117]  = "Bronzebeard,PVE,enUS,Cyclone,US,PST",
	[91]   = "Burning Blade,PVP,enUS,Vindication,US,EST",
	[102]  = "Burning Legion,PVP,enUS,Shadowburn,US,CST",
	[3721] = "Caelestrasz,PVE,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1430] = "US", -- Caelestrasz / old US datacenter
	[1361] = "Cairne,PVE,enUS,Cyclone,US,CST",
	[88]   = "Cenarion Circle,RP,enUS,Cyclone,US,PST",
	[2]    = "Cenarius,PVE,enUS,Cyclone,US,PST",
	[1067] = "Cho'gall,PVP,enUS,Vindication,US,CST",
	[1138] = "Chromaggus,PVP,enUS,Vengeance,US,CST",
	[1556] = "Coilfang,PVP,enUS,Shadowburn,US,PST",
	[107]  = "Crushridge,PVP,enUS,Vengeance,US,PST",
	[109]  = "Daggerspine,PVP,enUS,Vengeance,US,PST",
	[66]   = "Dalaran,PVE,enUS,Rampage,US,EST",
	[1278] = "Dalvengyr,PVP,enUS,Shadowburn,US,EST",
	[157]  = "Dark Iron,PVP,enUS,Shadowburn,US,PST",
	[120]  = "Darkspear,PVP,enUS,Cyclone,US,MST",
	[1351] = "Darrowmere,PVE,enUS,Reckoning,US,PST",
	[3735] = "Dath'Remar,PVE,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1434] = "US", -- Dath'Remar / old US datacenter
	[1582] = "Dawnbringer,PVE,enUS,Ruin,US,CST",
	[15]   = "Deathwing,PVP,enUS,Shadowburn,US,MST",
	[1286] = "Demon Soul,PVP,enUS,Shadowburn,US,EST",
	[1271] = "Dentarg,PVE,enUS,Rampage,US,EST",
	[79]   = "Destromath,PVP,enUS,Ruin,US,PST",
	[81]   = "Dethecus,PVP,enUS,Shadowburn,US,PST",
	[154]  = "Detheroc,PVP,enUS,Shadowburn,US,CST",
	[13]   = "Doomhammer,PVE,enUS,Shadowburn,US,MST",
	[115]  = "Draenor,PVE,enUS,Cyclone,US,PST",
	[114]  = "Dragonblight,PVE,enUS,Cyclone,US,PST",
	[84]   = "Dragonmaw,PVP,enUS,Reckoning,US,PST",
	[1362] = "Drak'Tharon,PVP,enUS,Reckoning,US,CST",
	[1140] = "Drak'thul,PVE,enUS,Reckoning,US,CST",
	[1139] = "Draka,PVE,enUS,Cyclone,US,CST",
	[1425] = "Drakkari,PVP,esMX,Vindication,US,CST",
	[3733] = "Dreadmaul,PVP,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1429] = "US", -- Dreadmaul / old US datacenter
	[1377] = "Drenden,PVE,enUS,Reckoning,US,EST",
	[111]  = "Dunemaul,PVP,enUS,Cyclone,US,PST",
	[63]   = "Durotan,PVE,enUS,Ruin,US,EST",
	[1258] = "Duskwood,PVE,enUS,Ruin,US,EST",
	[100]  = "Earthen Ring,RP,enUS,Vindication,US,EST",
	[1342] = "Echo Isles,PVE,enUS,Cyclone,US,PST",
	[47]   = "Eitrigg,PVE,enUS,Vengeance,US,CST",
	[123]  = "Eldre'Thalas,PVE,enUS,Reckoning,US,EST",
	[67]   = "Elune,PVE,enUS,Ruin,US,EST",
	[162]  = "Emerald Dream,RPPVP,enUS,Shadowburn,US,CST",
	[96]   = "Eonar,PVE,enUS,Vindication,US,EST",
	[93]   = "Eredar,PVP,enUS,Shadowburn,US,EST",
	[1277] = "Executus,PVP,enUS,Shadowburn,US,EST",
	[1565] = "Exodar,PVE,enUS,Ruin,US,EST",
	[1370] = "Farstriders,RP,enUS,Bloodlust,US,CST",
	[118]  = "Feathermoon,RP,enUS,Reckoning,US,PST",
	[1345] = "Fenris,PVE,enUS,Cyclone,US,EST",
	[127]  = "Firetree,PVP,enUS,Reckoning,US,EST",
	[1576] = "Fizzcrank,PVE,enUS,Vindication,US,CST",
	[128]  = "Frostmane,PVP,enUS,Reckoning,US,CST",
	[3725] = "Frostmourne,PVP,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1133] = "US", -- Frostmourne / old US datacenter
	[7]    = "Frostwolf,PVP,enUS,Bloodlust,US,PST",
	[1581] = "Galakrond,PVE,enUS,Rampage,US,PST",
	[3234] = "Gallywix,PVE,ptBR,Ruin,US,US",
	[1141] = "Garithos,PVP,enUS,Vengeance,US,CST",
	[51]   = "Garona,PVE,enUS,Rampage,US,CST",
	[1373] = "Garrosh,PVE,enUS,Vengeance,US,EST",
	[1578] = "Ghostlands,PVE,enUS,Rampage,US,CST",
	[97]   = "Gilneas,PVE,enUS,Ruin,US,EST",
	[1287] = "Gnomeregan,PVE,enUS,Shadowburn,US,PST",
	[3207] = "Goldrinn,PVE,ptBR,Rampage,US,US",
	[92]   = "Gorefiend,PVP,enUS,Shadowburn,US,EST",
	[80]   = "Gorgonnash,PVP,enUS,Ruin,US,PST",
	[158]  = "Greymane,PVE,enUS,Shadowburn,US,CST",
	[1579] = "Grizzly Hills,PVE,enUS,Ruin,US,EST",
	[1068] = "Gul'dan,PVP,enUS,Ruin,US,CST",
	[3737] = "Gundrak,PVP,enUS,Vengeance,US,AEST", -- US9 / new Oceanic datacenter
	[1149] = "US", -- Gundrak / old US datacenter
	[129]  = "Gurubashi,PVP,enUS,Vengeance,US,PST",
	[1142] = "Hakkar,PVP,enUS,Vengeance,US,CST",
	[1266] = "Haomarush,PVP,enUS,Shadowburn,US,EST",
	[53]   = "Hellscream,PVE,enUS,Rampage,US,CST",
	[1368] = "Hydraxis,PVE,enUS,Reckoning,US,CST",
	[6]    = "Hyjal,PVE,enUS,Vengeance,US,PST",
	[14]   = "Icecrown,PVE,enUS,Vindication,US,MST",
	[57]   = "Illidan,PVP,enUS,Rampage,US,CST",
	[3661] = "US", -- Internal Record 3661
	[3675] = "US", -- Internal Record 3675
	[3676] = "US", -- Internal Record 3676
	[3677] = "US", -- Internal Record 3677
	[3678] = "US", -- Internal Record 3678
	[3683] = "US", -- Internal Record 3683
	[3684] = "US", -- Internal Record 3684
	[3685] = "US", -- Internal Record 3685
	[3693] = "US", -- Internal Record 3693
	[3694] = "US", -- Internal Record 3694
	[3695] = "US", -- Internal Record 3695
	[3729] = "US", -- Internal Record 3695
	[3697] = "US", -- Internal Record 3697
	[3728] = "US", -- Internal Record 3697
	[1291] = "Jaedenar,PVP,enUS,Shadowburn,US,EST",
	[3736] = "Jubei'Thos,PVP,enUS,Vengeance,US,AEST", -- US9 / new Oceanic datacenter
	[1144] = "US", -- Jubei'Thos / old US datacenter
	[1069] = "Kael'thas,PVE,enUS,Rampage,US,CST",
	[155]  = "Kalecgos,PVP,enUS,Shadowburn,US,PST",
	[98]   = "Kargath,PVE,enUS,Vindication,US,EST",
	[16]   = "Kel'Thuzad,PVP,enUS,Vindication,US,MST",
	[65]   = "Khadgar,PVE,enUS,Rampage,US,EST",
	[1143] = "Khaz Modan,PVE,enUS,Cyclone,US,CST",
	[3726] = "Khaz'goroth,PVE,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1134] = "US", -- Khaz'goroth / old US datacenter
	[9]    = "Kil'jaeden,PVP,enUS,Bloodlust,US,PST",
	[4]    = "Kilrogg,PVE,enUS,Bloodlust,US,PST",
	[1071] = "Kirin Tor,RP,enUS,Rampage,US,CST",
	[1146] = "Korgath,PVP,enUS,Vengeance,US,CST",
	[1349] = "Korialstrasz,PVE,enUS,Reckoning,US,PST",
	[1147] = "Kul Tiras,PVE,enUS,Vengeance,US,CST",
	[101]  = "Laughing Skull,PVP,enUS,Vindication,US,CST",
	[1295] = "Lethon,PVP,enUS,Shadowburn,US,PST",
	[1]    = "Lightbringer,PVE,enUS,Cyclone,US,PST",
	[95]   = "Lightning's Blade,PVP,enUS,Vindication,US,EST",
	[1130] = "Lightninghoof,RPPVP,enUS,Shadowburn,US,CST",
	[99]   = "Llane,PVE,enUS,Vindication,US,EST",
	[68]   = "Lothar,PVE,enUS,Ruin,US,EST",
	[1173] = "Madoran,PVE,enUS,Ruin,US,CST",
	[163]  = "Maelstrom,RPPVP,enUS,Shadowburn,US,CST",
	[78]   = "Magtheridon,PVP,enUS,Ruin,US,EST",
	[1357] = "Maiev,PVP,enUS,Cyclone,US,PST",
	[59]   = "Mal'Ganis,PVP,enUS,Vindication,US,CST",
	[1132] = "Malfurion,PVE,enUS,Ruin,US,CST",
	[1148] = "Malorne,PVP,enUS,Reckoning,US,CST",
	[104]  = "Malygos,PVE,enUS,Vindication,US,CST",
	[70]   = "Mannoroth,PVP,enUS,Ruin,US,EST",
	[62]   = "Medivh,PVE,enUS,Ruin,US,EST",
	[1350] = "Misha,PVE,enUS,Vengeance,US,PST",
	[1374] = "Mok'Nathal,PVE,enUS,Reckoning,US,CST",
	[1365] = "Moon Guard,RP,enUS,Reckoning,US,CST",
	[153]  = "Moonrunner,PVE,enUS,Shadowburn,US,PST",
	[1145] = "Mug'thol,PVP,enUS,Reckoning,US,CST",
	[1182] = "Muradin,PVE,enUS,Vengeance,US,CST",
	[3734] = "Nagrand,PVE,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1432] = "US", -- Nagrand / old US datacenter
	[89]   = "Nathrezim,PVP,enUS,Vengeance,US,MST",
	[1169] = "US", -- Naxxramas
	[1367] = "Nazgrel,PVE,enUS,Bloodlust,US,EST",
	[1131] = "Nazjatar,PVP,enUS,Ruin,US,PST",
	[3208] = "Nemesis,PVP,ptBR,Rampage,US,US",
	[8]    = "Ner'zhul,PVP,enUS,Reckoning,US,PST",
	[1375] = "Nesingwary,PVE,enUS,Bloodlust,US,CST",
	[1359] = "Nordrassil,PVE,enUS,Vengeance,US,PST",
	[1262] = "Norgannon,PVE,enUS,Vindication,US,EST",
	[1285] = "Onyxia,PVP,enUS,Vindication,US,PST",
	[122]  = "Perenolde,PVE,enUS,Cyclone,US,MST",
	[5]    = "Proudmoore,PVE,enUS,Bloodlust,US,PST",
	[1428] = "Quel'Thalas,PVE,esMX,Vindication,US,CST",
	[1372] = "Quel'dorei,PVE,enUS,Bloodlust,US,CST",
	[1427] = "Ragnaros,PVP,esMX,Vindication,US,CST",
	[1072] = "Ravencrest,PVE,enUS,Rampage,US,CST",
	[1352] = "Ravenholdt,RPPVP,enUS,Shadowburn,US,EST",
	[1151] = "Rexxar,PVE,enUS,Vengeance,US,CST",
	[1358] = "Rivendare,PVP,enUS,Reckoning,US,PST",
	[151]  = "Runetotem,PVE,enUS,Vengeance,US,CST",
	[76]   = "Sargeras,PVP,enUS,Shadowburn,US,CST",
	[3738] = "Saurfang,PVE,enUS,Vengeance,US,AEST", -- US9 / new Oceanic datacenter
	[1153] = "US", -- Saurfang / old US datacenter
	[126]  = "Scarlet Crusade,RP,enUS,Reckoning,US,CST",
	[1267] = "Scilla,PVP,enUS,Shadowburn,US,EST",
	[1185] = "Sen'jin,PVE,enUS,Bloodlust,US,CST",
	[1290] = "Sentinels,RP,enUS,Rampage,US,PST",
	[125]  = "Shadow Council,RP,enUS,Reckoning,US,MST",
	[94]   = "Shadowmoon,PVP,enUS,Shadowburn,US,EST",
	[85]   = "Shadowsong,PVE,enUS,Reckoning,US,PST",
	[1364] = "Shandris,PVE,enUS,Cyclone,US,EST",
	[1557] = "Shattered Halls,PVP,enUS,Shadowburn,US,PST",
	[72]   = "Shattered Hand,PVP,enUS,Shadowburn,US,EST",
	[1354] = "Shu'halo,PVE,enUS,Vengeance,US,PST",
	[12]   = "Silver Hand,RP,enUS,Bloodlust,US,PST",
	[86]   = "Silvermoon,PVE,enUS,Reckoning,US,PST",
	[1356] = "Sisters of Elune,RP,enUS,Cyclone,US,CST",
	[74]   = "Skullcrusher,PVP,enUS,Ruin,US,EST",
	[131]  = "Skywall,PVE,enUS,Reckoning,US,PST",
	[130]  = "Smolderthorn,PVP,enUS,Vengeance,US,EST",
	[82]   = "Spinebreaker,PVP,enUS,Shadowburn,US,PST",
	[124]  = "Spirestone,PVP,enUS,Reckoning,US,PST",
	[160]  = "Staghelm,PVE,enUS,Shadowburn,US,CST",
	[1260] = "Steamwheedle Cartel,RP,enUS,Rampage,US,EST",
	[108]  = "Stonemaul,PVP,enUS,Cyclone,US,PST",
	[60]   = "Stormrage,PVE,enUS,Ruin,US,EST",
	[58]   = "Stormreaver,PVP,enUS,Rampage,US,CST",
	[110]  = "Stormscale,PVP,enUS,Reckoning,US,PST",
	[113]  = "Suramar,PVE,enUS,Cyclone,US,PST",
	[1292] = "Tanaris,PVE,enUS,Shadowburn,US,EST",
	[90]   = "Terenas,PVE,enUS,Reckoning,US,MST",
	[1563] = "Terokkar,PVE,enUS,Rampage,US,CST",
	[3724] = "Thaurissan,PVP,enUS,Bloodlust,US,AEST", -- US9 / new Oceanic datacenter
	[1433] = "US", -- Thaurissan / old US datacenter
	[1344] = "The Forgotten Coast,PVP,enUS,Ruin,US,EST",
	[1570] = "The Scryers,RP,enUS,Ruin,US,PST",
	[1559] = "The Underbog,PVP,enUS,Shadowburn,US,CST",
	[1289] = "The Venture Co,RPPVP,enUS,Shadowburn,US,PST",
	[1171] = "US", -- Theradras
	[1154] = "Thorium Brotherhood,RP,enUS,Bloodlust,US,CST",
	[1263] = "Thrall,PVE,enUS,Rampage,US,EST",
	[105]  = "Thunderhorn,PVE,enUS,Vindication,US,CST",
	[103]  = "Thunderlord,PVP,enUS,Ruin,US,CST",
	[11]   = "Tichondrius,PVP,enUS,Bloodlust,US,PST",
	[3210] = "Tol Barad,PVP,ptBR,Shadowburn,US,US",
	[1360] = "Tortheldrin,PVP,enUS,Reckoning,US,EST",
	[1175] = "Trollbane,PVE,enUS,Ruin,US,EST",
	[1265] = "Turalyon,PVE,enUS,Vindication,US,EST",
	[164]  = "Twisting Nether,RPPVP,enUS,Shadowburn,US,CST",
	[1283] = "Uldaman,PVE,enUS,Rampage,US,EST",
	[1426] = "US", -- Ulduar
	[116]  = "Uldum,PVE,enUS,Cyclone,US,PST",
	[1294] = "Undermine,PVE,enUS,Ruin,US,EST",
	[156]  = "Ursin,PVP,enUS,Shadowburn,US,PST",
	[3]    = "Uther,PVE,enUS,Vengeance,US,PST",
	[1348] = "Vashj,PVP,enUS,Bloodlust,US,PST",
	[1184] = "Vek'nilash,PVE,enUS,Bloodlust,US,CST",
	[1567] = "Velen,PVE,enUS,Vindication,US,PST",
	[71]   = "Warsong,PVP,enUS,Ruin,US,EST",
	[55]   = "Whisperwind,PVE,enUS,Rampage,US,CST",
	[159]  = "Wildhammer,PVP,enUS,Shadowburn,US,CST",
	[87]   = "Windrunner,PVE,enUS,Reckoning,US,PST",
	[1355] = "Winterhoof,PVE,enUS,Bloodlust,US,CST",
	[1369] = "Wyrmrest Accord,RP,enUS,Cyclone,US,PST",
	[1174] = "US", -- Xavius
	[1270] = "Ysera,PVE,enUS,Ruin,US,EST",
	[1268] = "Ysondre,PVP,enUS,Ruin,US,EST",
	[1572] = "Zangarmarsh,PVE,enUS,Rampage,US,MST",
	[61]   = "Zul'jin,PVE,enUS,Ruin,US,EST",
	[1259] = "Zuluhed,PVP,enUS,Shadowburn,US,EST",
--}}
--{{ Europe
	[577]  = "Aegwynn,PVP,deDE,Misery,EU",
	[1312] = "Aerie Peak,PVE,enGB,Reckoning / Abrechnung,EU",
	[518]  = "Agamaggan,PVP,enGB,Reckoning / Abrechnung,EU",
	[1413] = "Aggra (Português),PVP,ptPT,Misery,EU",
	[500]  = "Aggramar,PVE,enGB,Vengeance / Rache,EU",
	[1093] = "Ahn'Qiraj,PVP,enGB,Vindication,EU",
	[519]  = "Al'Akir,PVP,enGB,Glutsturm / Emberstorm,EU",
	[562]  = "Alexstrasza,PVE,deDE,Sturmangriff / Charge,EU",
	[563]  = "Alleria,PVE,deDE,Reckoning / Abrechnung,EU",
	[1391] = "Alonsus,PVE,enGB,Reckoning / Abrechnung,EU",
	[601]  = "Aman'Thul,PVE,deDE,Reckoning / Abrechnung,EU",
	[1330] = "Ambossar,PVE,deDE,Reckoning / Abrechnung,EU",
	[1394] = "Anachronos,PVE,enGB,Reckoning / Abrechnung,EU",
	[1104] = "Anetheron,PVP,deDE,Glutsturm / Emberstorm,EU",
	[564]  = "Antonidas,PVE,deDE,Vengeance / Rache,EU",
	[608]  = "Anub'arak,PVP,deDE,Glutsturm / Emberstorm,EU",
	[512]  = "Arak-arahm,PVP,frFR,Embuscade / Hinterhalt,EU",
	[1334] = "Arathi,PVP,frFR,Sturmangriff / Charge,EU",
	[501]  = "Arathor,PVE,enGB,Vindication,EU",
	[539]  = "Archimonde,PVP,frFR,Misery,EU",
	[1404] = "Area 52,PVE,deDE,Embuscade / Hinterhalt,EU",
	[536]  = "Argent Dawn,RP,enGB,Reckoning / Abrechnung,EU",
	[578]  = "Arthas,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1406] = "Arygos,PVE,deDE,Embuscade / Hinterhalt,EU",
	[1923] = "Ясеневый лес|Ashenvale,PVP,ruRU,Vindication,EU",
	[502]  = "Aszune,PVE,enGB,Reckoning / Abrechnung,EU",
	[1597] = "Auchindoun,PVP,enGB,Vindication,EU",
	[503]  = "Azjol-Nerub,PVE,enGB,Cruelty / Crueldad,EU",
	[579]  = "Azshara,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1922] = "Азурегос|Azuregos,PVE,ruRU,Vindication,EU",
	[1417] = "Azuremyst,PVE,enGB,Glutsturm / Emberstorm,EU",
	[565]  = "Baelgun,PVE,deDE,Reckoning / Abrechnung,EU",
	[607]  = "Balnazzar,PVP,enGB,Vindication,EU",
	[566]  = "Blackhand,PVE,deDE,Vengeance / Rache,EU",
	[580]  = "Blackmoore,PVP,deDE,Glutsturm / Emberstorm,EU",
	[581]  = "Blackrock,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1929] = "Черный Шрам|Blackscar,PVP,ruRU,Vindication,EU",
	[1416] = "Blade's Edge,PVE,enGB,Glutsturm / Emberstorm,EU",
	[521]  = "Bladefist,PVP,enGB,Cruelty / Crueldad,EU",
	[630]  = "Bloodfeather,PVP,enGB,Cruelty / Crueldad,EU",
	[504]  = "Bloodhoof,PVE,enGB,Reckoning / Abrechnung,EU",
	[522]  = "Bloodscalp,PVP,enGB,Reckoning / Abrechnung,EU",
	[1613] = "Blutkessel,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1924] = "Пиратская бухта|Booty Bay,PVP,ruRU,Vindication,EU",
	[1625] = "Борейская тундра|Borean Tundra,PVE,ruRU,Sturmangriff / Charge,EU",
	[1299] = "Boulderfist,PVP,enGB,Vindication,EU",
	[1393] = "Bronze Dragonflight,PVE,enGB,Cruelty / Crueldad,EU",
	[1081] = "Bronzebeard,PVE,enGB,Reckoning / Abrechnung,EU",
	[523]  = "Burning Blade,PVP,enGB,Reckoning / Abrechnung,EU",
	[524]  = "Burning Legion,PVP,enGB,Cruelty / Crueldad,EU",
	[1392] = "Burning Steppes,PVP,enGB,Cruelty / Crueldad,EU",
	[1381] = "C'Thun,PVP,esES,Cruelty / Crueldad,EU",
	[1315] = "EU", -- Caduta dei Draghi
	[3391] = "EU", -- Cerchio del Sangue
	[1307] = "Chamber of Aspects,PVE,enGB,Misery,EU",
	[1620] = "Chants éternels,PVE,frFR,Sturmangriff / Charge,EU",
	[545]  = "Cho'gall,PVP,frFR,Vengeance / Rache,EU",
	[1083] = "Chromaggus,PVP,enGB,Vindication,EU",
	[1395] = "Colinas Pardas,PVE,esES,Cruelty / Crueldad,EU",
	[1127] = "Confrérie du Thorium,RP,frFR,Embuscade / Hinterhalt,EU",
	[644]  = "Conseil des Ombres,RPPVP,frFR,Embuscade / Hinterhalt,EU",
	[525]  = "Crushridge,PVP,enGB,Reckoning / Abrechnung,EU",
	[1337] = "Culte de la Rive noire,RPPVP,frFR,Embuscade / Hinterhalt,EU",
	[526]  = "Daggerspine,PVP,enGB,Vindication,EU",
	[538]  = "Dalaran,PVE,frFR,Sturmangriff / Charge,EU",
	[1321] = "Dalvengyr,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1317] = "Darkmoon Faire,RP,enGB,Cruelty / Crueldad,EU",
	[631]  = "Darksorrow,PVP,enGB,Cruelty / Crueldad,EU",
	[1389] = "Darkspear,PVE,enGB,Cruelty / Crueldad,EU",
	[1619] = "Das Konsortium,RPPVP,deDE,Glutsturm / Emberstorm,EU",
	[614]  = "Das Syndikat,RPPVP,deDE,Glutsturm / Emberstorm,EU",
	[1605] = "Страж Смерти|Deathguard,PVP,ruRU,Vindication,EU",
	[1617] = "Ткач Смерти|Deathweaver,PVP,ruRU,Vindication,EU",
	[527]  = "Deathwing,PVP,enGB,Vindication,EU",
	[1609] = "Подземье|Deepholm,PVP,ruRU,Sturmangriff / Charge,EU",
	[635]  = "Defias Brotherhood,RPPVP,enGB,Glutsturm / Emberstorm,EU",
	[1084] = "Dentarg,PVP,enGB,Reckoning / Abrechnung,EU",
	[1327] = "Der Mithrilorden,RP,deDE,Embuscade / Hinterhalt,EU",
	[617]  = "Der Rat von Dalaran,RP,deDE,Embuscade / Hinterhalt,EU",
	[1326] = "Der abyssische Rat,RPPVP,deDE,Glutsturm / Emberstorm,EU",
	[582]  = "Destromath,PVP,deDE,Glutsturm / Emberstorm,EU",
	[531]  = "Dethecus,PVP,deDE,Embuscade / Hinterhalt,EU",
	[1618] = "Die Aldor,RP,deDE,Sturmangriff / Charge,EU",
	[1121] = "Die Arguswacht,RPPVP,deDE,Glutsturm / Emberstorm,EU",
	[1333] = "Die Nachtwache,RP,deDE,Embuscade / Hinterhalt,EU",
	[576]  = "Die Silberne Hand,RP,deDE,Glutsturm / Emberstorm,EU",
	[1119] = "Die Todeskrallen,RPPVP,deDE,Glutsturm / Emberstorm,EU",
	[1118] = "Die ewige Wacht,RP,deDE,Glutsturm / Emberstorm,EU",
	[505]  = "Doomhammer,PVE,enGB,Embuscade / Hinterhalt,EU",
	[506]  = "Draenor,PVE,enGB,Embuscade / Hinterhalt,EU",
	[507]  = "Dragonblight,PVE,enGB,Vindication,EU",
	[528]  = "Dragonmaw,PVP,enGB,Reckoning / Abrechnung,EU",
	[1092] = "Drak'thul,PVP,enGB,Reckoning / Abrechnung,EU",
	[641]  = "Drek'Thar,PVE,frFR,Embuscade / Hinterhalt,EU",
	[1378] = "Dun Modr,PVP,esES,Cruelty / Crueldad,EU",
	[600]  = "Dun Morogh,PVE,deDE,Embuscade / Hinterhalt,EU",
	[529]  = "Dunemaul,PVP,enGB,Vindication,EU",
	[535]  = "Durotan,PVE,deDE,Glutsturm / Emberstorm,EU",
	[561]  = "Earthen Ring,RP,enGB,Cruelty / Crueldad,EU",
	[1612] = "Echsenkessel,PVP,deDE,Sturmangriff / Charge,EU",
	[1123] = "Eitrigg,PVE,frFR,Embuscade / Hinterhalt,EU",
	[1336] = "Eldre'Thalas,PVP,frFR,Vengeance / Rache,EU",
	[540]  = "Elune,PVE,frFR,Misery,EU",
	[508]  = "Emerald Dream,PVE,enGB,Embuscade / Hinterhalt,EU",
	[1091] = "Emeriss,PVP,enGB,Reckoning / Abrechnung,EU",
	[1310] = "Eonar,PVE,enGB,Glutsturm / Emberstorm,EU",
	[583]  = "Eredar,PVP,deDE,Vengeance / Rache,EU",
	[1925] = "Вечная Песня|Eversong,PVE,ruRU,Vindication,EU",
	[1087] = "Executus,PVP,enGB,Cruelty / Crueldad,EU",
	[1385] = "Exodar,PVE,esES,Cruelty / Crueldad,EU",
	[1611] = "Festung der Stürme,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1623] = "Дракономор|Fordragon,PVE,ruRU,Sturmangriff / Charge,EU",
	[516]  = "Forscherliga,RP,deDE,Embuscade / Hinterhalt,EU",
	[1300] = "Frostmane,PVP,enGB,Misery,EU",
	[584]  = "Frostmourne,PVP,deDE,Glutsturm / Emberstorm,EU",
	[632]  = "Frostwhisper,PVP,enGB,Cruelty / Crueldad,EU",
	[585]  = "Frostwolf,PVP,deDE,Vengeance / Rache,EU",
	[1614] = "Галакронд|Galakrond,PVE,ruRU,Sturmangriff / Charge,EU",
	[1390] = "EU", -- GM Test realm 2
	[509]  = "Garona,PVP,frFR,Embuscade / Hinterhalt,EU",
	[1401] = "Garrosh,PVE,deDE,Embuscade / Hinterhalt,EU",
	[606]  = "Genjuros,PVP,enGB,Cruelty / Crueldad,EU",
	[1588] = "Ghostlands,PVE,enGB,Vindication,EU",
	[567]  = "Gilneas,PVE,deDE,Reckoning / Abrechnung,EU",
	[1403] = "EU", -- Gnomeregan
	[1928] = "Голдринн|Goldrinn,PVE,ruRU,Vindication,EU",
	[1602] = "Гордунни|Gordunni,PVP,ruRU,Vindication,EU",
	[586]  = "Gorgonnash,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1610] = "Седогрив|Greymane,PVP,ruRU,Vindication,EU",
	[1303] = "Grim Batol,PVP,enGB,Misery,EU",
	[1927] = "Гром|Grom,PVP,ruRU,Vindication,EU",
	[1325] = "EU", -- Grizzlyhügel
	[587]  = "Gul'dan,PVP,deDE,Glutsturm / Emberstorm,EU",
	[646]  = "Hakkar,PVP,enGB,Reckoning / Abrechnung,EU",
	[638]  = "Haomarush,PVP,enGB,Reckoning / Abrechnung,EU",
	[1587] = "Hellfire,PVE,enGB,Vindication,EU",
	[619]  = "Hellscream,PVE,enGB,Vengeance / Rache,EU",
	[1615] = "Ревущий фьорд|Howling Fjord,PVP,ruRU,Sturmangriff / Charge,EU",
	[542]  = "Hyjal,PVE,frFR,Misery,EU",
	[541]  = "Illidan,PVP,frFR,Sturmangriff / Charge,EU",
	[3656] = "EU", -- Internal Record 3656
	[3657] = "EU", -- Internal Record 3657
	[3660] = "EU", -- Internal Record 3660
	[3666] = "EU", -- Internal Record 3666
	[3674] = "EU", -- Internal Record 3674
	[3679] = "EU", -- Internal Record 3679
	[3680] = "EU", -- Internal Record 3680
	[3681] = "EU", -- Internal Record 3681
	[3682] = "EU", -- Internal Record 3682
	[3686] = "EU", -- Internal Record 3686
	[3687] = "EU", -- Internal Record 3687
	[3690] = "EU", -- Internal Record 3690
	[3691] = "EU", -- Internal Record 3691
	[3692] = "EU", -- Internal Record 3692
	[3696] = "EU", -- Internal Record 3696
	[3702] = "EU", -- Internal Record 3702
	[3703] = "EU", -- Internal Record 3703
	[3713] = "EU", -- Internal Record 3713
	[3714] = "EU", -- Internal Record 3714
	[1304] = "Jaedenar,PVP,enGB,Vindication,EU",
	[543]  = "Kael'thas,PVP,frFR,Embuscade / Hinterhalt,EU",
	[1596] = "Karazhan,PVP,enGB,Vindication,EU",
	[568]  = "Kargath,PVE,deDE,Reckoning / Abrechnung,EU",
	[1305] = "Kazzak,PVP,enGB,Misery,EU",
	[588]  = "Kel'Thuzad,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1080] = "Khadgar,PVE,enGB,Reckoning / Abrechnung,EU",
	[640]  = "Khaz Modan,PVE,frFR,Sturmangriff / Charge,EU",
	[569]  = "Khaz'goroth,PVE,deDE,Embuscade / Hinterhalt,EU",
	[589]  = "Kil'jaeden,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1311] = "Kilrogg,PVE,enGB,Misery,EU",
	[537]  = "Kirin Tor,RP,frFR,Glutsturm / Emberstorm,EU",
	[633]  = "Kor'gall,PVP,enGB,Cruelty / Crueldad,EU",
	[616]  = "Krag'jin,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1332] = "Krasus,PVE,frFR,Embuscade / Hinterhalt,EU",
	[1082] = "Kul Tiras,PVE,enGB,Reckoning / Abrechnung,EU",
	[613]  = "Kult der Verdammten,RPPVP,deDE,Glutsturm / Emberstorm,EU",
	[1086] = "La Croisade écarlate,RPPVP,frFR,Embuscade / Hinterhalt,EU",
	[621]  = "Laughing Skull,PVP,enGB,Vindication,EU",
	[1626] = "Les Clairvoyants,RP,frFR,Embuscade / Hinterhalt,EU",
	[647]  = "Les Sentinelles,RP,frFR,Embuscade / Hinterhalt,EU",
	[1603] = "Король-лич|Lich King,PVP,ruRU,Vindication,EU",
	[1388] = "Lightbringer,PVE,enGB,Cruelty / Crueldad,EU",
	[637]  = "Lightning's Blade,PVP,enGB,Vindication,EU",
	[1409] = "Lordaeron,PVE,deDE,Glutsturm / Emberstorm,EU",
	[1387] = "Los Errantes,PVE,esES,Cruelty / Crueldad,EU",
	[570]  = "Lothar,PVE,deDE,Reckoning / Abrechnung,EU",
	[571]  = "Madmortem,PVE,deDE,Vengeance / Rache,EU",
	[622]  = "Magtheridon,PVE,enGB,Cruelty / Crueldad,EU",
	[590]  = "Mal'Ganis,PVP,deDE,Sturmangriff / Charge,EU",
	[572]  = "Malfurion,PVE,deDE,Reckoning / Abrechnung,EU",
	[1324] = "Malorne,PVE,deDE,Reckoning / Abrechnung,EU",
	[1098] = "Malygos,PVE,deDE,Reckoning / Abrechnung,EU",
	[591]  = "Mannoroth,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1621] = "Marécage de Zangar,PVE,frFR,Sturmangriff / Charge,EU",
	[1089] = "Mazrigos,PVE,enGB,Cruelty / Crueldad,EU",
	[517]  = "Medivh,PVE,frFR,Vengeance / Rache,EU",
	[1402] = "EU", -- Menethil
	[1386] = "Minahonda,PVE,esES,Cruelty / Crueldad,EU",
	[1085] = "Moonglade,RP,enGB,Reckoning / Abrechnung,EU",
	[1319] = "Mug'thol,PVP,deDE,Embuscade / Hinterhalt,EU",
	[1329] = "EU", -- Muradin
	[1589] = "Nagrand,PVE,enGB,Misery,EU",
	[594]  = "Nathrezim,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1624] = "Naxxramas,PVP,frFR,Sturmangriff / Charge,EU",
	[1105] = "Nazjatar,PVP,deDE,Glutsturm / Emberstorm,EU",
	[612]  = "Nefarian,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1316] = "Nemesis,PVP,itIT,Misery,EU",
	[624]  = "Neptulon,PVP,enGB,Cruelty / Crueldad,EU",
	[544]  = "Ner'zhul,PVP,frFR,Embuscade / Hinterhalt,EU",
	[611]  = "Nera'thor,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1607] = "Nethersturm,PVE,deDE,Sturmangriff / Charge,EU",
	[618]  = "Nordrassil,PVE,enGB,Cruelty / Crueldad,EU",
	[1408] = "Norgannon,PVE,deDE,Embuscade / Hinterhalt,EU",
	[574]  = "Nozdormu,PVE,deDE,Embuscade / Hinterhalt,EU",
	[610]  = "Onyxia,PVP,deDE,Embuscade / Hinterhalt,EU",
	[1301] = "Outland,PVP,enGB,Misery,EU",
	[575]  = "Perenolde,PVE,deDE,Embuscade / Hinterhalt,EU",
	[1309] = "Pozzo dell'Eternità,PVE,itIT,Misery,EU",
	[593]  = "Proudmoore,PVE,deDE,Vengeance / Rache,EU",
	[623]  = "Quel'Thalas,PVE,enGB,Cruelty / Crueldad,EU",
	[626]  = "Ragnaros,PVP,enGB,Sturmangriff / Charge,EU",
	[1322] = "Rajaxx,PVP,deDE,Glutsturm / Emberstorm,EU",
	[642]  = "Rashgarroth,PVP,frFR,Embuscade / Hinterhalt,EU",
	[554]  = "Ravencrest,PVP,enGB,Vengeance / Rache,EU",
	[1308] = "Ravenholdt,RPPVP,enGB,Glutsturm / Emberstorm,EU",
	[1616] = "Разувий|Razuvious,PVP,ruRU,Sturmangriff / Charge,EU",
	[1099] = "Rexxar,PVE,deDE,Reckoning / Abrechnung,EU",
	[547]  = "Runetotem,PVE,enGB,Misery,EU",
	[1382] = "Sanguino,PVP,esES,Cruelty / Crueldad,EU",
	[546]  = "Sargeras,PVP,frFR,Embuscade / Hinterhalt,EU",
	[1314] = "Saurfang,PVE,enGB,Cruelty / Crueldad,EU",
	[1096] = "Scarshield Legion,RPPVP,enGB,Glutsturm / Emberstorm,EU",
	[602]  = "Sen'jin,PVE,deDE,Embuscade / Hinterhalt,EU",
	[2074] = "EU", -- Schwarznarbe
	[548]  = "Shadowsong,PVE,enGB,Reckoning / Abrechnung,EU",
	[1598] = "Shattered Halls,PVP,enGB,Vindication,EU",
	[556]  = "Shattered Hand,PVP,enGB,Cruelty / Crueldad,EU",
	[1608] = "Shattrath,PVE,deDE,Embuscade / Hinterhalt,EU",
	[1383] = "Shen'dralar,PVP,esES,Cruelty / Crueldad,EU",
	[549]  = "Silvermoon,PVE,enGB,Misery,EU",
	[533]  = "Sinstralis,PVP,frFR,Vengeance / Rache,EU",
	[557]  = "Skullcrusher,PVP,enGB,Glutsturm / Emberstorm,EU",
	[1604] = "Свежеватель Душ|Soulflayer,PVP,ruRU,Vindication,EU",
	[558]  = "Spinebreaker,PVP,enGB,Reckoning / Abrechnung,EU",
	[1606] = "Sporeggar,RPPVP,enGB,Glutsturm / Emberstorm,EU",
	[1117] = "Steamwheedle Cartel,RP,enGB,Reckoning / Abrechnung,EU",
	[550]  = "Stormrage,PVE,enGB,Glutsturm / Emberstorm,EU",
	[559]  = "Stormreaver,PVP,enGB,Reckoning / Abrechnung,EU",
	[560]  = "Stormscale,PVP,enGB,Vengeance / Rache,EU",
	[511]  = "Sunstrider,PVP,enGB,Vindication,EU",
	[1331] = "Suramar,PVE,frFR,Vengeance / Rache,EU",
	[628]  = "Sylvanas,PVP,enGB,Sturmangriff / Charge,EU",
	[1320] = "Taerar,PVP,deDE,Sturmangriff / Charge,EU",
	[1090] = "Talnivarr,PVP,enGB,Vindication,EU",
	[1306] = "Tarren Mill,PVP,enGB,Reckoning / Abrechnung,EU",
	[1407] = "Teldrassil,PVE,deDE,Embuscade / Hinterhalt,EU",
	[1622] = "Temple noir,PVP,frFR,Sturmangriff / Charge,EU",
	[551]  = "Terenas,PVE,enGB,Embuscade / Hinterhalt,EU",
	[1415] = "Terokkar,PVE,enGB,Cruelty / Crueldad,EU",
	[615]  = "Terrordar,PVP,deDE,Embuscade / Hinterhalt,EU",
	[627]  = "The Maelstrom,PVP,enGB,Vindication,EU",
	[1595] = "The Sha'tar,RP,enGB,Reckoning / Abrechnung,EU",
	[636]  = "The Venture Co,RPPVP,enGB,Glutsturm / Emberstorm,EU",
	[605]  = "Theradras,PVP,deDE,Embuscade / Hinterhalt,EU",
	[1926] = "Термоштепсель|Thermaplugg,PVP,ruRU,Vindication,EU",
	[604]  = "Thrall,PVE,deDE,Glutsturm / Emberstorm,EU",
	[643]  = "Throk'Feroth,PVP,frFR,Embuscade / Hinterhalt,EU",
	[552]  = "Thunderhorn,PVE,enGB,Misery,EU",
	[1106] = "Tichondrius,PVE,deDE,Glutsturm / Emberstorm,EU",
	[1328] = "Tirion,PVE,deDE,Glutsturm / Emberstorm,EU",
	[1405] = "Todeswache,RP,deDE,Embuscade / Hinterhalt,EU",
	[1088] = "Trollbane,PVP,enGB,Vindication,EU",
	[553]  = "Turalyon,PVE,enGB,Embuscade / Hinterhalt,EU",
	[513]  = "Twilight's Hammer,PVP,enGB,Reckoning / Abrechnung,EU",
	[625]  = "Twisting Nether,PVP,enGB,Sturmangriff / Charge,EU",
	[1384] = "Tyrande,PVE,esES,Cruelty / Crueldad,EU",
	[1122] = "Uldaman,PVE,frFR,Embuscade / Hinterhalt,EU",
	[1323] = "Ulduar,PVE,deDE,Reckoning / Abrechnung,EU",
	[1380] = "Uldum,PVP,esES,Cruelty / Crueldad,EU",
	[1400] = "Un'Goro,PVE,deDE,Embuscade / Hinterhalt,EU",
	[645]  = "Varimathras,PVE,frFR,Misery,EU",
	[629]  = "Vashj,PVP,enGB,Reckoning / Abrechnung,EU",
	[1318] = "Vek'lor,PVP,deDE,Glutsturm / Emberstorm,EU",
	[1298] = "Vek'nilash,PVE,enGB,Glutsturm / Emberstorm,EU",
	[510]  = "Vol'jin,PVE,frFR,Embuscade / Hinterhalt,EU",
	[1313] = "Wildhammer,PVE,enGB,Misery,EU",
	[2073] = "EU", -- Winterhuf
	[609]  = "Wrathbringer,PVP,deDE,Glutsturm / Emberstorm,EU",
	[639]  = "Xavius,PVP,enGB,Glutsturm / Emberstorm,EU",
	[1097] = "Ysera,PVE,deDE,Reckoning / Abrechnung,EU",
	[1335] = "Ysondre,PVP,frFR,Vengeance / Rache,EU",
	[515]  = "Zenedar,PVP,enGB,Cruelty / Crueldad,EU",
	[592]  = "Zirkel des Cenarius,RP,deDE,Embuscade / Hinterhalt,EU",
	[1379] = "Zul'jin,PVP,esES,Cruelty / Crueldad,EU",
	[573]  = "Zuluhed,PVP,deDE,Glutsturm / Emberstorm,EU",
EOF;

    $validRegions = ['US','EU'];
    $tr = [];

    $luaLines = explode("\n", $lua);
    foreach ($luaLines as $luaLine) {
        if (!preg_match('/\[(\d+)\]\s*=\s*"([^"]+)",(?:\s*-+\s*([^\r\n]+))?/', $luaLine, $res)) {
            echo "Could not parse: \"$luaLine\"\n";
            continue;
        }

        $guid = $res[1];
        $quoted = $res[2];
        $comment = isset($res[3]) ? $res[3] : '';
        $name = '';
        $region = '';

        $quotedParts = explode(',', $quoted);
        if (count($quotedParts) > 1) {
            $name = $quotedParts[0];
            for ($x = 1; $x < count($quotedParts); $x++) {
                if (in_array($quotedParts[$x], $validRegions)) {
                    $region = $quotedParts[$x];
                    break;
                }
            }
        } else {
            $region = $quotedParts[0];
            $name = $comment;
            if (strpos($name, '/') !== false) {
                $name = substr($name, 0, strpos($name, '/') - 1);
            }
        }
        $name = trim($name);

        if ($name == '') {
            echo "Could not find name: \"$luaLine\"\n";
            continue;
        }

        if ($region == '') {
            echo "Could not find region: \"$luaLine\"\n";
            continue;
        }

        $tr[$guid] = [$region];
        $name = explode('|', $name);
        foreach ($name as $nm) {
            $tr[$guid][] = $nm;
        }
    }

    return $tr;
}
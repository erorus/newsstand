--[[--------------------------------------------------------------------
	LibRealmInfo
	World of Warcraft library for obtaining information about realms.
	Copyright 2014 Phanx <addons@phanx.net>
	Do not distribute as a standalone addon.
	See accompanying LICENSE and README files for more details.
	https://github.com/Phanx/LibRealmInfo
	http://wow.curseforge.com/addons/librealminfo
	http://www.wowinterface.com/downloads/info22987-LibRealmInfo
----------------------------------------------------------------------]]

local MAJOR, MINOR = "LibRealmInfo", 10
assert(LibStub, MAJOR.." requires LibStub")
local lib, oldminor = LibStub:NewLibrary(MAJOR, MINOR)
if not lib then return end

local standalone = (...) == MAJOR
local realmData, connectionData
local Unpack

local function debug(...)
	if standalone then
		print("|cffff7f7f["..MAJOR.."]|r", ...)
	end
end

------------------------------------------------------------------------

local currentRegion

function lib:GetCurrentRegion()
	if currentRegion then
		return currentRegion
	end

	if Unpack then
		Unpack()
	end

	local guid = UnitGUID("player")
	if guid then
		local server = tonumber(strmatch(guid, "^Player%-(%d+)"))
		local realm = realmData[server]
		if realm then
			currentRegion = realm.region
			return currentRegion
		end
	end

	debug("GetCurrentRegion: could not identify region based on player GUID", guid)
end

------------------------------------------------------------------------

local validRegions = { US = true, EU = true, CN = true, TW = true } -- missing KR data :(

function lib:GetRealmInfo(name, region)
	debug("GetRealmInfo", name, region)
	if type(name) == "number" or strmatch(name, "^%d+$") then
		return self:GetRealmInfoByID(name)
	end
	name = type(name) == "string" and strtrim(name) or ""
	assert(strlen(name) > 0, "Usage: GetRealmInfo(name[, region])")

	if not region or not validRegions[region] then
		region = self:GetCurrentRegion()
	end

	if Unpack then
		Unpack()
	end

	for id, realm in pairs(realmData) do
		if realm.region == region and (realm.api_name == name or realm.name == name or realm.latin_api_name == name or realm.latin_name == name) then
			return id, realm.name, realm.api_name, realm.rules, realm.locale, realm.battlegroup, realm.region, realm.timezone, realm.connections, realm.latin_name, realm.latin_api_name
		end
	end

	debug("No info found for realm", name, "in region", region)
end

------------------------------------------------------------------------

function lib:GetRealmInfoByID(id)
	debug("GetRealmInfoByID", id)
	id = tonumber(id)
	assert(id, "Usage: GetRealmInfoByID(id)")

	if Unpack then
		Unpack()
	end

	local realm = realmData[id]
	if realm and realm.name then
		return realm.id, realm.name, realm.api_name, realm.rules, realm.locale, realm.battlegroup, realm.region, realm.timezone, realm.connections, realm.latin_name, realm.latin_api_name
	end

	debug("No info found for realm ID", name)
end

------------------------------------------------------------------------

function lib:GetRealmInfoByGUID(guid)
	assert(type(guid) == "string", "Usage: GetRealmInfoByGUID(guid)")
	if not strmatch(guid, "^Player%-") then
		return debug("Unsupported GUID type", (strsplit("-", guid)))
	end
	local _, _, _, _, _, _, realm = GetPlayerInfoByGUID(guid)
	if realm == "" then
		realm = GetRealmName()
	end
	return self:GetRealmInfo(realm)
end

------------------------------------------------------------------------

function lib:GetRealmInfoByUnit(unit)
	assert(type(unit) == "string", "Usage: GetRealmInfoByUnit(unit)")
	local guid = UnitGUID(unit)
	if not guid then
		return debug("No GUID available for unit", unit)
	end
	return self:GetRealmInfoByGUID(guid)
end

------------------------------------------------------------------------

function Unpack()
	debug("Unpacking data...")

	for id, info in pairs(realmData) do
		if not strfind(info, ",") then
			-- This server doesn't belong to a specific realm
			-- but may be used to temporarily host other realms
			-- and can be used to determine the player's region.
			realmData[id] = {
				region = info,
			}
		else
			-- Normal realm server
			-- Aegwynn,PVP,enUS,Vengeance,US,CST
			local name, rules, locale, battlegroup, region, timezone = strsplit(",", info)
			local name, latin_name = strsplit("|", name)
			realmData[id] = {
				id = id,
				name = name,
				api_name = (gsub(name, "%s", "")),
				rules = rules,
				locale = locale,
				battlegroup = battlegroup,
				region = region,
				timezone = timezone, -- only for US region realms
				latin_name = latin_name, -- only for ruRU language realms
				latin_api_name = latin_name and (gsub(latin_name, "%s", "")) or nil, -- only for ruRU language realms
			}
		end
	end

	for i = 1, #connectionData do
		local connections = { strsplit(",", connectionData[i]) }
		for j = 1, #connections do
			local id = tonumber(connections[j])
			connections[j] = id
			realmData[id].connections = connections
		end
	end

	connectionData = nil
	Unpack = nil
	collectgarbage()

	debug("Done unpacking data.")
--[[
	local auto = { GetAutoCompleteRealms() }
	if #auto > 1 then
		local id, _, _, _, _, _, _, _, connections = lib:GetRealmInfo(GetRealmName())
		if not id then
			return
		end
		if not connections then
			print("|cffffff7fLibRealmInfo:|r Missing connected realm info for", id, GetRealmName())
			return
		end
		for i = 1, #auto do
			local name = auto[i]
			auto[name] = true
			auto[i] = nil
		end
		for i = 1, #connections do
			local _, name = GetRealmInfo(connections[i])
			if auto[name] then
				auto[name] = nil
			else
				auto[name] = connections[i]
			end
		end
		if next(auto) then
			print("|cffffff7fLibRealmInfo:|r Incomplete connected realm info for", id, GetRealmName())
			for name, id in pairs(auto) do
				print(name, id == true and "MISSING" or "INCORRECT")
			end
		end
	end
]]
end

------------------------------------------------------------------------

realmData = {
--{{ North America
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
	[3695] = "US", -- Internal Record 3695<new>[3729] = "US", -- Internal Record 3695 US9
	[3697] = "US", -- Internal Record 3697<new>[3728] = "US", -- Internal Record 3697 US9
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
--}}
--[[ Korea
	[] = "가로나,PVP,koKR,격노의 전장,KR",
	[] = "굴단,PVP,koKR,징벌의 전장,KR",
	[] = "노르간논,PVP,koKR,징벌의 전장,KR",
	[] = "달라란,PVP,koKR,격노의 전장,KR",
	[] = "데스윙,PVP,koKR,격노의 전장,KR",
	[] = "듀로탄,PVP,koKR,징벌의 전장,KR",
	[] = "렉사르,PVE,koKR,격노의 전장,KR",
	[] = "말퓨리온,PVP,koKR,격노의 전장,KR",
	[] = "불타는 군단,PVE,koKR,격노의 전장,KR",
	[] = "세나리우스,PVP,koKR,격노의 전장,KR",
	[] = "스톰레이지,PVE,koKR,징벌의 전장,KR",
	[] = "아즈샤라,PVP,koKR,징벌의 전장,KR",
	[] = "알렉스트라자,PVP,koKR,격노의 전장,KR",
	[] = "와일드해머,PVE,koKR,격노의 전장,KR",
	[] = "윈드러너,PVE,koKR,징벌의 전장,KR",
	[] = "줄진,PVP,koKR,징벌의 전장,KR",
	[] = "하이잘,PVP,koKR,격노의 전장,KR",
	[] = "헬스크림,PVP,koKR,격노의 전장,KR",
]]
--{{ China
	[925]  = "万色星辰,PVE,zhCN,Battle Group 9,CN",
	[922]  = "世界之树,PVE,zhCN,Battle Group 9,CN",
	[1494] = "丹莫德,PVP,zhCN,Battle Group 13,CN",
	[794]  = "主宰之剑,PVP,zhCN,Battle Group 4,CN",
	[1696] = "亚雷戈斯,PVE,zhCN,Battle Group 16,CN",
	[2124] = "亡语者,PVP,zhCN,Battle Group 21,CN",
	[1663] = "伊兰尼库斯,PVP,zhCN,Battle Group 15,CN",
	[790]  = "伊利丹,PVP,zhCN,Battle Group 4,CN",
	[940]  = "伊森利恩,PVP,zhCN,Battle Group 10,CN",
	[1694] = "伊森德雷,PVP,zhCN,Battle Group 16,CN",
	[746]  = "伊瑟拉,PVE,zhCN,Battle Group 2,CN",
	[1502] = "伊莫塔尔,PVP,zhCN,Battle Group 14,CN",
	[951]  = "伊萨里奥斯,PVP,zhCN,Battle Group 10,CN",
	[944]  = "元素之力,PVP,zhCN,Battle Group 10,CN",
	[864]  = "克尔苏加德,PVP,zhCN,Battle Group 8,CN",
	[1207] = "克洛玛古斯,PVP,zhCN,Battle Group 11,CN",
	[1209] = "克苏恩,PVP,zhCN,Battle Group 11,CN",
	[1809] = "军团要塞,PVP,zhCN,Battle Group 17,CN",
	[2137] = "冬拥湖,PVP,zhCN,Battle Group 21,CN",
	[1693] = "冬泉谷,PVP,zhCN,Battle Group 16,CN",
	[1657] = "冰川之拳,PVP,zhCN,Battle Group 15,CN",
	[758]  = "冰霜之刃,PVP,zhCN,Battle Group 3,CN",
	[852]  = "冰风岗,PVP,zhCN,Battle Group 7,CN",
	[1794] = "凤凰之神,PVP,zhCN,Battle Group 17,CN",
	[863]  = "凯尔萨斯,PVP,zhCN,Battle Group 8,CN",
	[814]  = "凯恩血蹄,PVP,zhCN,Battle Group 5,CN",
	[867]  = "利刃之拳,PVP,zhCN,Battle Group 7,CN",
	[1658] = "刺骨利刃,PVP,zhCN,Battle Group 15,CN",
	[927]  = "加兹鲁维,PVP,zhCN,Battle Group 9,CN",
	[1498] = "加基森,PVP,zhCN,Battle Group 13,CN",
	[1944] = "加尔,PVP,zhCN,Battle Group 19,CN",
	[1499] = "加里索斯,PVP,zhCN,Battle Group 13,CN",
	[840]  = "勇士岛,PVP,zhCN,Battle Group 6,CN",
	[828]  = "千针石林,PVP,zhCN,Battle Group 6,CN",
	[771]  = "卡德加,PVP,zhCN,Battle Group 3,CN",
	[720]  = "卡德罗斯,PVP,zhCN,Battle Group 1,CN",
	[721]  = "卡扎克,PVP,zhCN,Battle Group 1,CN",
	[1216] = "卡拉赞,PVP,zhCN,Battle Group 11,CN",
	[916]  = "卡珊德拉,PVP,zhCN,Battle Group 9,CN",
	[1692] = "厄祖玛特,PVP,zhCN,Battle Group 16,CN",
	[1489] = "古加尔,PVP,zhCN,Battle Group 13,CN",
	[857]  = "古尔丹,PVP,zhCN,Battle Group 7,CN",
	[1223] = "古拉巴什,PVP,zhCN,Battle Group 12,CN",
	[2127] = "古达克,PVP,zhCN,Battle Group 21,CN",
	[1808] = "哈兰,PVP,zhCN,Battle Group 17,CN",
	[1224] = "哈卡,PVP,zhCN,Battle Group 12,CN",
	[1971] = "嚎风峡湾,PVP,zhCN,Battle Group 20,CN",
	[718]  = "回音山,PVE,zhCN,Battle Group 1,CN",
	[714]  = "国王之谷,PVP,zhCN,Battle Group 1,CN",
	[745]  = "图拉扬,PVE,zhCN,Battle Group 2,CN",
	[833]  = "圣火神殿,PVP,zhCN,Battle Group 6,CN",
	[762]  = "地狱之石,PVP,zhCN,Battle Group 3,CN",
	[761]  = "地狱咆哮,PVP,zhCN,Battle Group 3,CN",
	[1496] = "埃克索图斯,PVP,zhCN,Battle Group 13,CN",
	[750]  = "埃加洛尔,PVP,zhCN,Battle Group 3,CN",
	[797]  = "埃德萨拉,PVP,zhCN,Battle Group 5,CN",
	[751]  = "埃苏雷格,PVP,zhCN,Battle Group 3,CN",
	[846]  = "埃雷达尔,PVP,zhCN,Battle Group 7,CN",
	[859]  = "基尔加丹,PVP,zhCN,Battle Group 7,CN",
	[719]  = "基尔罗格,PVP,zhCN,Battle Group 1,CN",
	[1512] = "塔纳利斯,PVP,zhCN,Battle Group 14,CN",
	[1687] = "塞拉摩,PVP,zhCN,Battle Group 16,CN",
	[1514] = "塞拉赞恩,PVP,zhCN,Battle Group 14,CN",
	[1820] = "塞泰克,PVP,zhCN,Battle Group 18,CN",
	[782]  = "塞纳里奥,PVE,zhCN,Battle Group 4,CN",
	[1949] = "壁炉谷,PVP,zhCN,Battle Group 19,CN",
	[781]  = "夏维安,PVP,zhCN,Battle Group 4,CN",
	[1507] = "外域,PVP,zhCN,Battle Group 14,CN",
	[930]  = "大地之怒,PVP,zhCN,Battle Group 10,CN",
	[1503] = "大漩涡,PVP,zhCN,Battle Group 14,CN",
	[1508] = "天空之墙,PVP,zhCN,Battle Group 14,CN",
	[1824] = "太阳之井,PVP,zhCN,Battle Group 18,CN",
	[1682] = "夺灵者,PVP,zhCN,Battle Group 16,CN",
	[1228] = "奈法利安,PVP,zhCN,Battle Group 12,CN",
	[734]  = "奈萨里奥,PVP,zhCN,Battle Group 2,CN",
	[1965] = "奎尔丹纳斯,PVP,zhCN,Battle Group 20,CN",
	[1229] = "奎尔萨拉斯,PVP,zhCN,Battle Group 12,CN",
	[1505] = "奥妮克希亚,PVP,zhCN,Battle Group 14,CN",
	[2120] = "奥尔加隆,PVP,zhCN,Battle Group 21,CN",
	[757]  = "奥拉基尔,PVP,zhCN,Battle Group 3,CN",
	[1506] = "奥斯里安,PVP,zhCN,Battle Group 14,CN",
	[850]  = "奥特兰克,PVP,zhCN,Battle Group 7,CN",
	[706]  = "奥蕾莉亚,PVE,zhCN,Battle Group 1,CN",
	[705]  = "奥达曼,PVP,zhCN,Battle Group 1,CN",
	[918]  = "守护之剑,PVP,zhCN,Battle Group 9,CN",
	[1198] = "安其拉,PVP,zhCN,Battle Group 11,CN",
	[2122] = "安加萨,PVP,zhCN,Battle Group 21,CN",
	[952]  = "安多哈尔,PVP,zhCN,Battle Group 10,CN",
	[704]  = "安威玛尔,PVP,zhCN,Battle Group 1,CN",
	[1517] = "安戈洛,PVP,zhCN,Battle Group 14,CN",
	[2121] = "安格博达,PVP,zhCN,Battle Group 21,CN",
	[1199] = "安纳塞隆,PVP,zhCN,Battle Group 11,CN",
	[1933] = "安苏,PVP,zhCN,Battle Group 19,CN",
	[938]  = "密林游侠,PVP,zhCN,Battle Group 10,CN",
	[858]  = "寒冰皇冠,PVP,zhCN,Battle Group 7,CN",
	[710]  = "尘风峡谷,PVP,zhCN,Battle Group 1,CN",
	[788]  = "屠魔山谷,PVP,zhCN,Battle Group 4,CN",
	[740]  = "山丘之王,PVP,zhCN,Battle Group 2,CN",
	[861]  = "巨龙之吼,PVP,zhCN,Battle Group 7,CN",
	[1670] = "巫妖之王,PVP,zhCN,Battle Group 15,CN",
	[851]  = "巴尔古恩,PVP,zhCN,Battle Group 7,CN",
	[1486] = "巴瑟拉斯,PVP,zhCN,Battle Group 13,CN",
	[1203] = "巴纳扎尔,PVP,zhCN,Battle Group 11,CN",
	[921]  = "布兰卡德,PVP,zhCN,Battle Group 9,CN",
	[800]  = "布莱克摩,PVP,zhCN,Battle Group 5,CN",
	[1501] = "布莱恩,PVE,zhCN,Battle Group 13,CN",
	[1937] = "布鲁塔卢斯,PVP,zhCN,Battle Group 19,CN",
	[885]  = "希尔瓦娜斯,PVP,zhCN,Battle Group 8,CN",
	[1819] = "希雷诺斯,PVP,zhCN,Battle Group 18,CN",
	[1676] = "幽暗沼泽,PVP,zhCN,Battle Group 15,CN",
	[1226] = "库尔提拉斯,PVP,zhCN,Battle Group 12,CN",
	[723]  = "库德兰,PVP,zhCN,Battle Group 1,CN",
	[766]  = "弗塞雷迦,PVP,zhCN,Battle Group 3,CN",
	[2133] = "影之哀伤,PVP,zhCN,Battle Group 21,CN",
	[891]  = "影牙要塞,PVP,zhCN,Battle Group 8,CN",
	[1214] = "德拉诺,PVP,zhCN,Battle Group 11,CN",
	[1488] = "恐怖图腾,PVP,zhCN,Battle Group 13,CN",
	[924]  = "恶魔之翼,PVP,zhCN,Battle Group 9,CN",
	[1492] = "恶魔之魂,PVP,zhCN,Battle Group 13,CN",
	[767]  = "戈古纳斯,PVP,zhCN,Battle Group 3,CN",
	[1947] = "戈提克,PVP,zhCN,Battle Group 19,CN",
	[793]  = "战歌,PVP,zhCN,Battle Group 4,CN",
	[1695] = "扎拉赞恩,PVP,zhCN,Battle Group 16,CN",
	[1515] = "托塞德林,PVP,zhCN,Battle Group 14,CN",
	[1823] = "托尔巴拉德,PVP,zhCN,Battle Group 18,CN",
	[772]  = "拉文凯斯,PVP,zhCN,Battle Group 4,CN",
	[1231] = "拉文霍德,PVP,zhCN,Battle Group 12,CN",
	[865]  = "拉格纳洛斯,PVP,zhCN,Battle Group 7,CN",
	[1230] = "拉贾克斯,PVP,zhCN,Battle Group 12,CN",
	[954]  = "提尔之手,PVP,zhCN,Battle Group 10,CN",
	[882]  = "提瑞斯法,PVP,zhCN,Battle Group 8,CN",
	[1815] = "摩摩尔,PVP,zhCN,Battle Group 18,CN",
	[920]  = "斩魔者,PVP,zhCN,Battle Group 9,CN",
	[878]  = "斯坦索姆,PVP,zhCN,Battle Group 8,CN",
	[1240] = "无尽之海,PVP,zhCN,Battle Group 12,CN",
	[1803] = "无底海渊,PVP,zhCN,Battle Group 17,CN",
	[946]  = "日落沼泽,PVP,zhCN,Battle Group 10,CN",
	[737]  = "普瑞斯托,PVE,zhCN,Battle Group 2,CN",
	[827]  = "普罗德摩,PVP,zhCN,Battle Group 6,CN",
	[756]  = "暗影之月,PVP,zhCN,Battle Group 3,CN",
	[849]  = "暗影议会,PVP,zhCN,Battle Group 7,CN",
	[1821] = "暗影迷宫,PVP,zhCN,Battle Group 18,CN",
	[943]  = "暮色森林,PVP,zhCN,Battle Group 10,CN",
	[708]  = "暴风祭坛,PVP,zhCN,Battle Group 1,CN",
	[791]  = "月光林地,PVE,zhCN,Battle Group 4,CN",
	[792]  = "月神殿,PVE,zhCN,Battle Group 4,CN",
	[1827] = "末日祷告祭坛,PVP,zhCN,Battle Group 18,CN",
	[1939] = "末日行者,PVP,zhCN,Battle Group 19,CN",
	[959]  = "朵丹尼尔,PVP,zhCN,Battle Group 10,CN",
	[802]  = "杜隆坦,PVP,zhCN,Battle Group 5,CN",
	[1222] = "格瑞姆巴托,PVP,zhCN,Battle Group 12,CN",
	[1500] = "格雷迈恩,PVP,zhCN,Battle Group 13,CN",
	[1807] = "格鲁尔,PVP,zhCN,Battle Group 17,CN",
	[1212] = "桑德兰,PVP,zhCN,Battle Group 11,CN",
	[775]  = "梅尔加尼,PVP,zhCN,Battle Group 4,CN",
	[776]  = "梦境之树,PVE,zhCN,Battle Group 4,CN",
	[1232] = "森金,PVP,zhCN,Battle Group 12,CN",
	[741]  = "死亡之翼,PVP,zhCN,Battle Group 2,CN",
	[1802] = "死亡熔炉,PVP,zhCN,Battle Group 17,CN",
	[769]  = "毁灭之锤,PVP,zhCN,Battle Group 3,CN",
	[928]  = "水晶之刺,PVP,zhCN,Battle Group 9,CN",
	[956]  = "永夜港,PVE,zhCN,Battle Group 10,CN",
	[1236] = "永恒之井,PVP,zhCN,Battle Group 12,CN",
	[1970] = "沙怒,PVP,zhCN,Battle Group 20,CN",
	[960]  = "法拉希姆,PVP,zhCN,Battle Group 10,CN",
	[787]  = "泰兰德,PVE,zhCN,Battle Group 4,CN",
	[1234] = "泰拉尔,PVP,zhCN,Battle Group 12,CN",
	[1227] = "洛丹伦,PVP,zhCN,Battle Group 12,CN",
	[2129] = "洛肯,PVP,zhCN,Battle Group 21,CN",
	[730]  = "洛萨,PVP,zhCN,Battle Group 2,CN",
	[1225] = "海克泰尔,PVP,zhCN,Battle Group 12,CN",
	[768]  = "海加尔,PVP,zhCN,Battle Group 4,CN",
	[1237] = "海达希亚,PVE,zhCN,Battle Group 12,CN",
	[936]  = "浸毒之骨,PVP,zhCN,Battle Group 9,CN",
	[1793] = "深渊之喉,PVP,zhCN,Battle Group 17,CN",
	[1659] = "深渊之巢,PVP,zhCN,Battle Group 15,CN",
	[926]  = "激流之傲,PVP,zhCN,Battle Group 9,CN",
	[860]  = "激流堡,PVP,zhCN,Battle Group 7,CN",
	[1664] = "火喉,PVP,zhCN,Battle Group 15,CN",
	[1662] = "火烟之谷,PVP,zhCN,Battle Group 15,CN",
	[770]  = "火焰之树,PVP,zhCN,Battle Group 3,CN",
	[810]  = "火羽山,PVP,zhCN,Battle Group 5,CN",
	[1484] = "灰谷,PVP,zhCN,Battle Group 13,CN",
	[727]  = "烈焰峰,PVP,zhCN,Battle Group 2,CN",
	[1681] = "烈焰荆棘,PVP,zhCN,Battle Group 16,CN",
	[838]  = "熊猫酒仙,PVP,zhCN,Battle Group 6,CN",
	[1221] = "熔火之心,PVP,zhCN,Battle Group 11,CN",
	[1941] = "熵魔,PVP,zhCN,Battle Group 19,CN",
	[829]  = "燃烧之刃,PVP,zhCN,Battle Group 6,CN",
	[1206] = "燃烧军团,PVP,zhCN,Battle Group 11,CN",
	[738]  = "燃烧平原,PVP,zhCN,Battle Group 2,CN",
	[755]  = "爱斯特纳,PVP,zhCN,Battle Group 3,CN",
	[915]  = "狂热之刃,PVP,zhCN,Battle Group 9,CN",
	[815]  = "狂风峭壁,PVP,zhCN,Battle Group 5,CN",
	[731]  = "玛多兰,PVE,zhCN,Battle Group 2,CN",
	[773]  = "玛法里奥,PVP,zhCN,Battle Group 4,CN",
	[2130] = "玛洛加尔,PVP,zhCN,Battle Group 21,CN",
	[732]  = "玛瑟里顿,PVP,zhCN,Battle Group 2,CN",
	[869]  = "玛诺洛斯,PVP,zhCN,Battle Group 8,CN",
	[822]  = "玛里苟斯,PVP,zhCN,Battle Group 6,CN",
	[874]  = "瑞文戴尔,PVP,zhCN,Battle Group 8,CN",
	[1513] = "瑟莱德丝,PVP,zhCN,Battle Group 14,CN",
	[1829] = "瓦丝琪,PVP,zhCN,Battle Group 18,CN",
	[1235] = "瓦拉斯塔兹,PVP,zhCN,Battle Group 12,CN",
	[1202] = "瓦里玛萨斯,PVE,zhCN,Battle Group 11,CN",
	[835]  = "甜水绿洲,PVP,zhCN,Battle Group 6,CN",
	[1934] = "生态船,PVP,zhCN,Battle Group 19,CN",
	[707]  = "白银之手,PVE,zhCN,Battle Group 1,CN",
	[1936] = "白骨荒野,PVP,zhCN,Battle Group 19,CN",
	[1948] = "盖斯,PVP,zhCN,Battle Group 19,CN",
	[786]  = "石爪峰,PVP,zhCN,Battle Group 4,CN",
	[1685] = "石锤,PVP,zhCN,Battle Group 16,CN",
	[1208] = "破碎岭,PVP,zhCN,Battle Group 11,CN",
	[1519] = "祖尔金,PVP,zhCN,Battle Group 14,CN",
	[1830] = "祖阿曼,PVP,zhCN,Battle Group 18,CN",
	[941]  = "神圣之歌,PVE,zhCN,Battle Group 10,CN",
	[1813] = "穆戈尔,PVP,zhCN,Battle Group 18,CN",
	[803]  = "符文图腾,PVP,zhCN,Battle Group 5,CN",
	[1672] = "米奈希尔,PVP,zhCN,Battle Group 15,CN",
	[742]  = "索拉丁,PVP,zhCN,Battle Group 2,CN",
	[807]  = "红云台地,PVP,zhCN,Battle Group 5,CN",
	[717]  = "红龙军团,PVP,zhCN,Battle Group 1,CN",
	[806]  = "红龙女王,PVP,zhCN,Battle Group 5,CN",
	[1239] = "纳克萨玛斯,PVP,zhCN,Battle Group 12,CN",
	[825]  = "纳沙塔尔,PVP,zhCN,Battle Group 6,CN",
	[2123] = "织亡者,PVP,zhCN,Battle Group 21,CN",
	[729]  = "罗宁,PVP,zhCN,Battle Group 2,CN",
	[841]  = "羽月,PVE,zhCN,Battle Group 6,CN",
	[1832] = "翡翠梦境,PVE,zhCN,Battle Group 18,CN",
	[872]  = "耐奥祖,PVP,zhCN,Battle Group 8,CN",
	[778]  = "耐普图隆,PVP,zhCN,Battle Group 4,CN",
	[856]  = "耳语海岸,PVE,zhCN,Battle Group 7,CN",
	[1942] = "能源舰,PVP,zhCN,Battle Group 19,CN",
	[843]  = "自由之风,PVP,zhCN,Battle Group 6,CN",
	[754]  = "艾森娜,PVE,zhCN,Battle Group 3,CN",
	[847]  = "艾欧纳尔,PVP,zhCN,Battle Group 7,CN",
	[1485] = "艾维娜,PVE,zhCN,Battle Group 13,CN",
	[703]  = "艾苏恩,PVP,zhCN,Battle Group 1,CN",
	[1495] = "艾莫莉丝,PVP,zhCN,Battle Group 13,CN",
	[753]  = "艾萨拉,PVP,zhCN,Battle Group 3,CN",
	[1812] = "艾露恩,PVE,zhCN,Battle Group 17,CN",
	[949]  = "芬里斯,PVP,zhCN,Battle Group 10,CN",
	[929]  = "苏塔恩,PVP,zhCN,Battle Group 9,CN",
	[1828] = "范克里夫,PVP,zhCN,Battle Group 18,CN",
	[1233] = "范达尔鹿盔,PVP,zhCN,Battle Group 12,CN",
	[1510] = "荆棘谷,PVP,zhCN,Battle Group 14,CN",
	[2131] = "莫德雷萨,PVP,zhCN,Battle Group 21,CN",
	[1241] = "莱索恩,PVP,zhCN,Battle Group 12,CN",
	[1497] = "菲拉斯,PVP,zhCN,Battle Group 13,CN",
	[1943] = "菲米丝,PVP,zhCN,Battle Group 19,CN",
	[2132] = "萨塔里奥,PVP,zhCN,Battle Group 21,CN",
	[830]  = "萨尔,PVP,zhCN,Battle Group 6,CN",
	[739]  = "萨格拉斯,PVP,zhCN,Battle Group 2,CN",
	[1969] = "萨洛拉丝,PVP,zhCN,Battle Group 20,CN",
	[1238] = "萨菲隆,PVP,zhCN,Battle Group 12,CN",
	[725]  = "蓝龙军团,PVP,zhCN,Battle Group 2,CN",
	[709]  = "藏宝海湾,PVP,zhCN,Battle Group 1,CN",
	[842]  = "蜘蛛王国,PVP,zhCN,Battle Group 6,CN",
	[1946] = "血吼,PVP,zhCN,Battle Group 19,CN",
	[839]  = "血牙魔王,PVP,zhCN,Battle Group 6,CN",
	[799]  = "血环,PVP,zhCN,Battle Group 5,CN",
	[1205] = "血羽,PVP,zhCN,Battle Group 11,CN",
	[886]  = "血色十字军,PVP,zhCN,Battle Group 8,CN",
	[1487] = "血顶,PVP,zhCN,Battle Group 13,CN",
	[1817] = "试炼之环,PVP,zhCN,Battle Group 18,CN",
	[826]  = "诺兹多姆,PVE,zhCN,Battle Group 6,CN",
	[1504] = "诺森德,PVP,zhCN,Battle Group 14,CN",
	[736]  = "诺莫瑞根,PVP,zhCN,Battle Group 2,CN",
	[1950] = "贫瘠之地,PVE,zhCN,Battle Group 19,CN",
	[933]  = "踏梦者,PVP,zhCN,Battle Group 10,CN",
	[780]  = "轻风之语,PVE,zhCN,Battle Group 4,CN",
	[2125] = "达克萨隆,PVP,zhCN,Battle Group 21,CN",
	[1940] = "达基萨斯,PVP,zhCN,Battle Group 19,CN",
	[1938] = "达尔坎,PVP,zhCN,Battle Group 19,CN",
	[1490] = "达文格尔,PVP,zhCN,Battle Group 13,CN",
	[760]  = "达斯雷玛,PVP,zhCN,Battle Group 3,CN",
	[711]  = "达纳斯,PVP,zhCN,Battle Group 1,CN",
	[855]  = "达隆米尔,PVP,zhCN,Battle Group 7,CN",
	[917]  = "迅捷微风,PVP,zhCN,Battle Group 9,CN",
	[2135] = "远古海滩,PVP,zhCN,Battle Group 21,CN",
	[2118] = "迦拉克隆,PVE,zhCN,Battle Group 21,CN",
	[1667] = "迦玛兰,PVP,zhCN,Battle Group 15,CN",
	[812]  = "迦罗娜,PVP,zhCN,Battle Group 5,CN",
	[1945] = "迦顿,PVP,zhCN,Battle Group 19,CN",
	[712]  = "迪托马斯,PVP,zhCN,Battle Group 1,CN",
	[1493] = "迪瑟洛克,PVP,zhCN,Battle Group 13,CN",
	[1511] = "逐日者,PVE,zhCN,Battle Group 14,CN",
	[883]  = "通灵学院,PVP,zhCN,Battle Group 8,CN",
	[887]  = "遗忘海岸,PVE,zhCN,Battle Group 8,CN",
	[1668] = "金度,PVP,zhCN,Battle Group 15,CN",
	[962]  = "金色平原,rppvp,zhCN,Battle Group 10,CN",
	[744]  = "铜龙军团,PVP,zhCN,Battle Group 2,CN",
	[889]  = "银月,PVE,zhCN,Battle Group 8,CN",
	[888]  = "银松森林,PVE,zhCN,Battle Group 8,CN",
	[784]  = "闪电之刃,PVP,zhCN,Battle Group 4,CN",
	[749]  = "阿克蒙德,PVP,zhCN,Battle Group 3,CN",
	[1200] = "阿努巴拉克,PVP,zhCN,Battle Group 11,CN",
	[1482] = "阿卡玛,PVP,zhCN,Battle Group 13,CN",
	[1795] = "阿古斯,PVP,zhCN,Battle Group 17,CN",
	[844]  = "阿尔萨斯,PVP,zhCN,Battle Group 7,CN",
	[1483] = "阿扎达斯,PVP,zhCN,Battle Group 13,CN",
	[1201] = "阿拉希,PVP,zhCN,Battle Group 11,CN",
	[845]  = "阿拉索,PVP,zhCN,Battle Group 7,CN",
	[1935] = "阿斯塔洛,PVP,zhCN,Battle Group 19,CN",
	[1932] = "阿曼尼,PVP,zhCN,Battle Group 19,CN",
	[700]  = "阿格拉玛,PVP,zhCN,Battle Group 1,CN",
	[1931] = "阿比迪斯,PVP,zhCN,Battle Group 19,CN",
	[1210] = "阿纳克洛斯,PVP,zhCN,Battle Group 11,CN",
	[748]  = "阿迦玛甘,PVP,zhCN,Battle Group 3,CN",
	[931]  = "雏龙之翼,PVP,zhCN,Battle Group 9,CN",
	[817]  = "雷克萨,PVP,zhCN,Battle Group 6,CN",
	[816]  = "雷斧堡垒,PVP,zhCN,Battle Group 5,CN",
	[1211] = "雷霆之怒,PVP,zhCN,Battle Group 11,CN",
	[726]  = "雷霆之王,PVP,zhCN,Battle Group 2,CN",
	[818]  = "雷霆号角,PVP,zhCN,Battle Group 5,CN",
	[1955] = "霍格,PVP,zhCN,Battle Group 20,CN",
	[877]  = "霜之哀伤,PVE,zhCN,Battle Group 8,CN",
	[876]  = "霜狼,PVP,zhCN,Battle Group 8,CN",
	[764]  = "风暴之怒,PVP,zhCN,Battle Group 3,CN",
	[953]  = "风暴之眼,PVP,zhCN,Battle Group 10,CN",
	[1509] = "风暴之鳞,PVP,zhCN,Battle Group 14,CN",
	[2134] = "风暴峭壁,PVP,zhCN,Battle Group 21,CN",
	[765]  = "风行者,PVP,zhCN,Battle Group 3,CN",
	[804]  = "鬼雾峰,PVP,zhCN,Battle Group 5,CN",
	[1798] = "鲜血熔炉,PVP,zhCN,Battle Group 17,CN",
	[890]  = "鹰巢山,PVP,zhCN,Battle Group 8,CN",
	[1810] = "麦姆,PVP,zhCN,Battle Group 17,CN",
	[774]  = "麦维影歌,PVP,zhCN,Battle Group 4,CN",
	[870]  = "麦迪文,PVE,zhCN,Battle Group 8,CN",
	[808]  = "黄金之路,PVE,zhCN,Battle Group 5,CN",
	[1204] = "黑手军团,PVP,zhCN,Battle Group 11,CN",
	[805]  = "黑暗之矛,PVP,zhCN,Battle Group 5,CN",
	[1801] = "黑暗之门,PVP,zhCN,Battle Group 17,CN",
	[1516] = "黑暗虚空,PVP,zhCN,Battle Group 14,CN",
	[932]  = "黑暗魅影,PVP,zhCN,Battle Group 9,CN",
	[716]  = "黑石尖塔,PVP,zhCN,Battle Group 1,CN",
	[1213] = "黑翼之巢,PVP,zhCN,Battle Group 11,CN",
	[1491] = "黑铁,PVP,zhCN,Battle Group 13,CN",
	[2126] = "黑锋哨站,PVP,zhCN,Battle Group 21,CN",
	[715]  = "黑龙军团,PVP,zhCN,Battle Group 1,CN",
	[1215] = "龙骨平原,PVP,zhCN,Battle Group 11,CN",
--}}
--{{ Taiwan
	[982]  = "世界之樹,PVE,zhTW,嗜血,TW",
	[1038] = "亞雷戈斯,PVE,zhTW,嗜血,TW",
	[977]  = "冰霜之刺,PVP,zhTW,嗜血,TW",
	[1001] = "冰風崗哨,PVP,zhTW,嗜血,TW",
	[979]  = "地獄吼,PVP,zhTW,嗜血,TW",
	[1043] = "夜空之歌,PVP,zhTW,嗜血,TW",
	[980]  = "天空之牆,PVE,zhTW,嗜血,TW",
	[1057] = "寒冰皇冠,PVP,zhTW,嗜血,TW",
	[964]  = "尖石,PVP,zhTW,嗜血,TW",
	[1023] = "屠魔山谷,PVP,zhTW,嗜血,TW",
	[966]  = "巨龍之喉,PVP,zhTW,嗜血,TW",
	[1049] = "憤怒使者,PVP,zhTW,嗜血,TW",
	[978]  = "日落沼澤,PVP,zhTW,嗜血,TW",
	[963]  = "暗影之月,PVE,zhTW,嗜血,TW",
	[985]  = "水晶之刺,PVP,zhTW,嗜血,TW",
	[999]  = "狂熱之刃,PVP,zhTW,嗜血,TW",
	[1056] = "眾星之子,PVE,zhTW,嗜血,TW",
	[1006] = "米奈希爾,PVP,zhTW,嗜血,TW",
	[1046] = "聖光之願,PVE,zhTW,嗜血,TW",
	[1037] = "血之谷,PVP,zhTW,嗜血,TW",
	[1033] = "語風,PVE,zhTW,嗜血,TW",
	[1048] = "銀翼要塞,PVP,zhTW,嗜血,TW",
	[1054] = "阿薩斯,PVP,zhTW,嗜血,TW",
	[3663] = "米奈希爾,PVP,zhTW,嗜血,TW",
	[965]  = "雷鱗,PVP,zhTW,嗜血,TW",
--}}
}

------------------------------------------------------------------------

connectionData = {
--{{ North America
	-- http://us.battle.net/wow/en/blog/11393305
	"1136,83,109,129,1142", -- Aegwynn, Bonechewer, Daggerspine, Gurubashi, Hakkar
	"1129,56,1291,1559", -- Agamaggan, Archimonde, Jaedenar, The Underbog
	"106,1576", -- Aggramar, Fizzcrank
	"1137,84,1145", -- Akama, Dragonmaw, Mug'thol
	"1070,1563", -- Alexstrasza, Terokkar
	"52,65", -- Alleria, Khadgar
	"1282,1264,78,1268", -- Altar of Storms, Anetheron, Magtheridon, Ysondre
	"1293,1075,80,1344,71", -- Alterac Mountains, Balnazzar, Gorgonnash, The Forgotten Coast, Warsong
	"1276,1267,156,1259", -- Andorhal, Scilla, Ursin, Zuluhed
	"1363,116", -- Antonidas, Uldum
	"1346,1138,107,1141,130", -- Anub'arak, Chromaggus, Crushridge, Garithos, Nathrezim, Smolderthorn
	"1288,1294", -- Anvilmar, Undermine
	"1165,1377", -- Arathor, Drenden
	"75,1570", -- Argent Dawn, The Scryers
	"1297,99", -- Arygos, Llane
	"1555,1067,101", -- Auchindoun, Cho'gall, Laughing Skull
	"77,1128,79,103", -- Azgalor, Azshara, Destromath, Thunderlord
	"121,1143", -- Azjol-Nerub, Khaz Modan
	"1549,160", -- Azuremyst, Staghelm
	"1190,13", -- Baelgun, Doomhammer
	"1280,1068,74", -- Black Dragonflight, Gul'dan, Skullcrusher
	"54,1581", -- Blackhand, Galakrond
	"1347,125", -- Blackwater Raiders, Shadow Council
	"1296,81,154,1266,1295", -- Blackwing Lair, Dethecus, Detheroc, Haomarush, Lethon
	"1353,1147", -- Bladefist, Kul Tiras
	"1564,105", -- Blade's Edge, Thunderhorn
	"1558,70,1131", -- Blood Furnace, Mannoroth, Nazjatar
	"64,1258", -- Bloodhoof, Duskwood
	"119,112,111,1357,108", -- Bloodscalp, Boulderfist, Dunemaul, Maiev, Stonemaul
	"1371,85", -- Borean Tundra, Shadowsong
	"117,1364", -- Bronzebeard, Shandris
	"91,95,1285", -- Burning Blade, Lightning's Blade, Onyxia
	"1430,1432", -- Caelestrasz, Nagrand
	"1361,122", -- Cairne, Perenolde
	"88,1356", -- Cenarion Circle, Sisters of Elune
	"1556,1278,157,1286,72", -- Coilfang, Dalvengyr, Dark Iron, Demon Soul, Shattered Hand
	"1351,87", -- Darrowmere, Windrunner
	"1434,1134", -- Dath'Remar, Khaz'goroth
	"1582,1173", -- Dawnbringer, Madoran
	"15,1277,155,1557", -- Deathwing, Executus, Kalecgos, Shattered Halls
	"1271,55", -- Dentarg, Whisperwind
	"115,1342", -- Draenor, Echo Isles
	"114,1345", -- Dragonblight, Fenris
	"1139,113", -- Draka, Suramar
	"1362,127,1148,1358,124,110", -- Drak'Tharon, Firetree, Malorne, Rivendare, Spirestone, Stormscale
	"1140,131", -- Drak'thul, Skywall
	"1429,1433", -- Dreadmaul, Thaurissan
	"63,1270", -- Durotan, Ysera
	"58,1354", -- Eitrigg, Shu'halo
	"123,1349", -- Eldre'Thalas, Korialstrasz
	"67,97", -- Elune, Gilneas
	"96,1567", -- Eonar, Velen
	"93,92,82,159", -- Eredar, Gorefiend, Spinebreaker, Wildhammer
	"1565,62", -- Exodar, Medivh
	"1370,12,1154", -- Farstriders, Silver Hand, Thorium Brotherhood
	"118,126", -- Feathermoon, Scarlet Crusade
	"128,8,1360", -- Frostmane, Ner'zhul, Tortheldrin
	"7,1348", -- Frostwolf, Vashj
	"1578,1069", -- Ghostlands, Kael'thas
	"1287,153", -- Gnomeregan, Moonrunner
	"158,1292", -- Greymane, Tanaris
	"1579,68", -- Grizzly Hills, Lothar
	"1149,1144", -- Gundrak, Jubei'Thos
	"53,1572", -- Hellscream, Zangarmarsh
	"1368,90", -- Hydraxis, Terenas
	"14,104", -- Icecrown, Malygos
	"98,1262", -- Kargath, Norgannon
	"4,1355", -- Kilrogg, Winterhoof
	"1071,1290,1260", -- Kirin Tor, Sentinels, Steamwheedle Cartel
	"1130,163,1289", -- Lightninghoof, Maelstrom, The Venture Co
	"1132,1175", -- Malfurion, Trollbane
	"1350,1151", -- Misha, Rexxar
	"1374,86", -- Mok'Nathal, Silvermoon
	"1182,1359", -- Muradin, Nordrassil
	"1367,1375,1184", -- Nazgrel, Nesingwary, Vek'nilash
	"1372,1185", -- Quel'dorei, Sen'jin
	"1072,1283", -- Ravencrest, Uldaman
	"1352,164", -- Ravenholdt, Twisting Nether
	"151,3", -- Runetotem, Uther
--}}
--{{ Europe
	-- Current:  http://eu.battle.net/wow/en/forum/topic/8715582685
	-- Upcoming: http://eu.battle.net/wow/en/forum/topic/9582578502

	-- English
	-- PVE
	"1082,1391,1394", -- Kul Tiras / Alonsus / Anachronos
	"1081,1312", -- Bronzebeard / Aerie Peak
	"1416,1298,1310", -- Blade's Edge / Vek'nilash / Eonar
	"1313,552", -- Wildhammer / Thunderhorn
	"1311,547,1589", -- Kilrogg / Runetotem / Nagrand
	"500,619", -- Aggramar / Hellscream
	"1587,501", -- Hellfire / Arathor
	"633,630,1087,1392,556", -- Kor’gall / Bloodfeather / Executus / Burning Steppes / Shattered Hand
	"503,623", -- Azjol-Nerub / Quel'Thalas
	"1588,507", -- Ghostlands / Dragonblight
	"1389,1415,1314", -- Darkspear / Terokkar / Saurfang
	"502,548", -- Aszune / Shadowsong
	"1080,504", -- Khadgar / Bloodhoof
	"1393,618", -- Bronze Dragonflight / Nordrassil
	"1388,1089", -- Lightbringer / Mazrigos
	"1417,550", -- Azuremyst / Stormrage
	"505,553", -- Doomhammer / Turalyon
	"508,551", -- Emerald Dream / Terenas
	-- PVP
	"1598,607,1093,1088,1090,1083,1299,526,621,511", -- Shattered Halls / Balnazzar / Ahn'Qiraj / Trollbane / Talnivarr / Chromaggus / Boulderfist / Daggerspine / Laughing Skull / Sunstrider
	"1091,518,646,525,522,513", -- Emeriss / Agamaggan / Hakkar / Crushridge / Bloodscalp / Twilight's Hammer
	"1303,1413", -- Grim Batol / Aggra
	"1596,637,527,627", -- Karazhan / Lightning’s Blade / Deathwing / The Maelstrom
	"1597,529,1304", -- Auchindoun / Dunemaul / Jaedenar
	"528,558,638,629,559", -- Dragonmaw / Spinebreaker / Haomarush / Vashj / Stormreaver
	"515,521,632", -- Zenedar / Bladefist / Frostwhisper
	"639,557,519", -- Xavius / Skullcrusher / Al'Akir
	"631,606,624", -- Darksorrow / Genjuros / Neptulon
	"1092,523", -- Drak’thul / Burning Blade
	"1084,1306", -- Dentarg / Tarren Mill
	-- RP
	"1085,1595,1117", -- Moonglade / The Sha'tar / Steamwheedle Cartel
	"1317,561", -- Darkmoon Faire / Earthen Ring
	-- RP PVP
	"1096,1308,636,1606,635", -- Scarshield Legion / Ravenholdt / The Venture Co / Sporeggar / Defias Brotherhood

	-- French
	-- PVE
	"1620,510", -- Chants éternels / Vol'jin
	"540,645", -- Elune / Varimathras
	"1621,538", -- Marécage de Zangar / Dalaran
	"1123,1332", -- Eitrigg / Krasus
	"1331,517", -- Suramar / Medivh
	"1122,641", -- Uldaman / Drek'Thar
	-- PvE
	"1620,510", -- Chants éternels / Vol'jin
	"540,645", -- Elune / Varimathras
	"1621,538", -- Marécage de Zangar / Dalaran
	"1123,1332", -- Eitrigg / Krasus
	"1331,517", -- Suramar / Medivh
	"1122,641", -- Uldaman / Drek'Thar
	-- PvP
	"512,643,642,543", -- Arak-arahm / Throk'Feroth / Rashgarroth / Kael'Thas
	"1624,1334,1622,541", -- Naxxramas / Arathi / Temple noir / Illidan
	"546,509,544", -- Sargeras / Garona / Ner'zhul
	"1336,545,533", -- Eldre'Thalas / Cho'gall / Sinstralis
	-- RP
	"1127,1626,647", -- Confrérie du Thorium / Les Clairvoyants / Les Sentinelles
	-- RP PvP
	"1086,1337,644", -- La Croisade écarlate / Culte de la Rive noire / Conseil des Ombres

	-- German
	-- PVE
	"567,1323", -- Gilneas / Ulduar
	"1401,1608,574", -- Garrosh / Shattrath / Nozdormu
	"1607,562", -- Nethersturm / Alexstrasza
	"1400,1404,602", -- Un'GoroArea 52 / Sen'jin
	"1330,568", -- Ambossar / Kargath
	"1097,1324", -- Ysera / Malorne
	"1098,572", -- Malygos / Malfurion
	"1106,1409", -- Tichondrius / Lordaeron
	"1406,569", -- Arygos / Khaz'goroth
	"1407,575", -- Teldrassil / Perenolde
	"535,1328", -- Durotan / Tirion
	"570,565", -- Lothar / Baelgun
	"1408,600", -- Norgannon / Dun Morogh
	"1099,563", -- Rexxar / Alleria
	"593,571", -- Proudmoore / Madmortem
	-- PVP
	"1105,1321,584,573,608", -- Nazjatar / Dalvengyr / Frostmourne / Zuluhed / Anub'arak
	"578,1318,1613,588,609", -- Arthas / Vek'lor / Blutkessel / Kel'Thuzad / Wrathbringer
	"531,615,1319,605,610", -- Dethecus / Terrordar / Mug'thol / Theradras / Onyxia
	"1612,1320,590", -- Echsenkessel / Taerar / Mal'Ganis
	"1104,1611,1322,587,594,589", -- Anetheron / Festung der Stürme / Rajaxx / Gul'dan / Nathrezim / Kil'jaeden
	"612,611,591,582,586", -- Nefarian / Nera'thor / Mannoroth / Destromath / Gorgonnash
	"579,616", -- Azshara / Krag'jin
	-- RP
	"1118,576", -- Die ewige Wacht / Die Silberne Hand
	"1405,592", -- Todeswache / Zirkel des Cenarius
	"1327,617", -- Der Mithrilorden / Der Rat von Dalaran
	"516,1333", -- Die Nachtwache / Forscherliga
	-- RP PVP
	"1121,1119,614,1326,613,1619", -- Die Arguswacht / Die Todeskrallen / Das Syndikat / Der abyssische Rat / Kult der Verdammten / Das Konsortium

	-- Spanish
	-- PVE
	"1385,1386", -- Exodar / Minahonda
	"1395,1384,1387", -- Colinas Pardas / Tyrande / Los Errantes
	-- PVP
	"1379,1382,1383,1380", -- Zul'jin / Sanguino / Shen'dralar / Uldum

	-- Russian
	-- PVP
	"1924,1617", -- Booty Bay (RU) / Deathweaver (RU)
	"1609,1616", -- Deepholm (RU) / Razuvious (RU)
	"1927,1926", -- Grom (RU) / Thermaplugg (RU)
	"1603,1610", -- Lich King (RU) / Greymane (RU)
--}}
--{{ Taiwan
	-- inferred by GUID sniffing, needs confirmation by GetAutoCompleteRealms
	"3663,982,1038",
	"963,1056,1033",
	"964,1001,1057",
	"966,1043,965",
	"978,1023",
	"980,1046",
	"985,1049",
	"999,979,1054",
--}}
}

------------------------------------------------------------------------

if standalone then
	LRI_RealmData = realmData
	LRI_ConnectionData = connectionData
end
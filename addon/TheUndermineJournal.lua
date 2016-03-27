--[[

TheUndermineJournal addon, v 4.0
https://theunderminejournal.com/

You should be able to query this DB from other addons:

    o={}
    TUJMarketInfo(52719,o)
    print(o['market'])

Prints the market price of Greater Celestial Essence.
The item can be identified by anything GetItemInfo takes (itemid, itemstring, itemlink) or a battlepet itemstring/itemlink.

Prices are returned in copper, but accurate to the last *silver* (with coppers always 0).

    o['input']          -> the item parameter you just passed in, verbatim
    o['itemid']         -> the ID of the item you just passed in
    o['bonuses']        -> if present, a colon-separated list of bonus IDs that were considered as uniquely identifying the item for pricing
    o['age']            -> number of seconds since data was compiled

    o['globalMedian']   -> median market price across all US and EU realms
    o['globalMean']     -> mean market price across all US and EU realms
    o['globalStdDev']   -> standard deviation of the market price across all US and EU realms

    o['market']         -> average market price of the item on this AH over the past 14 days.
    o['stddev']         -> standard deviation of market price of the item on this AH over the past 14 days.
    o['recent']         -> average market price of the item on this AH over the past 3 days.

    o['days']           -> number of days since item was last seen on the auction house, when data was compiled. valid values 0 - 250.
     o['days'] = 251 means item was seen on this AH, but over 250 days ago
     o['days'] = 252 means the item is sold by vendors in unlimited quantities
     o['days'] = 255 means item was never seen on this AH (since 6.0 patch)


You can also query and set whether the additional tooltip lines appear from this addon.
This is useful for other addons (Auctioneer, TSM, etc) that have their own fancy tooltips to disable TUJ tooltips and use TUJ simply as a data source.

    TUJTooltip()        -> returns a boolean whether TUJ tooltips are enabled
    TUJTooltip(true)    -> enables TUJ tooltips
    TUJTooltip(false)   -> disables TUJ tooltips

You may need to re-disable tooltips upon reloadui, or any other event that resets runtime variables.
Tooltips are enabled by default and there is no savedvariable that remembers to shut them back off.
See http://tuj.me/TUJTooltip for more information/examples.

]]

local floor = math.floor
local tonumber = tonumber

local function coins(money)
    local GOLD="ffd100"
    local SILVER="e6e6e6"
    local COPPER="c8602c"

    local GSC_3 = "|cff%s%d|cff999999.|cff%s%02d|cff999999.|cff%s%02d|r"
    local GSC_2 = "|cff%s%d|cff999999.|cff%s%02d|r"

    money = floor(tonumber(money) or 0)
    local g = floor(money / 10000)
    local s = floor(money % 10000 / 100)
    local c = money % 100

    if (c > 0) then
        return GSC_3:format(GOLD, g, SILVER, s, COPPER, c)
    else
        return GSC_2:format(GOLD, g, SILVER, s)
    end
end

local function char2dec(s)
    local n, l = 0, string.len(s)
    for i=1,l,1 do
        n = n * 256 + string.byte(s,i)
    end
    return n
end

local function roundToOdd(num)
    local floored = floor(num)
    if floor((num - floored) * 1000000) == 500000 then
        if floored % 2 == 0 then
            return floored + 1
        end
        return floored
    end
    return floor(num + 0.5)
end

local addonName, addonTable = ...
local breedPoints = {
    [3] = {0.5,0.5,0.5,["name"]="B/B"},
    [4] = {0,2,0,["name"]="P/P"},
    [5] = {0,0,2,["name"]="S/S"},
    [6] = {2,0,0,["name"]="H/H"},
    [7] = {0.9,0.9,0,["name"]="H/P"},
    [8] = {0,0.9,0.9,["name"]="P/S"},
    [9] = {0.9,0,0.9,["name"]="H/S"},
    [10] = {0.4,0.9,0.4,["name"]="P/B"},
    [11] = {0.4,0.4,0.9,["name"]="S/B"},
    [12] = {0.9,0.4,0.4,["name"]="H/B"},
}

local function getBreedFromPetLink(link)
    local petString = string.match(link, "battlepet[%-?%d:]+")
    local _, speciesID, level, quality, health, power, speed = strsplit(":", petString)

    speciesID = tonumber(speciesID,10)
    level = tonumber(level,10)
    quality = tonumber(quality,10)
    health = tonumber(health,10)
    power = tonumber(power,10)
    speed = tonumber(speed,10)

    local speciesStats = addonTable.speciesStats[speciesID] or addonTable.speciesStats[0]

    for breed, points in pairs(breedPoints) do
        local breedHealth = roundToOdd((8 + speciesStats[1] / 200 + points[1]) * 5 * level * (1 + quality / 10) + 100)
        local breedPower = roundToOdd((8 + speciesStats[2] / 200 + points[2]) * level * (1 + quality / 10))
        local breedSpeed = roundToOdd((8 + speciesStats[3] / 200 + points[3]) * level * (1 + quality / 10))

        if (breedHealth == health) and (breedPower == power) and (breedSpeed == speed) then
            return breed, speciesID, level, quality, health, power, speed
        end
    end

    return nil, speciesID, level, quality, health, power, speed
end

local lastMarketInfo

--[[
    pass a table as the second argument to wipe and reuse that table
    otherwise a new table will be created and returned
]]
function TUJMarketInfo(item,...)
    if item == 0 then
        if addonTable.marketData then
            return true
        else
            return false
        end
    end

    local numargs = select('#', ...)
    local tr

    if (numargs > 0) and (type(select(1,...)) == 'table') then
        tr = select(1,...)
        wipe(tr)
    end

    if not addonTable.marketData then return tr end

    if lastMarketInfo and lastMarketInfo.input == item then
        tr = lastMarketInfo
        return tr
    end

    local _, link, dataKey;
    local iid, bonusSet, species, breed, quality

    if (strfind(item, 'battlepet:')) then
        breed, species, _, quality = getBreedFromPetLink(item)
        if not breed then return tr end
        dataKey = species..'b'..breed
    else
        _, link = GetItemInfo(item)
        if not link then return tr end

        local itemString = string.match(link, "item[%-?%d:]+")
        local itemStringParts = { strsplit(":", itemString) }
        iid = itemStringParts[2]

        local numBonuses = tonumber(itemStringParts[14],10)
        bonusSet = 0

        if numBonuses > 0 then
            local matched = 0

            for s,setBonuses in pairs(addonTable.bonusSets) do
                local matches = 0
                for x = 1,#setBonuses,1 do
                    for y = 1,numBonuses,1 do
                        if itemStringParts[14+y] == setBonuses[x] then
                            matches = matches + 1
                            break
                        end
                    end
                end
                if (matches > matched) and (matches == #setBonuses) then
                    matched = matches
                    bonusSet = s
                end
            end
        end

        dataKey = iid
        if bonusSet > 0 then
            dataKey = dataKey .. 'x' .. bonusSet
        end
    end

    local dta = addonTable.marketData[dataKey]
    if not dta then return tr end

    if not tr then tr = {} end

    tr['input'] = item
    if (iid) then
        tr['itemid'] = tonumber(iid,10)
        if bonusSet > 0 then
            tr['bonuses'] = table.concat(addonTable.bonusSets[bonusSet], ':')
        end
    end
    if (species) then
        tr['species'] = species
        tr['breed'] = breed
        tr['quality'] = quality
    end

    if addonTable.dataAge then
        tr['age'] = time() - addonTable.dataAge
    end

    local priceSize = string.byte(dta, 1);

    local offset = 2

    tr['globalMedian'] = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
    if tr['globalMedian'] == 0 then tr['globalMedian'] = nil end
    offset = offset + priceSize

    tr['globalMean'] = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
    offset = offset + priceSize

    tr['globalStdDev'] = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
    offset = offset + priceSize

    tr['days'] = string.byte(dta, offset, offset)
    offset = offset + 1

    tr['market'] = char2dec(string.sub(dta, offset, offset+priceSize-1)) * 100
    offset = offset + priceSize

    tr['stddev'] = char2dec(string.sub(dta, offset, offset+priceSize-1)) * 100
    offset = offset + priceSize

    tr['recent'] = char2dec(string.sub(dta, offset, offset+priceSize-1)) * 100
    --offset = offset + priceSize

    lastMarketInfo = tr

    return tr
end

--[[
    enable/disable/query whether the TUJ tooltip additions are enabled
]]
local tooltipsEnabled = true
function TUJTooltip(...)
    if select('#', ...) >= 1 then
        tooltipsEnabled = not not select(1,...) --coerce into boolean
    end
    return tooltipsEnabled
end

local qualityRGB = {
    [0] = {0.616,0.616,0.616},
    [1] = {1,1,1},
    [2] = {0.118,1,0},
    [3] = {0,0.439,0.867},
    [4] = {0.639,0.208,0.933},
    [5] = {1,0.502,0},
    [6] = {0.898,0.8,0.502},
    [7] = {0.898,0.8,0.502},
    [8] = {0,0.8,1},
    [9] = {0.443,0.835,1},
}

local LibExtraTip = LibStub("LibExtraTip-1")

local function buildExtraTip(tooltip, pricingData)
    local r,g,b = .9,.8,.5

    if (pricingData['breed'] and breedPoints[pricingData['breed']] and qualityRGB[pricingData['quality']]) then
        LibExtraTip:AddLine(tooltip,"Breed " .. breedPoints[pricingData['breed']]["name"],qualityRGB[pricingData['quality']][1],qualityRGB[pricingData['quality']][2],qualityRGB[pricingData['quality']][3])
    end

    if (pricingData['age'] > 3*24*60*60) then
        LibExtraTip:AddLine(tooltip,"As of "..SecondsToTime(pricingData['age'],pricingData['age']>60).." ago:",r,g,b)
    end

    if pricingData['recent'] then
        LibExtraTip:AddDoubleLine(tooltip,"3-Day Price",coins(pricingData['recent']),r,g,b)
    end
    if pricingData['market'] then
        LibExtraTip:AddDoubleLine(tooltip,"14-Day Price",coins(pricingData['market']),r,g,b)
    end
    if pricingData['market'] then
        LibExtraTip:AddDoubleLine(tooltip,"14-Day Std Dev",coins(pricingData['stddev']),r,g,b)
    end
    if pricingData['globalMedian'] then
        LibExtraTip:AddDoubleLine(tooltip,"Global Median",coins(pricingData['globalMedian']),r,g,b)
    end
    if pricingData['globalMean'] then
        LibExtraTip:AddDoubleLine(tooltip,"Global Mean",coins(pricingData['globalMean']),r,g,b)
    end
    if pricingData['globalStdDev'] then
        LibExtraTip:AddDoubleLine(tooltip,"Global Std Dev",coins(pricingData['globalStdDev']),r,g,b)
    end

    if pricingData['days'] == 255 then
        LibExtraTip:AddLine(tooltip,"Never seen since WoD",r,g,b)
    elseif pricingData['days'] == 252 then
        LibExtraTip:AddLine(tooltip,"Sold by Vendors",r,g,b)
    elseif pricingData['days'] > 250 then
        LibExtraTip:AddLine(tooltip,"Last seen over 250 days ago",r,g,b)
    elseif pricingData['days'] > 1 then
        LibExtraTip:AddLine(tooltip,"Last seen "..SecondsToTime(pricingData['days']*24*60*60).." ago",r,g,b)
    end
end

local dataResults = {}

local function onTooltipSetItem(tooltip, itemLink, quantity)
    if not addonTable.marketData then return end
    if not tooltipsEnabled then return end
    if not itemLink then return end

    TUJMarketInfo(itemLink, dataResults)

    if not (dataResults['input'] and (dataResults['input'] == itemLink)) then
        return
    end

    buildExtraTip(tooltip, dataResults)
end

local eventframe = CreateFrame("FRAME",addonName.."Events");

local function onEvent(self,event,arg)
    if event == "PLAYER_ENTERING_WORLD" then
        eventframe:UnregisterEvent("PLAYER_ENTERING_WORLD")

        local realmId = LibStub("LibRealmInfo"):GetRealmInfo(GetRealmName())
        if not realmId then
            local guid = UnitGUID("player")
            if guid then
                realmId = tonumber(strmatch(guid, "^Player%-(%d+)"))
            end
        end
        if not realmId then
            realmId = "nil"
        else
            for i=1,#addonTable.dataLoads,1 do
                addonTable.dataLoads[i](realmId)
                addonTable.dataLoads[i]=nil
            end
        end
        wipe(addonTable.dataLoads)
        collectgarbage("collect") -- lots of strings made and trunc'ed in MarketData

        if not addonTable.realmIndex then
            print("The Undermine Journal - Warning: could not find data for realm ID "..realmId..", no data loaded!")
        elseif not addonTable.marketData then
            print("The Undermine Journal - Warning: no data loaded!")
        else
            LibExtraTip:AddCallback({type = "item", callback = onTooltipSetItem});
            LibExtraTip:AddCallback({type = "battlepet", callback = onTooltipSetItem});
            LibExtraTip:RegisterTooltip(GameTooltip)
            LibExtraTip:RegisterTooltip(ItemRefTooltip)
            LibExtraTip:RegisterTooltip(BattlePetTooltip)
            LibExtraTip:RegisterTooltip(FloatingBattlePetTooltip)
        end
    end
end

eventframe:RegisterEvent("PLAYER_ENTERING_WORLD")
eventframe:SetScript("OnEvent", onEvent)

local origGetAuctionBuyout = GetAuctionBuyout
local getAuctionBuyoutTable = {}

function GetAuctionBuyout(item) -- Tekkub's API
    local result = TUJMarketInfo(item, getAuctionBuyoutTable)
    if (result and result['input'] == item) then
        if (result['market']) then
            return result['market']
        else
            return result['globalMedian']
        end
    end

    if (origGetAuctionBuyout) then
        return origGetAuctionBuyout(item)
    end

    return nil
end



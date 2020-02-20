--[[

TheUndermineJournal addon, v 5.5
https://theunderminejournal.com/

You should be able to query this DB from other addons:

    o={}
    TUJMarketInfo(52719,o)
    print(o['market'])

Prints the market price of Greater Celestial Essence.
The item can be identified by anything GetItemInfo takes (itemid, itemstring, itemlink) or a battlepet itemstring/itemlink.

Prices are returned in copper, but accurate to the last *silver* (with coppers always 0).

    o['input']          -> the item/battlepet parameter you just passed in, verbatim

    o['itemid']         -> the ID of the item you just passed in

    o['species']        -> the species of the battlepet you just passed in
    o['quality']        -> the numeric quality/rarity of the battlepet

    o['age']            -> number of seconds since data was compiled

    o['globalMedian']   -> median market price across all realms in this region
    o['globalMean']     -> mean market price across all realms in this region
    o['globalStdDev']   -> standard deviation of the market price across all realms in this region

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

]]

local floor = math.floor
local tinsert, tonumber = tinsert, tonumber

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

local function round(num)
    return floor(num + 0.5)
end

local addonName, addonTable = ...

local function getSpeciesFromPetLink(link)
    local petString = string.match(link, "battlepet[%-?%d:]+")
    local _, speciesID, level, quality, health, power, speed = strsplit(":", petString)

    speciesID = tonumber(speciesID,10)
    level = tonumber(level,10)
    quality = tonumber(quality,10)
    health = tonumber(health,10)
    power = tonumber(power,10)
    speed = tonumber(speed,10)

    return speciesID, level, quality, health, power, speed
end

local marketInfoCache, marketInfoCacheKeys, marketInfoCacheMaxDepth = {}, {}, 16
local GetDetailedItemLevelInfo = addonTable.GetDetailedItemLevelInfo

local function InfoCacheKeyComparitor(x,y)
    return marketInfoCache[x]['__queried'] < marketInfoCache[y]['__queried']
end

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

    if marketInfoCache[item] then
        marketInfoCache[item]['__queried'] = GetTime()
        if not marketInfoCache[item]['input'] then return tr end
        if not tr then tr = {} end
        for k,v in pairs(marketInfoCache[item]) do
            tr[k] = v
        end
        tr['__queried'] = nil
        return tr
    end

    if #marketInfoCacheKeys >= marketInfoCacheMaxDepth then
        -- reuse oldest cache table
        sort(marketInfoCacheKeys, InfoCacheKeyComparitor)
        marketInfoCache[item] = marketInfoCache[marketInfoCacheKeys[1]]
        wipe(marketInfoCache[item])

        while #marketInfoCacheKeys >= marketInfoCacheMaxDepth do
            marketInfoCache[marketInfoCacheKeys[1]] = nil
            table.remove(marketInfoCacheKeys, 1)
        end
    else
        -- add new table to cache
        marketInfoCache[item] = {}
    end
    table.insert(marketInfoCacheKeys, item)
    marketInfoCache[item]['__queried'] = GetTime()

    local _, link, dataKey
    local iid, pricingLevel, species, quality

    if (strfind(item, 'battlepet:')) then
        species, _, quality = getSpeciesFromPetLink(item)
        dataKey = 's'..species
    else
        local itemId, _, _, _, _, itemClass = GetItemInfoInstant(item)
        if not itemId then return tr end

        iid = itemId
        dataKey = tostring(iid)

        pricingLevel = 0
        if (itemClass == 2) or (itemClass == 4) then
            local effectiveLevel, previewLevel, origLevel = GetDetailedItemLevelInfo(item)
            -- effectiveLevel may be nil when GetItemInfo didn't have the item readily available.
            if effectiveLevel then
                pricingLevel = effectiveLevel
                if not addonTable.marketData[dataKey .. 'x' .. pricingLevel] then
                    local low, high = math.min(effectiveLevel, previewLevel or effectiveLevel, origLevel), math.max(effectiveLevel, previewLevel or effectiveLevel, origLevel)
                    for i=low,high,1 do
                        if addonTable.marketData[dataKey .. 'x' .. i] then
                            pricingLevel = i
                        end
                    end
                end
            end
        end

        if pricingLevel > 0 then
            dataKey = dataKey .. 'x' .. pricingLevel
        end
    end

    local dta = addonTable.marketData[dataKey]
    if not dta then return tr end

    if not tr then tr = {} end

    tr['input'] = item
    if (iid) then
        tr['itemid'] = iid
    end
    if (species) then
        tr['species'] = species
        tr['quality'] = quality
    end

    if addonTable.dataAge then
        tr['age'] = time() - addonTable.dataAge
    end

    local priceSize = string.byte(dta, 1)

    local offset = 2

    tr['globalMedian'] = round(char2dec(string.sub(dta, offset, offset+priceSize-1))) * 100
    if tr['globalMedian'] == 0 then tr['globalMedian'] = nil end
    offset = offset + priceSize

    tr['globalMean'] = round(char2dec(string.sub(dta, offset, offset+priceSize-1))) * 100
    offset = offset + priceSize

    tr['globalStdDev'] = round(char2dec(string.sub(dta, offset, offset+priceSize-1))) * 100
    offset = offset + priceSize

    tr['days'] = string.byte(dta, offset, offset)
    offset = offset + 1

    tr['market'] = round(char2dec(string.sub(dta, offset, offset+priceSize-1))) * 100
    offset = offset + priceSize

    tr['stddev'] = round(char2dec(string.sub(dta, offset, offset+priceSize-1))) * 100
    offset = offset + priceSize

    tr['recent'] = round(char2dec(string.sub(dta, offset, offset+priceSize-1))) * 100
    --offset = offset + priceSize

    for k,v in pairs(tr) do
        marketInfoCache[item][k] = v
    end

    return tr
end

--[[
    enable/disable/query whether the TUJ tooltip additions are enabled
]]
local tooltipsEnabled = true
function TUJTooltip(...)
    if select('#', ...) >= 1 then
        tooltipsEnabled = not not select(1,...) --coerce into boolean

        local line = strmatch(debugstack(2, 1, 0), "[Aa][Dd][Dd][Oo][Nn][Ss]\\([^\\]+)")
        if line and line ~= addonName then
            local callingName, callingTitle = GetAddOnInfo(line)
            if callingName then
                callingName = callingTitle or callingName
                if tooltipsEnabled then line = 'enabled' else line = 'disabled' end
                print("The Undermine Journal - Tooltip prices " .. line .. " by " .. callingName)
            end
        end
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

    LibExtraTip:AddLine(tooltip," ",r,g,b,true)

    if (pricingData['age'] > 3*24*60*60) then
        LibExtraTip:AddLine(tooltip,"As of "..SecondsToTime(pricingData['age'],pricingData['age']>60).." ago:",r,g,b,true)
    end

    if pricingData['recent'] then
        LibExtraTip:AddDoubleLine(tooltip,"3-Day Price",coins(pricingData['recent']),r,g,b,nil,nil,nil,true)
    end
    if pricingData['market'] then
        LibExtraTip:AddDoubleLine(tooltip,"14-Day Price",coins(pricingData['market']),r,g,b,nil,nil,nil,true)
    end
    if pricingData['stddev'] then
        LibExtraTip:AddDoubleLine(tooltip,"14-Day Std Dev",coins(pricingData['stddev']),r,g,b,nil,nil,nil,true)
    end

    local regionName = addonTable.region or "Regional"

    if pricingData['globalMedian'] then
        LibExtraTip:AddDoubleLine(tooltip,regionName.." Median",coins(pricingData['globalMedian']),r,g,b,nil,nil,nil,true)
    end
    if pricingData['globalMean'] then
        LibExtraTip:AddDoubleLine(tooltip,regionName.." Mean",coins(pricingData['globalMean']),r,g,b,nil,nil,nil,true)
    end
    if pricingData['globalStdDev'] then
        LibExtraTip:AddDoubleLine(tooltip,regionName.." Std Dev",coins(pricingData['globalStdDev']),r,g,b,nil,nil,nil,true)
    end

    if pricingData['days'] == 255 then
        LibExtraTip:AddLine(tooltip,"Never seen since WoD",r,g,b,true)
    elseif pricingData['days'] == 252 then
        LibExtraTip:AddLine(tooltip,"Sold by Vendors",r,g,b,true)
    elseif pricingData['days'] > 250 then
        LibExtraTip:AddLine(tooltip,"Last seen over 250 days ago",r,g,b,true)
    elseif pricingData['days'] > 1 then
        LibExtraTip:AddLine(tooltip,"Last seen "..SecondsToTime(pricingData['days']*24*60*60).." ago",r,g,b,true)
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

local eventframe = CreateFrame("FRAME",addonName.."Events")

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
            if not tooltipsEnabled then
                print("The Undermine Journal - Tooltip prices disabled. Run /tujtooltip to toggle.")
            end
            LibExtraTip:AddCallback({type = "item", callback = onTooltipSetItem})
            LibExtraTip:AddCallback({type = "battlepet", callback = onTooltipSetItem})
            LibExtraTip:RegisterTooltip(GameTooltip)
            LibExtraTip:RegisterTooltip(ItemRefTooltip)
            LibExtraTip:RegisterTooltip(BattlePetTooltip)
            LibExtraTip:RegisterTooltip(FloatingBattlePetTooltip)
        end
    elseif event == "ADDON_LOADED" then
        tooltipsEnabled = not _G["TUJTooltipsHidden"]
    elseif event == "PLAYER_LOGOUT" then
        _G["TUJTooltipsHidden"] = not tooltipsEnabled
    end
end

eventframe:RegisterEvent("PLAYER_ENTERING_WORLD")
eventframe:RegisterEvent("ADDON_LOADED")
eventframe:RegisterEvent("PLAYER_LOGOUT")
eventframe:SetScript("OnEvent", onEvent)

SLASH_THEUNDERMINEJOURNAL1 = '/tujtooltip'
function SlashCmdList.THEUNDERMINEJOURNAL(msg)
    local newEnabled = not TUJTooltip()
    if msg == 'on' then
        newEnabled = true
    elseif msg == 'off' then
        newEnabled = false
    end
    if TUJTooltip(newEnabled) then
        print("The Undermine Journal - Tooltip prices enabled.")
    else
        print("The Undermine Journal - Tooltip prices disabled.")
    end
end

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



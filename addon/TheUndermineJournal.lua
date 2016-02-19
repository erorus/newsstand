--[[

TheUndermineJournal addon, v 3.7
https://theunderminejournal.com/

You should be able to query this DB from other addons:

    o={}
    TUJMarketInfo(52719,o)
    print(o['market'])

Prints the market price of Greater Celestial Essence. The item can be identified by anything GetItemInfo takes (itemid, itemstring, itemlink).

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

--[[
    This chunk from:

    Norganna's Tooltip Helper class
    Version: 1.0

    License:
        This program is free software; you can redistribute it and/or
        modify it under the terms of the GNU General Public License
        as published by the Free Software Foundation; either version 2
        of the License, or (at your option) any later version.

        This program is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
        GNU General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with this program(see GPL.txt); if not, write to the Free Software
        Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

    Note:
        This source code is specifically designed to work with World of Warcraft's
        interpreted AddOn system.
        You have an implicit licence to use this code with these facilities
        since that is its designated purpose as per:
        http://www.fsf.org/licensing/licenses/gpl-faq.html#InterpreterIncompat

        If you copy this code, please rename it to your own tastes, as this file is
        liable to change without notice and could possibly destroy any code that relies
        on it staying the same.
        We will attempt to avoid this happening where possible (of course).
]]

local function coins(money, graphic)
    local GOLD="ffd100"
    local SILVER="e6e6e6"
    local COPPER="c8602c"

    local GSC_3 = "|cff%s%d|cff000000.|cff%s%02d|cff000000.|cff%s%02d|r"
    local GSC_2 = "|cff%s%d|cff000000.|cff%s%02d|r"
    local GSC_1 = "|cff%s%d|r"

    local iconpath = "Interface\\MoneyFrame\\UI-"
    local goldicon = "%d|T"..iconpath.."GoldIcon:0|t"
    local silvericon = "%s|T"..iconpath.."SilverIcon:0|t"
    local coppericon = "%s|T"..iconpath.."CopperIcon:0|t"

    money = math.floor(tonumber(money) or 0)
    local g = math.floor(money / 10000)
    local s = math.floor(money % 10000 / 100)
    local c = money % 100

    if not graphic then
        if g > 0 then
            if (c > 0) then
                return GSC_3:format(GOLD, g, SILVER, s, COPPER, c)
            else
                return GSC_2:format(GOLD, g, SILVER, s)
            end
        elseif s > 0 then
            if (c > 0) then
                return GSC_2:format(SILVER, s, COPPER, c)
            else
                return GSC_1:format(SILVER, s)
            end
        else
            return GSC_1:format(COPPER, c)
        end
    else
        if g > 0 then
            if (c > 0) then
                return goldicon:format(g)..silvericon:format("%02d"):format(s)..coppericon:format("%02d"):format(c)
            else
                return goldicon:format(g)..silvericon:format("%02d"):format(s)
            end
        elseif s > 0  then
            if (c > 0) then
                return silvericon:format("%d"):format(s)..coppericon:format("%02d"):format(c)
            else
                return silvericon:format("%d"):format(s)
            end
        else
            return coppericon:format("%d"):format(c)
        end
    end
end

--[[
    End of chunk from 
    
    Norganna's Tooltip Helper class
    Version: 1.0
]]

local function char2dec(s)
    local n, l = 0, string.len(s)
    for i=1,l,1 do
        n = n * 256 + string.byte(s,i)
    end
    return n
end

local function round(num, idp)
    local mult = 10^(idp or 0)
    return math.floor(num * mult + 0.5) / mult
end

local addonName, addonTable = ...

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

    local _, link = GetItemInfo(item)
    if not link then return tr end
    local itemString = string.match(link, "item[%-?%d:]+")
    local itemStringParts = { strsplit(":", itemString) }
    local iid = itemStringParts[2]

    local numBonuses = tonumber(itemStringParts[14],10)
    local bonusSet = 0

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

    local dataKey = iid
    if bonusSet > 0 then
        dataKey = dataKey .. 'x' .. bonusSet
    end

    if not addonTable.marketData[dataKey] then return tr end

    if not tr then tr = {} end

    tr['input'] = item
    tr['itemid'] = tonumber(iid,10)
    if bonusSet > 0 then
        tr['bonuses'] = table.concat(addonTable.bonusSets[bonusSet], ':')
    end

    if addonTable.dataAge then
        tr['age'] = time() - addonTable.dataAge
    end

    local dta = addonTable.marketData[dataKey]

    local priceSize = string.byte(dta, 1);

    local offset = 2

    tr['globalMedian'] = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
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

local _tooltipcallback, lasttooltip
local dataResults = {}

local function ClearLastTip(...)
    lasttooltip = nil
end

local function GetCallback()
    _tooltipcallback = _tooltipcallback or function(tooltip,...)
        if not addonTable.marketData then return end
        if not tooltipsEnabled then return end
        if lasttooltip == tooltip then return end
        lasttooltip = tooltip

        local itemLink
        local spellName, spellRank, spellID = GameTooltip:GetSpell()
        if spellID then
            return
            --item = addonTable.spelltoitem[spellID]
        else
            local name, item = tooltip:GetItem()
            if (not name) or (not item) then return end
            itemLink = item
        end

        if not itemLink then
            return
        end

        TUJMarketInfo(itemLink, dataResults)

        if dataResults['input'] and (dataResults['input'] == itemLink) then
            local r,g,b = .9,.8,.5

            tooltip:AddLine(" ")

            if (dataResults['age'] > 3*24*60*60) then
                tooltip:AddLine("As of "..SecondsToTime(dataResults['age'],dataResults['age']>60).." ago:",r,g,b)
            end

            if dataResults['recent'] then
                tooltip:AddDoubleLine("3-Day Price",coins(dataResults['recent'],false),r,g,b)
            end
            if dataResults['market'] then
                tooltip:AddDoubleLine("14-Day Price",coins(dataResults['market'],false),r,g,b)
            end
            if dataResults['market'] then
                tooltip:AddDoubleLine("14-Day Std Dev",coins(dataResults['stddev'],false),r,g,b)
            end
            if dataResults['globalMedian'] then
                tooltip:AddDoubleLine("Global Median",coins(dataResults['globalMedian'],false),r,g,b)
            end
            if dataResults['globalMean'] then
                tooltip:AddDoubleLine("Global Mean",coins(dataResults['globalMean'],false),r,g,b)
            end
            if dataResults['globalStdDev'] then
                tooltip:AddDoubleLine("Global Std Dev",coins(dataResults['globalStdDev'],false),r,g,b)
            end

            if dataResults['days'] == 255 then
                tooltip:AddLine("Never seen since WoD",r,g,b)
            elseif dataResults['days'] > 250 then
                tooltip:AddLine("Last seen over 250 days ago",r,g,b)
            elseif dataResults['days'] > 1 then
                tooltip:AddLine("Last seen "..SecondsToTime(dataResults['days']*24*60*60).." ago",r,g,b)
            end

        end
    end
    return _tooltipcallback
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
            for _,frame in pairs{GameTooltip, ItemRefTooltip, ShoppingTooltip1, ShoppingTooltip2} do
                frame:HookScript("OnTooltipSetItem", GetCallback())
                --frame:HookScript("OnTooltipSetSpell", GetCallback())
                frame:HookScript("OnTooltipCleared", ClearLastTip)
            end
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



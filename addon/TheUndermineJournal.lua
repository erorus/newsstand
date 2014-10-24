--[[

TheUndermineJournal addon, v 3.0
https://theunderminejournal.com/

You should be able to query this DB from other addons:

o={}
TUJMarketInfo(52719,o)
print(o['market'])

Prints the market price of Greater Celestial Essence.

o['age'] = num of days since import
o['market'] = most recent market price on this realm
o['regionmarket'] = median market price over all realms
o['lastseen'] = date that the item was last seen in a scan

You can query and set whether the additional tooltip lines appear from this addon.
This is useful for other addons (Auctioneer, TSM, etc) that have their own fancy tooltips to disable TUJ tooltips and use TUJ simply as a data source.

TUJTooltip() returns a boolean whether TUJ tooltips are enabled
TUJTooltip(true) enables TUJ tooltips
TUJTooltip(false) disables TUJ tooltips

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

-- Lua 5.1+ base64 v3.0 (c) 2009 by Alex Kloss <alexthkloss@web.de>
-- licensed under the terms of the LGPL2
function base64dec(data)
    local b='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
    data = string.gsub(data, '[^'..b..'=]', '')
    return (data:gsub('.', function(x)
        if (x == '=') then return '' end
        local r,f='',(b:find(x)-1)
        for i=6,1,-1 do r=r..(f%2^i-f%2^(i-1)>0 and '1' or '0') end
        return r;
    end):gsub('%d%d%d?%d?%d?%d?%d?%d?', function(x)
        if (#x ~= 8) then return '' end
        local c=0
        for i=1,8 do c=c+(x:sub(i,i)=='1' and 2^(8-i) or 0) end
        return string.char(c)
    end))
end

local function char2dec(s)
    local n, l = 0, string.len(s)
    for i=1,l,1 do
        n = n * 256 + string.byte(s,i)
    end
    return n
end

local addonName, addonTable = ...
addonTable.region = 'US' --string.upper(string.sub(GetCVar("realmList"),1,2))
if addonTable.region ~= 'EU' then
    addonTable.region = 'US'
end
addonTable.realmName = string.upper(GetRealmName())
addonTable.tooltipsEnabled = true

local function GetIDFromLink(SpellLink)
	local _, l = GetItemInfo(SpellLink)
	if not l then return end
	return tonumber(string.match(l, "^|c%x%x%x%x%x%x%x%x|H%w+:(%d+)"))
end

local _tooltipcallback, lasttooltip

local function ClearLastTip(...)
	lasttooltip = nil
end

local function GetCallback()
	_tooltipcallback = _tooltipcallback or function(tooltip,...)
		if not addonTable.marketData then return end
		if not addonTable.tooltipsEnabled then return end
		if lasttooltip == tooltip then return end
		lasttooltip = tooltip

		local iid
		local spellName, spellRank, spellID = GameTooltip:GetSpell()
		if spellID then
			return
			--iid = addonTable.spelltoitem[spellID]
		else
			local name, item = tooltip:GetItem()
			if (not name) or (not item) then return end
			iid = GetIDFromLink(item)
		end

		if iid and addonTable.marketData[iid] then
			local r,g,b = .9,.8,.5
			local dta = addonTable.marketData[iid]
            dta = base64dec(dta)

            local offset, priceSize = 2, string.byte(dta, 1);

            local market, regionMedian, regionAverage, regionStdDev

            regionMedian = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
            offset = offset + priceSize

            regionAverage = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
            offset = offset + priceSize

            regionStdDev = char2dec(string.sub(dta, offset, offset+priceSize-1))*100;
            offset = offset + priceSize

            offset = offset + priceSize * addonTable.realmIndex;
            market = char2dec(string.sub(dta, offset, offset+priceSize-1))*100

            tooltip:AddLine(" ")

			--[[
			if addonTable.updatetime then
				age = time() - (addonTable.updatetime+timeoffset)
				if (age > 0) then
					tooltip:AddLine("As of "..SecondsToTime(age,age>60).." Ago:",r,g,b)
				end
			end
			]]

			if market then
				tooltip:AddDoubleLine("Realm Price",coins(market,false),r,g,b)
			end
            if regionMedian then
                tooltip:AddDoubleLine("Global Median",coins(regionMedian,false),r,g,b)
            end
            if regionAverage then
                tooltip:AddDoubleLine("Global Mean",coins(regionAverage,false),r,g,b)
            end
            if regionStdDev then
                tooltip:AddDoubleLine("Global Std Dev",coins(regionStdDev,false),r,g,b)
            end

			--[[
			if addonTable.updatetime and dta['lastseen'] then
				local ts = dta['lastseen']
				local y,m,d = strsplit('-',strsub(ts,1,10))
				local h,mi,s = strsplit(':',strsub(ts,12,19))
				local lastseen = time({['year']=y,['month']=m,['day']=d,['hour']=h,['min']=mi,['sec']=s,['isdst']=nil})
				if ((addonTable.updatetime - lastseen) > 129600) then
					lastseen = time() - (lastseen + timeoffset)
					tooltip:AddLine("Last seen "..SecondsToTime(lastseen,lastseen>60).." ago!",r,g,b)
				end
			end
			]]
		end
	end
	return _tooltipcallback
end

if not addonTable.skiploading then
	--[[
		pass a table as the second argument to wipe and reuse that table
		otherwise a new table will be created and returned
	]]
	function TUJMarketInfo(iid,...)
		if iid == 0 then
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
		
		if not iid then return tr end
		if not addonTable.marketData then return tr end
		if not addonTable.marketData[iid] then return tr end
		
		if not tr then tr = {} end
		
		tr['itemid'] = iid
		--[[
		if addonTable.updatetime then
			tr['age'] = time() - (addonTable.updatetime+timeoffset)
		end
		]]
		
		for k,v in pairs(addonTable.marketData[iid]) do
			tr[k] = v
		end

		return tr
	end
	
	--[[
		enable/disable/query whether the TUJ tooltip additions are enabled
	]]
	function TUJTooltip(...)
		if select('#', ...) >= 1 then
			addonTable.tooltipsEnabled = not not select(1,...) --coerce into boolean
		end
		return addonTable.tooltipsEnabled
	end
	
end

local eventframe = CreateFrame("FRAME",addonName.."Events");

local function onEvent(self,event,arg)
	if event == "PLAYER_LOGIN" then
		local _,mon,day,yr = CalendarGetDate()
		local hr,min = GetGameTime()
		local servertime = time({['year']=yr,['month']=mon,['day']=day,['hour']=hr,['min']=min,['sec']=time()%60,['isdst']=nil})
		--timeoffset = time()-servertime;
	end
	if event == "PLAYER_ENTERING_WORLD" then
		eventframe:UnregisterEvent("PLAYER_ENTERING_WORLD")
		--[[
		if addonTable.region ~= string.upper(string.sub(GetCVar("realmList"),1,2)) then
			print("The Undermine Journal - Warning: Unknown region from realmlist.wtf: '"..string.upper(string.sub(GetCVar("realmList"),1,2)).."', assuming '"..addonTable.region.."'")
		end
		]]
        for _,frame in pairs{GameTooltip, ItemRefTooltip, ShoppingTooltip1, ShoppingTooltip2} do
            frame:HookScript("OnTooltipSetItem", GetCallback())
            frame:HookScript("OnTooltipSetSpell", GetCallback())
            frame:HookScript("OnTooltipCleared", ClearLastTip)
        end
	end
end

if not addonTable.skiploading then
	eventframe:RegisterEvent("PLAYER_LOGIN")
	eventframe:RegisterEvent("PLAYER_ENTERING_WORLD")
	eventframe:SetScript("OnEvent", onEvent)
end


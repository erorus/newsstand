local addonName, addonTable = ...
local eventframe = CreateFrame("FRAME",addonName.."Events")

local function SeenItemMessage(itemLink, id)
    local _
    if not itemLink then
        _, itemLink = GetItemInfo(id)
    end
    print(_ and 'Sniffed' or 'Seen', id, itemLink)
end

local function onEvent(self,event,arg)
    if event == "GET_ITEM_INFO_RECEIVED" then
        SeenItemMessage(nil, arg)
    end
    if event == "ADDON_LOADED" then
        if TUJSniff_Last then
            print("TUJSniff: last scan index: " .. TUJSniff_Last)
        end
    end
end

eventframe:RegisterEvent("GET_ITEM_INFO_RECEIVED")
eventframe:RegisterEvent("ADDON_LOADED")
eventframe:SetScript("OnEvent", onEvent)

local scanIndex = 1
local running = false

local function SniffItems()
    if not running then
        return
    end

    local curId = addonTable.missingItems[scanIndex]
    if not curId then
        print("Done scanning!")
        return
    end
    local _, itemLink = GetItemInfo(curId);
    if _ then
        SeenItemMessage(itemLink, curId)
    else
        local remaining = SecondsToTime((#addonTable.missingItems - scanIndex) * 0.3)
        print("Fetching", curId, ""..scanIndex..'/'..#addonTable.missingItems, '('..math.floor(scanIndex/#addonTable.missingItems*100)..'% '..remaining..')');
    end
    TUJSniff_Last = scanIndex
    scanIndex = scanIndex + 1
    C_Timer.After(0.3, SniffItems)
end

SLASH_TUJSNIFF1 = '/tujsniff'
function SlashCmdList.TUJSNIFF(msg)
    if msg == "stop" then
        running = false
    else
        if msg then
            scanIndex = tonumber(msg) or scanIndex
        end
        if not running then
            running = true
            SniffItems(msg)
        end
    end
end

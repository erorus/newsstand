DELIMITER //

DROP FUNCTION IF EXISTS GetMarketPrice//
CREATE FUNCTION GetMarketPrice(inHouse smallint, inID mediumint, inBonusSet tinyint, inDate timestamp)
  RETURNS BIGINT DETERMINISTIC
  BEGIN
    DECLARE tr BIGINT;

    SELECT
      copper
    INTO tr
    FROM tblDBCItemVendorCost
    WHERE item = inID;

    IF tr IS NOT NULL
    THEN RETURN tr; END IF;

    if inDate is not null then
      select (case hour(s.updated)
              when 0 then ih.silver00
              when 1 then ih.silver01
              when 2 then ih.silver02
              when 3 then ih.silver03
              when 4 then ih.silver04
              when 5 then ih.silver05
              when 6 then ih.silver06
              when 7 then ih.silver07
              when 8 then ih.silver08
              when 9 then ih.silver09
              when 10 then ih.silver10
              when 11 then ih.silver11
              when 12 then ih.silver12
              when 13 then ih.silver13
              when 14 then ih.silver14
              when 15 then ih.silver15
              when 16 then ih.silver16
              when 17 then ih.silver17
              when 18 then ih.silver18
              when 19 then ih.silver19
              when 20 then ih.silver20
              when 21 then ih.silver21
              when 22 then ih.silver22
              when 23 then ih.silver23
              else null end) * 100
      into tr
      from tblSnapshot s
        join tblItemHistoryHourly ih on date(s.updated) = ih.`when` and ih.house = s.house
      where s.house = inHouse
            and ih.item = inID
            and ih.bonusset = ifnull(inBonusSet, ih.bonusset)
            and s.updated <= inDate
            and ifnull(case hour(s.updated)
                       when 0 then ih.quantity00
                       when 1 then ih.quantity01
                       when 2 then ih.quantity02
                       when 3 then ih.quantity03
                       when 4 then ih.quantity04
                       when 5 then ih.quantity05
                       when 6 then ih.quantity06
                       when 7 then ih.quantity07
                       when 8 then ih.quantity08
                       when 9 then ih.quantity09
                       when 10 then ih.quantity10
                       when 11 then ih.quantity11
                       when 12 then ih.quantity12
                       when 13 then ih.quantity13
                       when 14 then ih.quantity14
                       when 15 then ih.quantity15
                       when 16 then ih.quantity16
                       when 17 then ih.quantity17
                       when 18 then ih.quantity18
                       when 19 then ih.quantity19
                       when 20 then ih.quantity20
                       when 21 then ih.quantity21
                       when 22 then ih.quantity22
                       when 23 then ih.quantity23
                       else null end, 0) > 0
      order by s.updated desc
      limit 1;
    end if;

    IF tr IS NULL
    THEN
      select price
      into tr
      from tblItemSummary
      where house = inHouse and item = inID and bonusset = ifnull(inBonusSet, bonusset)
      order by if(quantity=0, 0, 1) desc, price desc
      limit 1;
    END IF;

    RETURN tr;
  END//

DROP PROCEDURE IF EXISTS GetReagentPriceR//
drop function if exists GetReagentPrice//

drop function if exists GetCurrentCraftingPrice//
create function GetCurrentCraftingPrice(inHouse INT, inID INT) returns bigint reads sql data
begin
  declare result bigint;

  select min(cost)
  into result
  from (
    select sc.spell, ceil(sum(ifnull(ivc.copper, tis.price) * sr.qty) / s.qtymade) cost
    from tblDBCSpellCrafts sc
      join tblDBCSpell s on sc.spell = s.id
      join tblDBCSpellReagents sr on sr.spell = sc.spell
      join tblDBCItem i on i.id = sr.item
      join tblItemSummary tis on tis.item = sr.item and tis.level = if(i.class in (2,4), i.level, 0)
      left join tblDBCItemVendorCost ivc on ivc.item = sr.item
    where not exists (select 1 from tblDBCSpell sr where sr.replacesspell = s.id)
      and sc.item = inID
      and tis.house = inHouse
      and s.cooldown = 0
    group by sc.spell
  ) zz;

  return result;
end//

DELIMITER ;

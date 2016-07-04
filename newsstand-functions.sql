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
CREATE PROCEDURE GetReagentPriceR(
  IN  inHouse     SMALLINT,
  IN  inID        MEDIUMINT,
  IN  inDate      TIMESTAMP,
  IN  inLevels    TINYINT,
  IN  inSkillLine SMALLINT,
  OUT outPrice    BIGINT) DETERMINISTIC READS SQL DATA
  BEGIN
    DECLARE x INT DEFAULT 0;
    DECLARE done INT DEFAULT 0;
    DECLARE seenspells INT DEFAULT 0;
    DECLARE _spellid MEDIUMINT;
    DECLARE _reagentid MEDIUMINT;
    DECLARE _istransmute INT;
    DECLARE _quantity FLOAT;
    DECLARE _skillline SMALLINT;
    DECLARE runningprice BIGINT;
    DECLARE retprice BIGINT;
    DECLARE retailprice BIGINT;

    DECLARE curspells CURSOR FOR
      SELECT DISTINCT
        ir.spell,
        ir.skillline,
        if(s.name LIKE '%Transmute%', 1, 0)
      FROM tblDBCItemReagents ir
        JOIN tblDBCSpell s ON ir.spell = s.id
      WHERE ir.item = inID AND ir.skillline = ifnull(inSkillLine, ir.skillline) and s.cooldown < 10
      ORDER BY 3 ASC;

    DECLARE curreagents CURSOR FOR
      SELECT
        reagent,
        quantity
      FROM tblDBCItemReagents
      WHERE item = inID AND spell = _spellid;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done := 1;

    sproc: BEGIN

      set retailprice := GetMarketPrice(inHouse, inID, null, inDate);

      SET outPrice := retailprice;

      SELECT
        count(*)
      INTO x
      FROM tblDBCItemReagents
      WHERE item = inID;

      IF (inLevels >= 3) OR (x = 0)
      THEN
        LEAVE sproc;
      END IF;

      IF inLevels = 1
      THEN
        SET outPrice := NULL;
      END IF;

      OPEN curspells;
      spell_loop: LOOP
        FETCH curspells
        INTO _spellid, _skillline, _istransmute;
        IF done
        THEN
          LEAVE spell_loop;
        END IF;
        IF _istransmute > 0 AND seenspells > 0
        THEN
          LEAVE spell_loop;
        END IF;

        SET seenspells := seenspells + 1;
        SET runningprice := 0;
        SET retprice := NULL;

        OPEN curreagents;
        reagent_loop: LOOP
          FETCH curreagents
          INTO _reagentid, _quantity;
          IF done
          THEN
            LEAVE reagent_loop;
          END IF;

          CALL GetReagentPriceR(inHouse, _reagentid, inDate, inLevels + 1, _skillline, retprice);
          IF retprice IS NULL
          THEN
            LEAVE reagent_loop;
          END IF;

          SET runningprice := runningprice + (retprice * _quantity);
        END LOOP;
        CLOSE curreagents;
        SET done := 0;

        IF (retprice IS NOT NULL) AND ((outPrice IS NULL) OR (runningprice < outPrice))
        THEN
          SET outPrice := runningprice;
        END IF;
      END LOOP;
      CLOSE curspells;

      IF outPrice IS NULL
      THEN
        SET outPrice := retailprice;
      END IF;

    END sproc;
  END//

drop function if exists GetReagentPrice//

create function GetReagentPrice(inHouse INT, inID INT, inDate timestamp) returns bigint reads sql data
  begin
    declare tr bigint;
    declare x int;

    select count(*)
    into x
    from tblDBCItemReagents
    where item = inID;

    if x = 0 then
      return null;
    end if;

    set max_sp_recursion_depth=10;

    call GetReagentPriceR(inHouse, inID, inDate, 1, null, tr);

    return tr;
  end//

DELIMITER ;

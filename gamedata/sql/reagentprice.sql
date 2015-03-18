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
      select price
      into tr
      from tblItemHistory
      where house = inHouse
      and item = inID
      and bonusset = ifnull(inBonusSet, bonusset)
      and `snapshot` <= inDate
      order by `snapshot` desc, if(quantity=0, 0, 1) desc, price desc
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

      IF (inLevels >= 4) OR (x = 0)
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

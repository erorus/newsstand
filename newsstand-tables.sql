-- phpMyAdmin SQL Dump
-- version 2.11.11.3
-- http://www.phpmyadmin.net
--
-- Generation Time: Jun 25, 2016 at 06:46 PM
-- Server version: 5.5.45
-- PHP Version: 5.3.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `newsstand`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`newsstand`@`localhost` PROCEDURE `GetReagentPriceR`(
  IN  inHouse     SMALLINT,
  IN  inID        MEDIUMINT,
  IN  inDate      TIMESTAMP,
  IN  inLevels    TINYINT,
  IN  inSkillLine SMALLINT,
  OUT outPrice    BIGINT)
READS SQL DATA
DETERMINISTIC
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
  END$$

--
-- Functions
--
CREATE DEFINER=`newsstand`@`localhost` FUNCTION `GetMarketPrice`(inHouse smallint, inID mediumint, inBonusSet tinyint, inDate timestamp) RETURNS bigint(20)
DETERMINISTIC
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
  END$$

CREATE DEFINER=`newsstand`@`localhost` FUNCTION `GetReagentPrice`(inHouse INT, inID INT, inDate timestamp) RETURNS bigint(20)
READS SQL DATA
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
  end$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblAuction`
--

CREATE TABLE IF NOT EXISTS `tblAuction` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `item` mediumint(8) unsigned NOT NULL,
  `quantity` smallint(5) unsigned NOT NULL,
  `bid` decimal(11,0) NOT NULL,
  `buy` decimal(11,0) NOT NULL,
  `seller` int(10) unsigned NOT NULL,
  `timeleft` enum('SHORT','MEDIUM','LONG','VERY_LONG') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`house`,`id`),
  KEY `seller` (`seller`),
  KEY `item` (`item`,`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblAuctionExtra`
--

CREATE TABLE IF NOT EXISTS `tblAuctionExtra` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `rand` int(11) NOT NULL,
  `seed` int(11) NOT NULL,
  `context` tinyint(3) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bonus1` smallint(5) unsigned DEFAULT NULL,
  `bonus2` smallint(5) unsigned DEFAULT NULL,
  `bonus3` smallint(5) unsigned DEFAULT NULL,
  `bonus4` smallint(5) unsigned DEFAULT NULL,
  `bonus5` smallint(5) unsigned DEFAULT NULL,
  `bonus6` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`house`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblAuctionPet`
--

CREATE TABLE IF NOT EXISTS `tblAuctionPet` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `species` smallint(5) unsigned NOT NULL,
  `breed` tinyint(3) unsigned NOT NULL,
  `level` tinyint(3) unsigned NOT NULL,
  `quality` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`house`,`id`),
  KEY `species` (`species`,`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblAuctionRare`
--

CREATE TABLE IF NOT EXISTS `tblAuctionRare` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `prevseen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`house`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblBonusSet`
--

CREATE TABLE IF NOT EXISTS `tblBonusSet` (
  `set` tinyint(3) unsigned NOT NULL,
  `bonus` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`set`,`bonus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblCaptcha`
--

CREATE TABLE IF NOT EXISTS `tblCaptcha` (
  `id` int(10) unsigned NOT NULL,
  `race` tinyint(3) unsigned NOT NULL,
  `gender` tinyint(3) unsigned NOT NULL,
  `helm` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `race` (`race`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItem`
--

CREATE TABLE IF NOT EXISTS `tblDBCItem` (
  `id` mediumint(8) unsigned NOT NULL,
  `name_enus` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `name_dede` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_eses` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_frfr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_itit` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ptbr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ruru` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `quality` tinyint(3) unsigned NOT NULL,
  `level` smallint(5) unsigned DEFAULT NULL,
  `class` tinyint(3) unsigned NOT NULL,
  `subclass` tinyint(3) unsigned NOT NULL,
  `icon` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `stacksize` smallint(5) unsigned DEFAULT NULL,
  `binds` tinyint(3) unsigned DEFAULT NULL,
  `buyfromvendor` int(10) unsigned DEFAULT NULL,
  `selltovendor` int(10) unsigned DEFAULT NULL,
  `auctionable` tinyint(3) unsigned DEFAULT NULL,
  `type` tinyint(3) unsigned DEFAULT NULL,
  `requiredlevel` tinyint(3) unsigned DEFAULT NULL,
  `requiredskill` smallint(5) unsigned DEFAULT NULL,
  `basebonus` smallint(5) unsigned NOT NULL DEFAULT '0',
  `display` mediumint(8) unsigned DEFAULT NULL,
  `flags` set('pvp','notransmog') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemBonus`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemBonus` (
  `id` smallint(5) unsigned NOT NULL,
  `quality` tinyint(3) unsigned DEFAULT NULL,
  `level` smallint(6) DEFAULT NULL,
  `tag_enus` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag_dede` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag_eses` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag_frfr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag_itit` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag_ptbr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag_ruru` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tagpriority` tinyint(3) unsigned DEFAULT NULL,
  `name_enus` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_dede` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_eses` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_frfr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_itit` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ptbr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ruru` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `namepriority` tinyint(3) unsigned DEFAULT NULL,
  `flags` set('setmember') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemRandomSuffix`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemRandomSuffix` (
  `locale` char(4) COLLATE utf8_unicode_ci NOT NULL,
  `suffix` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`locale`,`suffix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemReagents`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemReagents` (
  `item` mediumint(8) unsigned NOT NULL,
  `skillline` smallint(5) unsigned NOT NULL,
  `reagent` mediumint(8) unsigned NOT NULL,
  `quantity` decimal(8,4) unsigned NOT NULL,
  `spell` mediumint(9) DEFAULT NULL,
  `fortooltip` tinyint(1) NOT NULL,
  KEY `itemid` (`item`),
  KEY `reagentid` (`reagent`),
  KEY `skillid` (`skillline`),
  KEY `spell` (`spell`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemSpell`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemSpell` (
  `item` mediumint(8) unsigned NOT NULL,
  `spell` mediumint(8) unsigned NOT NULL,
  PRIMARY KEY (`item`,`spell`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemSubClass`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemSubClass` (
  `class` tinyint(3) unsigned NOT NULL,
  `subclass` tinyint(3) unsigned NOT NULL,
  `name_enus` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `name_dede` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_eses` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_frfr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_itit` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ptbr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ruru` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`class`,`subclass`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemVendorCost`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemVendorCost` (
  `item` mediumint(8) unsigned NOT NULL,
  `copper` int(10) unsigned DEFAULT NULL,
  `npc` mediumint(8) unsigned DEFAULT NULL,
  `npccount` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCPet`
--

CREATE TABLE IF NOT EXISTS `tblDBCPet` (
  `id` smallint(5) unsigned NOT NULL,
  `name_enus` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `name_dede` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_eses` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_frfr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_itit` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ptbr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ruru` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `icon` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `npc` int(10) unsigned DEFAULT NULL,
  `category` tinyint(3) unsigned DEFAULT NULL,
  `flags` mediumint(8) unsigned DEFAULT NULL,
  `power` smallint(6) DEFAULT NULL,
  `stamina` smallint(6) DEFAULT NULL,
  `speed` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCRandEnchants`
--

CREATE TABLE IF NOT EXISTS `tblDBCRandEnchants` (
  `id` mediumint(9) NOT NULL,
  `name_enus` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `name_dede` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_eses` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_frfr` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_itit` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ptbr` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_ruru` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCSpell`
--

CREATE TABLE IF NOT EXISTS `tblDBCSpell` (
  `id` mediumint(8) unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `icon` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `cooldown` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `skillline` smallint(5) unsigned DEFAULT NULL,
  `qtymade` decimal(7,2) unsigned NOT NULL DEFAULT '0.00',
  `yellow` smallint(5) unsigned DEFAULT NULL,
  `crafteditem` mediumint(8) unsigned DEFAULT NULL,
  `expansion` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crafteditem` (`crafteditem`),
  KEY `skilllineid` (`skillline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblEmailBlocked`
--

CREATE TABLE IF NOT EXISTS `tblEmailBlocked` (
  `address` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblEmailLog`
--

CREATE TABLE IF NOT EXISTS `tblEmailLog` (
  `sha1id` binary(20) NOT NULL,
  `sent` datetime NOT NULL,
  `recipient` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`sha1id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblHouseCheck`
--

CREATE TABLE IF NOT EXISTS `tblHouseCheck` (
  `house` smallint(5) unsigned NOT NULL,
  `nextcheck` timestamp NULL DEFAULT NULL,
  `lastdaily` date DEFAULT NULL,
  `lastcheck` timestamp NULL DEFAULT NULL,
  `lastcheckresult` text COLLATE utf8_unicode_ci,
  `lastchecksuccess` timestamp NULL DEFAULT NULL,
  `lastchecksuccessresult` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemExpired`
--

CREATE TABLE IF NOT EXISTS `tblItemExpired` (
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `when` date NOT NULL,
  `created` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `expired` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`item`,`house`,`bonusset`,`when`),
  KEY `house` (`house`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemGlobal`
--

CREATE TABLE IF NOT EXISTS `tblItemGlobal` (
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `median` decimal(11,0) unsigned NOT NULL,
  `mean` decimal(11,0) unsigned NOT NULL,
  `stddev` decimal(11,0) unsigned NOT NULL,
  PRIMARY KEY (`item`,`bonusset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemGlobalWorking`
--

CREATE TABLE IF NOT EXISTS `tblItemGlobalWorking` (
  `when` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `median` decimal(11,0) unsigned NOT NULL,
  `mean` decimal(11,0) unsigned NOT NULL,
  `stddev` decimal(11,0) unsigned NOT NULL,
  PRIMARY KEY (`when`,`item`,`bonusset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemHistoryDaily`
--

CREATE TABLE IF NOT EXISTS `tblItemHistoryDaily` (
  `item` mediumint(8) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `when` date NOT NULL,
  `pricemin` int(10) unsigned NOT NULL,
  `priceavg` int(10) unsigned NOT NULL,
  `pricemax` int(10) unsigned NOT NULL,
  `pricestart` int(10) unsigned NOT NULL,
  `priceend` int(10) unsigned NOT NULL,
  `quantitymin` mediumint(8) unsigned NOT NULL,
  `quantityavg` mediumint(8) unsigned NOT NULL,
  `quantitymax` mediumint(8) unsigned NOT NULL,
  PRIMARY KEY (`house`,`item`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemHistoryHourly`
--

CREATE TABLE IF NOT EXISTS `tblItemHistoryHourly` (
  `house` smallint(5) unsigned NOT NULL,
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL,
  `when` date NOT NULL,
  `silver00` int(10) unsigned DEFAULT NULL,
  `quantity00` mediumint(8) unsigned DEFAULT NULL,
  `silver01` int(10) unsigned DEFAULT NULL,
  `quantity01` mediumint(8) unsigned DEFAULT NULL,
  `silver02` int(10) unsigned DEFAULT NULL,
  `quantity02` mediumint(8) unsigned DEFAULT NULL,
  `silver03` int(10) unsigned DEFAULT NULL,
  `quantity03` mediumint(8) unsigned DEFAULT NULL,
  `silver04` int(10) unsigned DEFAULT NULL,
  `quantity04` mediumint(8) unsigned DEFAULT NULL,
  `silver05` int(10) unsigned DEFAULT NULL,
  `quantity05` mediumint(8) unsigned DEFAULT NULL,
  `silver06` int(10) unsigned DEFAULT NULL,
  `quantity06` mediumint(8) unsigned DEFAULT NULL,
  `silver07` int(10) unsigned DEFAULT NULL,
  `quantity07` mediumint(8) unsigned DEFAULT NULL,
  `silver08` int(10) unsigned DEFAULT NULL,
  `quantity08` mediumint(8) unsigned DEFAULT NULL,
  `silver09` int(10) unsigned DEFAULT NULL,
  `quantity09` mediumint(8) unsigned DEFAULT NULL,
  `silver10` int(10) unsigned DEFAULT NULL,
  `quantity10` mediumint(8) unsigned DEFAULT NULL,
  `silver11` int(10) unsigned DEFAULT NULL,
  `quantity11` mediumint(8) unsigned DEFAULT NULL,
  `silver12` int(10) unsigned DEFAULT NULL,
  `quantity12` mediumint(8) unsigned DEFAULT NULL,
  `silver13` int(10) unsigned DEFAULT NULL,
  `quantity13` mediumint(8) unsigned DEFAULT NULL,
  `silver14` int(10) unsigned DEFAULT NULL,
  `quantity14` mediumint(8) unsigned DEFAULT NULL,
  `silver15` int(10) unsigned DEFAULT NULL,
  `quantity15` mediumint(8) unsigned DEFAULT NULL,
  `silver16` int(10) unsigned DEFAULT NULL,
  `quantity16` mediumint(8) unsigned DEFAULT NULL,
  `silver17` int(10) unsigned DEFAULT NULL,
  `quantity17` mediumint(8) unsigned DEFAULT NULL,
  `silver18` int(10) unsigned DEFAULT NULL,
  `quantity18` mediumint(8) unsigned DEFAULT NULL,
  `silver19` int(10) unsigned DEFAULT NULL,
  `quantity19` mediumint(8) unsigned DEFAULT NULL,
  `silver20` int(10) unsigned DEFAULT NULL,
  `quantity20` mediumint(8) unsigned DEFAULT NULL,
  `silver21` int(10) unsigned DEFAULT NULL,
  `quantity21` mediumint(8) unsigned DEFAULT NULL,
  `silver22` int(10) unsigned DEFAULT NULL,
  `quantity22` mediumint(8) unsigned DEFAULT NULL,
  `silver23` int(10) unsigned DEFAULT NULL,
  `quantity23` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`house`,`item`,`bonusset`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemHistoryMonthly`
--

CREATE TABLE IF NOT EXISTS `tblItemHistoryMonthly` (
  `item` mediumint(8) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `month` tinyint(3) unsigned NOT NULL,
  `mktslvr01` int(10) unsigned DEFAULT NULL,
  `qty01` smallint(5) unsigned DEFAULT NULL,
  `mktslvr02` int(10) unsigned DEFAULT NULL,
  `qty02` smallint(5) unsigned DEFAULT NULL,
  `mktslvr03` int(10) unsigned DEFAULT NULL,
  `qty03` smallint(5) unsigned DEFAULT NULL,
  `mktslvr04` int(10) unsigned DEFAULT NULL,
  `qty04` smallint(5) unsigned DEFAULT NULL,
  `mktslvr05` int(10) unsigned DEFAULT NULL,
  `qty05` smallint(5) unsigned DEFAULT NULL,
  `mktslvr06` int(10) unsigned DEFAULT NULL,
  `qty06` smallint(5) unsigned DEFAULT NULL,
  `mktslvr07` int(10) unsigned DEFAULT NULL,
  `qty07` smallint(5) unsigned DEFAULT NULL,
  `mktslvr08` int(10) unsigned DEFAULT NULL,
  `qty08` smallint(5) unsigned DEFAULT NULL,
  `mktslvr09` int(10) unsigned DEFAULT NULL,
  `qty09` smallint(5) unsigned DEFAULT NULL,
  `mktslvr10` int(10) unsigned DEFAULT NULL,
  `qty10` smallint(5) unsigned DEFAULT NULL,
  `mktslvr11` int(10) unsigned DEFAULT NULL,
  `qty11` smallint(5) unsigned DEFAULT NULL,
  `mktslvr12` int(10) unsigned DEFAULT NULL,
  `qty12` smallint(5) unsigned DEFAULT NULL,
  `mktslvr13` int(10) unsigned DEFAULT NULL,
  `qty13` smallint(5) unsigned DEFAULT NULL,
  `mktslvr14` int(10) unsigned DEFAULT NULL,
  `qty14` smallint(5) unsigned DEFAULT NULL,
  `mktslvr15` int(10) unsigned DEFAULT NULL,
  `qty15` smallint(5) unsigned DEFAULT NULL,
  `mktslvr16` int(10) unsigned DEFAULT NULL,
  `qty16` smallint(5) unsigned DEFAULT NULL,
  `mktslvr17` int(10) unsigned DEFAULT NULL,
  `qty17` smallint(5) unsigned DEFAULT NULL,
  `mktslvr18` int(10) unsigned DEFAULT NULL,
  `qty18` smallint(5) unsigned DEFAULT NULL,
  `mktslvr19` int(10) unsigned DEFAULT NULL,
  `qty19` smallint(5) unsigned DEFAULT NULL,
  `mktslvr20` int(10) unsigned DEFAULT NULL,
  `qty20` smallint(5) unsigned DEFAULT NULL,
  `mktslvr21` int(10) unsigned DEFAULT NULL,
  `qty21` smallint(5) unsigned DEFAULT NULL,
  `mktslvr22` int(10) unsigned DEFAULT NULL,
  `qty22` smallint(5) unsigned DEFAULT NULL,
  `mktslvr23` int(10) unsigned DEFAULT NULL,
  `qty23` smallint(5) unsigned DEFAULT NULL,
  `mktslvr24` int(10) unsigned DEFAULT NULL,
  `qty24` smallint(5) unsigned DEFAULT NULL,
  `mktslvr25` int(10) unsigned DEFAULT NULL,
  `qty25` smallint(5) unsigned DEFAULT NULL,
  `mktslvr26` int(10) unsigned DEFAULT NULL,
  `qty26` smallint(5) unsigned DEFAULT NULL,
  `mktslvr27` int(10) unsigned DEFAULT NULL,
  `qty27` smallint(5) unsigned DEFAULT NULL,
  `mktslvr28` int(10) unsigned DEFAULT NULL,
  `qty28` smallint(5) unsigned DEFAULT NULL,
  `mktslvr29` int(10) unsigned DEFAULT NULL,
  `qty29` smallint(5) unsigned DEFAULT NULL,
  `mktslvr30` int(10) unsigned DEFAULT NULL,
  `qty30` smallint(5) unsigned DEFAULT NULL,
  `mktslvr31` int(10) unsigned DEFAULT NULL,
  `qty31` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`item`,`house`,`bonusset`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemSummary`
--

CREATE TABLE IF NOT EXISTS `tblItemSummary` (
  `house` smallint(5) unsigned NOT NULL,
  `item` mediumint(9) NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `age` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`house`,`item`,`bonusset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPaypalTransactions`
--

CREATE TABLE IF NOT EXISTS `tblPaypalTransactions` (
  `test_ipn` tinyint(1) NOT NULL DEFAULT '0',
  `txn_id` char(30) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `txn_type` char(50) COLLATE utf8_unicode_ci NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `parent_txn_id` char(30) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `mc_currency` char(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mc_fee` decimal(6,2) DEFAULT NULL,
  `mc_gross` decimal(6,2) DEFAULT NULL,
  `payment_status` char(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user` mediumint(8) unsigned DEFAULT NULL,
  `pending_reason` char(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `reason_code` char(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`test_ipn`,`txn_id`),
  KEY `txn_type` (`txn_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPet`
--

CREATE TABLE IF NOT EXISTS `tblPet` (
  `id` smallint(5) unsigned NOT NULL,
  `name` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `json` text COLLATE utf8_unicode_ci,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `type` tinyint(3) unsigned NOT NULL,
  `icon` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `npc` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPetHistory`
--

CREATE TABLE IF NOT EXISTS `tblPetHistory` (
  `house` smallint(5) unsigned NOT NULL,
  `species` smallint(5) unsigned NOT NULL,
  `breed` tinyint(3) unsigned NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`house`,`species`,`breed`,`snapshot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPetSummary`
--

CREATE TABLE IF NOT EXISTS `tblPetSummary` (
  `house` smallint(5) unsigned NOT NULL,
  `species` smallint(5) unsigned NOT NULL,
  `breed` tinyint(3) unsigned NOT NULL,
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`house`,`species`,`breed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblRealm`
--

CREATE TABLE IF NOT EXISTS `tblRealm` (
  `id` smallint(5) unsigned NOT NULL,
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `locale` char(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `house` smallint(5) unsigned DEFAULT NULL,
  `canonical` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ownerrealm` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `population` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `realmset` (`region`,`slug`),
  UNIQUE KEY `region` (`region`,`name`),
  KEY `house` (`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblRealmGuidHouse`
--

CREATE TABLE IF NOT EXISTS `tblRealmGuidHouse` (
  `realmguid` smallint(5) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`realmguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSeller`
--

CREATE TABLE IF NOT EXISTS `tblSeller` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `realm` smallint(5) unsigned NOT NULL,
  `name` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `realmname` (`realm`,`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=33485149 ;

-- --------------------------------------------------------

--
-- Table structure for table `tblSellerHistory`
--

CREATE TABLE IF NOT EXISTS `tblSellerHistory` (
  `seller` int(10) unsigned NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `new` smallint(5) unsigned NOT NULL DEFAULT '0',
  `total` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`snapshot`,`seller`),
  KEY `seller` (`seller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSellerItemHistory`
--

CREATE TABLE IF NOT EXISTS `tblSellerItemHistory` (
  `item` mediumint(8) unsigned NOT NULL,
  `seller` int(10) unsigned NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `house` smallint(5) unsigned NOT NULL DEFAULT '0',
  `auctions` smallint(5) unsigned NOT NULL,
  `quantity` mediumint(8) unsigned NOT NULL,
  PRIMARY KEY (`item`,`seller`,`snapshot`),
  KEY `snapshot` (`snapshot`),
  KEY `seller` (`seller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSnapshot`
--

CREATE TABLE IF NOT EXISTS `tblSnapshot` (
  `house` smallint(5) unsigned NOT NULL,
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `maxid` int(10) unsigned DEFAULT NULL,
  `flags` set('NoHistory') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`house`,`updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUser`
--

CREATE TABLE IF NOT EXISTS `tblUser` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `locale` char(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `acceptedterms` timestamp NULL DEFAULT NULL,
  `paiduntil` timestamp NULL DEFAULT NULL,
  `email` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `emailset` timestamp NULL DEFAULT NULL,
  `emailverification` varchar(12) COLLATE utf8_unicode_ci DEFAULT NULL,
  `watchsequence` int(10) unsigned NOT NULL DEFAULT '0',
  `watchperiod` smallint(5) unsigned NOT NULL DEFAULT '715',
  `watchesobserved` timestamp NULL DEFAULT NULL,
  `watchesreported` timestamp NULL DEFAULT NULL,
  `rss` varchar(24) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=21113 ;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserAuth`
--

CREATE TABLE IF NOT EXISTS `tblUserAuth` (
  `provider` enum('Battle.net') COLLATE utf8_unicode_ci NOT NULL,
  `providerid` bigint(20) unsigned NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`provider`,`providerid`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserMessages`
--

CREATE TABLE IF NOT EXISTS `tblUserMessages` (
  `user` mediumint(8) unsigned NOT NULL,
  `seq` mediumint(8) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserRare`
--

CREATE TABLE IF NOT EXISTS `tblUserRare` (
  `user` mediumint(8) unsigned NOT NULL,
  `seq` tinyint(3) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `itemclass` tinyint(3) unsigned NOT NULL,
  `minquality` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `minlevel` smallint(5) unsigned DEFAULT NULL,
  `maxlevel` smallint(5) unsigned DEFAULT NULL,
  `flags` set('includecrafted','includevendor') COLLATE utf8_unicode_ci NOT NULL,
  `days` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`user`,`seq`),
  KEY `house` (`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserRareReport`
--

CREATE TABLE IF NOT EXISTS `tblUserRareReport` (
  `user` mediumint(8) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL,
  `prevseen` timestamp NULL DEFAULT NULL,
  `price` decimal(11,0) NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user`,`house`,`item`,`bonusset`),
  KEY `house` (`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserSession`
--

CREATE TABLE IF NOT EXISTS `tblUserSession` (
  `session` binary(18) NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `useragent` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`session`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserWatch`
--

CREATE TABLE IF NOT EXISTS `tblUserWatch` (
  `user` mediumint(8) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `region` enum('US','EU') COLLATE utf8_unicode_ci DEFAULT NULL,
  `house` smallint(5) unsigned DEFAULT NULL,
  `item` mediumint(8) unsigned DEFAULT NULL,
  `bonusset` tinyint(3) unsigned DEFAULT NULL,
  `species` smallint(5) unsigned DEFAULT NULL,
  `breed` tinyint(3) unsigned DEFAULT NULL,
  `direction` enum('Under','Over') COLLATE utf8_unicode_ci NOT NULL,
  `quantity` mediumint(8) unsigned DEFAULT NULL,
  `price` decimal(11,0) unsigned DEFAULT NULL,
  `currently` decimal(11,0) unsigned DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `observed` timestamp NULL DEFAULT NULL,
  `reported` timestamp NULL DEFAULT NULL,
  `deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user`,`seq`),
  KEY `house` (`house`),
  KEY `item` (`item`),
  KEY `region` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblWowToken`
--

CREATE TABLE IF NOT EXISTS `tblWowToken` (
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `when` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `marketgold` mediumint(8) unsigned DEFAULT NULL,
  `timeleft` enum('Short','Medium','Long','Very Long') COLLATE utf8_unicode_ci DEFAULT NULL,
  `timeleftraw` int(10) unsigned DEFAULT NULL,
  `result` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`region`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblWowTokenEvents`
--

CREATE TABLE IF NOT EXISTS `tblWowTokenEvents` (
  `subid` int(10) unsigned NOT NULL,
  `region` enum('NA','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `direction` enum('over','under') COLLATE utf8_unicode_ci NOT NULL,
  `value` mediumint(8) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lasttrigger` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`subid`,`region`,`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblWowTokenSubs`
--

CREATE TABLE IF NOT EXISTS `tblWowTokenSubs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint` varbinary(400) NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastpush` timestamp NULL DEFAULT NULL,
  `lastfail` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subdex` (`endpoint`(20))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=673 ;

-- --------------------------------------------------------

--
-- Table structure for table `ttblRareStageTemplate`
--

CREATE TABLE IF NOT EXISTS `ttblRareStageTemplate` (
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL,
  `price` decimal(11,0) NOT NULL,
  `lastseen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`item`,`bonusset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblAuctionExtra`
--
ALTER TABLE `tblAuctionExtra`
  ADD CONSTRAINT `tblAuctionExtra_ibfk_1` FOREIGN KEY (`house`, `id`) REFERENCES `tblAuction` (`house`, `id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblAuctionPet`
--
ALTER TABLE `tblAuctionPet`
  ADD CONSTRAINT `tblAuctionPet_ibfk_1` FOREIGN KEY (`house`, `id`) REFERENCES `tblAuction` (`house`, `id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblAuctionRare`
--
ALTER TABLE `tblAuctionRare`
  ADD CONSTRAINT `tblAuctionRare_ibfk_1` FOREIGN KEY (`house`, `id`) REFERENCES `tblAuction` (`house`, `id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblUserAuth`
--
ALTER TABLE `tblUserAuth`
  ADD CONSTRAINT `tblUserAuth_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblUserMessages`
--
ALTER TABLE `tblUserMessages`
  ADD CONSTRAINT `tblUserMessages_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblUserRare`
--
ALTER TABLE `tblUserRare`
  ADD CONSTRAINT `tblUserRare_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblUserRareReport`
--
ALTER TABLE `tblUserRareReport`
  ADD CONSTRAINT `tblUserRareReport_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblUserSession`
--
ALTER TABLE `tblUserSession`
  ADD CONSTRAINT `tblUserSession_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblUserWatch`
--
ALTER TABLE `tblUserWatch`
  ADD CONSTRAINT `tblUserWatch_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

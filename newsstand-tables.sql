-- phpMyAdmin SQL Dump
-- version 4.2.7.1
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Apr 30, 2016 at 06:36 PM
-- Server version: 5.5.47
-- PHP Version: 5.5.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

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
  `timeleft` enum('SHORT','MEDIUM','LONG','VERY_LONG') COLLATE utf8_unicode_ci NOT NULL
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
  `bonus6` smallint(5) unsigned DEFAULT NULL
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
  `quality` tinyint(3) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblAuctionRare`
--

CREATE TABLE IF NOT EXISTS `tblAuctionRare` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `prevseen` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblBonusSet`
--

CREATE TABLE IF NOT EXISTS `tblBonusSet` (
  `set` tinyint(3) unsigned NOT NULL,
  `bonus` smallint(5) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblCaptcha`
--

CREATE TABLE IF NOT EXISTS `tblCaptcha` (
  `id` int(10) unsigned NOT NULL,
  `race` tinyint(3) unsigned NOT NULL,
  `gender` tinyint(3) unsigned NOT NULL,
  `helm` tinyint(3) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCEnchants`
--

CREATE TABLE IF NOT EXISTS `tblDBCEnchants` (
  `id` mediumint(8) unsigned NOT NULL,
  `effect` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `gem` mediumint(8) unsigned DEFAULT NULL
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
  `flags` set('pvp','notransmog') COLLATE utf8_unicode_ci NOT NULL
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
  `flags` set('setmember') COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemRandomSuffix`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemRandomSuffix` (
  `locale` char(4) COLLATE utf8_unicode_ci NOT NULL,
  `suffix` varchar(120) COLLATE utf8_unicode_ci NOT NULL
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
  `fortooltip` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemSpell`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemSpell` (
  `item` mediumint(8) unsigned NOT NULL,
  `spell` mediumint(8) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemSubClass`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemSubClass` (
  `class` smallint(6) NOT NULL,
  `id` smallint(6) NOT NULL,
  `name` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `fullname` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemVendorCost`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemVendorCost` (
  `item` mediumint(8) unsigned NOT NULL,
  `copper` int(10) unsigned DEFAULT NULL,
  `npc` mediumint(8) unsigned DEFAULT NULL,
  `npccount` smallint(5) unsigned DEFAULT NULL
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
  `speed` smallint(6) DEFAULT NULL
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
  `name_ruru` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCSkillLines`
--

CREATE TABLE IF NOT EXISTS `tblDBCSkillLines` (
  `id` smallint(5) unsigned NOT NULL,
  `name` char(50) COLLATE utf8_unicode_ci NOT NULL
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
  `expansion` tinyint(3) unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblEmailBlocked`
--

CREATE TABLE IF NOT EXISTS `tblEmailBlocked` (
  `address` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblEmailLog`
--

CREATE TABLE IF NOT EXISTS `tblEmailLog` (
  `sha1id` binary(20) NOT NULL,
  `sent` datetime NOT NULL,
  `recipient` varchar(255) COLLATE utf8_unicode_ci NOT NULL
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
  `lastchecksuccessresult` text COLLATE utf8_unicode_ci
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
  `expired` mediumint(8) unsigned NOT NULL DEFAULT '0'
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
  `stddev` decimal(11,0) unsigned NOT NULL
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
  `stddev` decimal(11,0) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemHistory`
--

CREATE TABLE IF NOT EXISTS `tblItemHistory` (
  `house` smallint(5) unsigned NOT NULL,
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `age` tinyint(3) unsigned NOT NULL DEFAULT '0'
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
  `quantitymin` smallint(5) unsigned NOT NULL,
  `quantityavg` smallint(5) unsigned NOT NULL,
  `quantitymax` smallint(5) unsigned NOT NULL,
  `presence` tinyint(3) unsigned NOT NULL
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
  `qty31` smallint(5) unsigned DEFAULT NULL
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
  `age` tinyint(3) unsigned NOT NULL DEFAULT '0'
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
  `reason_code` char(50) COLLATE utf8_unicode_ci DEFAULT NULL
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
  `npc` int(10) unsigned DEFAULT NULL
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
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0'
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
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblRealm`
--

CREATE TABLE IF NOT EXISTS `tblRealm` (
  `id` smallint(5) unsigned NOT NULL,
  `region` enum('US','EU') COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `locale` char(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `house` smallint(5) unsigned DEFAULT NULL,
  `canonical` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ownerrealm` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `population` mediumint(8) unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblRealmGuidHouse`
--

CREATE TABLE IF NOT EXISTS `tblRealmGuidHouse` (
  `realmguid` smallint(5) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSeller`
--

CREATE TABLE IF NOT EXISTS `tblSeller` (
  `id` int(10) unsigned NOT NULL,
  `realm` smallint(5) unsigned NOT NULL,
  `name` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSellerHistory`
--

CREATE TABLE IF NOT EXISTS `tblSellerHistory` (
  `seller` int(10) unsigned NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `new` smallint(5) unsigned NOT NULL DEFAULT '0',
  `total` smallint(5) unsigned NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSellerItemHistory`
--

CREATE TABLE IF NOT EXISTS `tblSellerItemHistory` (
  `seller` int(10) unsigned NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `item` mediumint(8) unsigned NOT NULL,
  `auctions` smallint(5) unsigned NOT NULL,
  `quantity` mediumint(8) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSnapshot`
--

CREATE TABLE IF NOT EXISTS `tblSnapshot` (
  `house` smallint(5) unsigned NOT NULL,
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `maxid` int(10) unsigned DEFAULT NULL,
  `flags` set('NoHistory') COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUser`
--

CREATE TABLE IF NOT EXISTS `tblUser` (
  `id` mediumint(8) unsigned NOT NULL,
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
  `watchesreported` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblUserAuth`
--

CREATE TABLE IF NOT EXISTS `tblUserAuth` (
  `provider` enum('Battle.net') COLLATE utf8_unicode_ci NOT NULL,
  `providerid` bigint(20) unsigned NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
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
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL
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
  `days` smallint(5) unsigned NOT NULL
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
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
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
  `useragent` varchar(250) COLLATE utf8_unicode_ci NOT NULL
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
  `deleted` timestamp NULL DEFAULT NULL
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
  `result` tinyint(4) DEFAULT NULL
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
  `lasttrigger` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblWowTokenSubs`
--

CREATE TABLE IF NOT EXISTS `tblWowTokenSubs` (
  `id` int(10) unsigned NOT NULL,
  `endpoint` varbinary(400) NOT NULL,
  `firstseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastpush` timestamp NULL DEFAULT NULL,
  `lastfail` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ttblRareStageTemplate`
--

CREATE TABLE IF NOT EXISTS `ttblRareStageTemplate` (
  `item` mediumint(8) unsigned NOT NULL,
  `bonusset` tinyint(3) unsigned NOT NULL,
  `price` decimal(11,0) NOT NULL,
  `lastseen` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblAuction`
--
ALTER TABLE `tblAuction`
ADD PRIMARY KEY (`house`,`id`), ADD KEY `item` (`item`), ADD KEY `seller` (`seller`);

--
-- Indexes for table `tblAuctionExtra`
--
ALTER TABLE `tblAuctionExtra`
ADD PRIMARY KEY (`house`,`id`);

--
-- Indexes for table `tblAuctionPet`
--
ALTER TABLE `tblAuctionPet`
ADD PRIMARY KEY (`house`,`id`);

--
-- Indexes for table `tblAuctionRare`
--
ALTER TABLE `tblAuctionRare`
ADD PRIMARY KEY (`house`,`id`);

--
-- Indexes for table `tblBonusSet`
--
ALTER TABLE `tblBonusSet`
ADD PRIMARY KEY (`set`,`bonus`);

--
-- Indexes for table `tblCaptcha`
--
ALTER TABLE `tblCaptcha`
ADD PRIMARY KEY (`id`), ADD KEY `race` (`race`);

--
-- Indexes for table `tblDBCEnchants`
--
ALTER TABLE `tblDBCEnchants`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblDBCItem`
--
ALTER TABLE `tblDBCItem`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblDBCItemBonus`
--
ALTER TABLE `tblDBCItemBonus`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblDBCItemRandomSuffix`
--
ALTER TABLE `tblDBCItemRandomSuffix`
ADD PRIMARY KEY (`locale`,`suffix`);

--
-- Indexes for table `tblDBCItemReagents`
--
ALTER TABLE `tblDBCItemReagents`
ADD KEY `itemid` (`item`), ADD KEY `reagentid` (`reagent`), ADD KEY `skillid` (`skillline`), ADD KEY `spell` (`spell`);

--
-- Indexes for table `tblDBCItemSpell`
--
ALTER TABLE `tblDBCItemSpell`
ADD PRIMARY KEY (`item`,`spell`);

--
-- Indexes for table `tblDBCItemSubClass`
--
ALTER TABLE `tblDBCItemSubClass`
ADD PRIMARY KEY (`class`,`id`);

--
-- Indexes for table `tblDBCItemVendorCost`
--
ALTER TABLE `tblDBCItemVendorCost`
ADD PRIMARY KEY (`item`);

--
-- Indexes for table `tblDBCPet`
--
ALTER TABLE `tblDBCPet`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblDBCRandEnchants`
--
ALTER TABLE `tblDBCRandEnchants`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblDBCSkillLines`
--
ALTER TABLE `tblDBCSkillLines`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblDBCSpell`
--
ALTER TABLE `tblDBCSpell`
ADD PRIMARY KEY (`id`), ADD KEY `crafteditem` (`crafteditem`), ADD KEY `skilllineid` (`skillline`);

--
-- Indexes for table `tblEmailBlocked`
--
ALTER TABLE `tblEmailBlocked`
ADD PRIMARY KEY (`address`);

--
-- Indexes for table `tblEmailLog`
--
ALTER TABLE `tblEmailLog`
ADD PRIMARY KEY (`sha1id`);

--
-- Indexes for table `tblHouseCheck`
--
ALTER TABLE `tblHouseCheck`
ADD PRIMARY KEY (`house`);

--
-- Indexes for table `tblItemExpired`
--
ALTER TABLE `tblItemExpired`
ADD PRIMARY KEY (`item`,`bonusset`,`house`,`when`), ADD KEY `house` (`house`,`when`);

--
-- Indexes for table `tblItemGlobal`
--
ALTER TABLE `tblItemGlobal`
ADD PRIMARY KEY (`item`,`bonusset`);

--
-- Indexes for table `tblItemGlobalWorking`
--
ALTER TABLE `tblItemGlobalWorking`
ADD PRIMARY KEY (`when`,`item`,`bonusset`);

--
-- Indexes for table `tblItemHistory`
--
ALTER TABLE `tblItemHistory`
ADD PRIMARY KEY (`house`,`item`,`bonusset`,`snapshot`);

--
-- Indexes for table `tblItemHistoryDaily`
--
ALTER TABLE `tblItemHistoryDaily`
ADD PRIMARY KEY (`item`,`house`,`when`);

--
-- Indexes for table `tblItemHistoryMonthly`
--
ALTER TABLE `tblItemHistoryMonthly`
ADD PRIMARY KEY (`item`,`house`,`bonusset`,`month`);

--
-- Indexes for table `tblItemSummary`
--
ALTER TABLE `tblItemSummary`
ADD PRIMARY KEY (`house`,`item`,`bonusset`);

--
-- Indexes for table `tblPaypalTransactions`
--
ALTER TABLE `tblPaypalTransactions`
ADD PRIMARY KEY (`test_ipn`,`txn_id`), ADD KEY `txn_type` (`txn_type`);

--
-- Indexes for table `tblPet`
--
ALTER TABLE `tblPet`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblPetHistory`
--
ALTER TABLE `tblPetHistory`
ADD PRIMARY KEY (`house`,`species`,`breed`,`snapshot`);

--
-- Indexes for table `tblPetSummary`
--
ALTER TABLE `tblPetSummary`
ADD PRIMARY KEY (`house`,`species`,`breed`);

--
-- Indexes for table `tblRealm`
--
ALTER TABLE `tblRealm`
ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `realmset` (`region`,`slug`), ADD UNIQUE KEY `region` (`region`,`name`), ADD KEY `house` (`house`);

--
-- Indexes for table `tblRealmGuidHouse`
--
ALTER TABLE `tblRealmGuidHouse`
ADD PRIMARY KEY (`realmguid`);

--
-- Indexes for table `tblSeller`
--
ALTER TABLE `tblSeller`
ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `realmname` (`realm`,`name`);

--
-- Indexes for table `tblSellerHistory`
--
ALTER TABLE `tblSellerHistory`
ADD PRIMARY KEY (`snapshot`,`seller`), ADD KEY `seller` (`seller`);

--
-- Indexes for table `tblSellerItemHistory`
--
ALTER TABLE `tblSellerItemHistory`
ADD PRIMARY KEY (`seller`,`snapshot`,`item`), ADD KEY `snapshot` (`snapshot`), ADD KEY `item` (`item`);

--
-- Indexes for table `tblSnapshot`
--
ALTER TABLE `tblSnapshot`
ADD PRIMARY KEY (`house`,`updated`);

--
-- Indexes for table `tblUser`
--
ALTER TABLE `tblUser`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblUserAuth`
--
ALTER TABLE `tblUserAuth`
ADD PRIMARY KEY (`provider`,`providerid`), ADD KEY `user` (`user`);

--
-- Indexes for table `tblUserMessages`
--
ALTER TABLE `tblUserMessages`
ADD PRIMARY KEY (`user`,`seq`);

--
-- Indexes for table `tblUserRare`
--
ALTER TABLE `tblUserRare`
ADD PRIMARY KEY (`user`,`seq`), ADD KEY `house` (`house`);

--
-- Indexes for table `tblUserRareReport`
--
ALTER TABLE `tblUserRareReport`
ADD PRIMARY KEY (`user`,`house`,`item`,`bonusset`), ADD KEY `house` (`house`);

--
-- Indexes for table `tblUserSession`
--
ALTER TABLE `tblUserSession`
ADD PRIMARY KEY (`session`), ADD KEY `user` (`user`);

--
-- Indexes for table `tblUserWatch`
--
ALTER TABLE `tblUserWatch`
ADD PRIMARY KEY (`user`,`seq`), ADD KEY `house` (`house`), ADD KEY `item` (`item`), ADD KEY `region` (`region`);

--
-- Indexes for table `tblWowToken`
--
ALTER TABLE `tblWowToken`
ADD PRIMARY KEY (`region`,`when`);

--
-- Indexes for table `tblWowTokenEvents`
--
ALTER TABLE `tblWowTokenEvents`
ADD PRIMARY KEY (`subid`,`region`,`direction`);

--
-- Indexes for table `tblWowTokenSubs`
--
ALTER TABLE `tblWowTokenSubs`
ADD PRIMARY KEY (`id`), ADD KEY `subdex` (`endpoint`(20));

--
-- Indexes for table `ttblRareStageTemplate`
--
ALTER TABLE `ttblRareStageTemplate`
ADD PRIMARY KEY (`item`,`bonusset`);

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

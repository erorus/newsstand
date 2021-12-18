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

-- --------------------------------------------------------

--
-- Table structure for table `tblAuction`
--

CREATE TABLE IF NOT EXISTS `tblAuction` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `item` mediumint(8) unsigned NOT NULL,
  `quantity` smallint(5) unsigned NOT NULL,
  `bid` bigint(20) NOT NULL,
  `buy` bigint(20) NOT NULL,
  `timeleft` enum('SHORT','MEDIUM','LONG','VERY_LONG') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`house`,`id`),
  KEY `item` (`item`,`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblAuctionBonus`
--

CREATE TABLE IF NOT EXISTS `tblAuctionBonus` (
  `house` smallint(5) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL,
  `bonus` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`house`, `id`, `bonus`)
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
  `lootedlevel` tinyint(3) unsigned DEFAULT NULL,
  `level` smallint(5) unsigned DEFAULT NULL,
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
-- Table structure for table `tblBitPayTransactions`
--

CREATE TABLE IF NOT EXISTS `tblBitPayTransactions` (
  `id` char(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user` mediumint(8) unsigned DEFAULT NULL,
  `subextended` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `price` decimal(6,2) NOT NULL,
  `currency` char(3) COLLATE utf8_unicode_ci NOT NULL,
  `rate` decimal(6,2) NOT NULL,
  `btcprice` decimal(16,8) NOT NULL,
  `btcpaid` decimal(16,8) NOT NULL,
  `btcdue` decimal(16,8) NOT NULL,
  `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `exception` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `url` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `posdata` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `invoiced` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `expired` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblBuilding`
--

CREATE TABLE IF NOT EXISTS `tblBuilding` (
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `when` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `id` tinyint(3) UNSIGNED NOT NULL,
  `state` tinyint(3) UNSIGNED NOT NULL,
  `next` timestamp NULL DEFAULT NULL,
  `contributed` double DEFAULT NULL,
  `buff1` mediumint(8) UNSIGNED DEFAULT NULL,
  `buff2` mediumint(8) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`region`,`when`,`id`) USING BTREE
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
-- Table structure for table `tblDBCCurvePoint`
--

CREATE TABLE IF NOT EXISTS `tblDBCCurvePoint` (
  `curve` smallint(5) unsigned NOT NULL,
  `step` tinyint(3) unsigned NOT NULL,
  `key` float NOT NULL,
  `value` float NOT NULL,
  PRIMARY KEY (`curve`,`step`)
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
  `name_kokr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_zhtw` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
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
  `requiredside` enum('Alliance', 'Horde', '') NOT NULL DEFAULT '',
  `display` mediumint(8) unsigned DEFAULT NULL,
  `othersideitem` mediumint(8) unsigned DEFAULT NULL,
  `flags` set('pvp','notransmog','sniffed') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `display` (`display`),
  KEY `class` (`class`, `quality`),
  KEY `otherside` (`othersideitem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemBonus`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemBonus` (
  `id` smallint(5) unsigned NOT NULL,
  `quality` tinyint(3) unsigned DEFAULT NULL,
  `level` smallint(6) DEFAULT NULL,
  `previewlevel` smallint(5) unsigned DEFAULT NULL,
  `levelcurve` smallint(5) unsigned DEFAULT NULL,
  `levelcurvepriority` int(10) unsigned DEFAULT NULL,
  `requiredlevel` tinyint(3) unsigned DEFAULT NULL,
  `tagid` mediumint(8) unsigned DEFAULT NULL,
  `tagpriority` tinyint(3) unsigned DEFAULT NULL,
  `nameid` mediumint(8) unsigned DEFAULT NULL,
  `namepriority` tinyint(3) unsigned DEFAULT NULL,
  `socketmask` bit(32) DEFAULT NULL,
  `statmask` set('speed','leech','avoidance','indestructible','corruption') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCItemNameDescription`
--

CREATE TABLE IF NOT EXISTS `tblDBCItemNameDescription` (
  `id` mediumint(5) unsigned NOT NULL,
  `desc_enus` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_dede` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_eses` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_frfr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_itit` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_ptbr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_ruru` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_kokr` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `desc_zhtw` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
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
  `name_kokr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_zhtw` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
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
  `name_kokr` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_zhtw` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
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
  `name_kokr` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_zhtw` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCSpell`
--

CREATE TABLE IF NOT EXISTS `tblDBCSpell` (
  `id` mediumint(8) unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `cooldown` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `skillline` smallint(5) unsigned DEFAULT NULL,
  `qtymade` decimal(7,2) unsigned NOT NULL DEFAULT '0.00',
  `tradeskillcategory` smallint(5) unsigned DEFAULT NULL,
  `replacesspell` mediumint(8) unsigned DEFAULT NULL,
  `expansion` tinyint(3) unsigned DEFAULT NULL,
  `requiredside` enum('Alliance', 'Horde', '') NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `skilllineid` (`skillline`),
  KEY `replaces` (`replacesspell`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCSpellCrafts`
--

CREATE TABLE IF NOT EXISTS `tblDBCSpellCrafts` (
  `spell` MEDIUMINT(8) UNSIGNED NOT NULL,
  `item` MEDIUMINT(8) UNSIGNED NOT NULL,
  PRIMARY KEY (`spell`, `item`),
  KEY `item` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCSpellReagents`
--

CREATE TABLE IF NOT EXISTS `tblDBCSpellReagents` (
  `spell` MEDIUMINT(8) UNSIGNED NOT NULL,
  `item` MEDIUMINT(8) UNSIGNED NOT NULL,
  `qty` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`spell`, `item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblDBCTradeSkillCategory`
--

CREATE TABLE IF NOT EXISTS `tblDBCTradeSkillCategory` (
  `id` SMALLINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `parent` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `skillline` SMALLINT UNSIGNED NOT NULL,
  `order` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`)
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
  `lastchecksuccess` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemBonusesSeen`
--

CREATE TABLE IF NOT EXISTS `tblItemBonusesSeen` (
  `item` mediumint(8) unsigned NOT NULL,
  `bonus1` smallint(5) unsigned NOT NULL DEFAULT '0',
  `bonus2` smallint(5) unsigned NOT NULL DEFAULT '0',
  `bonus3` smallint(5) unsigned NOT NULL DEFAULT '0',
  `bonus4` smallint(5) unsigned NOT NULL DEFAULT '0',
  `observed` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`item`,`bonus1`,`bonus2`,`bonus3`,`bonus4`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemExpired`
--

CREATE TABLE IF NOT EXISTS `tblItemExpired` (
  `item` mediumint(8) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `when` date NOT NULL,
  `created` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `expired` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`house`,`item`,`level`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemGlobal`
--

CREATE TABLE IF NOT EXISTS `tblItemGlobal` (
  `item` mediumint(8) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL DEFAULT '0',
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `median` decimal(11,0) unsigned NOT NULL,
  `mean` decimal(11,0) unsigned NOT NULL,
  `stddev` decimal(11,0) unsigned NOT NULL,
  PRIMARY KEY (`item`,`level`,`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemGlobalWorking`
--

CREATE TABLE IF NOT EXISTS `tblItemGlobalWorking` (
  `when` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `item` mediumint(8) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL DEFAULT '0',
  `median` decimal(11,0) unsigned NOT NULL,
  `mean` decimal(11,0) unsigned NOT NULL,
  `stddev` decimal(11,0) unsigned NOT NULL,
  PRIMARY KEY (`when`,`region`,`item`,`level`)
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
  `level` smallint(5) unsigned NOT NULL,
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
  PRIMARY KEY (`house`,`item`,`level`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
PARTITION BY HASH (to_days(`when`)) PARTITIONS 16;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemHistoryMonthly`
--

CREATE TABLE IF NOT EXISTS `tblItemHistoryMonthly` (
  `item` mediumint(8) unsigned NOT NULL,
  `house` smallint(5) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL DEFAULT '0',
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
  PRIMARY KEY (`item`,`house`,`level`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
PARTITION BY HASH (`month`) PARTITIONS 12;

-- --------------------------------------------------------

--
-- Table structure for table `tblItemSummary`
--

CREATE TABLE IF NOT EXISTS `tblItemSummary` (
  `house` smallint(5) unsigned NOT NULL,
  `item` mediumint(9) NOT NULL,
  `level` smallint(5) unsigned NOT NULL DEFAULT '0',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `age` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`house`,`item`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPatreonLog`
--

CREATE TABLE `tblPatreonLog` (
  `logged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `patreonUser` bigint(20) unsigned NOT NULL,
  `cents` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`logged`,`patreonUser`),
  KEY `user` (`patreonUser`)
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
-- Table structure for table `tblPetGlobal`
--

CREATE TABLE IF NOT EXISTS `tblPetGlobal` (
  `species` smallint(5) unsigned NOT NULL,
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `median` decimal(11,0) unsigned NOT NULL,
  `mean` decimal(11,0) unsigned NOT NULL,
  `stddev` decimal(11,0) unsigned NOT NULL,
  PRIMARY KEY (`species`,`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPetGlobalWorking`
--

CREATE TABLE IF NOT EXISTS `tblPetGlobalWorking` (
  `when` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL,
  `species` smallint(5) unsigned NOT NULL,
  `median` decimal(11,0) unsigned NOT NULL,
  `mean` decimal(11,0) unsigned NOT NULL,
  `stddev` decimal(11,0) unsigned NOT NULL,
  PRIMARY KEY (`when`,`region`,`species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPetHistoryHourly`
--

CREATE TABLE IF NOT EXISTS `tblPetHistoryHourly` (
  `house` smallint(5) unsigned NOT NULL,
  `species` smallint(5) unsigned NOT NULL,
  `when` date NOT NULL,
  `silver00` int(10) unsigned DEFAULT NULL,
  `quantity00` smallint(5) unsigned DEFAULT NULL,
  `silver01` int(10) unsigned DEFAULT NULL,
  `quantity01` smallint(5) unsigned DEFAULT NULL,
  `silver02` int(10) unsigned DEFAULT NULL,
  `quantity02` smallint(5) unsigned DEFAULT NULL,
  `silver03` int(10) unsigned DEFAULT NULL,
  `quantity03` smallint(5) unsigned DEFAULT NULL,
  `silver04` int(10) unsigned DEFAULT NULL,
  `quantity04` smallint(5) unsigned DEFAULT NULL,
  `silver05` int(10) unsigned DEFAULT NULL,
  `quantity05` smallint(5) unsigned DEFAULT NULL,
  `silver06` int(10) unsigned DEFAULT NULL,
  `quantity06` smallint(5) unsigned DEFAULT NULL,
  `silver07` int(10) unsigned DEFAULT NULL,
  `quantity07` smallint(5) unsigned DEFAULT NULL,
  `silver08` int(10) unsigned DEFAULT NULL,
  `quantity08` smallint(5) unsigned DEFAULT NULL,
  `silver09` int(10) unsigned DEFAULT NULL,
  `quantity09` smallint(5) unsigned DEFAULT NULL,
  `silver10` int(10) unsigned DEFAULT NULL,
  `quantity10` smallint(5) unsigned DEFAULT NULL,
  `silver11` int(10) unsigned DEFAULT NULL,
  `quantity11` smallint(5) unsigned DEFAULT NULL,
  `silver12` int(10) unsigned DEFAULT NULL,
  `quantity12` smallint(5) unsigned DEFAULT NULL,
  `silver13` int(10) unsigned DEFAULT NULL,
  `quantity13` smallint(5) unsigned DEFAULT NULL,
  `silver14` int(10) unsigned DEFAULT NULL,
  `quantity14` smallint(5) unsigned DEFAULT NULL,
  `silver15` int(10) unsigned DEFAULT NULL,
  `quantity15` smallint(5) unsigned DEFAULT NULL,
  `silver16` int(10) unsigned DEFAULT NULL,
  `quantity16` smallint(5) unsigned DEFAULT NULL,
  `silver17` int(10) unsigned DEFAULT NULL,
  `quantity17` smallint(5) unsigned DEFAULT NULL,
  `silver18` int(10) unsigned DEFAULT NULL,
  `quantity18` smallint(5) unsigned DEFAULT NULL,
  `silver19` int(10) unsigned DEFAULT NULL,
  `quantity19` smallint(5) unsigned DEFAULT NULL,
  `silver20` int(10) unsigned DEFAULT NULL,
  `quantity20` smallint(5) unsigned DEFAULT NULL,
  `silver21` int(10) unsigned DEFAULT NULL,
  `quantity21` smallint(5) unsigned DEFAULT NULL,
  `silver22` int(10) unsigned DEFAULT NULL,
  `quantity22` smallint(5) unsigned DEFAULT NULL,
  `silver23` int(10) unsigned DEFAULT NULL,
  `quantity23` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`house`,`species`,`when`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPetSummary`
--

CREATE TABLE IF NOT EXISTS `tblPetSummary` (
  `house` smallint(5) unsigned NOT NULL,
  `species` smallint(5) unsigned NOT NULL,
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`house`,`species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPromoCode`
--

CREATE TABLE IF NOT EXISTS `tblPromoCode` (
  `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `maxuses` smallint(5) UNSIGNED NOT NULL DEFAULT '1',
  `addseconds` int(10) UNSIGNED NOT NULL DEFAULT '2592000',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblPromoCodeUsed`
--

CREATE TABLE `tblPromoCodeUsed` (
  `code` mediumint(8) UNSIGNED NOT NULL,
  `user` mediumint(8) UNSIGNED NOT NULL,
  `used` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`code`,`user`),
  CONSTRAINT `tblPromoCodeUsed_ibfk_1` FOREIGN KEY (`user`) REFERENCES `tblUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tblPromoCodeUsed_ibfk_2` FOREIGN KEY (`code`) REFERENCES `tblPromoCode` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
  `blizzId` int(10) unsigned DEFAULT NULL,
  `blizzConnection` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `realmset` (`region`,`slug`),
  UNIQUE KEY `region` (`region`,`name`),
  KEY `house` (`house`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblSnapshot`
--

CREATE TABLE IF NOT EXISTS `tblSnapshot` (
  `house` smallint(5) unsigned NOT NULL,
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `maxid` int(10) unsigned DEFAULT NULL,
  `flags` set('NoHistory') COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
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
  `provider` enum('Battle.net', 'Patreon') COLLATE utf8_unicode_ci NOT NULL,
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
  `level` smallint(5) unsigned NOT NULL,
  `prevseen` timestamp NULL DEFAULT NULL,
  `price` decimal(11,0) NOT NULL,
  `snapshot` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user`,`house`,`item`,`level`),
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
  `level` smallint(5) unsigned DEFAULT NULL,
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
  `level` smallint(5) unsigned NOT NULL,
  `price` decimal(11,0) NOT NULL,
  `lastseen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`item`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ttblItemSummaryTemplate`
--

CREATE TABLE IF NOT EXISTS `ttblItemSummaryTemplate` (
  `item` mediumint(9) NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblAuctionBonus`
--
ALTER TABLE `tblAuctionBonus`
  ADD CONSTRAINT `auc` FOREIGN KEY (`house`, `id`) REFERENCES `tblAuction` (`house`, `id`) ON DELETE CASCADE ON UPDATE CASCADE;

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

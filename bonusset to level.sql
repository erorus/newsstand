delete from tblAuction;
ALTER TABLE `tblAuctionExtra` DROP `bonusset`;
DROP TABLE tblBonusSet;
DROP TABLE tblItemLevelsSeen;

insert ignore into `tblItemBonusesSeen` (item, bonusset, bonus1, bonus2, bonus3, bonus4, observed)
(select s2.item, 0, s2.bonus1, s2.bonus2, s2.bonus3, s2.bonus4, s2.observed from tblItemBonusesSeen s2 WHERE s2.bonusset != 0);
delete from tblItemBonusesSeen where bonusset != 0;
ALTER TABLE `tblItemBonusesSeen` DROP `bonusset`;

CREATE TABLE `tblItemExpired_new` (
  `item` mediumint(8) UNSIGNED NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL,
  `house` smallint(5) UNSIGNED NOT NULL,
  `when` date NOT NULL,
  `created` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `expired` mediumint(8) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `tblItemExpired_new`
  ADD PRIMARY KEY (`house`,`item`,`level`,`when`);
  
insert into tblItemExpired_new
(select ie.item, ifnull(i.level, 0), ie.house, ie.when, ie.created, ie.expired
from tblItemExpired ie
left join tblDBCItem i on ie.item = i.id and i.class in (2,4)
where ie.bonusset = 0);

rename table tblItemExpired to tblItemExpired_old, tblItemExpired_new to tblItemExpired;  
drop table tblItemExpired_old;

CREATE TABLE `tblItemGlobal_new` (
  `item` mediumint(8) UNSIGNED NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'US',
  `median` decimal(11,0) UNSIGNED NOT NULL,
  `mean` decimal(11,0) UNSIGNED NOT NULL,
  `stddev` decimal(11,0) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `tblItemGlobal_new`
  ADD PRIMARY KEY (`item`,`level`,`region`);

insert into tblItemGlobal_new (
select ig.item, ifnull(i.level, 0), ig.region, ig.median, ig.mean, ig.stddev
from tblItemGlobal ig
left join tblDBCItem i on ig.item = i.id and i.class in (2,4)
where ig.bonusset = 0);

rename table tblItemGlobal to tblItemGlobal_old, tblItemGlobal_new to tblItemGlobal;
drop table tblItemGlobal_old;

CREATE TABLE `tblItemGlobalWorking_new` (
  `when` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `region` enum('US','EU','CN','TW','KR') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'US',
  `item` mediumint(8) UNSIGNED NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `median` decimal(11,0) UNSIGNED NOT NULL,
  `mean` decimal(11,0) UNSIGNED NOT NULL,
  `stddev` decimal(11,0) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `tblItemGlobalWorking_new`
  ADD PRIMARY KEY (`when`,`region`,`item`,`level`);
  
insert into tblItemGlobalWorking_new (
select ig.`when`, ig.region, ig.item, ifnull(i.level, 0), ig.median, ig.mean, ig.stddev
from tblItemGlobalWorking ig
left join tblDBCItem i on ig.item = i.id and i.class in (2,4)
where ig.bonusset = 0);

rename table tblItemGlobalWorking to tblItemGlobalWorking_old, tblItemGlobalWorking_new to tblItemGlobalWorking;
drop table tblItemGlobalWorking_old;

CREATE TABLE `tblItemHistoryHourly_new` (
  `house` smallint(5) UNSIGNED NOT NULL,
  `item` mediumint(8) UNSIGNED NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL,
  `when` date NOT NULL,
  `silver00` int(10) UNSIGNED DEFAULT NULL,
  `quantity00` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver01` int(10) UNSIGNED DEFAULT NULL,
  `quantity01` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver02` int(10) UNSIGNED DEFAULT NULL,
  `quantity02` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver03` int(10) UNSIGNED DEFAULT NULL,
  `quantity03` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver04` int(10) UNSIGNED DEFAULT NULL,
  `quantity04` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver05` int(10) UNSIGNED DEFAULT NULL,
  `quantity05` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver06` int(10) UNSIGNED DEFAULT NULL,
  `quantity06` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver07` int(10) UNSIGNED DEFAULT NULL,
  `quantity07` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver08` int(10) UNSIGNED DEFAULT NULL,
  `quantity08` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver09` int(10) UNSIGNED DEFAULT NULL,
  `quantity09` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver10` int(10) UNSIGNED DEFAULT NULL,
  `quantity10` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver11` int(10) UNSIGNED DEFAULT NULL,
  `quantity11` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver12` int(10) UNSIGNED DEFAULT NULL,
  `quantity12` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver13` int(10) UNSIGNED DEFAULT NULL,
  `quantity13` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver14` int(10) UNSIGNED DEFAULT NULL,
  `quantity14` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver15` int(10) UNSIGNED DEFAULT NULL,
  `quantity15` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver16` int(10) UNSIGNED DEFAULT NULL,
  `quantity16` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver17` int(10) UNSIGNED DEFAULT NULL,
  `quantity17` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver18` int(10) UNSIGNED DEFAULT NULL,
  `quantity18` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver19` int(10) UNSIGNED DEFAULT NULL,
  `quantity19` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver20` int(10) UNSIGNED DEFAULT NULL,
  `quantity20` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver21` int(10) UNSIGNED DEFAULT NULL,
  `quantity21` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver22` int(10) UNSIGNED DEFAULT NULL,
  `quantity22` mediumint(8) UNSIGNED DEFAULT NULL,
  `silver23` int(10) UNSIGNED DEFAULT NULL,
  `quantity23` mediumint(8) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `tblItemHistoryHourly_new`
  ADD PRIMARY KEY (`house`,`item`,`level`,`when`);

insert into tblItemHistoryHourly_new
(select x.house, x.item, ifnull(i.level, 0), x.`when`,
x.silver00, x.quantity00,
x.silver01, x.quantity01,
x.silver02, x.quantity02,
x.silver03, x.quantity03,
x.silver04, x.quantity04,
x.silver05, x.quantity05,
x.silver06, x.quantity06,
x.silver07, x.quantity07,
x.silver08, x.quantity08,
x.silver09, x.quantity09,
x.silver10, x.quantity10,
x.silver11, x.quantity11,
x.silver12, x.quantity12,
x.silver13, x.quantity13,
x.silver14, x.quantity14,
x.silver15, x.quantity15,
x.silver16, x.quantity16,
x.silver17, x.quantity17,
x.silver18, x.quantity18,
x.silver19, x.quantity19,
x.silver20, x.quantity20,
x.silver21, x.quantity21,
x.silver22, x.quantity22,
x.silver23, x.quantity23
from tblItemHistoryHourly x
left join tblDBCItem i on x.item = i.id and i.class in (2,4)
where x.bonusset=0);

rename table tblItemHistoryHourly to tblItemHistoryHourly_old, tblItemHistoryHourly_new to tblItemHistoryHourly;
drop table tblItemHistoryHourly_old;

CREATE TABLE `tblItemHistoryMonthly_new` (
  `item` mediumint(8) UNSIGNED NOT NULL,
  `house` smallint(5) UNSIGNED NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `month` tinyint(3) UNSIGNED NOT NULL,
  `mktslvr01` int(10) UNSIGNED DEFAULT NULL,
  `qty01` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr02` int(10) UNSIGNED DEFAULT NULL,
  `qty02` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr03` int(10) UNSIGNED DEFAULT NULL,
  `qty03` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr04` int(10) UNSIGNED DEFAULT NULL,
  `qty04` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr05` int(10) UNSIGNED DEFAULT NULL,
  `qty05` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr06` int(10) UNSIGNED DEFAULT NULL,
  `qty06` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr07` int(10) UNSIGNED DEFAULT NULL,
  `qty07` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr08` int(10) UNSIGNED DEFAULT NULL,
  `qty08` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr09` int(10) UNSIGNED DEFAULT NULL,
  `qty09` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr10` int(10) UNSIGNED DEFAULT NULL,
  `qty10` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr11` int(10) UNSIGNED DEFAULT NULL,
  `qty11` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr12` int(10) UNSIGNED DEFAULT NULL,
  `qty12` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr13` int(10) UNSIGNED DEFAULT NULL,
  `qty13` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr14` int(10) UNSIGNED DEFAULT NULL,
  `qty14` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr15` int(10) UNSIGNED DEFAULT NULL,
  `qty15` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr16` int(10) UNSIGNED DEFAULT NULL,
  `qty16` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr17` int(10) UNSIGNED DEFAULT NULL,
  `qty17` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr18` int(10) UNSIGNED DEFAULT NULL,
  `qty18` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr19` int(10) UNSIGNED DEFAULT NULL,
  `qty19` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr20` int(10) UNSIGNED DEFAULT NULL,
  `qty20` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr21` int(10) UNSIGNED DEFAULT NULL,
  `qty21` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr22` int(10) UNSIGNED DEFAULT NULL,
  `qty22` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr23` int(10) UNSIGNED DEFAULT NULL,
  `qty23` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr24` int(10) UNSIGNED DEFAULT NULL,
  `qty24` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr25` int(10) UNSIGNED DEFAULT NULL,
  `qty25` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr26` int(10) UNSIGNED DEFAULT NULL,
  `qty26` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr27` int(10) UNSIGNED DEFAULT NULL,
  `qty27` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr28` int(10) UNSIGNED DEFAULT NULL,
  `qty28` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr29` int(10) UNSIGNED DEFAULT NULL,
  `qty29` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr30` int(10) UNSIGNED DEFAULT NULL,
  `qty30` smallint(5) UNSIGNED DEFAULT NULL,
  `mktslvr31` int(10) UNSIGNED DEFAULT NULL,
  `qty31` smallint(5) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `tblItemHistoryMonthly_new`
  ADD PRIMARY KEY (`item`,`house`,`level`,`month`);
  
insert into tblItemHistoryMonthly_new
(select x.`item`, x.house, ifnull(i.level, 0), x.month, 
  x.`mktslvr01`, x.`qty01`,
  x.`mktslvr02`, x.`qty02`,
  x.`mktslvr03`, x.`qty03`,
  x.`mktslvr04`, x.`qty04`,
  x.`mktslvr05`, x.`qty05`,
  x.`mktslvr06`, x.`qty06`,
  x.`mktslvr07`, x.`qty07`,
  x.`mktslvr08`, x.`qty08`,
  x.`mktslvr09`, x.`qty09`,
  x.`mktslvr10`, x.`qty10`,
  x.`mktslvr11`, x.`qty11`,
  x.`mktslvr12`, x.`qty12`,
  x.`mktslvr13`, x.`qty13`,
  x.`mktslvr14`, x.`qty14`,
  x.`mktslvr15`, x.`qty15`,
  x.`mktslvr16`, x.`qty16`,
  x.`mktslvr17`, x.`qty17`,
  x.`mktslvr18`, x.`qty18`,
  x.`mktslvr19`, x.`qty19`,
  x.`mktslvr20`, x.`qty20`,
  x.`mktslvr21`, x.`qty21`,
  x.`mktslvr22`, x.`qty22`,
  x.`mktslvr23`, x.`qty23`,
  x.`mktslvr24`, x.`qty24`,
  x.`mktslvr25`, x.`qty25`,
  x.`mktslvr26`, x.`qty26`,
  x.`mktslvr27`, x.`qty27`,
  x.`mktslvr28`, x.`qty28`,
  x.`mktslvr29`, x.`qty29`,
  x.`mktslvr30`, x.`qty30`,
  x.`mktslvr31`, x.`qty31`
from tblItemHistoryMonthly x
left join tblDBCItem i on i.id=x.item and i.class in (2,4)
where x.bonusset=0);

rename table tblItemHistoryMonthly to tblItemHistoryMonthly_old, tblItemHistoryMonthly_new to tblItemHistoryMonthly;
drop table tblItemHistoryMonthly_old;

CREATE TABLE `tblItemSummary_new` (
  `house` smallint(5) UNSIGNED NOT NULL,
  `item` mediumint(9) NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  `quantity` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `lastseen` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `tblItemSummary_new`
  ADD PRIMARY KEY (`house`,`item`,`level`);
  
insert into tblItemSummary_new
(select x.house, x.item, ifnull(i.level, 0), x.price, x.quantity, x.lastseen
from tblItemSummary x
left join tblDBCItem i on i.id=x.item and i.class in (2,4)
where x.bonusset=0);

rename table tblItemSummary to tblItemSummary_old, tblItemSummary_new to tblItemSummary;
drop table tblItemSummary_old;

truncate tblUserRareReport;
ALTER TABLE `tblUserRareReport` CHANGE `bonusset` `level` SMALLINT UNSIGNED NOT NULL DEFAULT '0';

update tblUserWatch set deleted=now() where ifnull(bonusset,0) != 0 and deleted is null;
alter table tblUserWatch change `bonusset` `level` SMALLINT UNSIGNED NULL DEFAULT NULL;
update tblUserWatch set level = (select ifnull(min(i.level),0) from tblDBCItem i where i.id=item and i.class in (2,4)) where item is not null and level is not null and deleted is null;

drop table if exists ttblRareStageTemplate;
CREATE TABLE IF NOT EXISTS `ttblRareStageTemplate` (
  `item` mediumint(8) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL,
  `price` decimal(11,0) NOT NULL,
  `lastseen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`item`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ttblItemSummaryTemplate` (
  `item` mediumint(9) NOT NULL,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `price` decimal(11,0) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

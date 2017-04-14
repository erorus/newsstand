# Newsstand

[The Undermine Journal](https://theunderminejournal.com) shows you Auction House statistics and data from World of Warcraft.

[WoW Token Info](https://wowtoken.info) shows you prices and historical statistics of the WoW Token in World of Warcraft.

[MageTower.info](https://magetower.info) shows you dynamic building status in World of Warcraft.

Newsstand is the codename for this, the second major version of The Undermine Journal. It is designed to be a one-page app, building pages via a few javascript modules, and fetching data from the server via JSON APIs.

WoW Token Info and MageTower.info were added later as small sites on the same backend server, though their operations are mostly separate from The Undermine Journal.

## System Requirements

Newsstand is currently hosted on a dedicated server with 16GB of memory, a quad core CPU, and a 256GB SSD for the database. It runs:
 - CentOS 6.9
 - nginx 1.12
 - MySQL 5.5
 - Memcached 1.4.4
 - PHP 7.1

## How It Works

It's assumed that this repo lives in `/var/newsstand`. The MySQL table schema is in newsstand-tables.sql. The `public` directory is the HTTP root for The Undermine Journal, and `wowtoken` is the HTTP root for WoW Token Info.

Start with running `scripts/realms2houses.php` to populate your realms table. It tries to figure out which realms are connected by looking at the AH data.

Look at crontab.txt for the variety of `scripts/` that keep the site updated.
 - `itemupdate.php` updates some static item data from the Battle.net API and from Wowhead.
 - `fetchsnapshot.php` intelligently polls the Battle.net Auction Data API and saves any new json to a working directory to be parsed. One copy is run for each region.
 - `parsesnapshot.php` picks up the saved json and parses it, saving new and updated stats to the database tables. Usually two are running at once, though they are not region-bound like fetchsnapshot. They must fully parse snapshots quickly, ideally under 10 seconds each.
 - `realms2houses.php` occasionally updates the matching of realms to their "auction houses", i.e. connected realms.
 - `itemglobal.php` updates the global averages table for each item.
 - `itemdaily.php` updates the daily prices table (which powers the OHLC charts) for each stackable item, based on the snapshot data.
 - `historyprune.php` prunes the detailed (hourly) snapshot history, removing data older than 2 weeks old.
 - `buildaddon.sh` runs the expensive addon build process, which saves some average pricing data for every item on every realm into the in-game addon.
 - `wowtoken.php` fetches the WoW Token pricing data placed by the bot server, saves it to the database, and generates the json and csv files.

## How am I expected to clone this?

You aren't, not really. I don't really expect Newsstand to be forked/cloned, and this is more for reference for your own Battle.net projects (or just for curiosity).

## License

Copyright 2015 Gerard Dombroski

Licensed under the Apache License, Version 2.0 (the "License");
you may not use these files except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

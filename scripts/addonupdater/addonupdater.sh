#!/bin/bash

vers=`unzip -p /var/newsstand/addon/TheUndermineJournal.zip TheUndermineJournal/TheUndermineJournal.toc | grep Version:`
idx=`expr index "$vers" :`
vers=${vers:($idx + 1)}

## curse
php /var/newsstand/scripts/addonupdater/curseupdater.php /var/newsstand/addon/TheUndermineJournal.zip "$vers"

## wow interface
php /var/newsstand/scripts/addonupdater/wowiupdater.php /var/newsstand/addon/TheUndermineJournal.zip "$vers"


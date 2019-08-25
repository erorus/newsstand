#!/bin/bash
php /var/newsstand/scripts/buildaddon.php
if [ $? -eq 0 ]; then
    advzip -z -2 /var/newsstand/addon/TheUndermineJournal.zip
    chmod +r /var/newsstand/addon/TheUndermineJournal.zip
    /var/newsstand/scripts/addonupdater/addonupdater.sh
fi

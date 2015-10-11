#!/bin/bash
php /var/newsstand/scripts/buildaddon.php
if [ $? -eq 0 ]; then
    /var/newsstand/scripts/addonupdater/curseupdater.sh
#    curl https://www.cloudflare.com/api_json.html -d 'a=zone_file_purge' -d 'tkn=ce2d32655610115c1866795590af0c3e27483' -d 'email=cloudflare@everynothing.net' -d 'z=theunderminejournal.com' -d 'url=http://addon.theunderminejournal.com/TheUndermineJournal.zip'
#    curl https://www.cloudflare.com/api_json.html -d 'a=zone_file_purge' -d 'tkn=ce2d32655610115c1866795590af0c3e27483' -d 'email=cloudflare@everynothing.net' -d 'z=theunderminejournal.com' -d 'url=https://addon.theunderminejournal.com/TheUndermineJournal.zip'
# don't bother checking, those cloudflare keys are no longer valid
fi

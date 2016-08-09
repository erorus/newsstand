#!/bin/bash
find /var/newsstand/backup -name '*.sql.gz' -ctime +29 -delete
php /var/newsstand/scripts/backupdata.php

latest=`find /var/newsstand/backup -name 'backupdata.*.sql.gz' -ctime -2 | sort | tail -n 1`
if [ "$latest" != "" ]; then
    source /var/newsstand/scripts/newswire.credentials.sh

    echo "`date` Turning off newswire sql"
    ssh newswire ./sqloff.sh
    echo "`date` Updating newswire sql"
    cat "$latest" | ssh newswire 'zcat | mysql -u root -p'\'"$NEWSWIREMYSQL"\'' newsstand'
    echo "`date` Turning on newswire sql"
    ssh newswire ./sqlon.sh
    echo "`date` Done with newswire"
fi

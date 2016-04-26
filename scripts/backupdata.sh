#!/bin/bash
find /var/newsstand/backup -name '*.sql.gz' -ctime +29 -delete
php /var/newsstand/scripts/backupdata.php

latest=`find /var/newsstand/backup -name 'backupdata.*.sql.gz' -ctime -2 | sort | tail -n 1`
if [ "$latest" != "" ]; then
    scp "$latest" newswire:backupdata.sql.gz && ssh newswire ./nohuprestore.sh
fi

#!/bin/bash
find /var/newsstand/backup -name '*.sql.gz' -ctime +29 -delete
php /var/newsstand/scripts/backupdata.php

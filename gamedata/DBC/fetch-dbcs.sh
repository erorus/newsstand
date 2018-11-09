#!/bin/bash

cd "${0%/*}"

locales=$1
if [ "$locales" == "" ]; then
    locales="enUS deDE esES frFR itIT ptBR ruRU zhTW koKR"
fi

for locale in $locales; do
    mkdir -p current/$locale
    php casc/casc.php --files dbcs.txt --out current/$locale --locale $locale
    mv current/$locale/DBFilesClient/* current/$locale/
    rmdir current/$locale/DBFilesClient
done

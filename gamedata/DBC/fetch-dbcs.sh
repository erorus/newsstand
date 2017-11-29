#!/bin/bash

cd "${0%/*}"

for locale in enUS deDE esES frFR itIT ptBR ruRU; do
    mkdir -p current/$locale
    php casc/casc.php --files dbcs.txt --out current/$locale --locale $locale
    mv current/$locale/DBFilesClient/* current/$locale/
    rmdir current/$locale/DBFilesClient
done

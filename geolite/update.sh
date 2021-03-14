#!/bin/bash

set -e

cd "$( dirname "${BASH_SOURCE[0]}" )"

source ./credentials.sh

urls="https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-ASN&license_key=$MAXMIND_KEY&suffix=tar.gz"

for url in $urls; do
    mkdir -p working
    cd working

    wget "$url" -O db.tar.gz
    tar xzvf *.tar.gz --strip-components 1
    mv -v *.mmdb ../data/

    cd ..
    rm -rf working
done


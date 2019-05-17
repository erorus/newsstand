#!/bin/bash

set -e

urls="https://geolite.maxmind.com/download/geoip/database/GeoLite2-ASN.tar.gz"

cd "$( dirname "${BASH_SOURCE[0]}" )"

for url in $urls; do
    mkdir working
    cd working

    wget "$url"
    tar xzvf *.tar.gz --strip-components 1
    mv -v *.mmdb ../data/

    cd ..
    rm -rf working
done


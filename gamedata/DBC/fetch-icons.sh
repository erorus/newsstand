#!/bin/bash

cd "${0%/*}"

echo Getting listfile..
php ../icons/iconlist.php > ../icons/listfile.txt

echo Extracting files..
mkdir -p ../icons/casc.out
php casc/casc.php --files ../icons/listfile.txt --out ../icons/casc.out

echo Building raw.tar..

rm -rf ../icons/current
mkdir ../icons/current

tar cf ../icons/current/blp.tar --xform='s,./,,' -C ../icons/casc.out/interface/icons .

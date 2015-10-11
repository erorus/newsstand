#!/bin/bash

source /var/newsstand/scripts/addonupdater/curseupdater.credentials.sh

mkdir /tmp/curseupdater.$$
cp /var/newsstand/addon/TheUndermineJournal.zip /tmp/curseupdater.$$

if [ -s "/tmp/curseupdater.$$/TheUndermineJournal.zip" ]; then
	boundary=`php /var/newsstand/scripts/addonupdater/curseupdater.php /tmp/curseupdater.$$`
	if [ $? -eq 0 ]; then
		echo Boundary: $boundary

		wget -O /tmp/curseupdater.$$/curseout.txt --header "Content-type: multipart/form-data; boundary=$boundary" --header "X-API-Key: $CURSEAPIKEY" --post-file /tmp/curseupdater.$$/topost.txt "http://wow.curseforge.com/addons/undermine-journal/upload-file.json"
		cat /tmp/curseupdater.$$/curseout.txt
		rm /tmp/curseupdater.$$/curseout.txt

	else
		echo Some error making mime string:
		echo $boundary
	fi

	## wow interface
	vers=`unzip -p /tmp/curseupdater.$$/TheUndermineJournal.zip TheUndermineJournal/TheUndermineJournal.toc | grep Version:`
	idx=`expr index "$vers" :`
	vers=${vers:($idx + 1)}
	php /var/newsstand/scripts/addonupdater/wowiupdater.php /var/newsstand/addon/TheUndermineJournal.zip "$vers"
else
	echo Could not get new zip file.
fi
rm -rf /tmp/curseupdater.$$

#!/bin/bash
me=`whoami`
pth=/tmp/heartbeat

for pid in `find $pth -user $me -cmin +7 -printf "%f "`; do
	kill -9 $pid
	echo "`date` - Sent KILL to process $pid by $me last modified on `date -r $pth/$pid` - `ps -p $pid -o args h`"
	rm $pth/$pid
done
for pid in `find $pth -user $me -cmin +5 -printf "%f "`; do
	kill $pid
	echo "`date` - Sent TERM to process $pid by $me last modified on `date -r $pth/$pid` - `ps -p $pid -o args h`"
done

#!/bin/bash
curl -s 'http://us.patch.battle.net:1119/wow/versions' | grep '^us' | awk -F '|' '{print $6}'


#!/bin/bash
curl -s 'https://ribbit.everynothing.net/products/wow/versions' | grep '^us' | awk -F '|' '{print $6}'


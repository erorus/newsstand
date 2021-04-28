#!/bin/bash
curl -s 'https://everynothing.net/ribbit/products/wow/versions' | grep '^us' | awk -F '|' '{print $6}'


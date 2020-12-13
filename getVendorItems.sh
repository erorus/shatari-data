#!/bin/bash

cd "$( dirname "${BASH_SOURCE[0]}" )"

wget -O vendor-items.json 'https://www.wowhead.com/data/vendor-items'

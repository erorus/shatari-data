#!/bin/bash

cd "$( dirname "${BASH_SOURCE[0]}" )"

cp -v out/battlepets.json out/bonuses.json out/items.all.json out/names.bound.*.json ../shatari/
cp -v out/battlepets.json out/battlepets.*.json out/categories.*.json out/items.unbound.json out/names.unbound.*.json out/name-suffixes.*.json out/vendor.json out/bonusToStats.json ../shatari-front/public/json/

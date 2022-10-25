#!/bin/bash

cd "$( dirname "${BASH_SOURCE[0]}" )"

for locale in enus dede eses frfr itit ptbr ruru zhtw kokr; do
  echo "Starting $locale..."
  php categories.php $locale
  echo "Sleeping..."
  sleep 5
done
echo "Done"

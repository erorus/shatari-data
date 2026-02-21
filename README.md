# Project Shatari - Data

This is the static game data processing code for [Undermine Exchange](https://undermine.exchange), which provides historical auction pricing data for World of Warcraft.

This is one of three layers to this application stack:
* [Project Shatari - Data](https://github.com/erorus/shatari-data) - Parses static game data into JSON files used by other layers. Run from a development environment.
* [Project Shatari - Back End](https://github.com/erorus/shatari) - Regularly consumes dynamic API data into custom-format data files consumed by the front end. Run on the server.
* [Project Shatari - Front End](https://github.com/erorus/shatari-front) - Presents the web interface to the application which consumes data from other layers to render the output. Serve via HTTPS.

## What's This For

This reads Blizzard's DB2 database files and repackages useful data into JSON files for the other Project Shatari layers.

You run these scripts on your dev environment, then copy the JSON files it creates into the Back End and Front End repositories as necessary.

## Scripts

* `src/battlepets.php` extracts battle pet names and stats.
* `src/bonuses.php` tries to make sense of item bonuses, to get data for 3 major areas:
  * How does this bonus change the item level?
  * How does this bonus set the item's name suffix?
  * How does this bonus apply a tertiary stat (speed, leech, etc)?
* `src/categories.php` (and `.sh`) build the localized auction house categories seen on the left panel of the in-game auction house (and the left panel of the website). A lot of this is hardcoded.
* `src/items.php` extracts a variety of pertinent data for items.
* `src/non-patch/` has scripts that aren't run every patch, but may be useful at expansion releases, or other ad-hoc uses.

## Directories

* `out/` is where the JSON output will be saved.
* `src/` is all the code we're running.
* `current/` is where the current build's DB2 files are held.
Example path: `current/enUS/DBFilesClient/ItemSparse.db2`

## Thanks

Thanks to the [WoWDBDefs](https://github.com/wowdev/WoWDBDefs/) project for sharing and maintaining DB2 field definitions for DB2 files.

Click here to support my WoW projects: [![Become a Patron!](https://everynothing.net/patronButton.png)](https://www.patreon.com/bePatron?u=4445407)

## License

Copyright 2026 Gerard Dombroski

Licensed under the Apache License, Version 2.0 (the "License");
you may not use these files except in compliance with the License.
You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

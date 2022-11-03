# Dota 2 League Report Generator (D2-LRG-lrg2)

## Current version: 2.21.0

This is the **lrg2** version that won't be updated any further.

Well, kind of. I guess it will be updated eventually and the repo will be renamed, but for now it's a huge mess.

**D2-LRG** (for short) is a tool for fetching stats for dota matches and forming fancy stats pages with every data you may need.

## Before we start

So, first of all, **I do not recommend using this project for yourself**. Like at all.

It was created just as three (four) simple PHP scripts to gather detailed match data for Dota and generate JSON report blobs and HTML pages for them. It was pretty simple initially and it just became a much bigger thing I initially intended it to be. As you may understand, it's pretty hard to understand what's going on in this code and it's hard to modify it. It doesn't follow any PSR suggestions. It doesn't use Composer. It's pretty simple in its functionality and it was written mostly for myself.

The reason why it's open source project is pretty simple: I just thought "why not"?

So technically you *can* use if you want. Altho you need to keep in mind some things:

1. It's pretty ugly inside, it's hard to modify and some things are just illogical.
2. I abandoned it a long time ago. I released some fixes relatively recently and I will release some hotfixes later on (probably), but the thing is it won't be usable at some point.

Around Jan 2019 I switched to part-time development of a D2-LRG-Simon project that should've effectively replace this project and it would be much cleaner and easier to use/modify. However I ended up switching to another project with closed source (D2-LRG-Guame) that's using a lot of Simon ideas and code inside. Guame is still in PHP and it's using ReactPHP, the fetcher is server that's listening for orders all the time, and there's just WebAPI for all the stuff.

I could've develop Simon in this same repo, but it's so defferent in it's core so it's easier to write everything from scratch. And the final nail is Simon will probably be a JavaScript based project.

So what does it mean in context of this repository:
1. It doesn't show how I would actually write a decent PHP code. I will write some showcase projects later on for that.
2. As soon as I finish the first working version of D2-Simon, this repo will be abandoned completely.
  - However, Simon will be able to transform lrg2 JSON reports into its own reports format. So technically (if you want) you can use this collection of scripts and make your reports and then reuse it here
3. Simon will be somewhat ready sometime around Feb 2020 (maybe even later) and then I will be working on an interface for all this, so this one will be completely shut down around June 2020.

### Features
* Fetching data from OpenDota
* Getting player information and Ranked All Pick from Stratz (for non-tournament matches)
* Botmatches support
* Custom leagues (you form list of matches manually)
* Advanced positions stats for both players and heroes
* Advanced draft stats
* Individual teams stats
* Interactive meta graphs
* Mix-tournaments and leagues support
* Regions stats

Thanks to custom leagues, it can be used as a tool for teams as well. It is possible to fetch data for enemy teams before tournaments, as well as forming stats reports for your own team to make analysis for that.

Working prototype: http://stats.spectral.gg/

## How does it work

D2LRG is made of four scripts, divided into smaller modules:
- `init` creates a .league file with parameters and a database on MySQL-based server. It also creates .matchlist file and requests matches from Steam API or just leaves it blank
- `fetcher` reads matches from .matchlist and requests OpenDota analysis data for every match in it. After that every match is added to database
- `analyzer` makes series of SQL requests to get ready-to-use data and saves it as .report file as JSON data. It can be transferred to another D2LRG instance from now on and not linked to MySQL anymore
- `view` is standalone module that reads .report file and allows easy to use basic web interface for it
- `webapi` is the same as view, but for WebAPI calls
- `backup` creates a tarball with all report data

### Why PHP and Why migrate to LRG-Simon

1. The project was created as a proof-of-concept collection of scripts. Initially it should be working on a cheapest web server possible with minimal effort. It became much bigger than intended, but it still can be used that way.
2. PHP5 and PHP7 are easy and fast tools to work with
3. LRG-Simon (LRG3) will be rebuilt from scratch. A lot of features and ideas will be backported from LRG2, but the code will be much easier to read and to work with. It will also be harder to work with, but it will be more powerful too.

### What do I need to use it?

MySQL database server and PHP interpreter. Nothing really special, you can use regular XAMPP for it.

I also recommend using PHPMyAdmin for manual data change.

It would be nice to have bash on your system. For Windows it's recommended to have git bash or cygwin with ConEmu.

## Dependencies:
* PHP 7+
* php mbstring
* MySQL 8+
* cURL

After getting D2LRG code to your computer, run `php setup.php`. It will install the rest of dependencies:
* Vis.js @ 4.21.0
* Chart.js @ 2.9.1
* Chart.js Boxplot @ 2.3.2
* jQuery
* jquery-tablesorter
* Simple PHP OpenDota SDK
* D2-LRG-Metadata

## Setup

Use `php setup.php` to install, setup and update all the things.

You can also specify special parameters:
* `-l` - skip dependency updates
* `-s` - skip settings update
* `-d` - skip directory check

It's recommended to use `php setup.php -ds` to update.

## How to use

LRG is made of these modules:
* `rg_init.php` - Initialises new league database and other resources.
* `rg_fetcher.php` - Fetches matchdata from OpenDota, adds it to MySQL database (there's no special reason to use it as well).
* `rg_analyzer.php` - Generates precached report file based on matchdata.
* `rg_report.php` - Generates fancy (or not so fancy) report page for rg_analyzer output. Can be used on your webserver as well.

Beforehand you should use `setup.php` to initialise settings (like database credentials or Steam API key) file and download dependencies. Just run it and follow instructions.

You can also use `setup.php` to update dependencies or settings. It has special parameters to skip unnecessary parts:
* `-l` - skip downloading/updating libraries
* `-s` - skip updating settings
* `-d` - skip directories check

### rg_init

Creates database from template and saves leaguefile.

Parameters:
* `-l / --league=` - required, says what will be the tag of your database, league and report file
* `-T / --template=` - use a template of a league as a reference (default: none)
* `-I / --id=` - use league ID to fetch matches and record it to league information (default: null)
* `-N / --name=` - specify league's name (if empty it will be asked anyway)
* `-D / --desc=` - specify league's description (if empty it will be asked anyway)
* `-S / --settings=` - set specific parameters for the league. You can check default template to see all the parameters that you can change

### rg_backup

Creates/restores backup.
Backups are saved to `backups` folder.

Parameters:
* `-R` - restore mode, requires following parameters:
  * `-l` - league tag
  * `-f` - source tar.gz file
* `-r` - generate report and add it to backup
* `-F` - remove data after creating backup
* `-o` - path for output file

### rg_fetcher

Fetches data for matches in matchlist.

Parameters:
* `-l` - required, the tag of your database, league and report file
* `-K` - ignore OpenDota API key
* `-F` - add match to database even if there's no replay analysis available
* `-cDIRECTORY` - Use DIRECTORY to store and check cached data (./cache by default)
* `-R` - Automatically request a match to parse and try again later
* `-S` - Strongly require STRATZ (for Ranked All Pick and Random Draft pick order and non-tournament matches player names), skip it STRATZ doesn't respond. In combination with -R reschedules match and tries again later
* `-s` - Softly require STRATZ (works just like -S, but after failure just continues without STRATZ response) **DEPRECATED**
* `-Z` - Use full match data request from Stratz (called when can't get data using shortcuts) **DEPRECATED**
* `-w123` - Specify number of seconds to wait before requesting scheduled matches again (default: 60)
* `-A` - Force await flag, forces awaiting for availability of replay data (for cases when you pass data to the script and don't want it to end on EOF)
* `-P` - Playerlist to use (discards any match that doesn't have all 10 players in the playerlist)
* `-N` - Set a minimal (stratz) raNk required for the match
* `-d123` - Specify a custom API cooldown (in seconds)
* `-u` - try to update matches without adv_matchlines data (unparsed matches)
* `-U` - try to update all the matches, finding unparsed ones. Automatically enables `-u`
* `-p` - counts matches with negative player ids are required for data update
* `-Q` - disables unparsed matches in -U mode (to only work with matches without detailed player data)
* `-G` - use Stratz GraphQL to populate data (need to specify a group size, min 2, real group size will be N-1)
* `-n` - force update player names
* `-f` - ignore abandons

Fetcher has two modes: "listen" mode and the regular one. Fetcher is using matchlist from `matchlists` follder by default, listen mode changes it to STDIN. It's not async because of time limitation on OpenDota side (basically you can't be too fast with your requests anyway, so there's no need to be asynchronous).

Matchlist is basically a line delimited text file, each line is a new match string. It's using following rules:
* Lines starting with `#` symbol or empty lines are skipped
* If a line consists of a number, it's used as a match ID
* If a line is a matchrule string, it's being parsed by `processRules()`

Listen mode accepts lines in the same format.

Fetcher matchrule string is a string matching following format: `{matchid}::{rule_type}:{search}:{value}::...rules`, where {search} is replaced by {value} following specific {rule_type} rules. Any matchrule string may have an unlmited number of rules.

Rules types:

* `player` - replaces one player steam ID with another (`player:1234:2345`)
* `pslot` - replaces player steam ID of a player in a given slot (`player:0:1234`)
* `team` - replaces one team ID with another (`team:1:2`)
* `side` - replaces team ID of a team based on it's side, given sides are either `radiant_team` or `dire_team` (`side:dire_team:1234`)
* `cluster` - replaces current cluster (`cluster:rep:123`)

Additional parameters to inject in league json descriptor:
* `teams_allowlist`
* `teams_denylist`
* `players_allowlist`
* `players_denylist`
* `force_cluster`
* `cluster_allowlist`
* `cluster_denylist`

And some things about the listen mode:

* it ends on EOF
* it keeps going on forever otherwise
* it's not async and it looks horrifying; I was thinking about implementing some kind of setTimeout() thingy from JS, but it's not compatible with all OSes
* it may or may not hang up when you're passing matches in force mode and it's not a big amount of matches. Basically the script will be waiting for your input and then will continue working. Or maybe not. I'm not entirely sure.

### rg_analyzer

Analyzes and requests data, forms report file.

Parameters:
* `-l / --league=` - required, the tag of your database, league and report file
* `-K` - ignore OpenDota API key
* `-T` - merge settings with a template
* `-oFILENAME` - save result to FILENAME

### rg_report_web

This file (and rg_report_out_settings.php) should be put on your webserver, as well as "reports/" folder with all the reports in it and "res/", "modules/functions" and "modules/view". You will also need to use .htaccess to disable access to modules and settings files.

Settings you can change in it:
* `$lrg_use_get` - Use GET parameters for opening league files and generating modules, `true` by default
* `$lrg_get_depth` - Sets module link depth for GET parameters, `2` by default. Modules deeper than this will be fully generated
* `$locale` - Translation file you will use for your report, `"en"` by default
* `$locales` - List of locales, available for choosing by user. Every value is recorded in format `"locale tag" => "Locale name"`
* `$max_tabs` - Maximum amount of links shown before it gets replaced by `<select>`
* `$custom_head` - Custom text and tags that will be used in `<head>`
* `$custom_body` - Custom text and tags that will be used in `<body>`
* `$custom_content` - Custom text that's placed before actual generated content
* `$custom_footer` - Additional text that will be added to `<footer>`
* `$title_links` - List of links in title bar. Values are arrays `[ "link" => "http...", "title" => "Lorem Ipsum", "text" => "Text" ]`
* `$main_path` - Link to an adress that should be opened by clicking top left icon in title bar
* `$default_style` - Custom style used by default
* `$noleague_style` - Custom style used if no report is selected
* `$instance_title` - Instance title
* `$instance_name` - Instance description
* `$reports_dir` - Path to reports' directory
* `$report_mask` - Mask for finding report files in directory
* `$report_mask_search` - Left and right parts of default report's filename
* `$cache_file` - Path to cache file
* `$cats_file` - Path to categories file
* `$hidden_cat` - Category that will be used as "hidden": reports that apply for it will not be shown in main list
* `$index_list` - Number of reports on main page. `-1` is equal to showing all reports, positive number is equal to showing a chosen number of reports, zero is equal for not showing any reports

If cache file is specified, it will be generated at first page load and then every time any report changes. While generating, it opens every report and creates a descriptor for it, so it may take a while at first.

GET parameters:
* `loc` - Force use of locale
* `stow` - Force use of custom style
* `mod` - Module link
* `league` - Current league report

It can use custom styles. To use them you need to put a .css file to "res/custom_styles" directory. You can also get some additional information from custom styles library repository: https://github.com/leamare/D2-LRG-Custom-Styles

Categories file is a simple JSON configuration file. If it is specified and it exists, categories will be loaded and then applied to every report on instance. There is an example of categories config available, but essentially it looks like this:
```json
{
  "category_tag": {
    "name": "Category Name",
    "names_locales": { "ru": "Category name for a specific locale" },
    "desc": "Category Description",
    "desc_locales": { "ru": "Category description for a specific locale" },
    "filters": [
      [ [ 0, "value" ] ]
    ],
    "custom_style": "custom style",
    "custom_logo": "custom logo"
  }
}
```

All category filters types are listed in `modules/view/functions/check_filters.php`. First level filters are applied with logical OR, second level filters are applied with logical AND.

## Tools

Tools are additional scripts that can be used for specific things. All of them should be executed from the main directory with command similar to `php tools/%TOOL%.php %PARAMETERS%`.

* `remove_match` - removes match from a league's database. Uses only `-l%TAG%` and `-m%MATCH%` parameters. Example: `php tools/remove_match.php -ltestleague -m1234567891`
* `remove_matches` - removes matches from a league's database. Uses only `-l%TAG%` and `-f%FNAME%` parameters. Example: `php tools/remove_match.php -ltestleague -flist`. Can also remove matches that don't fit into a specified time period (`-Tperiod` specifies a time period script, like `-T-1day` and `-eTIME` specifies endpoint timestamp (current unix time by default)) -- if you need a specific subset using filters - use backport_matchlist first and then -f param
* `replay_request` - sends API commands to OpenDota and Stratz, requesting every match from a file. Accepts only one arguement: `-f%FILENAME%`. You can use failed matches dump files (from fetcher) with it
* `replay_request_od` - same as `replay_request`, but strictly requesting match analysis with OpenDota
* `replay_request_stratz` - same as `replay_request`, but strictly requesting match analysis with Stratz
* `clear_database` - removes all data from a league's database. Args: `-l%LEAGUETAG%`
* `update_league` - updates league parameters to new D2LRG API/leaguefile format. Accepts only `-l%LEAGUETAG%`
* `remove_cached` - removes cached matches listed in `-f%FNAME%` file.
* `update_all_reports` - updates all reports
* `backport_matchlist` - generates full matchlist based on league's database. Args: `-l%LEAGUETAG%`, `-Tperiod`, `-P4601` where 4601 is ID of a patch, `-r` to fetch only unparsed matches or `-R` to fetch only parsed, `-Z` to reverse filters (get every match that doesn't fit the filters), `-o` to output to a speficic file
* `backport_cache` - backports all matches from a league database as .lrgcache.json files to cache folder. Args: `-l%LEAGUETAG%`, `-c%FNAME%` for a list of matches, `-Tperiod` if -c is not specified -- if you need a specific subset using filters - use backport_matchlist first and then -c param
* `update_rosters` - updates official rosters for all teams in report. Args: `-l%LEAGUETAG%`
* `update_playerids` - updates missing ranked playerids
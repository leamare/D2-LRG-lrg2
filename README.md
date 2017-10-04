Dota 2 League Stats Fetcher and Report Generator

*Q: Why?*
A: Because I can. Initially I wanted to make script that could generate nice-looking stats report for leagues. Then I wanted to get some special stats for mathes, like average lasthits at 10 minute mark or statistics for a hero on a specific position.

*Q: Why PHP?*
A: It's just proof of concept. PHP is the language for making some scripts in small amount of time. I will probably rewrite it in Python or C++ later.


- how to use
- plans
- Modules
- - init (create database from template, load settings from database, clone database)
- - fetcher (load matchdata and add it to database)
- - - playerinfo (load information about players in league)
- - - teaminfo (load information about players in league)
- - analyzer (get required data from database and drop JSON file)
- - report (generate HTML, Fancy HTML or Makrdown based on JSON report file)

- settings
- tables
- how it works



*Q: Any plans?*
A: Maybe some kind of UI for generating reports or automatic generating of players nicknames table. I will definately try to minimize resources I fetch data from or maybe try to analyze replays by myself. I also want to add data about match keypoints, item timings. Future versions will definately have teams stats and so on. I'd also like to rewrite all of this and add documentation, but right now let's assume it just works.


*Q: How does it work?*
I use SQL and PHP for it right now. The script is divided in four parts: init, fetcher, analyzer, report.
* Firstly, init will check your local SQL server for required d2_general_info database (it has some stuff like HeroIDs and so on). Then it will create d2_league_* database (* = name for league you're working on).
* Secondly, fetcher part comes in. It gets list of matchIDs from local file OR from Valve's API. Initially it got information from various sources, but now it gets everthing from OpenDota API.
* Third part is Analyzer. It basically creates LEAGUETAG.report file which is just marked down text. It's divided in thematic blocks.
* End part: report. It uses .report file and just makes cool HTML page for it. It can be saved to pregenerated .html file or used to get new reports very time.

The database is divided in tables, you mostly care about *matchlines* and *adv matchlines*. Basically, it's just data about every hero in match, divided in 10 different rows. It doesn't look very good, but it makes working with this data much easier.

=== Tables

*matches*. MatchID, winner, length, game mode, league ID and date.

*matchlines*. Just regular dota match data, nothing special. Hero, side, Kills, Deaths, Assists, GPM, XPM, Networth, Damage and heal, lasthits, denies. It doesn't have items data because straight item slots in the end of a game is just useless.

*draft*. What heroes were picked and banned by a team in a specific match and the order of picks/bans.

*adv matchlines*. The most interesting part. This table is kind of second part of *matchlines*, but it's made as separate one just for the cases when something went wrong (replay wasn't analyzed). It has:
* lasthits at 10 minute mark (OpenDota) -- Just to see how many lasthits the hero usually gets at this point and how good it is at dominating a lane
* isCore (Dotabuff) -- Getting data about positions
* lane (Dotabuff) -- In combination with isCore it's used to get data about specific hero on a specific position (safelane core, midlane core, midlane support, etc)
* efficiency_at10 (OpenDota) -- How efficient player was in lane. Completes the picture of hero's laning phase in combination with lasthits at 10
* wards and sentries (Dotabuff)
* roshans and couriers killed (OpenDota)
* maximum multi kill in game and longest kill streak by a hero (OpenDota)
* stacks (OpenDota)
* time dead (OpenDota)
* buybacks (OpenDota)
* wards destroyed (OpenDota)
* map pings by player (OpenDota) -- Just for lulz

Later there will be *players* and *teams*.

=== Console commands

run.sh
init -n LEAGUETAG
fetcher -n LEAGUETAG -f FILE -lid LEAGUE_ID
analyzer -n LEAGUETAG
report -n REPORTFILE
report.php?r=REPORTFILE

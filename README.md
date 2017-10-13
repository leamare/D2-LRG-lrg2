Dota 2 League Stats Fetcher and Report Generator

**Q: But why PHP?**

Just because. There's no reasoning behind it. I just wanted to do so. Don't judge me.

**Q: What does it do?**

* rg_fetcher - Fetches matchdata from OpenDota, adds it to MySQL database (there's no special reason to use it as well).
* rg_analyzer - Generates precached report file based on matchdata.
* (TODO) rg_report - Generates fancy (or not so fancy) report page for rg_analyzer output. Can be used on your webserver as well.
* (TODO) rg_init - Initialises new league database and other resources.

TODO

- analyzer: team competitions
- update metadata
- backup metadata
- backup database & restore (JSON?)
- clone
- time limits for reports (leaguetag._matchid1._matchid2_.json)
- initialiser
- report generator (on-fly)
- report generator (markdown or simple html)
- proper readme

# LRG2API

So here’s the deal.

I said a long time ago that I’m not really interested in reworking LRG2 codebase into something decent and I’d rather make something new. However I have a good amount of data and other reports now and I should provide an API to access this.

LRG2API is mostly reusing code from the main web view script (WV from this point). However it’s much more clean and has some additional features on top of that.

There’s no routing. It’s using a modline parser I was using for WV. Data in API essentially mirrors module structure from WV and you can just copy modline from here and get the same data from API.

Describing data structure for every single thing here may be too much and you’ll probably test the endpoint yourself first. The structure is pretty simple by itself and every valid modline returns the same data as its WV counterpart. However there are some unique things I’d like to mention some API-unique things here (and there will be a structure for it as well, marked by [EXCL]).

Ah, and another thing. The format of reports lacks consistency. There are a lot of reasons why it happened, but it doesn’t matter that much right now. What matters is you have to deal with it since most of data in this API are fetched from the reports directly. There’s also an endpoint that returns full report “as is”. It’s recommended to use if you need to perform a lot of requests (and from the raw report you can get virtually every single bit of data that I can). But in most cases it’s better to use specific API calls instead since LRG2API provides a bit of consistency and makes it less painful to work with the old reports’ format.

And another another thing. One of the “consistency” problems is numbers being strings all around the place. It actually has a reason to be like that in the reports and I don’t want to fix every single thing in API endpoints descriptors. You can handle it yourself, I believe in you, kiddo.

There’s also a note about versions format. It’s basically a number based on Opendota versions with a postfix to indicate specific lettered patch. For example, 4200 is 7.23(a), then 4201 is 7.23b, 4202 is 7.23c and so on.

## Now it’s time to talk about methods

One major GET parameter is `report`. It specifies a tag of a report to load. If such report exists then GET `mod` is parsed. This is case one – Report is loaded. The second case is opposite and it has some unique endpoints.

There's also a thing called "repeater". Basically (if the method supports it) you can pass a comma delimited input (e.g. `1,2,3,5`) or an asterisk (`*`) instead of value. The resulting set will have responses for all selected values (asterisk selects all possible values) for this method.

### Variables

* (GET) `league` – report tag for report endpoints
* (GET) `gets` – a comma-delimited list of parameters (for non-report endpoints)
* (GET) `mod` – Modpath to execute. It’s readed from last to read to find the next specified method. Think of modline as of a path to a file with directory names. You can use `/` or `-` as separators.
  * Modline examples: `heroes-positions-position_1.1`, `regions/region2/heroes/meta_graph`
  * The modline if being read right to left so you can specify a very long modline, but as soon as it finds a correct endpoint, it gets executed regardless of everything else.
  * Modline can contain some parameters (described below as MODL). Modline parameters can be placed anywhere in modline.
* (MODL) `region#` – sets `region` variable to `#` value (expected region codes listed in metadata)
* (MODL) `position_C.L` – sets position to `C.L` code, where `C` is Core flag (0/1) and `L` is lane flag
* (MODL) `heroid#` – sets `heroid` to `#` value (based on in-game hero IDs)
* (MODL) `playerid#` – sets `playerid` to `#` value (steam userid 3 expected)
* (MODL) `team#` – sets `team` to `#` value (expected dota team ID), alias `teamid#`
* (MODL) `itemid#` – sets `item` to `#` value (expected dota item ID)
* (GET) `rep` – report tag (for non-report endpoints)
* (GET) `pretty` – flag, if it’s used response will be nicely formatted
* (GET) `desc` - flag, adds report descriptor to the response if set
* (GET) `teamcard` - flag, adds team card to the response if set
* (GET) `search` - search query used for search

* (GET) `item_cat` - comma delimited list of item categories, allowed values are `all`, `major`, `medium` (~1-2.5k gold items) and `early` (early game items), used only by items modules
* (GET) `simple_matchcard` - GET flag, returns ALL match cards as simplified ones
* (GET) `include_matches` - (for team profiles) GET flag, returns matches of players on heroes as matchcards if set

### Typical data objects

#### Report Descriptor

```json
{
    "tag": "league_tag",
    "name": "Report Name",
    "desc": "Report Description",
    "id": 1234, // league ID of a report if specified, null otherwise
    "first_match": {
        "mid": "123455678", // match id
        "date": "15231231313" // unix timestamp
    },
    "last_match": {
        "mid": "123455678", // match id
        "date": "15231231313" // unix timestamp
    },
    "matches": 123, // number of matches
    "ver": [4,2,0,0,0], // version of the report generator
    "days": 10, // number of days of the report
    "anonymous_players": true/false, // shows if player data is in the report
    "matches_additional": true/false, // true if additional matches data is in the report
    "tvt": true/false, // true if it's team vs team league
    "teams": [...], // if tvt is true - array of team IDs (not set if false)
    "players": [...], // if tvt is false and report is not anonymous - array of player IDs
    "regions": [...], // array of region IDs if there are regions in the report
    "style": "...", // custom style name if set
    "logo": "...", // custom logo name if set
    "endpoints": [...] // array of correct endpoints (only shows up in `info` endpoint)
}
```



#### Match Card

```json
{
	"match_id": "1234567890",
	"players": {
	    "radiant": [
	        {
	        	"player_id": "123456", // steam userid3
	        	"player_name": "player name",
	        	"hero_id": "123"
	        }...
	    ],
	    "dire": [
	        // same as radiant
	    ]
	},
	"teams": { // team ids
	    "radiant": "123456",
	    "dire": "1234556",
	},
	"score": {
	    "radiant": "10",
	    "dire": "20"
	},
	"radiant_win": "1", // it's secretly a boolean flag
    "networth": {
        "radiant": "123456",
        "dire": "123145"
    },
    "duration": "2000", // seconds
    "cluster": "200",
    "region": "1",
    "date": "12345678901", // unix timestamp
    "lid": 12345 // league/ticket ID of the match, null if not assigned
}
```



#### Team Card

```json
{
    "team_id": 5,
    "team_name": "INVICTUS GAMING",
    "matches": "10",
    "wins": "7",
    "winrate": 70, // from 0 to 100
    "gpm": "2268.4000",
    "xpm": "2633.9000",
    "kills": 26.8,
    "deaths": 22.8,
    "assists": 59.1,
    "regions": 12,
    "roster": [
        {
            "player_id": "101259972",
            "player_name": "IG.Oli~",
            "position": "0.0"
        }...
    ],
    "add_info": {
        // key-value map of additional computed stats for this team, e.g.:
        "rampages_total": "3",     // total rampages scored by the team
        "rampages_last_match": "7944065089" // match ID of the last match where team scored a rampage
    },
    "top_heroes": [ // up to 4
        {
            "hero_id": 79,
            "matches_picked": "5",
            "wins_picked": null
        }...
    ],
    "top_pairs": [ // up to 3
        {
            "hero_ids": [
                "19",
                "79"
            ],
            "matches": "3",
            "winrate": 66.67
        }...
    ]
}
```



#### Player card

```json
{
    "player_id": "85937380",
    "player_name": "player name",
    "team_id": 67,
    "matches": "12",
    "wins": "8",
    "winrate": 66.67, // 0-100
    "gpm": "582.3333",
    "xpm": "599.5833",
    "hero_pool": "9",
    "regions": [
        10,...
    ],
    "heroes": { // up to 4
        "72": {
            "matches": "4",
            "wins": "2"
        }...
    },
    "positions": {
        "1.1": {
            "matches": "6",
            "wins": "5"
        }...
    }
}
```



#### Summary

It’s fairly simple to explain. It doesn’t have a structure and is used in `summary`-like modules (positions for example). It’s just an object without any fixed structure, every key corresponds for a value. That’s pretty much it.

### Report is loaded

* `info` (returns report information, fallback endpoint)
* `overview` (may use `region`)
* `records` (may use `region`)
* `heroes/haverages` (may use `region`)
* `players/haverages` (may use `region`, may return `null` for some reports, like immortal rank meta ones)
* `participants` (returns a list of player cards, may be empty for non-player reports)
* `participants-teams` (returnsa a list of teams cards, may be empty if there are no teams)
* `matches` (may use `region` and `team`)
* `heroes/combos` (may use `region` and `team`)
* `players/combos` (may use `region`)
* `meta_graph` (may use `region`, `team`)
* `party_graph` (may use `region`)
* `heroes/pickban` (may use `region`)
* `heroes/draft` (may use `region`, `team`)
* `players/draft` (may use `region`, `team`)
* `heroes/draftvs` (requires `team`)
* `heroes/positions` (returns positions overview by default, may use `region`, `team` and `position`)
* `players/positions` (returns positions overview by default, may use `region`, `team` and `position`)
* [EXCL] `heroes/positions_matches` (may use `region`, `team` and requires `heroid`, returns null if this data is not in the report)
* [EXCL] `players/positions_matches`  (may use `region`, `team` and requires `playerid`, returns null if this data is not in the report)
* `pvp` (requires `playerid`, returns full data otherwise, not available for teams reports)
* `hvh` (requires `heroid`, returns full data otherwise)
* `laning` (requires `heroid`, returns total data otherwise, not available for teams/regions)
* `heroes/summary` (may use `region`)
* `players/summary` (may use `region`)
* `heroes/sides` (may use `region`)
* `players/sides` (usually doesn't exist in a report, may use `region`)
* `teams/summary` - when `team` variable is set, the response additionally includes `add_info` (see Team Card above for structure)
* [EXCL] `matchcards` (requires `gets`, returns match cards for matches listed in `gets`)
* [EXCL] `teams_raw` (requires `team`, returns raw team object from report)
* `teams/cards`
* `teams/roster` (may use `team`)
* `teams/grid` (may use `region`)
* [EXCL] `teams/grid/raw` (same as regular grid, but returns raw TVT data object)
* [EXCL] `teams/grid/source` (same as regular grid, but returns unpacked TVT data)
* `items/overview`
* `items/stats` and `items/boxplots` (identical) - may use `item` variable, returns `total` if not set. Each item now includes `grad_per_gold` (winrate gradient per 100k gold spent, 0 for free items).
* `items/heroes` and `items/heroboxplots` (identical) - may use `hero` variable. Each hero row and the `total` object now include `grad_per_gold`.
* `items/icombos` - may use `item` variable, returns `total` if not set
* `items/irecords` - may use `item` variable, returns `overview` if not set
* `items/icritical` - may use `hero` variable
* `items/progression` and `items/proglist` (identical) - may use `hero` variable, returns `overview` if not set
* `items/progrole` - may use `hero` and `position`, supports repeaters for both, if hero is not set returns the list of all possible role progressions for every hero
* `items/builds` - may use `hero` and `position`, pretty much same rules as progrole. When enchantments data is present in the report, each hero build profile includes an `enchantments` array (per tier, items sorted by matches) containing `tier`, `category`, and `items` fields.
* `items/enchantments` - may use `heroid` variable, returns data for `total` if not set. Returns `[]` if the report has no enchantments data or if the specified hero has no enchantment stats.

#### items/enchantments response

```json
{
  "hero": 65,            // heroid requested, or "total"
  "categories": [
    {
      "id": 0,           // internal category index (0 = overall total)
      "name": "enhancement_tier_total",  // category name from item_categories metadata
      "items": [
        {
          "item_id": 123,
          "matches": 10,
          "prate": 0.45,    // pick rate (matches / (matches + matches_without))
          "wr": 0.6,        // winrate when picking this enchantment
          "wr_diff": 0.05   // winrate delta vs not picking it
        }
      ]
    },
    // per-tier categories follow (enhancement_tier_1, enhancement_tier_2, ...)
  ]
}
```

* `items/profiles` - requires `itemid`. Profiles for enchantment items additionally include:
  - `type.is_enchantment: true` in the item type flags
  - `stats.overview.items_wr_gradient_per_gold` - gradient per 100k gold (same as `grad_per_gold` in stats/heroes)
  - `stats.enchantments` - enchantment-specific breakdown:

```json
{
  "total": {
    "matches": 10,
    "prate": 0.45,
    "wr": 0.6,
    "wr_diff": 0.05
  },
  "per_tier": {
    "tier_1": [
      {
        "hero_id": 65,
        "matches": 4,
        "prate": 0.5,
        "wr": 0.75,
        "wr_diff": 0.1
      }
    ]
    // tier_2, tier_3, ...
  }
}
```

* `heroes/daily_wr`
* `heroes/wrtimings`
* `heroes/wrplayers`
* `heroes/profiles` - requires `heroid`
* `players/profiles` - requires `playerid`
* `heroes/rolepickban` - also works for regions and teams, requires heroes-positions to be enabled
* `milestones`
* `tickets` - returns list of league tickets that matches in this report belong to, sorted by number of matches descending. Returns an empty array if the report has no ticket data.

#### Tickets response

Array of ticket objects:

```json
[
  {
    "lid": 12345,               // Dota league/ticket ID (0 for matches with no ticket)
    "matches": 10,              // number of matches played under this ticket in the report
    "league_name": "ESL One",   // league name from DB, "League #<id>" if name unknown, "none" for lid 0
    "url": "https://...",       // optional: league URL (omitted if not set)
    "description": "..."        // optional: league description (omitted if not set)
  }
]
```

### Non-report endpoints

* `list` (may use `cat`, returns list of existing reports for selected category and categories for them)
* `metadata` (requires `gets`, returns metadata, mirrors the same data you can get from [github/leamare/D2-LRG-Metadata](https://github.com/leamare/D2-LRG-Metadata))
* `locales` (requires `gets`, returns locale objects used by LRG2 WV)
* `getcache` (returns raw cache used for lists)
* `raw` (requires `rep`, returns raw report)
* `search` (requires `search`, returns list of reports found)
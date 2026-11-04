# Meta Levels

Computed live from the report (`meta_levels_from_report()` → `meta_levels_build()`). Surfaces: web view `heroes/meta_levels`, WebAPI `mod=heroes-meta_levels`. Hero tier lists reuse the same result as a rank boost.

## The model

A metagame develops in layers:

- **Meta-0** forms when something resets the game: a patch lands, a tournament starts. It's the heroes that are simply, objectively strong or pupular: omnipresent, contested in almost every draft.
- **Meta-1** emerges as the answer to Meta-0.
- **Meta-2** answers Meta-1, and so on.

Notes:
- Earlier layers don't disappear when the next arrives; they lose a little popularity and winrate. Weaker players stay on the earlier layers, stronger players move to the next.
- Eventually layers **loop**, because the answers to certain heroes repeat.

Each layer has a **core** (the heroes that define it) and **complimentary** heroes (that pair with the core well). Past the last observed layer, a few **projections**
extrapolate what comes next.

```mermaid
flowchart LR
  subgraph observed ["Observed"]
    M0["Meta-0<br/>founders after a reset"]
    M1["Meta-1<br/>answers Meta-0"]
    M2["Meta-2<br/>answers Meta-1"]
    Mn["Meta-n"]
    M0 --> M1 --> M2 --> Mn
  end
  subgraph extra ["Unobserved"]
    P1["Projection"]
    P2["... up to META_LEVELS_MAX_PROJECTIONS"]
  end
  Mn --> P1 --> P2
  Mn -.->|"Jaccard ≥ 0.5"| Loop["loops_to earlier layer"]
  Loop -.-> M0
```

A layer is not a replacement of the previous one. The earlier core stays in the pool; the next window is scored as *who answers it*.

## Inputs

All four must be present or the feature renders nothing (`meta_levels_from_report()`):

| Section | Used for |
|---|---|
| `hero_daily_wr` | per-day pick/ban/winrate — the timeline |
| `pickban` | overall picks, bans, winrate |
| `hvh` | hero-vs-hero winrates — "counters" |
| `hph` | hero+hero synergy — "combos" |

```mermaid
flowchart TB
  R["report JSON"] --> Need{"hero_daily_wr<br/>pickban<br/>hvh<br/>hph?"}
  Need -->|missing any| Empty["return null / empty UI"]
  Need -->|all present| Unwrap["unwrap wrapped sections"]
  Unwrap --> Build["meta_levels_build()"]
  Build --> WV["view: heroes/meta_levels"]
  Build --> API["webapi: MetaLevels"]
  Build --> TL["tier_list_apply_meta_boost()"]
```

## Pipeline

```mermaid
flowchart TD
  Daily["hero_daily_wr"] --> Emer["1. emergence<br/>EWMA share, first day ≥ ½·P90"]
  PB["pickban"] --> Emer
  Emer --> Pool["2. eligible pool<br/>pick_rate ≥ median"]
  Pool --> Win["3. equal-count windows<br/>sorted by emergence day"]
  Win --> Score["4. score window<br/>prominence × (1 + counter bonus)"]
  HVH["hvh counters"] --> Score
  Score --> Cut["dynamic cutoff → core"]
  Cut --> Norm["5. normalize to a fixed point<br/>no consecutive dupes, fold thin layers"]
  Norm --> Combo["6. complimentary from hph"]
  HPH["hph combos"] --> Combo
  Combo --> Loop["7. Jaccard loop tags"]
  Loop --> Proj["8. projections"]
  Proj --> Out["layers + projections + emergence"]
```

## Step 1: when did each hero emerge?

For hero *h* on day *t*, its share of that day's **contest** (picks plus bans) is

```
share_h(t) = (picks_h(t) + bans_h(t)) / Σ_i (picks_i(t) + bans_i(t))
```

smoothed with an EWMA (α = `META_LEVELS_EWMA_ALPHA` = 0.3):

```
E_h(t) = (1-α)·E_h(t-1) + α·share_h(t)
```

The **emergence day** is the first *t* where `E_h(t) ≥ ½ · P90(E_h)` -- classic time-to-half-max, with the 90th percentile standing in for the peak.

Notes:
- **Not** "first day the hero was picked at all". In a long report nearly every hero has a stray early pick, which makes that measure pure noise.
- **P90, not the maximum.** One freak day on a low-volume date would otherwise set the scale.
- **Not the hero's own long-run average** either. A hero that is omnipresent from day one has an average nearly equal to its steady level, so it only clears its own average after the EWMA has fully ramped, pushing them out of Meta-0.

```mermaid
flowchart LR
  Picks["picks_h(t) + bans_h(t)"] --> Share["share_h(t) = contest_h / day total"]
  Share --> EWMA["E_h(t) = (1-α)·E + α·share"]
  EWMA --> Peak["peak = P90(E_h)"]
  Peak --> Day["emergence = first t where E ≥ ½·peak"]
```

## Step 2: who is eligible

**Prominence** is contest-first, with winrate as a multiplier. A hero constantly removed from the pool defines the meta as much as one constantly picked, so picks and bans count the same (`contest_rate = pick_rate + ban_rate`):

```
prominence_h = contest_rate_h · (1 + 4·max(0, winrate_h − 0.5))
```

The **eligible pool** is heroes at or above the median contest rate (`META_LEVELS_ELIGIBILITY_MULT` = 1.0). This is what keeps a hero with a great winrate over a handful of games out of the layers entirely. A hero needs `META_LEVELS_MIN_PICKS_FLOOR` = 3 picks *plus bans* to be considered at all.

## Step 3: emergence windows

Heroes are sorted by emergence day and cut into **equal-count windows**; window *w* is layer *w*'s pool of new arrivals. The layer count is

```
k = clamp( min( ⌈days / 3⌉, ⌊|pool| / 8⌋ ), 1, 12 )
```

bounded by both how long the timeline is and how many heroes there are to go round

Hero turnover isn't uniform over a season, and equal-duration windows leave some layers with nothing to draw a core from.

```mermaid
flowchart TD
  Pool["eligible heroes"] --> Sort["sort by emergence day<br/>nulls last, then prominence"]
  Sort --> K["k = clamp(min(⌈days/3⌉, ⌊|pool|/8⌋), 1, 20)"]
  K --> Slice["equal-count slices of size ⌈|pool|/k⌉"]
  Slice --> W0["window 0 → Meta-0 candidates"]
  Slice --> W1["window 1 → Meta-1 candidates"]
  Slice --> Wn["window n"]
```

## Step 4: the core of each layer

Candidates are that window's **new arrivals**. They qualify on prominence alone:

```
score_h = prominence_h · (1 + counter_bonus(h, core_{w-1}))
```

Scoring the whole pool instead looks reasonable and is badly wrong: prominence barely changes between windows, so the same few most-picked heroes win every window they aren't explicitly blocked from, producing an A/B/A/B ping-pong and crowding genuine late emergers out of the final
layers.

A former core hero **may return**, but it's an exception with a price of admission: it must have sat out `META_LEVELS_RETURN_COOLDOWN` = 3 layers, be measurably more picked during this window than across the report as a whole (`META_LEVELS_RETURN_RESURGENCE` = 1.15 -- a real resurgence, not just a big name), and beat the previous core. At most 2 per layer, discounted x0.9 so a layer stays mostly its own era.

### The counter bonus

Given hero *h* and a core *C*, over pairings with at least `META_LEVELS_MIN_PAIR_MATCHES` = 5
games:

```
beat_share = |{c ∈ C : winrate(h,c) > 0.5}| / |C_trusted|
edge       = mean(winrate(h,c) − 0.5)
bonus      = max(0, 2·(beat_share − ½)) · (1 + 4·max(0, edge))
```

**`beat_share`, not the aggregate winrate.** Counter relationships in Dota are cyclic: a genuine
answer beats *part* of a core decisively while losing mildly to the rest. Averaged across six
heroes that nets out near even and the hero looks like nothing — in testing, all 46 candidates
had head-to-head data and *zero* cleared an aggregate-winrate bar.

```mermaid
flowchart TD
  Win["window arrivals"] --> Self{"in previous core?"}
  Self -->|yes| Drop["skip: nothing answers itself"]
  Self -->|no| Sc["score = prominence × (1 + counter_bonus)"]
  Pool["rest of eligible pool"] --> Ret{"sat out ≥ 3 layers<br/>AND window share ≥ 1.15 × overall<br/>AND counter_bonus > 0?"}
  Ret -->|yes| Disc["score × 0.9, at most 2 returns"]
  Ret -->|no| SkipR["not a return"]
  Sc --> Cut
  Disc --> Cut["dynamic_cutoff<br/>within 0.3 of best<br/>4–12 heroes"]
```

```mermaid
flowchart LR
  H["candidate h"] --> Pairs["hvh vs each core hero<br/>matches ≥ 5"]
  Pairs --> BS["beat_share = fraction WR > 0.5"]
  Pairs --> ED["edge = mean(WR − 0.5)"]
  BS --> B["bonus = max(0, 2·(beat_share − ½)) · (1 + 4·max(0, edge))"]
  ED --> B
```

## Step 5: complimentary heroes

Computed **after every core is final**, for a pair (core hero *a*, candidate *o*) from `hph`:

```
requires: matches(a,o) ≥ 5,  matches > expected,  wr_diff > 0
score_o = max over a of  (matches − expected) · (1 + 5·wr_diff)
```

## Step 6: layer sizing, loops, normalisation

Layers aren't a fixed size. `meta_levels_dynamic_cutoff()` takes everyone within `META_LEVELS_CORE_RATIO` = 0.3 of the window's best score, bounded by `META_LEVELS_MIN_CORE` = 4 and `META_LEVELS_MAX_CORE` = 8, topping up from the next-best candidates rather than emitting a thin layer.

`meta_levels_normalize()` then enforces, **to a fixed point** (the two rules re-trigger each other):

1. no hero is core in two consecutive layers: layer N+1 answers layer N, and nothing answers itself;
1. layers under the minimum are folded into the previous one.

A **loop** is annotated when Jaccard similarity between two layers' cores reaches `META_LEVELS_LOOP_SIMILARITY` = 0.5, provided both have ≥ 3 heroes (below that, similarity is
coincidence).

```mermaid
flowchart TD
  Cores["raw cores from every window"] --> Pass["repeat up to 8 times"]
  Pass --> Dupes{"any hero in core N and core N+1?"}
  Dupes -->|yes| Strip["drop those heroes from N+1"]
  Dupes -->|no| Thin{"any core smaller than MIN_CORE?"}
  Strip --> Thin
  Thin -->|yes, i>0| Fold["merge into previous layer, drop this one"]
  Thin -->|empty Meta-0 and more layers| Drop0["drop empty Meta-0"]
  Thin -->|no| Done["sequence is stable"]
  Fold --> Pass
  Drop0 --> Pass
  Done --> Jaccard["tag loops_to when Jaccard ≥ 0.5<br/>and both cores have ≥ 3 heroes"]
```

## Step 7: projections

Up to `META_LEVELS_MAX_PROJECTIONS` = 3 layers past the last real one, each chained off the previous projection.

Projections draw from a **wider, less-picked band** (≥ 0.5× median contest rate): the layers consume nearly all the prominent heroes.

The evidence bar **loosens with each step** (`min_pairs = max(1, 3 − step)`), because each step's reference core is itself speculative and its samples are thin. Holding every step to the first step's bar is what made the chain stop after one projection on long reports.

If the trend path still can't fill a layer, it falls back to a **loop**: the earlier layer whose core best beats the current reference, flagged `method: 'loop'` with `loops_to`.

```mermaid
flowchart TD
  Last{"last observed layer<br/>already loops?"}
  Last -->|yes| Replay["replay the loop period<br/>for MAX_PROJECTIONS steps"]
  Last -->|no| Step["for each projection step"]
  Step --> Wide["pool at ≥ 0.5× median pick rate<br/>block last 2 cores"]
  Wide --> Trend["score vs reference core<br/>min_pairs = max(1, 3 − step)"]
  Trend --> Full{"core ≥ MIN_CORE?"}
  Full -->|yes| Keep["method: trend<br/>chain reference = this core"]
  Full -->|no| Best["earlier layer whose core<br/>best beats the reference"]
  Best --> LoopP["method: loop, loops_to that layer"]
  Keep --> Next{"more steps?"}
  LoopP --> Next
  Next -->|yes| Step
  Next -->|no| Stop["projections[]"]
```

## Heroes of a player or a team

Same Wilson score per hero, `confidence = 1 − matches/games` (games = how many matches the player or team played), blended with contest the way the heroes list is. For a player, contest is the number of games on the hero; for a team it's the team's picks plus the bans opponents spent on that hero against the team (`pickban_vs`).

The "too few games" bar adapts to the sample: `max(1, min(3, ⌊games/5⌋))`, so a short roster history doesn't push everything into **Unplaced**.

## Output model

API omits `emergence`; the view uses cores/combos only. `reasoning` tags heroes as `meta0_founder`, `counters_*`, `carried_over`, or `combo_piece`.

```mermaid
classDiagram
  class MetaLevelsResult {
    layers : Layer[]
    projections : Projection[]
    emergence : map heroId Emergence
  }
  class Layer {
    core : heroId[]
    combo : heroId[]
    reasoning : map
    loops_to : int?
  }
  class Projection {
    method : trend or loop
    core : heroId[]
    combo : heroId[]
    loops_to : int?
    loop_period : int?
    loop_start : int?
  }
  class Emergence {
    emerged : unix or null
    peak_share : float
    pick_rate : float
    ban_rate : float
    contest_rate : float
    prominence : float
    overall_wr : float
    overall_picks : int
  }
  MetaLevelsResult --> Layer
  MetaLevelsResult --> Projection
  MetaLevelsResult --> Emergence
```

---

# Tier Lists

Structured as `Heroes → [Meta, Players, Teams]`, `Players → Heroes`, `Teams → Heroes`; all soft-gen:

- **Heroes → Meta** -- overall, one list per role, and all roles side by side (one column per role).
- **Heroes → Players** -- pick a hero, see who performs best on it.
- **Heroes → Teams** -- the same by team.
- **Players → Heroes** -- pick a player, see tiers of the heroes they play. Linked from the player profile, after the heroes table.
- **Teams → Heroes** -- the same for a team; it is the very list shown in the team profile (`heroes → tierlist`).

Surfaces: web view `tierlists-heroes-{meta,players,teams}`, `tierlists-players-heroes-playerid<id>`, `tierlists-teams-heroes-team<id>` (the old `tierlists-heroes-total`, `tierlists-players-heroid<id>` etc. are redirected).
WebAPI `mod=tierlists-heroes-meta[-position_1.1]`, `tierlists-heroes-players-heroid<id>`, `tierlists-heroes-teams-heroid<id>`, `tierlists-players-heroes-playerid<id>`, `tierlists-teams-heroes-team<id>`; the legacy `tierlists-players` / `tierlists-teams` with `hero=` still mean players/teams on a hero.

All cards in these lists can be collapsed with the **Simple view** toggle (next to the explainer; also on meta levels and builds): labels disappear and unravel on hover, the state lives in `localStorage`.

```mermaid
flowchart TB
  subgraph kinds ["Three lists"]
    H["heroes"]
    P["players on a hero"]
    T["teams on a hero"]
    HP2["heroes of a player"]
    HT["heroes of a team"]
  end
  PB["pickban"] --> Overall["tier_list_heroes_overall"]
  HP["hero_positions"] --> Role["tier_list_heroes_position"]
  Overall --> H
  Role --> H
  Matches["matches + matches_additional"] --> PStats["tier_list_player_hero_stats"]
  Teams["teams[].pickban"] --> TStats["tier_list_team_hero_stats"]
  PStats --> Rec["tier_list_entities_by_record"]
  TStats --> Rec
  Rec --> P
  Rec --> T
  PStats --> EH["tier_list_entity_heroes"]
  TH["team pickban + pickban_vs"] --> EH
  EH --> HP2
  EH --> HT
  ML["meta_levels_from_report"] --> Boost["tier_list_apply_meta_boost"]
  Boost --> Overall
  Boost --> Role
```

## Heroes: the score

The base is the same **rank** the pick/ban and positions tables show: a Wilson score folding popularity and winrate together, normalised to 0..100 across the field. Then two corrections are applied.

**Contest.** A hero can rank well off a small favourable sample, and a hero nobody dares to leave in the pool can win few games when it does get picked. So the score blends the rank with how contested the hero was, picks **plus bans**, relative to the most contested hero:

```
score = 100 · (rank/100)^0.75 · (contest / max contest)^0.25
```

(`TIER_LIST_SKILL_WEIGHT` / `TIER_LIST_CONTEST_WEIGHT`.) A hero picked 8 times and banned 139 of 147 matches with 87.5 % winrate sits in S; a hero with a perfect rank off three games doesn't. For a role list, bans aren't tracked per role, so a hero's bans are split between its roles by the share of its picks.

**Meta levels**, when available. Being part of a formed meta is evidence the raw rank of a niche-but-effective hero doesn't capture. For a hero in layer *i* of *n* (projections counted as the last layers):

```
boost = (3 + 5 · i/(n−1)) × (1 for core, 0.5 for complimentary)
```

Later layers get more -- they're the more current answers. The final ordering score is `score + boost`, clamped to 100.

**Not meta** is no longer a score cutoff. A hero goes there when both of these hold: picks below `TIER_LIST_PICKS_FLOOR` = 0.9× the median number of picks, and contest rate below `TIER_LIST_CONTEST_RATE_FLOOR` = 5 % (picks plus bans, over the matches in the list). Either one on its own keeps the hero tiered, so a rarely picked hero that is heavily banned stays in, and a modestly picked hero stays in whatever its contest rate.

Contest still moves a hero up or down inside the tiers, and that can park a badly performing hero next to much stronger ranks. There is no fixed rank floor for that. After the presence check, the bottom of the plain-rank list is split off into **Not meta** only when the step up to the next hero is more than `TIER_LIST_RANK_GAP` = 4 times the typical step. A rank of 11 falling away from a cluster at 30+ goes; a smooth run of close ranks stays.

```mermaid
flowchart TD
  Ctx["pickban or one hero_positions lane"] --> Rank["Wilson wrank<br/>compound_ranking / positions_ranking"]
  Rank --> Norm["normalize min..max → 0..100"]
  Norm --> Pop["blend with contest (picks+bans)<br/>rank^0.75 · (contest/max)^0.25"]
  ML["meta levels"] --> MB["boost = (3 + 5·i/(n-1)) × factor<br/>core 1.0, complimentary 0.5"]
  Pop --> Sum["score = min(100, blend + boost)"]
  MB --> Sum
  Sum --> Floor{"picks < 0.9 × median picks<br/>and contest rate < 5%?"}
  Floor -->|yes| NM["Not meta"]
  Floor -->|no| Gap{"plain rank separated from<br/>the cluster above by a large gap?"}
  Gap -->|yes| NM
  Gap -->|no| Split["tier_list_split on percentiles"]
```

Displayed rank in the UI is still the **plain** 0..100 Wilson rank (`base`). Contest and meta boost only decide which letter the hero sits in.

## Cutting into tiers

Tiers are cut on **percentile**, not on the raw rank value.

The cut points are **not** six equal slices either. `TIER_LIST_SHARES` gives each tier a share of the field, best first:

| S | A | B | C | D | E |
|---|---|---|---|---|---|
| 7.5 % | 14 % | 20.5 % | 23.5 % | 20.5 % | 14 % |

Roughly a normal distribution centred on **C**, so the bulk of the roster sits mid-table and S is a genuine outlier rather than a flat sixth of everything.

It's really a bell over *seven* tiers — S A B C D E **F**, with C dead centre, but F is never rendered.

```mermaid
flowchart LR
  Scores["eligible scores, high to low"] --> Pct["percentile of each score<br/>ties get the mid-rank"]
  Pct --> Floors["cumulative TIER_LIST_SHARES<br/>S 7.5% → A 14% → ... → E"]
  Floors --> Letter["first floor the percentile clears"]
  Letter --> Ranges["ranges[letter] = plain-rank span<br/>at those percentiles"]
```

## Players and teams on a hero

No contest or meta correction, just performance on the selected hero, by Wilson score so a short strong run doesn't outrank a long record on noise:

```
wilson(wins, matches, confidence = 1 − matches/total)
```

Player + hero records are walked out of the baked `matches` list; team + hero records come straight off each team's `pickban` block.

Entities under `TIER_LIST_MIN_MATCHES` = 3 games on the hero go to an **Unplaced** bucket.

```mermaid
flowchart TD
  Src{"source"}
  Src -->|players| M["walk report.matches<br/>win if radiant_win matches side"]
  Src -->|teams| TP["each team's pickban block"]
  M --> Rec["wins, matches per entity"]
  TP --> Rec
  Rec --> W["wilson_rating(wins, matches,<br/>confidence = 1 − matches/total)"]
  W --> N["normalize 0..100"]
  N --> Min{"matches ≥ 3?"}
  Min -->|no| U["Unplaced"]
  Min -->|yes| Split["same S–E percentile split"]
```

## Output model

`not_meta` (heroes) / unplaced ids (players, teams) live on the same `tiers` map. `ranges` is the displayed Wilson-rank span, not the boosted ordering score.

```mermaid
classDiagram
  class TierListResult {
    tiers : map letter heroOrEntityId[]
    ranges : map letter [min, max]
    ranks : map id boostedScore
    base : map id plainRank
  }
  class HeroTiers {
    type = heroes
    position : core.lane?
  }
  class EntityTiers {
    type = players or teams
    hero : int
  }
  class EntityHeroTiers {
    type = heroes
    player : int?
    team : int?
  }
  TierListResult <|-- HeroTiers
  TierListResult <|-- EntityTiers
  TierListResult <|-- EntityHeroTiers
```

---

## Tuning

Every constant is at the top of its functions file, and each is a named `const` with a comment.
The ones most likely to want adjusting:

| Constant | Effect |
|---|---|
| `META_LEVELS_TARGET_WINDOW` | heroes per window → how loaded layers are, and how many there are |
| `META_LEVELS_BUCKETS_PER_LAYER` | how quickly timeline length turns into more layers |
| `META_LEVELS_MIN_PAIR_MATCHES` | evidence required before a matchup counts |
| `META_LEVELS_RETURN_COOLDOWN` / `_RESURGENCE` | how readily heroes come back |
| `TIER_LIST_PICKS_FLOOR` / `TIER_LIST_CONTEST_RATE_FLOOR` | both required for "Not meta": low picks and a low contest rate |
| `TIER_LIST_RANK_GAP` | how wide a plain-rank gap at the bottom has to be before that tail is "Not meta" |
| `TIER_LIST_SKILL_WEIGHT` / `TIER_LIST_CONTEST_WEIGHT` | how much winrate vs. contest decides the tier |
| `TIER_LIST_META_BOOST_BASE` / `_STEP` | how much meta-level membership is worth |
| `TIER_LIST_TIERS` | the tier letters themselves — the split adapts to however many there are |

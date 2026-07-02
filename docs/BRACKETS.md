# Bracket reconstruction

LRG generally stored only match info, without any series related details. At some point series automatic detection was introduced, but it was still lacking in some aspects:
- remade matches could be assigned to a different series
- teams could forget to use proper team ID or swap it mid series
etc.

Series ID was introduced to the database later on to increase certainty. And all these changes, in turn, allowed for creation of a system that "reconstructs" tournament brackets, based on match/series data, using heuristics.

Automatic detection is never perfect, but it is also possible to enforce specific rules, using report configuration (see `templates/bracket_config.example.json`).

It was tested mostly on DPC and TI reports, multi-event reports (to ensure it does sub-event detection properly), some edge cases (e.g. winner of the series received a tech loss), as well as some events with weird and uncertain events, like amateur leagues, Omega League, RD2L, AD2L, etc.

It was tuned to give somewhat sensible results for almost any event, so in most cases event format hinting should not be needed, and thus should be avoided generally, esepecially since it can only work after the report was updated and settings were recorded. It won't work retroactively on "broken" reports. And the more chaotic the report is in terms of team ID usage, roster shifts and match dates -- the more chaotic the result.

It will also not work on report of PvP type or mixed leagues without match information, or else.

Entry points:
- web_view: generator `bracket_render()`
- webapi: generator `bracket_json()`

Primarily generated live in the web version, but it can be generated and saved in the analyzer loop (`ana.bracket = true`).

## Main flow

(not counting small helpers)

- view -> generators -> bracket.php ->
  - bracket_generate()
    - bracket_report_matches()
    - view -> functions -> bracket -> 
      - ./roster.php
        - tb_fix_split_rosters()
        - tb_merge_roster_aliases()
    - bracket_team_index()
    - bracket_config()
    - view -> functions -> bracket -> 
      - ./series.php
        - tb_build_series()
          - tb_dedup_series()
      - ./config.php
        - tb_apply_overrides()
      - ./split.php
        - tb_split_events()
          - tb_partition_units()
          - tb_name_units()
      - ./analyze.php
        - tb_analyze_event()
          - tb_analyze_interest()
          - tb_phases_from_hint() /
            - tb_refine_phases()
            - tb_detect_phases()
            - tb_peel_outlier_wildcard()
          - tb_analyze_phase()
          - !hinted ->
            - tb_fold_back_strays()
            - tb_pp_tiebreaker_playoff()
            - tb_pp_group_to_bracket()
            - tb_pp_group_decider()
            - tb_pp_merge_brackets()
            - tb_pp_fold_seed_round()
            - tb_pp_fold_late_seeding()
          - tb_color_group_stages

## Files (by pipeline stage)

| File | Responsibility |
|------|----------------|
| `helpers.php` | Tiny shared utils: `tb_unique_teams`, `tb_rounds_series`, `tb_next_pow2`, union-find. |
| `config.php` | Event format hinting. Parsing + `tb_phases_from_hint`, `tb_apply_overrides`, `tb_divide_series`. |
| `series.php` | Matches → **series** (one per matchup: teams, score, bo, winner, flags). |
| `roster.php` | Fix mis-attributed `team_id`s (a borrowed/split roster) before series are built. |
| `split.php` | Series → **events** (separate sub-tournaments): forced divisions, interest recap, region/league-id/time partition, or a season-long month-grid aggregate. |
| `rounds.php` | Round & group **primitives + classifiers**: `tb_temporal_rounds`, `tb_round_stats`, `tb_find_groups`, `tb_is_elim_phase`, `tb_is_group_stage`, `tb_progression_is_group`, `tb_trailing_playoff`, … |
| `phases.php` | **Phase detection**: `tb_detect_phases` + `tb_boundary_candidates` + `tb_pick_playoff_start`. Splits one event into group/bracket phases. |
| `boundaries.php` | Detection helpers used by `tb_detect_phases`: front/tail/regroup/wildcard cutting (`tb_group_stage_front`, `tb_playoff_tail`, `tb_split_group_rounds`, `tb_rr_playoff_split`, …). |
| `stage.php` | Phase → **stage**: `tb_analyze_phase` (build a group or playoff stage), `tb_analyze_interest` (month/form grid), `tb_color_group_stages` (UB/LB/eliminated coloring). |
| `group.php` | Group standings, format inference (`tb_infer_group_format`: rr / swiss / short_swiss / mixed), tiebreakers, grids. |
| `bracket.php` | Playoff tree build (`tb_build_bracket`): UB/LB/GF assignment by loss-tracking, LB-seed detection, outcome repair. |
| `postpass.php` | Whole-event **structural repairs** that per-phase detection can't see: `tb_fold_back_strays` + the `tb_pp_*` passes. |
| `analyze.php` | **Orchestration**: `tb_analyze_event` (drives the per-event pipeline), `tb_refine_phases`, `tb_split_division_events`, and report/event-level heuristics. |

### tb_analyze_event (one event → stages)

1. **interest / aggregate** → `tb_analyze_interest` (a month-by-month form grid), for recaps and season pools too incoherent to be one tournament.
2. **phases**: forced shape → `tb_phases_from_hint` (config.php); otherwise `tb_detect_phases` (phases.php) then `tb_refine_phases` (reclassify a "bracket" that's really a group, merge split groups, carve a trailing playoff).
3. **each phase -> a stage** via `tb_analyze_phase` (stage.php) → `tb_build_group` (group.php) or `tb_build_bracket` (bracket.php).
4. **structural post-passes** (postpass.php, *skipped when the shape is forced*): `tb_fold_back_strays` then the `tb_pp_*` repairs (promote tiebreakers to a playoff, group→bracket, deciders, merge brackets, fold seed rounds).
5. `tb_color_group_stages` colors standings by who advanced.

### How tb_detect_phases routes (group vs bracket, where the playoff starts)

Series are first grouped into **temporal rounds** (`tb_temporal_rounds`: by day, splitting on gaps). Then, in order:

1. **Quick routes**
   - round-robin-then-playoff (`tb_rr_playoff_split`)
   - a single round (one connected component ⇒ bracket, else group)
   - a **wildcard front** (`tb_wildcard_front`)
   - an all-elimination event with no group (`tb_is_elim_phase` + `tb_playoff_tail`)
2. **Boundary candidates** (`tb_boundary_candidates`) round indices that could be the group→playoff seam: a 3+ day calendar gap, or where a multi-group stage collapses back to one connected component.
3. **Pick the playoff start** (`tb_pick_playoff_start`) -- the latest candidate whose tail is a single connected bracket (`tb_after_looks_bracket`), unless an intervening group-like block means an earlier seam is the real one.
4. **Adjustments** -- regroup block (`tb_find_regroup_block`), group-stage front (`tb_group_stage_front`), and a second cohort entering mid-event (riyadh-style).
5. **Split & peel** -- cut into `group_rounds` + `playoff_rounds`, peel any elimination rounds off the back of the group, and pull out a wildcard phase.

A phase's `is_elim` flag then decides group vs bracket; `tb_refine_phases` corrects obvious misreads (round-robin coverage, repeated meetings, or genuine ties - which elimination forbids - mark it a group).

### group vs bracket build

- **group** (`tb_build_group`): standings + `tb_infer_group_format` -- full pairwise coverage -> `round_robin`; no rematches with a handful of games each -> `swiss`/`short_swiss`; else `mixed`.
- **bracket** (`tb_build_bracket`): assign each series to UB/LB/GF by tracking losses chronologically; teams that open in the lower bracket (TI-style bottom half) are detected (`tb_detect_lb_seeds`) and given a phantom loss; outcomes are repaired for connectivity (tech-loss / orphan-winner).

<?php
# Some of these should be stored in SQL database, but I don't want to
# connect for it every time.
# I guess I'll dublicate league and analyzer parameters, so
# it will be able to restore data.

# global settings

# SQL Connection information

  $lrg_sql_host = "localhost";
  $lrg_sql_user = "root";
  $lrg_sql_pass = "";


#TODO settings prefix

  $lrg_league_name = "test";
  $lrg_league_desc = "Test Test Test";

  $lrg_sql_db   = "d2_league_".$lrg_league_name;

  $lrg_use_cache = true;

  $lg_settings = array(
    "main"=> array(),
    "ana" => array(),
    "web" => array()
  );

# League Parameters

  $lg_settings['main']['teams'] = false; # set team or player mix competition
                      # false = players competition
                      # true  = teams competition

  $lg_settings['main']['fantasy'] = false; # not implemented yet TODO

# TODO

# Init module

# TODO

# Fetcher module

# Player Info Module

# TODO
# use player nicknames

# Analyzer Module



$lg_settings['ana']['records']     = true; # records
$lg_settings['ana']['avg_heroes']  = true; # averages for heroes
$lg_settings['ana']['avg_players'] = true; # averages for players

$lg_settings['ana']['pickban']     = true; # pick/ban heroes stats

$lg_settings['ana']['hero_positions']             = true; # heroes on positions
$lg_settings['ana']['hero_positions_matches']     = true; #   include matchids
$lg_settings['ana']['hero_positions_player_data'] = true;
    # team games only: rely on player's positions instead of lanes
$lg_settings['ana']['hero_sides'] = true; # hero stats on sides

$lg_settings['ana']['draft_stages'] = true; # pick/ban draft stages stats

$lg_settings['ana']['hero_pairs']            = true; # hero pairs winrates
$lg_settings['ana']['hero_pairs_matches']    = true; #   include matchids

$lg_settings['ana']['hero_triplets']          = true; # hero triplets winrates
$lg_settings['ana']['hero_triplets_matches'] = true; #   include matchids

$lg_settings['ana']['matchlist'] = true; # matches list + drafts in matches and participants

# PLAYERS ONLY (only work with $lg_settings['main']['teams'] = false)
$lg_settings['ana']['pvp'] = true; # players only: player vs player winrates
$lg_settings['ana']['pvp_matches'] = true;

$lg_settings['ana']['player_positions'] = true; # players stats on positions
$lg_settings['ana']['player_positions_matches'] = true;

$lg_settings['ana']['player_pairs'] = true; # player pairs
$lg_settings['ana']['player_pairs_matches'] = true;

$lg_settings['ana']['player_triplets'] = true; # player triplets
$lg_settings['ana']['player_triplets_matches'] = true;

$lg_settings['ana']['player_vs_hero'] = false; # not implemented yet TODO
$lg_settings['ana']['player_hero_combos'] = false; # not implemented yet TODO
$lg_settings['ana']['player_hero_stats'] = false;  # not implemented yet TODO

$lg_settings['ana']['hero_vs_hero'] = false; # not implemented yet TODO

if($lg_settings['main']['teams']) {
  $lg_settings['ana']['teams'] = array();
  # TEAMS ONLY (only workwith $lg_settings['main']['teams'] = true)
  $lg_settings['ana']['teams']['rosters']  = true;
  $lg_settings['ana']['teams']['avg']      = true; # TODO
  $lg_settings['ana']['teams']['pickbans'] = true;
  $lg_settings['ana']['teams']['draft']    = true;
  $lg_settings['ana']['teams']['heropos']  = true;
  $lg_settings['ana']['teams']['pairs']    = true;
  $lg_settings['ana']['teams']['triplets'] = true;
  $lg_settings['ana']['teams']['matches']  = true;
  # teams only: team stats
  #   total games, winrate, average k / d / a / xpm / gpm / wards / wards_destroyed
  #   pick/ban stats
  #   draft stages stats
  #   heroes on positions
  #   hero pairs
  #   hero triplets
  #   matches list
  $lg_settings['ana']['teams']['team_vs_team']   = true; # TODO
}

$lg_settings['web'] = array(
  "custom_style" => "sa",
  "pvp_grid" => true,

  "hero_combos_graph" => true,
  "player_combos_graph" => true,
  "overview_charts" => true,
  "overview_regions" => true,
  "overview_modes" => true,
  "overview_records" => true,
  "overview_top_contested" => true,
  "overview_top_1st_stage" => true,
  "overview_top_positions" => true
);

?>

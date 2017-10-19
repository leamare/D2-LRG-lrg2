<?php
# Some of these should be stored in SQL database, but I don't want to
# connect for it every time.
# I guess I'll dublicate league and analyzer parameters, so
# it will be able to restore data.

# global settings

  $lrg_locale = "en";

# SQL Connection information

  $lrg_sql_host = "localhost";
  $lrg_sql_user = "root";
  $lrg_sql_pass = "";


#TODO settings prefix

  $lrg_league_name = "test";
  $lrg_league_desc = "Test Test Test";

# League Parameters

  $lrg_teams = false; # set team or player mix competition
                      # false = players competition
                      # true  = teams competition

$lrg_sql_db   = "d2_league_".$lrg_league_name;

# TODO

# Init module

# TODO

# Fetcher module

$lrg_input  = $lrg_league_name.".list";

# Player Info Module

# TODO
# use player nicknames

# Analyzer Module

$lrg_ana_records     = true; # records
$lrg_ana_avg_heroes  = true; # averages for heroes
$lrg_ana_avg_players = true; # averages for players

$lrg_ana_pickban     = true; # pick/ban heroes stats

$lrg_ana_heroes_positions          = true; # heroes on positions
$lrg_ana_heroes_positions_matchids = true; #   include matchids
$lrg_ana_heroes_positions_based_on_players = true;
    # team games only: rely on player's positions instead of lanes
$lrg_ana_heroes_stats_sides = true; # hero stats on sides

$lrg_ana_draft_stages = true; # pick/ban draft stages stats

$lrg_ana_hero_pairs             = true; # hero pairs winrates
$lrg_ana_hero_pairs_matchids    = true; #   include matchids

$lrg_ana_hero_triplets          = true; # hero triplets winrates
$lrg_ana_hero_triplets_matchids = true; #   include matchids

$lrg_ana_matchlist = true; # matches list + drafts in matches and participants
$lrg_ana_matchlist_players = true; #   players: player list

# PLAYERS ONLY (only work with $lrg_teams = false)
$lrg_ana_player_vs_player = true; # players only: player vs player winrates
$lrg_ana_player_positions_stats = true; # players stats on positions
$lrg_ana_player_pairs = true; # player pairs
$lrg_ana_player_triplets = true; # player triplets


# TEAMS ONLY (only workwith $lrg_teams = true)
$lrg_ana_teams_rosters  = true;
$lrg_ana_teams_avg      = true;
$lrg_ana_teams_pickbans = true;
$lrg_ana_teams_draft    = true;
$lrg_ana_teams_heropos  = true;
$lrg_ana_teams_pairs    = true;
$lrg_ana_teams_triplets = true;
$lrg_ana_teams_matches  = true;
# teams only: team stats
#   total games, winrate, average k / d / a / xpm / gpm / wards / wards_destroyed
#   pick/ban stats
#   draft stages stats
#   heroes on positions
#   hero pairs
#   hero triplets
#   matches list
$lrg_ana_team_vs_team   = true;

?>

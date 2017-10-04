<?php
  include_once("settings.php");

  # TODO
  # Analyzer module
  # JSON output

  echo("\nConnecting to database...\n");

  $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

  if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

  $result = array ();
  $result["leaguetag"]  = $lrg_league_name;
  $result["leaguedesc"] = $lrg_league_desc;


  if ($lrg_ana_records) {
    # records

    #  (type, value, matchid, player, hero)

    # record types: GPM XPM Wards Sentries
    # kills deaths assists Networth
    # heal heroDamage towerDamage
    # lane_efficiency
    # biggest stomp
    # biggest comeback
    # couriers_killed_in_game roshans_killed_in_game
    # game Length
    # wards destroyed
    # pings stuns
  }

  if ($lrg_ana_avg_heroes) {
    # average for heroes

    ORDER BY

    # k d a xpm gpm heroDMG/min towerDMG/min
    # lane_efficiency courier_kills stuns
  }

  if ($lrg_ana_avg_players) {
    # average for heroes

    ORDER BY

    # k d a xpm gpm heroDMG/min towerDMG/min
    # lane_efficiency courier_kills stuns
    # wards destroyed
  }

  if ($lrg_ana_pickban) {
    # pick/ban heroes stats
  }

  if ($lrg_ana_heroes_positions) {
    # heroes on positions

    GROUP BY HEROID

    # k d a xpm gpm heal/min heroDMG/min towerDMG/min
    # lh_at10
    # game Length
    # wnrate

    # $lrg_ana_heroes_positions_based_on_players

    if ($lrg_ana_heroes_positions_matchids) {
      #   include matchids
    }
  }

  if ($lrg_ana_heroes_stats_sides) {

  }

  if ($lrg_ana_draft_stages) {
    # pick/ban draft stages stats
  }

  if ($lrg_ana_hero_pairs) {


    if ($lrg_ana_hero_pairs_matchids) {

    }
  }

  if ($lrg_ana_hero_triplets) {


    if ($lrg_ana_hero_triplets_matchids) {

    }
  }

  if ($lrg_teams) {
    # team competitions placeholder
  } else {
    echo "[ ] Working for players competition...";

    if ($lrg_ana_player_vs_player) {

    }

    if ($lrg_ana_player_positions_stats) {

    }

    # placeholder

  }

  if ($lrg_ana_matchlist) {
    # $lrg_ana_matchlist_players
  }

 ?>

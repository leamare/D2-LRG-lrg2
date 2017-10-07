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
    $result["records"] = array();

    # gpm
    $sql  = "SELECT \"gpm\", matchid, gpm, playerid, heroid FROM matchlines ORDER BY gpm DESC;";
    # xpm
    $sql .= "SELECT \"xpm\", matchid, xpm, playerid, heroid FROM matchlines ORDER BY xpm DESC;";
    # kills
    $sql .= "SELECT \"kills\", matchid, kills, playerid, heroid FROM matchlines ORDER BY kills DESC;";
    # deaths
    $sql .= "SELECT \"deaths\", matchid, deaths, playerid, heroid FROM matchlines ORDER BY deaths DESC;";
    # assists
    $sql .= "SELECT \"assists\", matchid, assists, playerid, heroid FROM matchlines ORDER BY assists DESC;";
    # networth
    $sql .= "SELECT \"networth\", matchid, networth, playerid, heroid FROM matchlines ORDER BY networth DESC;";
    # hero damage
    $sql .= "SELECT \"hero_damage\", matchid, heroDamage, playerid, heroid FROM matchlines ORDER BY heroDamage DESC;";
    # tower damage
    $sql .= "SELECT \"tower_damage\", matchid, towerDamage, playerid, heroid FROM matchlines ORDER BY towerDamage DESC;";
    # heal
    $sql .= "SELECT \"heal\", matchid, heal, playerid, heroid FROM matchlines ORDER BY heal DESC;";

    # damage taken
    $sql .= "SELECT \"damage_taken\", matchid, damage_taken, playerid, heroid FROM adv_matchlines ORDER BY damage_taken DESC;";
    # lane efficiency
    $sql .= "SELECT \"lane_efficiency\", matchid, efficiency_at10, playerid, heroid FROM adv_matchlines ORDER BY efficiency_at10 DESC;";
    # wards
    $sql .= "SELECT \"wards_placed\", matchid, wards, playerid, heroid FROM adv_matchlines ORDER BY wards DESC;";
    # sentries
    $sql .= "SELECT \"sentries_placed\", matchid, sentries, playerid, heroid FROM adv_matchlines ORDER BY sentries DESC;";
    # teamfight participation
    $sql .= "SELECT \"teamfight_participation\", matchid, teamfight_part, playerid, heroid FROM adv_matchlines ORDER BY teamfight_part DESC;";
    # wards destroyed
    $sql .= "SELECT \"wards_destroyed\", matchid, wards_destroyed, playerid, heroid FROM adv_matchlines ORDER BY wards_destroyed DESC;";
    # pings by player
    $sql .= "SELECT \"pings\", matchid, pings, playerid, heroid FROM adv_matchlines ORDER BY pings DESC;";
    # stuns
    $sql .= "SELECT \"stuns\", matchid, stuns, playerid, heroid FROM adv_matchlines ORDER BY stuns DESC;";
    # courier kills by player
    $sql .= "SELECT \"couriers_killed_by_player\", matchid, couriers_killed, playerid, heroid FROM adv_matchlines ORDER BY couriers_killed DESC;";

    # couriers killed in game
    $sql .= "SELECT \"couriers_killed_in_game\", matchid, SUM(couriers_killed) cours, 0, 0 FROM adv_matchlines ORDER BY cours  DESC GROUP BY matchid;";
    # roshans killed in game
    $sql .= "SELECT \"roshans_killed_in_game\", matchid, SUM(roshans_killed) roshs, 0, 0 FROM adv_matchlines ORDER BY roshs  DESC GROUP BY matchid;";

    # stomp
    $sql .= "SELECT \"stomp\", matchid, stomp, 0 playerid, 0 heroid FROM matches ORDER BY stomp DESC;";
    # comeback
    $sql .= "SELECT \"comeback\", matchid, comeback, 0 playerid, 0 heroid FROM matches ORDER BY comeback DESC;";
    # length
    $sql .= "SELECT \"duration\", matchid, duration, 0 playerid, 0 heroid FROM matches ORDER BY duration DESC;";

    #   playerid and heroid = 0 for matchrecords

    # TODO request to sql and copy that to array

    $result["records"] = array();

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for RECORDS.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    do {
      $query_res = $conn->store_result();

      $row = $query_res->fetch_row();

      $result["records"][$row[0]] = array (
        "matchid"  => $row[1],
        "value"    => $row[2],
        "playerid" => $row[3],
        "heroid"   => $row[4]
      );

      $query_res->free_result();

      if(is_object($result)) {
          $result->free_result();
      }
    } while($conn->next_result());
  }

  if ($lrg_ana_avg_heroes) {
    # average for heroes

    # kills
    $sql  = "SELECT \"kills\", heroid, SUM(kills)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";
    # deaths
    $sql .= "SELECT \"deaths\", heroid, SUM(deaths)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value ASC;";
    # assists
    $sql .= "SELECT \"assists\", heroid, SUM(assists)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";

    # hero damage / minute
    $sql .= "SELECT \"hero_damage_per_min\", matchlines.heroid heroid, SUM(matchlines.heroDamage/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC";
    # tower damage / minute
    $sql .= "SELECT \"tower_damage_per_min\", matchlines.heroid heroid, SUM(matchlines.towerDamage/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC";
    # tower damage / minute
    $sql .= "SELECT \"taken_damage_per_min\", adv_matchlines.heroid heroid, SUM(adv_matchlines.damage_taken/(matches.duration/60))/SUM(1)
               value FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid matches GROUP BY heroid ORDER BY value DESC";

    # stuns
    $sql .= "SELECT \"stuns\", heroid, SUM(stuns)/SUM(1) value FROM adv_matchlines GROUP BY heroid ORDER BY value DESC;";
    # courier kills
    $sql .= "SELECT \"courier_kills\", heroid, SUM(couriers_killed)/SUM(1) value FROM adv_matchlines GROUP BY heroid ORDER BY value DESC;";
    # roshan kills by hero's team
    $sql .= "SELECT \"roshan_kills_with_team\", heroid, SUM(rs.rshs)/SUM(1) value FROM matchlines JOIN (
      SELECT matchid, SUM(roshans_killed) rshs FROM adv_matchlines GROUP BY matchid
    ) rs ON matchlines.matchid = rs.matchid GROUP BY heroid ORDER BY value DESC;";

    $result["averages"] = array();

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for RECORDS.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    do {
      $query_res = $conn->store_result();

      $row = $query_res->fetch_row();
      $result["averages"][$row[0]] = array();

      for ($i=0; $i<3 && $row != null; $i++, $row = $query_res->fetch_row()) {
        $result["averages"][sizeof($result["averages"])][$i] = array (
          "heroid" => $row[1],
          "value"  => $row[2]
        );
      }

      $query_res->free_result();

      if(is_object($result)) {
          $result->free_result();
      }
    } while($conn->next_result());
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

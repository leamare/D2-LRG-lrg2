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
    # lasthits
    $sql .= "SELECT \"lasthits\", matchid, lastHits, playerid, heroid FROM matchlines ORDER BY lastHits DESC;";
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
    } while($conn->next_result());
  }

  if ($lrg_ana_avg_heroes) {
    # average for heroes

    # kills
    $sql  = "SELECT \"kills\", heroid, SUM(kills)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";
    # least deaths
    $sql .= "SELECT \"least_deaths\", heroid, SUM(deaths)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value ASC;";
    # most deaths
    $sql .= "SELECT \"most_deaths\", heroid, SUM(deaths)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";
    # assists
    $sql .= "SELECT \"assists\", heroid, SUM(assists)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";

    # gpm
    $sql .= "SELECT \"gpm\", heroid, SUM(gpm)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";
    # xpm
    $sql .= "SELECT \"xpm\", heroid, SUM(xpm)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";
    # last hits per min
    $sql .= "SELECT \"lasthits_per_min\", matchlines.heroid heroid, SUM(matchlines.lastHits/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC;";
    # denies
    $sql .= "SELECT \"denies\", heroid, SUM(denies)/SUM(1) value FROM matchlines GROUP BY heroid ORDER BY value DESC;";

    # hero damage / minute
    $sql .= "SELECT \"hero_damage_per_min\", matchlines.heroid heroid, SUM(matchlines.heroDamage/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC";
    # tower damage / minute
    $sql .= "SELECT \"tower_damage_per_min\", matchlines.heroid heroid, SUM(matchlines.towerDamage/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC";
    # taken damage / minute
    $sql .= "SELECT \"taken_damage_per_min\", adv_matchlines.heroid heroid, SUM(adv_matchlines.damage_taken/(matches.duration/60))/SUM(1)
               value FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC";
    # heal / minute
    $sql .= "SELECT \"heal_per_min\", matchlines.heroid heroid, SUM(matchlines.heal/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY heroid ORDER BY value DESC";

    # stuns
    $sql .= "SELECT \"stuns\", heroid, SUM(stuns)/SUM(1) value FROM adv_matchlines GROUP BY heroid ORDER BY value DESC;";
    # stacks
    $sql .= "SELECT \"stacks\", heroid, SUM(stacks)/SUM(1) value FROM adv_matchlines GROUP BY heroid ORDER BY value DESC;";
    # courier kills
    $sql .= "SELECT \"courier_kills\", heroid, SUM(couriers_killed)/SUM(1) value FROM adv_matchlines GROUP BY heroid ORDER BY value DESC;";
    # roshan kills by hero's team
    $sql .= "SELECT \"roshan_kills_with_team\", heroid, SUM(rs.rshs)/SUM(1) value FROM matchlines JOIN (
      SELECT matchid, SUM(roshans_killed) rshs FROM adv_matchlines GROUP BY matchid
    ) rs ON matchlines.matchid = rs.matchid GROUP BY heroid ORDER BY value DESC;";

    $result["averages_heroes"] = array();

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for AVERAGE FOR HEROES.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    do {
      $query_res = $conn->store_result();

      $row = $query_res->fetch_row();
      $result["averages_heroes"][$row[0]] = array();

      for ($i=0; $i<3 && $row != null; $i++, $row = $query_res->fetch_row()) {
        $result["averages_heroes"][sizeof($result["averages_heroes"])][$i] = array (
          "heroid" => $row[1],
          "value"  => $row[2]
        );
      }

      $query_res->free_result();

    } while($conn->next_result());
  }

  if ($lrg_ana_avg_players) {
    # average for players

    # kills
    $sql  = "SELECT \"kills\", playerid, SUM(kills)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value DESC;";
    # least deaths
    $sql .= "SELECT \"least_deaths\", playerid, SUM(deaths)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value ASC;";
    # most deaths
    $sql .= "SELECT \"most_deaths\", playerid, SUM(deaths)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value DESC;";
    # assists
    $sql .= "SELECT \"assists\", playerid, SUM(assists)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value DESC;";

    # gpm
    $sql .= "SELECT \"gpm\", playerid, SUM(gpm)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value DESC;";
    # xpm
    $sql .= "SELECT \"xpm\", playerid, SUM(xpm)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value DESC;";
    # lane efficiency
    $sql .= "SELECT \"lane_efficiency\", playerid, SUM(efficiency_at10)/SUM(1) value FROM adv_matchlines GROUP BY playerid ORDER BY value DESC;";
    # denies
    $sql .= "SELECT \"denies\", playerid, SUM(denies)/SUM(1) value FROM matchlines GROUP BY playerid ORDER BY value DESC;";

    # hero damage / minute
    $sql .= "SELECT \"hero_damage_per_min\", matchlines.playerid playerid, SUM(matchlines.heroDamage/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY playerid ORDER BY value DESC";
    # tower damage / minute
    $sql .= "SELECT \"tower_damage_per_min\", matchlines.playerid playerid, SUM(matchlines.towerDamage/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY playerid ORDER BY value DESC";
    # taken damage / minute
    $sql .= "SELECT \"taken_damage_per_min\", adv_matchlines.playerid playerid, SUM(adv_matchlines.damage_taken/(matches.duration/60))/SUM(1)
               value FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid GROUP BY playerid ORDER BY value DESC";
    # heal / minute
    $sql .= "SELECT \"heal_per_min\", matchlines.playerid playerid, SUM(matchlines.heal/(matches.duration/60))/SUM(1)
               value FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid GROUP BY playerid ORDER BY value DESC";

    # stuns
    $sql .= "SELECT \"stuns\", playerid, SUM(stuns)/SUM(1) value FROM adv_matchlines GROUP BY playerid ORDER BY value DESC;";
    # courier kills
    $sql .= "SELECT \"courier_kills\", playerid, SUM(couriers_killed)/SUM(1) value FROM adv_matchlines GROUP BY playerid ORDER BY value DESC;";
    # roshan kills by hero's team
    $sql .= "SELECT \"roshan_kills_with_team\", playerid, SUM(rs.rshs)/SUM(1) value FROM matchlines JOIN (
      SELECT matchid, SUM(roshans_killed) rshs FROM adv_matchlines GROUP BY matchid
    ) rs ON matchlines.matchid = rs.matchid GROUP BY playerid ORDER BY value DESC;";
    # wards destroyed
    $sql .= "SELECT \"wards_destroyed\", playerid, SUM(wards_destroyed)/SUM(1) value FROM adv_matchlines GROUP BY playerid ORDER BY value DESC;";

    # longest killstreak
    $sql .= "SELECT \"longest_killstreak_in_match\", playerid, SUM(streak)/SUM(1) value FROM adv_matchlines GROUP BY playerid ORDER BY value DESC;";
    # stacks
    $sql .= "SELECT \"stacks\", playerid, SUM(stacks)/SUM(1) value FROM adv_matchlines GROUP BY playerid ORDER BY value DESC;";
    # pings per minute
    $sql .= "SELECT \"taken_damage_per_min\", adv_matchlines.playerid playerid, SUM(adv_matchlines.pings/(matches.duration/60))/SUM(1)
               value FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid GROUP BY playerid ORDER BY value DESC";

   $result["averages_players"] = array();

   if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for AVERAGES PLAYERS.\n";
   else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

   do {
     $query_res = $conn->store_result();

     $row = $query_res->fetch_row();
     $result["averages_players"][$row[0]] = array();

     for ($i=0; $i<3 && $row != null; $i++, $row = $query_res->fetch_row()) {
       $result["averages_players"][sizeof($result["averages_players"])][$i] = array (
         "playerid" => $row[1],
         "value"  => $row[2]
       );
     }

     $query_res->free_result();

   } while($conn->next_result());
  }

  if ($lrg_ana_pickban) {
    # pick/ban heroes stats

    $sql = "SELECT dp.hero_id, dp.matches, dp.winrate, db.matches, db.winrate, dp.matches+db.matches total_matches FROM (
         SELECT draft.hero_id hero_id, SUM(1) matches, SUM(NOT matches.radiantWin XOR draft.is_radiant)/SUM(1) winrate
    	   FROM draft JOIN matches ON draft.matchid = matches.matchid
    	   WHERE is_pick = true
    	   GROUP BY draft.hero_id
    ) dp JOIN (
         SELECT draft.hero_id hero_id, SUM(1) matches, SUM(NOT matches.radiantWin XOR draft.is_radiant)/SUM(1) winrate
   	     FROM draft JOIN matches ON draft.matchid = matches.matchid
    	   WHERE is_pick = false
    	   GROUP BY draft.hero_id
    ) db ON dp.hero_id = db.hero_id ORDER BY total_matches DESC;";

    $result["pickban"] = array();

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for PICKS AND BANS.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    $result["pickban"] = array();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $result["pickban"][] = array (
        "heroid" => $row[0],
        "matches_total"   => $row[5],
        "matches_picked"  => $row[1],
        "winrate_picked"  => $row[2],
        "matches_banned"  => $row[3],
        "winrate_banned"  => $row[4]
      );
    }

    $query_res->free_result();

    # matches
    # TODO
  }

  if ($lrg_ana_draft_stages) {
    # pick/ban draft stages stats
    $result["draft"] = array (
      "picks" => array(),
      "bans"  => array()
    );

    for ($pick = 0; $pick < 2; $pick++) {
      for ($stage = 1; $stage < 6; $stage++) {
        $sql = "SELECT draft.hero_id hero_id, SUM(1) matches, SUM(NOT matches.radiantWin XOR draft.is_radiant)/SUM(1) winrate
                FROM draft JOIN matches ON draft.matchid = matches.matchid
                WHERE is_pick = ".($pick ? "true" : "false")." AND stage = ".$stage."
                GROUP BY draft.hero_id";
        if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for DRAFT STAGE $pick $stage.\n";
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

        $query_res = $conn->store_result();

        $result["pickban"] = array();

        for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
          $result["pickban"][] = array (
            "heroid" => $row[0],
            "matches"=> $row[1],
            "winrate"=> $row[2]
          );
        }

        $query_res->free_result();
      }
    }

    # stages: 5
    # types: 2 (pick and ban)
    # total of 10 requests
    # matches
    #
    # TODO

  }

  if ($lrg_ana_heroes_positions) {
    # heroes on positions

    $result["hero_positions"] = array ();

    for ($core = 0; $core < 2; $core++) {
      for ($lane = 1; $lane > 0 && $lane < 6; $lane++) {
        if (!$core) { $lane = 0; }
        $result["hero_positions"][$core][$lane] = array();

        $sql = "SELECT
                  am.heroid heroid,
                  SUM(1) matches,
                  SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate,
                  SUM(ml.kills)/SUM(1) kills,
                  SUM(ml.deaths)/SUM(1) deaths,
                  SUM(ml.assists)/SUM(1) assists,
                  SUM(ml.gpm)/SUM(1) gpm,
                  SUM(ml.xpm)/SUM(1) xpm,
                  SUM( ml.heal / (m.duration/60) )/SUM(1) avg_heal,
                  SUM( ml.heroDamage / (m.duration/60) )/SUM(1) avg_hero_dmg,
                  SUM( ml.towerDamage / (m.duration/60) )/SUM(1) avg_tower_dmg,
                  SUM( am.damage_taken / (m.duration/60) )/SUM(1) avg_dmg_taken,
                  SUM(am.stuns)/SUM(1) stuns,
                  SUM(am.lh_at10)/SUM(1) lh_10,
                  SUM(m.duration)/(SUM(1)*60) avg_duration
                FROM adv_matchlines am JOIN
                	matchlines ml
                    	ON am.matchid = ml.matchid AND am.heroid = ml.heroid
                    JOIN matches m
                    	ON m.matchid = am.matchid ".
               ($core == 0 ? "WHERE am.isCore = 0"
              :"WHERE am.isCore = 1 AND am.lane = $lane")
              ." \nGROUP BY am.heroid
                ORDER BY matches DESC;";

        if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO POSITIONS $core $lane.\n";
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

        $query_res = $conn->store_result();

        for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
          $result["hero_positions"][$core][$lane][] = array (
            "heroid" => $row[0],
            "matches"=> $row[1],
            "winrate"=> $row[2],
            "kills"  => $row[3],
            "deaths" => $row[4],
            "assists"=> $row[5],
            "gpm"    => $row[6],
            "xpm"    => $row[7],
            "heal_per_min" => $row[8],
            "hero_damage_per_min" => $row[9],
            "tower_damage_per_min"=> $row[10],
            "damage_taken" => $row[11],
            "stuns" => $row[12],
            "lh_at10" => $row[13],
            "duration" => $row[14]
          );
        }

        $query_res->free_result();
        if (!$core) { break; }
      }
    }

    # $lrg_ana_heroes_positions_based_on_players

    if ($lrg_ana_heroes_positions_matchids) {
      #   include matchids
      $result["hero_positions_matches"] = array();

      for ($core = 0; $core < 2; $core++) {
        for ($lane = 1; $lane < 6; $lane++) {
          if (!$core) { $lane = 0; }
          $result["hero_positions_matches"][$core][$lane] = array();

          echo "[S] Requested data for HERO POSITIONS MATCHES $core $lane.\n";

          foreach ($result["hero_positions"][$core][$lane] as $hero) {
            $sql = "SELECT matchid FROM adv_matchlines WHERE heroid = ".$hero['heroid']." AND isCore = ".$core." AND lane = ".$lane.";";

            if ($conn->multi_query($sql) === TRUE);
            else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

            $query_res = $conn->store_result();

            for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
              $result["hero_positions"][$core][$lane][] = $row[0];
            }

            $query_res->free_result();
          }
          if (!$core) { break; }
        }
      }
    }
  }

  if ($lrg_ana_heroes_stats_sides) {
    $result["hero_sides"] = array ();

    for ($side = 0; $side < 2; $side++) {
      $result["hero_sides"][$side] = array();

      $sql = "SELECT
                ml.heroid, SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate,
                SUM(ml.gpm)/SUM(1) gpm,
                SUM(ml.xpm)/SUM(1) xpm
              FROM matchlines ml JOIN matches m
                    ON m.matchid = ml.matchid
              WHERE ml.isRadiant = $side
              GROUP BY ml.heroid
              ORDER BY matches DESC;";
      if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO SIDES $side.\n";
      else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

      $query_res = $conn->store_result();

      for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
        $result["hero_sides"][$side] = array (
          "heroid" => $row[0],
          "matches"=> $row[1],
          "winrate"=> $row[2],
          "gpm"    => $row[3],
          "xpm"    => $row[4]
        );
      }

      $query_res->free_result();
    }
  }

  if ($lrg_ana_hero_pairs) {
    $result["hero_pairs"] = array();

    $sql = "SELECT m1.heroid, m2.heroid, SUM(1) match_count, SUM(NOT matches.radiantWin XOR m1.isRadiant)/SUM(1) winrate
            FROM matchlines m1 JOIN matchlines m2
              ON m1.matchid = m2.matchid and m1.isRadiant = m2.isRadiant and m1.heroid < m2.heroid
              JOIN matches ON m1.matchid = matches.matchid
            GROUP BY m1.heroid, m2.heroid
            HAVING match_count >= 3
            ORDER BY match_count DESC;";
    # limiting match count for hero pair to 3:
    # 1 match = every possible pair
    # 2 matches = may be a coincedence

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO PAIRS.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $result["hero_pairs"][] = array (
        "heroid1" => $row[0],
        "heroid2" => $row[1],
        "matches" => $row[2],
        "winrate" => $row[3]
      );
    }

    $query_res->free_result();


    if ($lrg_ana_hero_pairs_matchids) {
      $result["hero_pairs_matches"] = array ();

      foreach($result['hero_pairs'] as $pair) {
        $sql = "SELECT m1.matchid
                FROM matchlines m1 JOIN matchlines m2
                  ON m1.matchid = m2.matchid and m1.isRadiant = m2.isRadiant and m1.heroid < m2.heroid
                WHERE m1.heroid = ".$pair['heroid1']." AND m2.heroid = ".$pair['heroid2'].";";

        $result["hero_pairs_matches"][$pair['heroid1']."-".$pair['heroid2']] = array();

        if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO PAIRS MATCHES.\n";
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

        $query_res = $conn->store_result();

        for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
          $result["hero_pairs_matches"][$pair['heroid1']."-".$pair['heroid2']][] = $row[0];
        }

        $query_res->free_result();
      }
    }
  }

  if ($lrg_ana_hero_triplets) {
    $result["hero_triplets"] = array();

    $sql = "SELECT m1.heroid, m2.heroid, m3.heroid, SUM(1) match_count, SUM(NOT matches.radiantWin XOR m1.isRadiant)/SUM(1) winrate
            FROM matchlines m1
            	JOIN matchlines m2
              		ON m1.matchid = m2.matchid and m1.isRadiant = m2.isRadiant and m1.heroid < m2.heroid
              	JOIN matchlines m3
                	ON m1.matchid = m3.matchid and m1.isRadiant = m3.isRadiant and m2.heroid < m3.heroid
              	JOIN matches
                	ON m1.matchid = matches.matchid
            GROUP BY m1.heroid, m2.heroid, m3.heroid
            HAVING match_count >= 2
            ORDER BY match_count DESC;";
    # limiting match count for hero pair to 3:
    # 1 match = every possible pair
    # 2 matches = may be a coincedence

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO TRIPLETS.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $result["hero_pairs"][] = array (
        "heroid1" => $row[0],
        "heroid2" => $row[1],
        "heroid3" => $row[2],
        "matches" => $row[3],
        "winrate" => $row[4]
      );
    }

    $query_res->free_result();


    if ($lrg_ana_hero_triplets_matchids) {
      $result["hero_triplets_matches"] = array ();

      foreach($result['hero_triplets'] as $pair) {
        $sql = "SELECT m1.matchid
                FROM matchlines m1
                  JOIN matchlines m2
                      ON m1.matchid = m2.matchid and m1.isRadiant = m2.isRadiant and m1.heroid < m2.heroid
                    JOIN matchlines m3
                      ON m1.matchid = m3.matchid and m1.isRadiant = m3.isRadiant and m2.heroid < m3.heroid
                WHERE m1.heroid = ".$pair['heroid1']." AND m2.heroid = ".$pair['heroid2']." AND m3.heroid = ".$pair['heroid3'].";";

        $result["hero_pairs_matches"][$pair['heroid1']."-".$pair['heroid2']."-".$pair['heroid3']] = array();

        if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO TRIPLETS MATCHES.\n";
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

        $query_res = $conn->store_result();

        for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
          $result["hero_pairs_matches"][$pair['heroid1']."-".$pair['heroid2']."-".$pair['heroid3']][] = $row[0];
        }

        $query_res->free_result();
      }
    }
  }

  if ($lrg_teams) {
    # team competitions placeholder
    # TODO
  } else {
    echo "[ ] Working for players competition...\n";

    if ($lrg_ana_player_vs_player) {
        $result["pvp"] = array ();

        $sql = "SELECT m1.playerid, m2.playerid, SUM(1) match_count, SUM(NOT matches.radiantWin XOR m1.isRadiant) player1_won, SUM(NOT matches.radiantWin XOR m1.isRadiant)/SUM(1) p1_winrate
            FROM matchlines m1
            	JOIN matchlines m2
              		ON m1.matchid = m2.matchid and m1.isRadiant <> m2.isRadiant and m1.playerid < m2.playerid
              	JOIN matches
                	ON m1.matchid = matches.matchid
            GROUP BY m1.playerid, m2.playerid;";

        if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for PLAYER AGAINST PLAYER.\n";
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

        $query_res = $conn->store_result();

        for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
          $result["pvp"][] = array (
            "playerid1" => $row[0],
            "playerid2" => $row[1],
            "matches" => $row[2],
            "p1won" => $row[3],
            "p1winrate" => $row[4]
          );
        }

        $query_res->free_result();
    }

    if ($lrg_ana_player_positions_stats) {
      $result["player_positions"] = array();

      for ($core = 0; $core < 2; $core++) {
        for ($lane = 1; $lane < 6 && $lane > 0; $lane++) {
          if (!$core) { $lane = 0; }
          $result["player_positions"][$core][$lane] = array();

          $sql = "SELECT
                    am.playerid playerid,
                    SUM(1) matches,
                    SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate,
                    SUM(ml.gpm)/SUM(1) gpm,
                    SUM(ml.xpm)/SUM(1) xpm,
                    SUM( ml.heal / (m.duration/60) )/SUM(1) avg_heal,
                    SUM( ml.heroDamage / (m.duration/60) )/SUM(1) avg_hero_dmg,
                    SUM( ml.towerDamage / (m.duration/60) )/SUM(1) avg_tower_dmg,
                    SUM( am.damage_taken / (m.duration/60) )/SUM(1) avg_dmg_taken,
                    SUM(am.stuns)/SUM(1) stuns,
                    SUM(am.lh_at10)/SUM(1) lh_10,
                    SUM(ml.denies)/SUM(1) denies,
                    SUM(m.duration)/(SUM(1)*60) avg_duration
                  FROM adv_matchlines am JOIN
                  	matchlines ml
                      	ON am.matchid = ml.matchid AND am.playerid = ml.playerid
                      JOIN matches m
                      	ON m.matchid = am.matchid ".
                 ($core == 0 ? "WHERE am.isCore = 0"
                :"WHERE am.isCore = 1 AND am.lane = $lane")
                ." GROUP BY am.playerid
                  ORDER BY matches DESC;";
          if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for PLAYER POSITIONS $core $lane.\n";
          else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

          $query_res = $conn->store_result();

          for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
            $result["hero_positions"][$core][$lane][] = array (
              "playerid" => $row[0],
              "matches"=> $row[1],
              "winrate"=> $row[2],
              "gpm"  => $row[3],
              "xpm" => $row[4],
              "heal_per_min" => $row[5],
              "hero_damage_per_min" => $row[6],
              "tower_damage_per_min"=> $row[7],
              "damage_taken" => $row[8],
              "stuns" => $row[9],
              "lh_at10" => $row[10],
              "denies" => $row[11],
              "duration" => $row[12]
            );
          }

          $query_res->free_result();
          if (!$core) { break; }
        }
      }
    }

    if ($lrg_ana_player_pairs) {

    }
    # TODO
  }

  if ($lrg_ana_matchlist) {
    $result["matches"] = array();

    $sql = "SELECT matchid, heroid, playerid FROM matchlines;";

    if ($conn->multi_query($sql) === TRUE) echo "[S] MATCHES LIST.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($matchline = 1, $row = $query_res->fetch_row();
         $row != null;
         $row = $query_res->fetch_row(), $matchline = ($matchline == 10) ? 1 : $matchline+1) {
      if ($matchline == 1) {
        $result["matches"][$row[0]] = array();
      }
      $result["matches"][$row[0]][] = array (
        "hero" => $row[1],
        "player" => $row[2]
      );
    }

    $query_res->free_result();
  }

 echo("[ ] Encoding results to JSON\n");
 $output = json_encode($result);

 $filename = "reports/report_".$lrg_league_name.".json";
 $f = fopen($filename, "w") or die("[F] Couldn't open file to save results. Check working directory for `reports` folder.\n");
 fwrite($f, $output);
 fclose($f);
 echo("[S] Recorded results to file `report_$lrg_league_name.json`\n");


 ?>

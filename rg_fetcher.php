#!/bin/php
<?php

include_once("settings.php");

echo("\nInitialising...\n");

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$matches = array ();
$failed_matches = array ();

$input_cont = file_get_contents($lrg_input);
$input_cont = str_replace("\r\n", "\n", $input_cont);

$matches    = explode("\n", trim($input_cont));

$matches = array_unique($matches);


# https://api.opendota.com/api/matches/{match_id}

$json = "";
$t_matches = array ();
$t_matchlines = array();
$t_adv_matchlnes = array ();
$t_draft = array ();

foreach ($matches as $match) {
    if ($match[0] == "#") continue;

    $query = $conn->query("SELECT matchid FROM matches WHERE matchid = ".$match.";");

    if ($query->num_rows != 0) {
        echo("[E] Match $match: Already have it in database, skipping\n");
        continue;
    }

    echo("[_] Match $match: Requesting OpenDota API\n");

    $json = file_get_contents('https://api.opendota.com/api/matches/'.$match);
    $matchdata = json_decode($json, true);
    if ($matchdata == null) {
        echo("[E] Match $match: Can't parse JSON from OpenDota, skipping\n");
        $failed_matches[sizeof($failed_matches)] = $match;
        continue;
    } else if ($matchdata['players'][0]['lh_t'] == null) {
        echo("[E] Match $match: Replay is not parsed, skipping\n");
        $failed_matches[sizeof($failed_matches)] = $match;
        continue;
    }

    echo("[S] Match $match: Request OK\n");

    $i = sizeof($t_matches);
    $t_matches[$i]['matchid'] = $match;
    $t_matches[$i]['radiantWin'] = $matchdata['radiant_win'];
    $t_matches[$i]['duration'] = $matchdata['duration'];
    $t_matches[$i]['modeID'] = $matchdata['game_mode'];
    $t_matches[$i]['leagueID'] = $matchdata['leagueid'];
    $t_matches[$i]['date'] = $matchdata['start_time'];
    if (isset($matchdata['stomp']))
         $t_matches[$i]['stomp'] = $matchdata['stomp'];
    else $t_matches[$i]['stomp'] = $matchdata['loss'];
    if (isset($matchdata['comeback']))
         $t_matches[$i]['comeback'] = $matchdata['comeback'];
    else $t_matches[$i]['comeback'] = $matchdata['throw'];

    $i = sizeof($t_matchlines);
    for ($j = 0; $j < 10; $j++, $i++) {
        $t_matchlines[$i]['matchid'] = $match;
        $t_matchlines[$i]['playerid'] = $matchdata['players'][$j]['account_id'];
        $t_matchlines[$i]['heroid'] = $matchdata['players'][$j]['hero_id'];
        $t_matchlines[$i]['isRadiant'] = $matchdata['players'][$j]['isRadiant'];
        $t_matchlines[$i]['level'] = $matchdata['players'][$j]['level'];
        $t_matchlines[$i]['kills'] = $matchdata['players'][$j]['kills'];
        $t_matchlines[$i]['deaths'] = $matchdata['players'][$j]['deaths'];
        $t_matchlines[$i]['assists'] = $matchdata['players'][$j]['assists'];
        $t_matchlines[$i]['networth'] = $matchdata['players'][$j]['gold'];
        $t_matchlines[$i]['gpm'] = $matchdata['players'][$j]['gold_per_min'];
        $t_matchlines[$i]['xpm'] = $matchdata['players'][$j]['xp_per_min'];
        $t_matchlines[$i]['heal'] = $matchdata['players'][$j]['hero_healing'];
        $t_matchlines[$i]['heroDamage'] = $matchdata['players'][$j]['hero_damage'];
        $t_matchlines[$i]['towerDamage'] = $matchdata['players'][$j]['tower_damage'];
        $t_matchlines[$i]['lasthits'] = $matchdata['players'][$j]['last_hits'];
        $t_matchlines[$i]['denies'] = $matchdata['players'][$j]['denies'];

        $t_adv_matchlines[$i]['matchid'] = $match;
        $t_adv_matchlines[$i]['playerid'] = $matchdata['players'][$j]['account_id'];
        $t_adv_matchlines[$i]['heroid'] = $matchdata['players'][$j]['hero_id'];
        $t_adv_matchlines[$i]['lh10'] = $matchdata['players'][$j]['lh_t'][10];
        $t_adv_matchlines[$i]['lane'] = $matchdata['players'][$j]['lane_role'];
        if($matchdata['players'][$j]['lane_role'] == 5) $matchdata['players'][$j]['lane_role'] = 4; # we don't care about different jungles

        # trying to decide, is it a core
        $support_indicators = 0;
        if ($matchdata['players'][$j]['lh_t'][5] < 10) $support_indicators++;
        if ($matchdata['players'][$j]['observer_uses'] < 2) $support_indicators++;
        if ($matchdata['players'][$j]['gold_per_min'] < 350) $support_indicators++;
        if ($matchdata['players'][$j]['is_roaming']) {
          $support_indicators++;
          $matchdata['players'][$j]['lane_role'] = 5;
        }

        if ($support_indicators > 1) $t_adv_matchlines[$i]['is_core'] = true;
        else $t_adv_matchlines[$i]['is_core'] = false;


        $t_adv_matchlines[$i]['lane_efficiency'] = $matchdata['players'][$j]['lane_efficiency'];
        $t_adv_matchlines[$i]['observers'] = $matchdata['players'][$j]['observer_uses'];
        $t_adv_matchlines[$i]['sentries'] = $matchdata['players'][$j]['sentry_uses'];
        $t_adv_matchlines[$i]['couriers_killed'] = $matchdata['players'][$j]['courier_kills'];
        $t_adv_matchlines[$i]['roshans_killed'] = $matchdata['players'][$j]['roshan_kills'];
        $t_adv_matchlines[$i]['wards_destroyed'] = $matchdata['players'][$j]['observer_kills'];
        if (count($matchdata['players'][$j]['multi_kills']) == 0) $t_adv_matchlines[$i]['max_multikill'] = 0;
        else $t_adv_matchlines[$i]['max_multikill'] = end(array_keys($matchdata['players'][$j]['multi_kills']));
        if (count($matchdata['players'][$j]['kill_streaks']) == 0) $t_adv_matchlines[$i]['max_streak'] = 0;
        else $t_adv_matchlines[$i]['max_streak'] = end(array_keys($matchdata['players'][$j]['kill_streaks']));
        $t_adv_matchlines[$i]['stacks'] = $matchdata['players'][$j]['camps_stacked'];
        $t_adv_matchlines[$i]['time_dead'] = $matchdata['players'][$j]['life_state_dead'];
        $t_adv_matchlines[$i]['buybacks'] = $matchdata['players'][$j]['buyback_count'];
        $t_adv_matchlines[$i]['pings'] = $matchdata['players'][$j]['pings'];
        $t_adv_matchlines[$i]['stuns'] = $matchdata['players'][$j]['stuns'];
        $t_adv_matchlines[$i]['teamfight_part'] = $matchdata['players'][$j]['teamfight_participation'];
    }

    $i = sizeof($t_draft);

    # OpenDota doesn't have information about draft for Ranked All Pick
    # Game Mode IDs:
    # 2  = Captain's Mode
    # 9  = Reverse Captain's Mode
    # 16 = Captain's Draft
    # TODO Draft information from Stratz for ranked all pick (22)


    if ($matchdata['game_mode'] == 2 || $matchdata['game_mode'] == 9) {
        $stages = array (
            # (isPick + 1)*((-1)*isRadiant)
            1 => 0, # dire bans
            2 => 0, # dire picks
            -1 => 0,# radi bans
            -2 => 0 # radi bans
        );
        foreach ($matchdata['picks_bans'] as $draft_instance) {
            $stage_sum = (1+(int)$draft_instance['is_pick'])*($draft_instance['team'] ? 1 : -1);
            $draft_stage = 0;

            if    (++$stages[$stage_sum] < 3) $draft_stage = 1;
            else if ($stages[$stage_sum] < 5) $draft_stage = 2;
            else $draft_stage = 3;

            $t_draft[$i]['matchid'] = $match;
            $t_draft[$i]['is_radiant'] = $draft_instance['team'] ? 0 : 1;
            $t_draft[$i]['is_pick'] = $draft_instance['is_pick'];
            $t_draft[$i]['hero_id'] = $draft_instance['hero_id'];
            $t_draft[$i]['stage'] = $draft_stage;

            $i++;
        }
    } else if ($matchdata['game_mode'] == 16) {
        foreach ($matchdata['picks_bans'] as $draft_instance) {
            $t_draft[$i]['matchid'] = $match;
            $t_draft[$i]['is_radiant'] = $draft_instance['team'] ? 0 : 1;
            $t_draft[$i]['is_pick'] = $draft_instance['is_pick'];
            $t_draft[$i]['hero_id'] = $draft_instance['hero_id'];

            if ($draft_instance['is_pick']) {
                if ($draft_instance['order'] < 11) $t_draft[$i]['stage'] = 1;
                else if ($draft_instance['order'] < 15) $t_draft[$i]['stage'] = 2;
                else $t_draft[$i]['stage'] = 3;
            } else {
                if ($draft_instance['order'] < 3) $t_draft[$i]['stage'] = 1;
                else if ($draft_instance['order'] < 5) $t_draft[$i]['stage'] = 2;
                else $t_draft[$i]['stage'] = 3;
            }
            $i++;
        }
    }
}

# recording to database

if (sizeof($t_matches) == 0) die ("[W] No matches to record, exitting...\n");

$len = sizeof($t_matches);
$sql = "INSERT INTO matches (matchid, radiantWin, duration, modeID, leagueID, date, stomp, comeback) VALUES ";
for($i = 0; $i < $len; $i++) {
    $sql .= "(".$t_matches[$i]['matchid'].",".($t_matches[$i]['radiantWin'] ? "true" : "false" ).",".$t_matches[$i]['duration'].","
               .$t_matches[$i]['modeID'].",".$t_matches[$i]['leagueID'].",".$t_matches[$i]['date'].","
               .$t_matches[$i]['stomp'].",".$t_matches[$i]['comeback']."),";
}
$sql[strlen($sql)-1] = ";";

if ($conn->multi_query($sql) === TRUE) echo "[S] Successfully recorded matches to database.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

$sql = " INSERT INTO matchlines (matchID, playerID, heroID, level, isRadiant, kills, deaths, assists, networth,".
        "gpm, xpm, heal, heroDamage, towerDamage, lastHits, denies) VALUES ";
$len = sizeof($t_matchlines);
for($i = 0; $i < $len; $i++) {
    $sql .= "(".$t_matchlines[$i]['matchid'].",".$t_matchlines[$i]['playerid'].",".$t_matchlines[$i]['heroid'].",".
                $t_matchlines[$i]['level'].",".($t_matchlines[$i]['isRadiant'] ? "true" : "false").",".$t_matchlines[$i]['kills'].",".
                $t_matchlines[$i]['deaths'].",".$t_matchlines[$i]['assists'].",".$t_matchlines[$i]['networth'].",".
                $t_matchlines[$i]['gpm'].",".$t_matchlines[$i]['xpm'].",".$t_matchlines[$i]['heal'].",".
                $t_matchlines[$i]['heroDamage'].",".$t_matchlines[$i]['towerDamage'].",".$t_matchlines[$i]['lasthits'].",".
                $t_matchlines[$i]['denies']."),";
}
$sql[strlen($sql)-1] = ";";

if ($conn->multi_query($sql) === TRUE) echo "[S] Successfully recorded matchlines to database.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

$sql = " INSERT INTO adv_matchlines (matchid, playerid, heroid, lh_at10, isCore, lane, efficiency_at10, wards, sentries,".
        "couriers_killed, roshans_killed, multi_kill, streak, stacks, time_dead, buybacks, wards_destroyed, pings, stuns, teamfight_part) VALUES ";
for($i = 0; $i < $len; $i++) {
    $sql .= "(".$t_adv_matchlines[$i]['matchid'].",".$t_adv_matchlines[$i]['playerid'].",".$t_adv_matchlines[$i]['heroid'].",".
                $t_adv_matchlines[$i]['lh10'].",".$t_adv_matchlines[$i]['is_core'].",".$t_adv_matchlines[$i]['lane'].",".
                $t_adv_matchlines[$i]['lane_efficiency'].",".$t_adv_matchlines[$i]['observers'].",".$t_adv_matchlines[$i]['sentries'].",".
                $t_adv_matchlines[$i]['couriers_killed'].",".$t_adv_matchlines[$i]['roshans_killed'].",".$t_adv_matchlines[$i]['wards_destroyed'].",".
                $t_adv_matchlines[$i]['max_multikill'].",".$t_adv_matchlines[$i]['max_streak'].",".$t_adv_matchlines[$i]['stacks'].",".
                $t_adv_matchlines[$i]['time_dead'].",".$t_adv_matchlines[$i]['buybacks'].",".$t_adv_matchlines[$i]['pings'].",".
                $t_adv_matchlines[$i]['stuns'].",".$t_adv_matchlines[$i]['teamfight_part']."),";
}
$sql[strlen($sql)-1] = ";";

if ($conn->multi_query($sql) === TRUE) echo "[S] Successfully recorded adv matchlines to database.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

$sql = " INSERT INTO draft (matchid, is_radiant, is_pick, hero_id, stage) VALUES ";
$len = sizeof($t_draft);
for($i = 0; $i < $len; $i++) {
    $sql .= "(".$t_draft[$i]['matchid'].",".($t_draft[$i]['is_radiant'] ? "true" : "false").",".
                ($t_draft[$i]['is_pick'] ? "true" : "false").",".
                $t_draft[$i]['hero_id'].",".$t_draft[$i]['stage']."),";
}
$sql[strlen($sql)-1] = ";";

if ($conn->multi_query($sql) === TRUE) echo "[S] Successfully recorded draft to database.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

echo "\nUnparsed matches: \n";
foreach ($failed_matches as $fm)
    echo "\t$fm\n";

echo "\n[_] Recording failed matches to file...\n";

$output = implode("\n", $failed_matches);
$filename = "tmp_fm".time();
$f = fopen($filename, "w");
fwrite($f, $output);
fclose($f);

echo "[S] Recorded failed matches to $filename\n";

echo "[ ] Collecting players data\n";

$sql = "SELECT playerID FROM matchlines GROUP BY playerID;";
$players = array ();
if ($conn->multi_query($sql))
  do {
    $res = $conn->store_result($sql);
    $player = $result->fetch_row()[0];
    $json = file_get_contents('https://api.opendota.com/api/players/'.$player);
    $matchdata = json_decode($json, true);
    $players[sizeof($players)] = array ();
    $players[]["id"] = $player;
    if ($matchdata["profile"]["name"] == null) $players[]["name"] = $matchdata["profile"]["personaname"];
    else $players[]["name"] = $matchdata["profile"]["name"];
    # table also includes player positions and real names placeholders, but
    # for now you will fill it up by yourself
    # TODO
  } while ($mysqli->next_result());

# TODO

if ($lrg_teams) {
  echo "[ ] Collecting teams data\n";

  # TODO

  echo "[ ] Collecting match data about participating teams\n";

  # TODO
  # make it first by adding it to $matches block
} else {
  echo "[ ] Skipping team stats for PvP competition\n";
}

echo "[S] Fetch complete.\n";

?>

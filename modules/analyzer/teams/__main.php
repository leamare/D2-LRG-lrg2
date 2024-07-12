<?php
# team competitions placeholder
$result['teams'] = [];

$sql = "SELECT teams.teamid, teams.name, teams.tag, count(distinct teams_matches.matchid) ms
  FROM teams join teams_matches on teams.teamid = teams_matches.teamid 
  group by teams.teamid
  having ms > 0;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for TEAMS LIST.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

// if ($lg_settings['ana']['teams']['limiter']) {
//   $tm_set = [];
// }

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $result['teams'][$row[0]] = array(
    "name" => $row[1],
    "tag"  => $row[2]
  );
  if(empty($result['teams'][$row[0]]['tag'])) {
    $result['teams'][$row[0]]['tag'] = generate_tag($result['teams'][$row[0]]['name']);
  }
  // if ($lg_settings['ana']['teams']['limiter']) {
  //   if ($row[3] > 1)
  //     $tm_set[] = (int)$row[3];
  // }
}

// if ($lg_settings['ana']['teams']['limiter']) {
//   $tm_limiters = calculate_limiters($tm_set, null);
//   var_dump($tm_limiters); die();
// }

$query_res->free_result();

if ($lg_settings['ana']['teams'])
    foreach($result['teams'] as $id => $team) {
      # main information
      $err = require("overview.php");

      if ($err) {
        unset($result['teams'][$id]);
        continue;
      }
      $multiplier = ($result["teams"][$id]['matches_total'] ?? 0) / ($result["random"]['matches_total'] ?? 1);

      if ($lg_settings['ana']['teams']['limiter']) {
        if (!isset($interest)) $interest = [];
        if ($result["teams"][$id]['matches_total'] < $limiter) {
          continue;
        } else {
          $interest[] = $id;
        }
      }

      # averages
      require("averages.php");
      # heroes pickbans
      require("pickban.php");

      if ($schema['variant']) {
        require("heroes/variants.php");
      }

      if ($lg_settings['ana']['teams']['draft']) {
        # heroes draft
        require("heroes/draft.php");
      }

      if ($lg_settings['ana']['teams']['draft_against']) {
        # heroes draft againt team
        require("heroes/draft_vs.php");
      }

      if ($lg_settings['ana']['teams']['heropos']) {
        # heroes positions
        require("heroes/positions.php");
      }

      if ($lg_settings['ana']['teams']['pairs']) {
        # heroes pairs
        require("heroes/pairs.php");
      }

      if ($lg_settings['ana']['teams']['hero_graph']) {
        # heroes graph
        require("heroes/graph.php");
      }

      if ($lg_settings['ana']['teams']['triplets']) {
        # heroes trios
        require("heroes/trios.php");
      }

      if ($lg_settings['ana']['teams']['players_draft']) {
        # players draft
        require("players/draft.php");
      }

      if ($lg_settings['ana']['matchlist'] && $lg_settings['ana']['teams']['matches']) {
        # matches
        require("matches.php");
      }
    }
    
  if (!empty($interest))
    $result["teams_interest"] = $interest;

<?php
include_once("head.php");
ini_set('memory_limit', '4096M');

ini_set('mysqli.allow_persistent', '1');
ini_set('mysql.allow_persistent', '1');

ini_set('mysql.connect_timeout', '7200');
ini_set('default_socket_timeout', '7200');

ini_set('mysqli.reconnect', '1');
ini_set('mysqlnd.net_read_timeout', '7200');

const FP_ABLE = [ 23, 18, 21, 17, 16, 8, 2 ];

include_once("modules/commons/utf8ize.php");
include_once("modules/commons/quantile.php");
include_once("modules/commons/generate_tag.php");
include_once("modules/commons/metadata.php");
include_once("modules/commons/wrap_data.php");
include_once("modules/commons/array_pslice.php");
include_once("modules/commons/instaquery.php");
include_once("modules/view/functions/ranking.php");

echo("\nConnecting to database...\n");

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

// Queries generators for reusable queries
// IN THEORY it would be better to rewrite everthing and make request builders
// into their own class and so on and on
// but practically it's better to make separate query builders for
// every reusable query that may be changed for some reason
// e.g. formulas or structure
include_once("modules/analyzer/__queries/hero_pairs.php");
include_once("modules/analyzer/__queries/hero_pickban.php");
include_once("modules/analyzer/__queries/hero_draft.php");
include_once("modules/analyzer/__queries/hero_draft_tree.php");
include_once("modules/analyzer/__queries/hero_trios.php");
include_once("modules/analyzer/__queries/lane_combos.php");
include_once("modules/analyzer/__queries/hero_positions.php");
include_once("modules/analyzer/__queries/hero_summary.php");
include_once("modules/analyzer/__queries/hero_laning.php");

include_once("modules/analyzer/__queries/player_summary.php");
include_once("modules/analyzer/__queries/player_draft.php");
include_once("modules/analyzer/__queries/player_positions.php");
include_once("modules/analyzer/__queries/player_pairs.php");
include_once("modules/analyzer/__queries/player_trios.php");
include_once("modules/analyzer/__queries/player_graph.php");
include_once("modules/analyzer/__queries/player_laning.php");

include_once("modules/commons/schema.php");

$meta = new lrg_metadata;
$meta['heroes'];

$__start_time = microtime(true);

$result = [];
$result["league_name"]  = $lg_settings['league_name'];
$result["league_desc"] = $lg_settings['league_desc'];
$result['league_id'] = $lg_settings['league_id'];
$result["league_tag"] = $lg_settings['league_tag'];

$result["sponsors"] = $lg_settings['sponsors'] ?? null;
$result["orgs"] = $lg_settings['orgs'] ?? null;
$result["links"] = $lg_settings['links'] ?? null;

$result["localized"] = $lg_settings['localized'] ?? null;

$avg_limit = $lg_settings['ana']['avg_limit'];

if (isset($lg_settings['teams']) && !isset($lg_settings['players'])) {
  $result["teams_interest"] = $lg_settings['teams'];

  $players_interest = [];
  $sql = "SELECT ml.playerid from matchlines ml join teams_matches tm 
    on ml.matchid = tm.matchid and ml.isRadiant = tm.is_radiant 
    where tm.teamid in (".implode(',', $lg_settings['teams']).")
  group by 1;";

  if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $players_interest[] = (int)$row[0];
  }

  $query_res->free_result();

  $result["players_interest"] = $players_interest;
} else if (isset($lg_settings['players'])) {
  $result["players_interest"] = $lg_settings['players'];
  $players_interest = $lg_settings['players'];
}

if (compare_ver($lg_settings['version'], $lrg_version) < 0) {
  if (!file_exists("templates/default.json")) die("[F] No default league template found, exitting.");
  $tmp = json_decode(file_get_contents("templates/default.json"), true);

  if(isset($options['T'])) {
    if (file_exists("templates/".$options['T'].".json"))
      $tmpl = json_decode(file_get_contents("templates/".$options['T'].".json"), true);

    if (isset($tmpl['ana']) && isset($tmpl['ana']['regions']) && isset($tmpl['ana']['regions']['groups'])) {
      $tmp['ana']['regions']['groups'] = $tmpl['ana']['regions']['groups'];
    }
    
    $tmp = array_replace_recursive($tmp, $tmpl);
    unset($tmpl);
  }

  if (isset($lg_settings['ana']) && isset($lg_settings['ana']['regions']) && isset($lg_settings['ana']['regions']['groups'])) {
    $tmp['ana']['regions']['groups'] = $lg_settings['ana']['regions']['groups'];
  }

  $tmp = array_replace_recursive($tmp, $lg_settings);
  $lg_settings = $tmp;
  unset($tmp);
} else if(isset($options['T'])) {
  if (file_exists("templates/".$options['T'].".json"))
    $tmp = json_decode(file_get_contents("templates/".$options['T'].".json"), true);

  $tmp = array_replace_recursive($tmp, $lg_settings);
  $lg_settings = $tmp;
  unset($tmp);
}

/* first and last match */ {
  $sql = "SELECT matchid, start_date
          FROM matches
          ORDER BY start_date ASC LIMIT 1;";

  if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  $row = $query_res->fetch_row();

  if ($row) {
    $result["first_match"] = array( "mid" => $row[0], "date" => $row[1] );
  }

  $query_res->free_result();

  $sql = "SELECT matchid, start_date
          FROM matches
          ORDER BY start_date DESC LIMIT 1;";

  if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  $row = $query_res->fetch_row();

  if ($row) {
    $result["last_match"] = array( "mid" => $row[0], "date" => $row[1] );
  }

  $query_res->free_result();
}

// items support detection
$sql = "SELECT COUNT(*) z
FROM information_schema.tables WHERE table_schema = '$lrg_sql_db' 
AND table_name = 'items' HAVING z > 0;";

$query = $conn->query($sql);
if (!isset($query->num_rows) || !$query->num_rows) {
  $lg_settings['main']['items'] = false;
  echo "[N] Set &settings.items to false.\n";
}

# game versions
require_once("modules/analyzer/main/versions.php");
# game modes
require_once("modules/analyzer/main/modes.php");
# regions
require_once("modules/analyzer/main/regions.php");

# Overview stats
require_once("modules/analyzer/main/overview.php");

# pick/ban heroes stats
require_once("modules/analyzer/heroes/pickban.php");

# limiters
require_once("modules/analyzer/main/limiters.php");

if ($lg_settings['ana']['records']) {
  require_once("modules/analyzer/main/records.php");
}
if ($lg_settings['ana']['milestones']) {
  require_once("modules/analyzer/milestones.php");
}

if (!empty($result["first_match"])) {
  # league days
  require_once("modules/analyzer/main/days.php");
}


# Heroes modules
require_once("modules/analyzer/heroes/__main.php");

$result["players_additional"] = [];
// Players Summary
if($lg_settings['ana']['players']) {
  # player summary
  require_once("modules/analyzer/players/summary.php");
}

if ($lg_settings['ana']['players'] && $lg_settings['ana']['avg_players']) {
  # average for players
  require_once("modules/analyzer/players/averages.php");
}

if ($lg_settings['ana']['players'] && $lg_settings['ana']['player_positions']) {
  # players positions stats
  require_once("modules/analyzer/players/positions.php");
}

if($lg_settings['ana']['players'] && $lg_settings['ana']['players_draft']) {
  # player draft
  require_once("modules/analyzer/players/draft.php");
}

if($lg_settings['ana']['players'] && $lg_settings['ana']['player_laning']) {
  # player laning
  require_once("modules/analyzer/players/laning.php");
}

if ($lg_settings['main']['teams']) {
  require_once("modules/analyzer/teams/__main.php");

  if ($lg_settings['ana']['teams']['team_vs_team'])
    require_once("modules/analyzer/team_vs_team.php");
} else {
  echo "[ ] Working for players competition...\n";

  if ($lg_settings['ana']['players'] && $lg_settings['ana']['pvp']) {
    # pvp grid
    require_once("modules/analyzer/pvp/pvp.php");
  }

  if ($lg_settings['ana']['players'] && $lg_settings['ana']['players_combo_graph']) {
    # pvp graph
    require_once("modules/analyzer/pvp/graph.php");
  }

  if ($lg_settings['ana']['players'] && $lg_settings['ana']['player_pairs']) {
    # pvp pairs
    require_once("modules/analyzer/pvp/pairs.php");
  }

  if ($lg_settings['ana']['players'] && $lg_settings['ana']['player_triplets']) {
    # pvp trios
    require_once("modules/analyzer/pvp/trios.php");
  }

  if ($lg_settings['ana']['players'] && $lg_settings['ana']['players_lane_combos']) {
    # pvp lane combos
    require_once("modules/analyzer/pvp/lane_combos.php");
  }
}

if (isset($lg_settings['ana']['regions']) && is_array($lg_settings['ana']['regions'])) {
  # regions
  require_once("modules/analyzer/regions/__main.php");
}

if ($lg_settings['ana']['matchlist']) {
  # matches information
  require_once("modules/analyzer/matchlist.php");
}

# players metadata
if ($lg_settings['ana']['players']) {
  require_once("modules/analyzer/players/additional_data.php");
} else {
  require_once("modules/analyzer/players/unset_nm.php");
}

// ITEMS

if ($lg_settings['ana']['items'])  {
  $sql = "SELECT COUNT(*) z
  FROM information_schema.tables WHERE table_schema = '$lrg_sql_db' 
  AND table_name = 'itemslines' HAVING z > 0;";

  $query = $conn->query($sql);
  if (!isset($query->num_rows) || !$query->num_rows) {
    $lg_settings['main']['itemslines'] = false;
    echo "[N] Set &settings.items to false.\n";
  } else {
    $lg_settings['main']['itemslines'] = true;
    echo "[N] Set &settings.itemslines to true.\n";
  }

  require_once("modules/analyzer/items/__main.php");
}

// SKILL BUILDS

if ($lg_settings['ana']['skill_builds'] && $schema['skill_builds']) {
  require_once("modules/analyzer/skill_builds.php");
}

// ...

$result['settings'] = $lg_settings['web'];

if (isset($lg_settings['heroes_snapshot'])) {
  $result['settings']['heroes_snapshot'] = $lg_settings['heroes_snapshot'];
} else {
  if (isset($lg_settings['heroes_exclude'])) {
    foreach($lg_settings['heroes_exclude'] as $hid)
      unset($meta['heroes'][$hid]);
  } elseif (!empty($result['first_match'])) {
    $last_match = $result["last_match"]["date"];
    $first_match = $result["first_match"]["date"];
  
    $is_cm_only = ( isset($result["modes"][2]) || isset($result["modes"][8]) ) && count($result["modes"]) < 3;
    
    foreach ($meta['heroes'] as $hid => $data) {
      if (!isset($data['released'])) continue;
  
      $line = null;
      $last = null;
      $in_cm = false;
  
      foreach ($data['released'] as $l) {
        if ($l[0] > $last_match) break;
  
        $last = $l;

        $in_cm = (bool)($l[2] ?? 0);
  
        if ($l[0] > $first_match) {
          continue;
        }
        $line = $l;
      }
  
      if (!$line) {
        $line = $last;
      }
  
      if ($is_cm_only) {
        if (!$in_cm) {
          unset($meta['heroes'][$hid]);
        }
      } else {
        if (!$line) {
          unset($meta['heroes'][$hid]);
        }
      }
    }
  }
  $result['settings']['heroes_snapshot'] = array_keys($meta['heroes']);
}
$result['settings']['heroes_exclude'] = $lg_settings['heroes_exclude'] ?? null;


$result['settings']['limiter'] = $limiter;
$result['settings']['limiter_middle'] = $limiter_middle;
$result['settings']['limiter_triplets'] = $limiter_lower;
$result['settings']['limiter_combograph'] = $limiter_graph;
$result['settings']['limiter_players'] = $pl_limiter;
$result['settings']['limiter_players_median'] = $pl_limiter_median;
$result['ana_version'] = $lrg_version;

echo("[ ] Encoding results to JSON\n");
$output = json_encode(utf8ize($result));

$filename = $options['o'] ?? "reports/report_".$lg_settings['league_tag'].".json";
$f = fopen($filename, "w") or die("[F] Couldn't open file to save results.\n");
fwrite($f, $output);
fclose($f);
echo "[S] Recorded results to file `$filename`\n";

$minutes = (microtime(true)-$__start_time)/60;
echo "[ ] Ended execution in ".number_format($minutes, 2)." minutes\n";
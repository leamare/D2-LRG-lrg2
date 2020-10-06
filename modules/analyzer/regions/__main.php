<?php
$regions = [];

if(sizeof($result["regions"]) == 1 ||
    (sizeof($result["regions"]) == 2 && min($result["regions"]) < $limiter*2) ) {
  unset($lg_settings['ana']['regions']);
  return false;
}

$meta['clusters'];

$reg_matches = [];
foreach($result["regions"] as $clid => $matches) {
  if(!isset($reg_matches[ $meta['clusters'][$clid] ?? 0]))
    $reg_matches[ $meta['clusters'][$clid] ?? 0] = $matches;
  else
    $reg_matches[ $meta['clusters'][$clid] ?? 0] += $matches;
}

if(sizeof($reg_matches) == 1 ||
    (sizeof($reg_matches) == 2 && min($result["regions"]) <= $limiter_graph) ) {
  unset($lg_settings['ana']['regions']);
  return false;
}

$lg_settings['ana']['regions']['groups'] = $lg_settings['ana']['regions']['groups_overwrite'] ?? $lg_settings['ana']['regions']['groups'] ?? null;

foreach($meta['clusters'] as $cluster => $region) {
  if ( ($lg_settings['ana']['regions']['group_regions'] ?? false) && !empty($lg_settings['ana']['regions']['groups']) ) {
    foreach ($lg_settings['ana']['regions']['groups'] as $gid => $rs) {
      if (in_array((int)$region, $rs)) {
        $region = (int)$gid;
        break;
      }
    }
    if ($region < 100) $region = $lg_settings['ana']['regions']['fallback_group'] ?? $region;
  }

  if (($region < 100) && (!isset($result['regions'][$region]) || ( ($lg_settings['ana']['regions']['use_limiter'] ?? false) && $reg_matches[$region] < $limiter_median )) ) {
    if (($lg_settings['ana']['regions']['group_regions_when_lower_than_limiter'] ?? false) && isset($lg_settings['ana']['regions']['fallback_group'])) 
      $region = $lg_settings['ana']['regions']['fallback_group'] ?? $region;
    else 
      continue;
  }

  if(!isset($regions[$region])) $regions[$region] = [];
  $regions[$region][] = $cluster;
}

ksort($regions);

foreach ($regions as $region => $clusters) {
    # main stats
    $err = include("overview.php");
    if ($err) continue;

    echo "[ ] REGION $region\n";

    # pickbans
    include("heroes/pickban.php");

    $picks = [];
    foreach($result["regions_data"][$region]["pickban"] as $hero)
        $picks[] = $hero["matches_total"];
    $median = calculate_median($picks);

    $result["regions_data"][$region]['settings'] = calculate_limiters($picks, $result["regions_data"][$region]['main']['teams_on_event'] ?? null);

    # records
    if ($lg_settings['ana']['records'] && isset($lg_settings['ana']['regions']['records'])) {
      require("records.php");
    }

    # heroes
    if (isset($lg_settings['ana']['regions']['heroes'])) {
      if (isset($lg_settings['ana']['regions']['heroes']['haverages']) && $lg_settings['ana']['regions']['heroes']['haverages']) {
        require("heroes/haverages.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['draft']) && $lg_settings['ana']['regions']['heroes']['draft']) {
        require("heroes/draft.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['pairs']) && $lg_settings['ana']['regions']['heroes']['pairs']) {
        require("heroes/pairs.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['meta_graph']) && $lg_settings['ana']['regions']['heroes']['meta_graph']) {
        require("heroes/meta_graph.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['trios']) && $lg_settings['ana']['regions']['heroes']['trios']) {
        require("heroes/trios.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['lane_combos']) && $lg_settings['ana']['regions']['heroes']['lane_combos']) {
        require("heroes/lane_combos.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['positions']) && $lg_settings['ana']['regions']['heroes']['positions']) {
        require("heroes/positions.php");
      }
      if (isset($lg_settings['ana']['regions']['heroes']['summary']) && $lg_settings['ana']['regions']['heroes']['summary']) {
        require("heroes/summary.php");
      }
    }

    # players
    if (isset($lg_settings['ana']['regions']['players']) && $lg_settings['ana']['players']) {
      require("players/summary.php");

      if (isset($lg_settings['ana']['regions']['players']['haverages']) && $lg_settings['ana']['regions']['players']['haverages']) {
        require("players/haverages.php");
      }
      if (isset($lg_settings['ana']['regions']['players']['draft']) && $lg_settings['ana']['regions']['players']['draft']) {
        require("players/draft.php");
      }
      if (!$lg_settings['main']['teams'] && isset($lg_settings['ana']['regions']['players']['parties_graph']) && $lg_settings['ana']['regions']['players']['parties_graph']) {
        require("players/graph.php");
      }
      if (!$lg_settings['main']['teams'] && isset($lg_settings['ana']['regions']['players']['pairs']) && $lg_settings['ana']['regions']['players']['pairs']) {
        require("players/pairs.php");
      }
      if (!$lg_settings['main']['teams'] && isset($lg_settings['ana']['regions']['players']['trios']) && $lg_settings['ana']['regions']['players']['trios']) {
        require("players/trios.php");
      }
      if (!$lg_settings['main']['teams'] && isset($lg_settings['ana']['regions']['players']['lane_combos']) && $lg_settings['ana']['regions']['players']['lane_combos']) {
        require("players/lane_combos.php");
      }
      if (isset($lg_settings['ana']['regions']['players']['positions']) && $lg_settings['ana']['regions']['players']['positions']) {
        require("players/positions.php");
      }
    }

/*
"teams": {
  "summary": true,
  "cards": true
},
"matches": true,
"participants": true
*/

    # teams summary
    if ($lg_settings['main']['teams']) {
      require("teams.php");
    }

    # matches
    if (isset($lg_settings['ana']['matchlist']) && $lg_settings['ana']['matchlist']) {
      require("matches.php");
    }
}

?>

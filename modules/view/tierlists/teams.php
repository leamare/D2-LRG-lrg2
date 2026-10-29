<?php

function rg_view_generate_tierlists_teams($parent_module) {
  global $report, $root, $unset_module, $mod, $strings;
  if ($mod == rtrim($parent_module, "-")) $unset_module = true;

  include_once($root."/modules/view/generators/tier_list.php");

  $res = [];

  $res['heroes'] = [];
  if (!check_module($parent_module."heroes")) return $res;

  if ($mod == $parent_module."heroes") $unset_module = true;
  $parent_module .= "heroes-";

  $tnames = [];
  foreach ($report['teams'] as $tid => $team) {
    if (empty($team['pickban'])) continue;
    if (isset($report['teams_interest']) && !in_array($tid, $report['teams_interest']) && !check_module($parent_module."team".$tid)) continue;
    $tnames[$tid] = team_name($tid);
  }
  uasort($tnames, function($a, $b) {
    return strcasecmp($a, $b);
  });

  foreach ($tnames as $tid => $name) {
    $strings['en']["team".$tid] = $name;
    $res['heroes']["team".$tid] = "";

    if (!check_module($parent_module."team".$tid)) continue;

    [ $stats, $games ] = tier_list_team_heroes_stats($report['teams'][$tid]);

    $res['heroes']["team".$tid] = rg_generator_tier_list_entity_heroes(
      "tierlists-teams-heroes-$tid", $stats, $games, "desc_tierlists_team_heroes"
    );
  }

  return $res;
}

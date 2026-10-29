<?php

function rg_view_generate_tierlists_players($parent_module) {
  global $report, $root, $unset_module, $mod, $strings;
  if ($mod == rtrim($parent_module, "-")) $unset_module = true;

  include_once($root."/modules/view/generators/tier_list.php");

  $res = [];

  $res['heroes'] = [];
  if (!check_module($parent_module."heroes")) return $res;

  if ($mod == $parent_module."heroes") $unset_module = true;
  $parent_module .= "heroes-";

  $stats = tier_list_hero_stats_by_player($report);

  $pnames = [];
  foreach ($stats as $pid => $heroes) {
    $pnames[$pid] = player_name($pid, false);
  }
  uasort($pnames, function($a, $b) {
    return strcasecmp($a, $b);
  });

  foreach ($pnames as $pid => $name) {
    $strings['en']["playerid".$pid] = $name;
    $res['heroes']["playerid".$pid] = "";

    if (!check_module($parent_module."playerid".$pid)) continue;

    $player_stats = $stats[$pid];
    $games = 0;
    foreach ($player_stats as $s) $games += $s['matches'];

    $res['heroes']["playerid".$pid] = rg_generator_tier_list_entity_heroes(
      "tierlists-players-heroes-$pid", $player_stats, $games, "desc_tierlists_player_heroes"
    );
  }

  return $res;
}

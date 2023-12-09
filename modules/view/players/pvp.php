<?php

include_once($root."/modules/view/generators/pvp_unwrap_data.php");
include_once($root."/modules/view/generators/pvp_profile.php");
include_once($root."/modules/view/players/enemies.php");

$modules['players']['pvp'] = [];

function rg_view_generate_players_pvp() {
  global $report, $mod, $parent, $strings, $meta, $unset_module, $root;
  if($mod == $parent."pvp") $unset_module = true;
  $parent_module = $parent."pvp-";

  $winrates = [];
  if (isset($report['players_additional'])) {
    foreach($report['players_additional'] as $id => $player) {
      $winrates[$id]['matches'] = $player['matches'];
      $winrates[$id]['winrate'] = $player['won']/$player['matches'];
    }
  }

  $pvp = rg_generator_pvp_unwrap_data($report['pvp'], $winrates, false);

  $names = $report['players'];

  $res = [];

  $out = [];

  $out['pvpover'] = "";
  if (check_module($parent_module."pvpover")) {
    $parent = $parent_module;
    $out['pvpover'] = rg_view_generate_players_enemies();
  }

  uasort($names, function($a, $b) {
    if($a == $b) return 0;
    else return ($a > $b) ? 1 : -1;
  });

  if($report['settings']['pvp_grid']) {
    $res['grid'] = "";
    if(check_module($parent_module."grid")) {
      include_once($root."/modules/view/generators/pvp_grid.php");
      $res['grid'] = rg_generator_pvp_grid("players-pvp-grid", $report['players'], $pvp);
      $res['grid'] .= "<div class=\"content-text\">".locale_string("desc_players_pvp_grid")."</div>";
    }
    if(check_module($parent_module."profiles")) {
      if($mod == $parent_module."profiles") $unset_module = true;
    }
  }

  foreach($names as $id => $name) {
    $strings['en']["playerid".$id] = player_name($id);
    $out["playerid".$id] = "";

    if(check_module($parent_module.($report['settings']['pvp_grid'] ? "profiles-" : "")."playerid".$id)) {
      $out["playerid".$id] = "<div class=\"content-text\">".locale_string("desc_players_pvp")."</div>";
      $out["playerid".$id] .= rg_generator_pvp_profile("player-pvp-$id", $pvp[$id], $winrates, $id, false);
    }
  }

  if($report['settings']['pvp_grid']) {
    $res['profiles'] = $out;
  } else {
    $res = $out;
  }

  return $res;
}

?>

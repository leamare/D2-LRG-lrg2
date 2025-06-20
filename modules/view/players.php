<?php

$modules['players'] = [];

if (isset($report['averages_players']) )
  include("players/haverages.php");

if (isset($report['players_draft']))
  include("players/draft.php");

if (isset($report['player_positions']) )
  include("players/positions.php");

if (isset($report['player_laning']) )
  include("players/laning.php");

if (isset($report['pvp']) )
  include("players/pvp.php");

if (isset($report['player_pairs']) || isset($report['player_triplets']) || isset($report['player_lane_combos']))
  include("players/combos.php");

if (isset($report['players_combo_graph']) && $report['settings']['players_combo_graph'] && isset($report['players_additional']))
  include("players/party_graph.php");

if (isset($report['starting_items_players']) ) {
  $modules['players']['items'] = [];

  if (isset($report['starting_items_players']['items'])) {
    include("players/starting_items.php");
  }
  if (isset($report['starting_items_players']['builds'])) {
    include("players/starting_builds.php");
  }
  if (isset($report['starting_items_players']['consumables'])) {
    include("players/consumables.php");
  }
}

if (isset($report['fantasy']['players_mvp'])) {
  include("players/fantasy.php");
}

if (isset($report['players_summary']) ) {
  include("players/summary.php");
  include("players/profiles.php");
}


function rg_view_generate_players() {
  global $report, $mod, $parent, $unset_module, $carryon;

  if($mod == "players") $unset_module = true;
  $parent = "players-";
  $res = [];

  if (isset($report['averages_players'])) {
    if (check_module($parent."haverages")) {
      $res['haverages'] = rg_view_generate_players_haverages();
    }
  }
  if (isset($report['players_draft'])) {
    if (check_module($parent."draft")) {
      $res['draft'] = rg_view_generate_players_draft();
    }
  }
  if (isset($report['player_positions'])) {
    if(check_module($parent."positions")) {
      $res['positions'] = rg_view_generate_players_positions();
    }
  }
  if (isset($report['player_laning'])) {
    if(check_module($parent."laning")) {
      $res['laning'] = rg_view_generate_players_laning();
    }
  }
  if (isset($report['pvp'])) {
    if (check_module($parent."pvp")) {
      $res['pvp'] = rg_view_generate_players_pvp();
    }
  }
  if (isset($report['player_pairs']) || isset($report['player_triplets']) || isset($report['player_lane_combos'])) {
    if (check_module($parent."combos")) {
      $res['combos'] = rg_view_generate_players_combos();
    }
  }
  if (isset($report['players_combo_graph']) && $report['settings']['players_combo_graph'] && isset($report['players_additional'])) {
    if (check_module($parent."party_graph")) {
      $res['party_graph'] = rg_view_generate_players_party_graph();
    }
  }
  if (isset($report['starting_items_players'])) {
    if (check_module($parent."items")) {
      if($mod == $parent."items") {
        $unset_module = true;
      }
      $parent .= "items-";

      $carryon["/^players-items-(stitems|stibuilds|sticonsumables)$/"] = "/^players-items-(stitems|stibuilds|sticonsumables)/";

      if(isset($report['starting_items_players']['items']) && check_module($parent."stitems")) {
        $res['items']['stitems'] = rg_view_generate_players_sti_items();
      }
      if(isset($report['starting_items_players']['builds']) && check_module($parent."stibuilds")) {
        $res['items']['stibuilds'] = rg_view_generate_players_sti_builds();
      }
      if(isset($report['starting_items_players']['consumables']) && check_module($parent."sticonsumables")) {
        $res['items']['sticonsumables'] = rg_view_generate_players_sti_consumables();
      }
    }
  }
  if (isset($report['players_summary'])) {
    if(check_module($parent."summary")) {
      $res['summary'] = rg_view_generate_players_summary();
    }
    if(check_module($parent."profiles")) {
      $res['profiles'] = rg_view_generate_players_profiles();
    }
  }
  if (isset($report['fantasy']['players_mvp'])) {
    if(check_module($parent."fantasy")) {
      $res['fantasy'] = rg_view_generate_players_fantasy();
    }
  }

  return $res;
}

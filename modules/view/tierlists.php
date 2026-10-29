<?php

$modules['tierlists'] = [];

include_once($root."/modules/view/functions/tier_lists.php");
include_once($root."/modules/view/functions/entity_cards.php");

if (isset($report['pickban']) || !empty($report['matches']) || !empty($report['teams'])) {
  $modules['tierlists']['heroes'] = [];
  include("tierlists/heroes_meta.php");
  include("tierlists/heroes_players.php");
  include("tierlists/heroes_teams.php");
}

if (!empty($report['matches'])) {
  $modules['tierlists']['players'] = [];
  include("tierlists/players.php");
}

if (!empty($report['teams'])) {
  $modules['tierlists']['teams'] = [];
  include("tierlists/teams.php");
}

function rg_view_generate_tierlists() {
  global $report, $mod, $_rawmod, $parent, $unset_module, $carryon;

  if ($mod == "tierlists") $unset_module = true;
  $parent = "tierlists-";
  $res = [];

  generate_positions_strings();

  $carryon["/^tierlists-heroes-(players|teams)$/"] = "/^tierlists-heroes-(players|teams)/";

  if (check_module($parent."heroes")) {
    $res['heroes'] = rg_view_generate_tierlists_heroes($parent."heroes-");
  }
  if (!empty($report['matches']) && check_module($parent."players")) {
    $res['players'] = rg_view_generate_tierlists_players($parent."players-");
  }
  if (!empty($report['teams']) && check_module($parent."teams")) {
    $res['teams'] = rg_view_generate_tierlists_teams($parent."teams-");
  }

  return $res;
}

function rg_view_generate_tierlists_heroes($parent_module) {
  global $report, $mod, $unset_module;
  if ($mod == rtrim($parent_module, "-")) $unset_module = true;

  $res = [];

  if (isset($report['pickban'])) {
    $res['meta'] = "";
    if (check_module($parent_module."meta")) {
      $res['meta'] = rg_view_generate_tierlists_heroes_meta($parent_module."meta-");
    }
  }
  if (!empty($report['matches'])) {
    $res['players'] = "";
    if (check_module($parent_module."players")) {
      $res['players'] = rg_view_generate_tierlists_heroes_players($parent_module."players-");
    }
  }
  if (!empty($report['teams'])) {
    $res['teams'] = "";
    if (check_module($parent_module."teams")) {
      $res['teams'] = rg_view_generate_tierlists_heroes_teams($parent_module."teams-");
    }
  }

  return $res;
}

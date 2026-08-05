<?php

$modules['tierlists'] = [];

include_once($root."/modules/view/functions/tier_lists.php");
include_once($root."/modules/view/functions/entity_cards.php");

if (isset($report['pickban']))
  include("tierlists/heroes.php");

if (!empty($report['matches']))
  include("tierlists/players.php");

if (!empty($report['teams']))
  include("tierlists/teams.php");

function rg_view_generate_tierlists() {
  global $report, $mod, $parent, $unset_module, $carryon;

  if ($mod == "tierlists") $unset_module = true;
  $parent = "tierlists-";
  $res = [];

  generate_positions_strings();

  $carryon["/^tierlists-(players|teams)$/"] = "/^tierlists-(players|teams)/";

  if (isset($report['pickban']) && check_module($parent."heroes")) {
    $res['heroes'] = rg_view_generate_tierlists_heroes();
  }
  if (!empty($report['matches']) && check_module($parent."players")) {
    $res['players'] = rg_view_generate_tierlists_players();
  }
  if (!empty($report['teams']) && check_module($parent."teams")) {
    $res['teams'] = rg_view_generate_tierlists_teams();
  }

  return $res;
}

<?php
include_once("$root/modules/view/functions/teams_diversity_recalc.php");

$modules['teams'] = [];

function rg_view_generate_teams() {
  global $mod, $parent, $unset_module, $report, $meta, $strings, $root;
  if($mod == "teams") $unset_module = true;
  $parent = "teams-";

  $res = [];

  $res['summary'] = "";
  if (check_module($parent."summary")) {
    include_once("$root/modules/view/generators/teams_summary.php");

    foreach ($report['teams'] as $team => $data) {
      if (!isset($data['averages']) || !isset($data['averages']['hero_pool'])) continue;

      $report['teams'][$team]['averages']['diversity'] = teams_diversity_recalc($data);
    }

    $res['summary'] = rg_view_generator_teams_summary();
  }

  if (!empty($report['tvt'])) {
    $res['grid'] = "";
    if (check_module($parent."grid")) {
      include_once("teams/grid.php");
      $res['grid'] = rg_view_generate_teams_grid();
    }
  }

  $res['profiles'] = [];
  if (check_module($parent."profiles")) {
    include_once("teams/profiles.php");
    $res['profiles'] = rg_view_generate_teams_profiles($report['teams'], $parent."profiles-");
  }

  $res['cards'] = "";
  if (check_module($parent."cards")) {
    include_once("$root/modules/view/generators/participants_teams.php");
    $res['cards'] .= "<div class=\"content-text\">".locale_string("desc_participants")."</div>";
    $res['cards'] .= rg_generator_participants_teams($report['teams']);
  }

  return $res;
}
?>

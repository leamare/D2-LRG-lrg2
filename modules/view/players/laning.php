<?php

$modules['players']['laning'] = [];

function rg_view_generate_players_laning() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  if($mod == $parent."laning") $unset_module = true;
  $parent_module = $parent."laning-";
  $res = [];
  include_once($root."/modules/view/generators/laning.php");

  if (is_wrapped($report['player_laning'])) {
    $report['player_laning'] = unwrap_data($report['player_laning']);
  }

  $pids = array_keys($report['player_laning']);
  $pnames = [];
  foreach ($pids as $id) {
    if (!$id) continue;

    $pnames[$id] = player_name($id, false);
  }

  uasort($pnames, function($a, $b) {
    if($a == $b) return 0;
    
    return strcasecmp($a, $b);
  });

  $explainer = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_hvh")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_1")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_2")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_3")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_4")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_5")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_6")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_7")."</div>".
    "</div>".
  "</details>";

  $res['total'] = '';

  if(check_module($parent_module."total")) {
    $res["total"] = "<div class=\"content-text\">".locale_string("desc_heroes_hvh")."</div>";
    
    $res["total"] = $explainer;

    $res["total"] .= rg_generator_laning_profile("$parent_module-total", $report['player_laning'], 0, false);
  }

  foreach($pnames as $pid => $name) {
    $strings['en']["playerid".$pid] = player_name($pid);
    $res["playerid".$pid] = "";

    if(check_module($parent_module."playerid".$pid)) {
      $res["playerid".$pid] = $explainer;
      $res["playerid".$pid] .= rg_generator_laning_profile("$parent_module-$pid", $report['player_laning'], $pid, false);
    }
  }

  return $res;
}



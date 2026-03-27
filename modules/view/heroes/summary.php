<?php
include_once($root."/modules/view/generators/summary.php");
include_once($root."/modules/commons/volatility.php");
include_once($root."/modules/view/generators/pvp_unwrap_data.php");

$modules['heroes']['summary'] = "";

function rg_view_generate_heroes_summary() {
  global $report;

  $inject = [];
  if (isset($report['hvh']) && isset($report['pickban'])) {
    $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);
    foreach ($hvh as $heroid => $pairs) {
      if (!is_numeric($heroid)) continue;
      $vol = rg_volatility_metrics($pairs);
      $inject[$heroid] = [
        'volatility_normalized_relative'      => $vol['normalized_relative'],
        'volatility_normalized_total'         => $vol['normalized_total'],
        'volatility_normalized_avg_advantage' => $vol['normalized_avg_advantage'],
      ];
    }
    unset($hvh);
  }

  $res = rg_generator_summary("heroes-summary", $report['hero_summary'], true, false, false, $inject);

  $res .= "<div class=\"content-text\">".locale_string("desc_heroes_summary")."</div>";

  return $res;
}

?>

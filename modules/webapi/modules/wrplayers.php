<?php 

$endpoints['wrplayers'] = function($mods, $vars, &$report) {
  if (in_array("heroes", $mods)) {
    $type = "heroes";
  } else {
    throw new \Exception("Endpoint `wrplayers` only works for heroes");
  }

  if (is_wrapped($report['hero_winrate_spammers'])) {
    $report['hero_winrate_spammers'] = unwrap_data($report['hero_winrate_spammers']);
  }

  foreach ($report['hero_winrate_spammers'] as $hid => $data) {
    $report['hero_winrate_spammers'][$hid]['diff'] = round($data['q3_wr_avg'] - $data['q1_wr_avg'], 5);
    $report['hero_winrate_spammers'][$hid]['matches_total'] = $report['pickban'][$hid]['matches_picked'];
  }

  return $report['hero_winrate_spammers'];
};

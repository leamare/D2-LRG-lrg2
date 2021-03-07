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

  return $report['hero_winrate_spammers'];
};

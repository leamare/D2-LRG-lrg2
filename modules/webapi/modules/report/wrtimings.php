<?php 

$endpoints['wrtimings'] = function($mods, $vars, &$report) {
  if (in_array("heroes", $mods)) {
    $type = "heroes";
  } else {
    throw new \Exception("Endpoint `wrtimings` only works for heroes");
  }

  if (is_wrapped($report['hero_winrate_timings'])) {
    $report['hero_winrate_timings'] = unwrap_data($report['hero_winrate_timings']);
  }

  return $report['hero_winrate_timings'];
};

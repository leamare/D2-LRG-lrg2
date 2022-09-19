<?php 

function rgapi_generator_overview_positions_section($context, &$pickban, $count = 5, $sort_param = "matches") {
  if(!sizeof($context)) return "";

  for ($i=1; $i>=0 && !isset($keys); $i--) {
    for ($j=0; $j<6 && $j>=0; $j++) {
      //if (!$i) { $j = 0; }
      if(isset($context[$i][$j][0])) {
        $keys = array_keys($context[$i][$j][0]);
        break;
      }
      //if (!$i) { break; }
    }
  }

  for ($i=1; $i>=0; $i--) {
    for ($j=0; $j<4 && $j>=0; $j++) {
      //if (!$i) { $j = 0; }
      if(!empty($context[$i][$j])) {
        $position_overview_template = array("matches" => 0, "wr" => 0);
        break;
      }
      //if (!$i) { break; }
    }
  }

  $overview = [];
  $ranks = [];

  for ($i=1; $i>=0; $i--) {
    for ($j=0; $j<4 && $j>=0; $j++) {
      //if (!$i) { $j = 0; }

      if (empty($context[$i][$j])) {
        //if (!$i) { break; }
        continue;
      }

      $ranks[$i][$j] = [];
      $context_copy = $context[$i][$j];
      $total_matches = 0;
      foreach ($context_copy as $c) {
        if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
      }
  
      uasort($context_copy, function($a, $b) use ($total_matches) {
        return positions_ranking_sort($a, $b, $total_matches);
      });
  
      $increment = 100 / sizeof($context_copy); $k = 0;
      $last_rank = null;
  
      foreach ($context_copy as $id => $el) {
        if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
          $k++;
          $ranks[$i][$j][$id] = $last_rank;
        } else
          $ranks[$i][$j][$id] = 100 - $increment*$k++;
        $last = $el;
        $last_rank = $ranks[$i][$j][$id];
      }
      unset($last);
      unset($context_copy);

      $overview["$i.$j"] = [];

      foreach($context[$i][$j] as $id => $el) {
        if (!isset($overview["$i.$j"][$id])) $overview["$i.$j"][$id] = [];

        $overview["$i.$j"][ $id ]['matches'] = $el['matches_s'];
        $overview["$i.$j"][ $id ]['wr'] = $el['winrate_s'];
        $overview["$i.$j"][ $id ]['rank'] = $ranks[$i][$j][$id]; 
      }

      //if (!$i) { break; }
    }
  }

  $res = [];

  foreach ($overview as $k => $heroes) {
    if (empty($heroes)) continue;
    $res[$k] = [];
    
    uasort($heroes, function($a, $b) use ($sort_param) {
      return $b[$sort_param] <=> $a[$sort_param];
    });

    $i = 0;
    foreach ($heroes as $id => $v) {
      if (++$i > $count) break;
      $v['hero_id'] = $id;
      $res[$k][] = $v; 
    }
  }

  return $res;
}

<?php 

function find_comebacks($gold_times, $radiant_win) {
  $rad_lead = 0;
  $dire_lead = 0;

  $dire_swings = [];
  $rad_swings = [];

  $sz = count($gold_times);
  $half_sz = round(($sz-5)/2) + 5;
  $rad_swing = 0; $dire_swing = 0;
  $rad_swing_duration = 0; $dire_swing_duration = 0;
  $last_rad = $gold_times[4] > 0;

  for ($i = 5; $i < $sz; $i++) {
    if ($i < $half_sz) {
      if ($gold_times[$i] > 0) $rad_lead++;
      else $dire_lead++;
    }

    $diff = $gold_times[$i] - $gold_times[$i-1];
    $reset_radiant = false;
    $reset_dire = false;

    // if (abs($diff) < 900) continue;
    
    if ($diff > 0) {
      $rad_swing += $diff;
      $rad_swing_duration++;
      if (abs($diff) < $rad_swing*0.05) {
        $reset_radiant = true;
      }
      if (!$last_rad) {
        $reset_dire = true;
        $last_rad = true;
      }
    } else if ($diff < 0) {
      $dire_swing -= $diff;
      $dire_swing_duration++;
      if (abs($diff) < $dire_swing*0.05) {
        $reset_dire = true;
      }
      if ($last_rad) {
        $reset_radiant = true;
        $last_rad = false;
      }
    }

    if ($reset_radiant) {
      $rad_swings[] = [
        $rad_swing,
        $rad_swing_duration,
        $gold_times[$i-1-$rad_swing_duration] < 0
      ];
      $rad_swing = 0;
      $rad_swing_duration++;
    }

    if ($reset_dire) {
      $dire_swings[] = [ 
        $dire_swing, 
        $dire_swing_duration, 
        $gold_times[$i-1-$dire_swing_duration] > 0
      ];
      $dire_swing = 0;
      $dire_swing_duration = 0;
    }
  }

  if ($last_rad) {
    if (!$radiant_win) $dire_swings[] = [ $rad_swing, 1, true ];
    else $rad_swings[] = [ $rad_swing, $rad_swing_duration, $gold_times[$sz-1]-$rad_swing < 0 ];
  } else {
    if ($radiant_win) $rad_swings[] = [ $dire_swing, 1, true ];
    else $dire_swings[] = [ $dire_swing, $dire_swing_duration, $gold_times[$sz-1]+$dire_swing > 0 ];
  }

  $swings = ($dire_swings + $rad_swings);
  $comebacks = [0];
  $stomps = [0];
  foreach ($swings as $swing) {
    if($swing[1] < 10 && $swing[2]) $comebacks[] = $swing[0];
    else $stomps[] = $swing[0];
  }

  $max_rad = empty($rad_swings) ? 0 : max($rad_swings);
  $max_dire = empty($dire_swings) ? 0 : max($dire_swings);

  $is_rad_lead = $rad_lead > $dire_lead;

  $stomp_team = null; $stomp = 0;
  foreach($gold_times as $gt) {
    if (abs($gt) < 1500) continue;
    if ($stomp_team === null) {
      $stomp_team = $gt > 0 ? true : false;
    }

    if ($stomp_team) {
      if ($gt < 0) break;
      $stomp = max($gt, $stomp);
    } else {
      if ($gt > 0) break;
      $stomp = max(-$gt, $stomp);
    }
  }

  //$t_match['stomp'] = $is_rad_lead ? max($matchdata['radiant_gold_adv']) : -min($matchdata['radiant_gold_adv']);
  // $stomp = max($stomps);
  // var_dump($swings);
  //$t_match['comeback'] = $is_rad_lead ? max($dire_swings) : max($rad_swings);
  $comeback = max($comebacks);

  return [ $stomp, $comeback ];
}

function hero_tag_to_id($tag) {
  global $meta;

  $tag = str_replace("npc_dota_hero_", "", $tag);
  foreach ($meta['heroes'] as $hid => $hero) {
    if ($hero['tag'] == $tag) return $hid;
  }
}

// function inventory_at($purchase_log, $time) {
  
// }

function add_networths(&$matchdata) {
  global $meta;
  /* 
    use gold_t as networth at the time (then recalculate all following values)
    not accurate, but roughly close
  */

  $gold_times = $matchdata['radiant_gold_adv'];

  $death_times = [];
  $death_killers = [];
  $sides = [];
  $ids = [];
  $buybacks = [];
  foreach ($matchdata['players'] as $i => $player) {
    $sides[ $player['hero_id'] ] = $player['isRadiant'];
    $ids[ $player['hero_id'] ] = $i;

    foreach ($player['kills_log'] as $kill) {
      $hid = hero_tag_to_id($kill['key']);
      if (!isset($death_times[$hid])) { 
        $death_times[$hid] = [];
        $death_killers[$hid] = [];
      }
      $death_times[$hid][] = $kill['time'];
      $death_killers[$hid][$kill['time']] = $player['hero_id'];
    }

    foreach ($player['buyback_log'] as $bb) {
      $buybacks[] = [ $player['hero_id'], $bb['time'] ];
    }
  }

  $items_losable = [ 133, 30 ];
  $items_comsumables = [ 44, 39, 38, 216 ];
  // permanent_buffs

  // $items_purchases

  // 1. go through deaths of every hero
  // 2. for every death calculate death cost (roughly)
  //    if had rapier in their inventory: 
  //       1. remove it from the owner
  //       2. give it to the killer in their purchase log

  foreach ($death_times as $hid => $times) {
    $side = $sides[$hid];
    foreach ($times as $t) {
      $i = ceil($t/60);

      // $inventory = inventory_at($matchdata['players'][ $ids[$hid] ], $t);
      $has_rapier = 0;
      $has_gem = 0;
      foreach ($matchdata['players'][ $ids[$hid] ]['purchase_log'] as $i => $e) {
        if ($e['time'] < $t || $e['time'] === null) continue;
        if ($e['key'] == "rapier") {
          $has_rapier++;
          $matchdata['players'][ $ids[$hid] ]['purchase_log'][$i]['time'] = null;
          $matchdata['players'][ $ids[ $death_killers[$hid][$t] ] ]['purchase_log'][] = [
            'key' => 'rapier',
            'time' => $t,
          ];
        }
        if ($e['key'] == "gem") {
          $has_gem++;
          $matchdata['players'][ $ids[$hid] ]['purchase_log'][$i]['time'] = null;
          $matchdata['players'][ $ids[ $death_killers[$hid][$t] ] ]['purchase_log'][] = [
            'key' => 'gem',
            'time' => $t,
          ];
        }
      }
      
      if (!isset($matchdata['players'][ $ids[$hid] ]['gold_t'][$i])) $i = count($gold_times)-1;

      $delta = round($matchdata['players'][ $ids[$hid] ]['gold_t'][$i]/40)
        + ($has_rapier ? $meta['items_full'][133]['cost']*$has_rapier : 0)
        + ($has_gem ? $meta['items_full'][133]['cost']*$has_gem : 0);
      
      for ($j = $i, $c = count($gold_times); $j < $c; $j++) {
        $gold_times[$j] += $delta * ($side ? -1 : 1);
      }
    }
  }

  foreach ($buybacks as [ $hid, $t ]) {
    $side = $sides[$hid];

    if (!isset($matchdata['players'][ $ids[$hid] ]['gold_t'][$i])) $i = count($gold_times)-1;

    $delta = round(200 + $matchdata['players'][ $ids[$hid] ]['gold_t'][$i]/13);
    
    for ($j = $i, $c = count($gold_times); $j < $c; $j++) {
      $gold_times[$j] += $delta * ($side ? -1 : 1);
    }
  }

  $matchdata['radiant_nw_adv'] = $gold_times;
}
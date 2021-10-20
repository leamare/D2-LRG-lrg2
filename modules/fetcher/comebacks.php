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
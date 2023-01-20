<?php 

$endpoints['daily_wr'] = function($mods, $vars, &$report) {
  if (in_array("heroes", $mods)) {
    $type = "heroes";
  } else {
    throw new \Exception("Endpoint `daily_wr` only works for heroes");
  }

  if (is_wrapped($report['hero_daily_wr'])) {
    $days = $report['hero_daily_wr']['head'][0];
    sort($days);
    $report['hero_daily_wr_days'] = $days;
    $report['hero_daily_wr'] = unwrap_data($report['hero_daily_wr']);
  } else {
    $days = array_keys($report['hero_daily_wr'][ array_keys($report['hero_daily_wr'])[0] ]);
    sort($days);
  }

  $res = [];

  $global_days = [];
  if (count($days) == count($report['days'])) {
    foreach($report['days'] as $d) {
      $global_days[ $d['timestamp'] ] = $d['matches_num'];
      // $days[] = $d['timestamp'];
    }
  } else {
    $sz = count($days)-1; $i = -1;
    foreach($report['days'] as $d) {
      if ($i < $sz && $d['timestamp'] >= $days[ $i+1 ]) {
        $i++;
        $global_days[ $days[$i] ] = 0;
      }
      $global_days[ $days[$i] ] += $d['matches_num'];
    }
  }

  foreach ($report['hero_daily_wr'] as $hid => $days_data) {
    $dwr = []; $dm = []; $dmb = []; $prev = null; $prev_b = null;
    $prev_dt = null;
    $first_wr = 0; $first_ms = 0; $first_msb = 0;
    foreach($days as $dt) {
      $dd = $days_data[$dt] ?? [ 'ms' => 0, 'wr' => 0 ];

      if ($prev_dt == null || $global_days[$dt]/$prev_dt > 0.05) {
        $prev_dt = $global_days[$dt] ?? null;
  //       if (isset($prev_b) && $prev_b*0.15 > ($dd['bn'] ?? 0)) {
  //         $dmb[] = $dmb[ count($dmb)-1 ];
  //       } else {
  //         $prev_b = $dd['bn'] ?? 0;
          $dmb[] = round(100*($dd['bn'] ?? 0)/$global_days[$dt], 2);
  //       }
        if (!$first_msb && ($dd['bn'] ?? 0)) {
          $first_msb = round(100*$dd['bn']/$global_days[$dt], 2);
        }

  //       if (isset($prev) && $prev*0.15 > $dd['ms']) {
  //         $dwr[] = $dwr[ count($dwr)-1 ];
  //         $dm[] = $dm[ count($dm)-1 ];
  //         
  //         continue;
  //       } else {
  //         $prev = $dd['ms'];
  //       }
        if (!$first_ms && $dd['ms']) {
          $first_ms = round(100*$dd['ms']/$global_days[$dt], 2);
          $first_wr = $dd['wr']*100;
        }

        $dwr[] = $dd['wr']*100;
        $dm[] = round(100*$dd['ms']/$global_days[$dt], 2);
      } else {
        $dmb[] = $dmb[ sizeof($dmb)-1 ];
        $dm[] = $dm[ sizeof($dm)-1 ];
        $dwr[] = $dwr[ sizeof($dwr)-1 ];
      }

      foreach ($dm as $i => $m) {
        if ($m) continue;
  
        $prev = $i ? $dwr[$i-1] : null;
        $next = $i<count($dwr)-1 ? $dwr[$i+1] : null;
        $dwr[$i] = (($prev ?? $next) + ($next ?? $prev))/2;
      }
    }

    $res[$hid] = [
      'pickrate_first' => $first_ms,
      'pickrate_days' => $dm,
      'pickrate_last' => $dm[ count($dm)-1 ],
      'pickrate_diff' => round($dm[ count($dm)-1 ]-$first_ms, 4),

      'banrate_first' => $first_msb,
      'banrate_days' => $dmb,
      'banrate_last' => $dmb[ count($dmb)-1 ],
      'banrate_diff' => round($dmb[ count($dmb)-1 ]-$first_msb, 4),

      'winrate_first' => $first_wr,
      'winrate_days' => $dwr,
      'winrate_last' => $dwr[ count($dwr)-1 ],
      'winrate_diff' => round($dwr[ count($dwr)-1 ]-$first_wr, 4),
    ];
  }

  return $res;
};

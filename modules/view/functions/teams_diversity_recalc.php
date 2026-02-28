<?php 

function core_picked_percentage($array, $q1 = null, $q3 = null) {
  if (empty($array)) return 0;

  sort($array);
  
  $sz = count($array);
  if (!isset($q3)) $q3 = $array[min((int)round($sz*0.75), $sz-1)];
  if (!isset($q1)) $q1 = $array[min((int)round($sz*0.25), $sz-1)];
  $q2 = $array[min((int)round($sz*0.5), $sz-1)];

  $arr2 = array_filter($array, function($a) use ($q1, $q3) {
      return $a >= $q1 && $a <= $q3;
  });
  
  
  return count($arr2)/($sz);
}

function teams_diversity_recalc(&$team) {
  global $meta;

  $pb = [];

  foreach ($team['pickban'] as $line) {
    if ($line['matches_picked']) $pb[] = $line['matches_picked'];
  }

  $pool = $team['averages']['hero_pool'];
  $mpool = ceil(count($meta['heroes'])/5);

  $matches = (int)$team['matches_total'];

  $core = core_picked_percentage($pb);

  return sqrt( ( $pool / (min($matches, $mpool) * 5)) * $core );
}

function balance_rank($pickban) {
  global $meta, $report;

  $key = array_keys($pickban)[0];
  $facets = false;
  $hlist = $meta['heroes'];

  if (!is_numeric($key) && strpos($key, '-')) {
    $facets = true;
    $hlist_var = [];
    if (isset($report['meta']['variants'])) {
      foreach ($report['meta']['variants'] as $hid => $facets) {
        foreach ($facets as $i => $f) {
          $hlist[$hid.'-'.$i] = true;
        }
      }
    } else {
      foreach ($meta['facets']['heroes'] as $hid => $facets) {
        foreach ($facets as $i => $f) {
          $hlist[$hid.'-'.$i] = true;
        }
      }
    }
  }

  uasort($pickban, function($a, $b) {
    return $b['matches_total'] <=> $a['matches_total'];
  });
  $mc = $pickban[ array_keys($pickban)[ round(count($pickban)/2) ] ]['matches_total'];

  uasort($pickban, function($a, $b) {
      return $b['matches_picked'] <=> $a['matches_picked'];
  });
  $mp = $pickban[ array_keys($pickban)[ round(count($pickban)/2) ] ]['matches_picked'];

  $cr = [];
  $pr = [];
  $wr = [];

  foreach($pickban as $hid => $vals) {
    if (is_numeric($hid)) {
      unset($hlist[$hid]);
    } else {
      if (strpos($hid, '-')) {
        [$hero, $var] = explode('-', $hid);
        unset($hlist[$hero]);
        unset($hlist_var[$hid]);
      } else {
        [$hero, $var] = explode('|', $hid);
        unset($hlist[$hero]);
      }
    }

    $_cr = round($vals['matches_total']/($mc ? $mc : 1));
    $_pr = round($vals['matches_picked']/($mp ? $mp : 1));

    $cr[] = $_cr;
    $pr[] = $_pr;
    
    if ($vals['matches_picked'] && $_pr >= 1)
      $wr[] = $vals['winrate_picked'];
  }

  if ($facets) {
    $cr_f = $cr;
    $pr_f = $pr;

    foreach($hlist_var as $hid) {
      $cr_f[] = 0;
      $pr_f[] = 0;
    }
  }

  if (!empty($hlist)) {
    foreach($hlist as $hid) {
      $cr[] = 0;
      $pr[] = 0;
    }
  }

  sort($wr);
  $medwr = $wr[ round(count($wr)*0.5) ];

  $maxwr = max($wr)-$medwr;

  $skew_wr = core_picked_percentage($wr, $medwr-0.5*$maxwr, $medwr+0.5*$maxwr);
  $skew_pr = core_picked_percentage($pr, 1);
  $skew_cr = core_picked_percentage($cr, 1);

  if ($facets) {
    $skew_pr = core_picked_percentage($pr_f, 1);
    $skew_cr = core_picked_percentage($cr_f, 1);
  }

  return [
    (3 * $skew_wr + 2 * $skew_pr + $skew_cr ) / 6,
    $skew_wr,
    $skew_pr,
    $skew_cr
  ];
}
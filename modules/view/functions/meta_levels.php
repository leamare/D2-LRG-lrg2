<?php

const META_LEVELS_MIN_PICKS_FLOOR = 3;
const META_LEVELS_ELIGIBILITY_MULT = 1.0;
const META_LEVELS_EWMA_ALPHA = 0.3;
const META_LEVELS_BUCKETS_PER_LAYER = 3;
const META_LEVELS_TARGET_WINDOW = 8;
const META_LEVELS_MAX_LAYERS = 12;
const META_LEVELS_MIN_CORE = 4;
const META_LEVELS_MAX_CORE = 8;
const META_LEVELS_MAX_COMBO = 6;
const META_LEVELS_CORE_RATIO = 0.3;
const META_LEVELS_COMBO_RATIO = 0.3;
const META_LEVELS_MIN_PAIR_MATCHES = 5;
const META_LEVELS_LOOP_SIMILARITY = 0.5;
const META_LEVELS_LOOP_MIN_CORE_SIZE = 3;
const META_LEVELS_RETURN_COOLDOWN = 3;
const META_LEVELS_MAX_RETURNS = 2;
const META_LEVELS_RETURN_RESURGENCE = 1.15;
const META_LEVELS_PROJECTION_MIN_PAIR_MATCHES = 3;
const META_LEVELS_PROJECTION_ELIGIBILITY = 0.5;
const META_LEVELS_PROJECTION_BLOCK_LAYERS = 2;
const META_LEVELS_MAX_PROJECTIONS = 3;

function meta_levels_jaccard($a, $b) {
  if (empty($a) && empty($b)) return 1;
  $union = count(array_unique(array_merge($a, $b)));
  if (!$union) return 0;
  $intersect = count(array_intersect($a, $b));
  return $intersect / $union;
}

function meta_levels_hph_lookup(&$hph, $a, $b) {
  if (empty($hph[$a][$b]) || !is_array($hph[$a][$b])) return null;
  $p = $hph[$a][$b];
  if (($p['matches'] ?? -1) === -1) {
    $p = $hph[$b][$a] ?? null;
  }
  return (is_array($p) && ($p['matches'] ?? -1) >= 0) ? $p : null;
}

function meta_levels_hvh_index(&$hvh) {
  $idx = [];
  if (empty($hvh)) return $idx;
  foreach ($hvh as $row) {
    $idx[$row['heroid1']][$row['heroid2']] = $row;
  }
  return $idx;
}

function meta_levels_matchup(&$hvh_idx, $x, $y) {
  if ($x == $y) return null;
  if ($x < $y) {
    $r = $hvh_idx[$x][$y] ?? null;
    return $r ? [ 'wr' => $r['h1winrate'], 'matches' => $r['matches'] ] : null;
  }
  $r = $hvh_idx[$y][$x] ?? null;
  return $r ? [ 'wr' => 1 - $r['h1winrate'], 'matches' => $r['matches'] ] : null;
}

function meta_levels_counter_record(&$hvh_idx, $hid, $core, $min_matches) {
  $wins = 0; $n = 0; $pairs = 0; $beats = 0; $edge = 0;
  foreach ($core as $cid) {
    $m = meta_levels_matchup($hvh_idx, $hid, $cid);
    if (!$m || $m['matches'] < $min_matches) continue;
    $wins += $m['wr'] * $m['matches'];
    $n += $m['matches'];
    $pairs++;
    if ($m['wr'] > 0.5) $beats++;
    $edge += $m['wr'] - 0.5;
  }
  if ($n <= 0 || $pairs <= 0) return null;
  return [
    'wr' => $wins / $n,
    'matches' => $n,
    'pairs' => $pairs,
    'beats' => $beats,
    'beat_share' => $beats / $pairs,
    'edge' => $edge / $pairs,
  ];
}

function meta_levels_counter_bonus($rec) {
  if ($rec === null) return 0;
  return max(0, ($rec['beat_share'] - 0.5) * 2) * (1 + max(0, $rec['edge']) * 4);
}

function meta_levels_dynamic_cutoff($scores, $ratio, $max_n, $min_n = 0) {
  if (empty($scores)) return [];
  arsort($scores);
  $top = reset($scores);
  if ($top <= 0) return [];

  $out = [];
  foreach ($scores as $hid => $s) {
    if ($s <= 0) break;
    if ($s < $top * $ratio && count($out) >= $min_n) break;
    $out[] = $hid;
    if (count($out) >= $max_n) break;
  }
  return $out;
}

function meta_levels_build_emergence(&$pickban, &$daily, $matches_total, &$shares) {
  $total_picks_by_day = [];
  foreach ($daily as $hid => $series) {
    foreach ($series as $ts => $v) {
      $total_picks_by_day[$ts] = ($total_picks_by_day[$ts] ?? 0) + ($v['ms'] ?? 0);
    }
  }

  $shares = [];
  $emergence = [];
  foreach ($daily as $hid => $series) {
    $overall_picks = $pickban[$hid]['matches_picked'] ?? 0;
    if ($overall_picks < META_LEVELS_MIN_PICKS_FLOOR) continue;

    ksort($series);

    $ewma = 0;
    $ewma_by_day = [];
    $shares[$hid] = [];
    foreach ($series as $ts => $v) {
      $share = $total_picks_by_day[$ts] > 0 ? ($v['ms'] ?? 0) / $total_picks_by_day[$ts] : 0;
      $shares[$hid][$ts] = $share;
      $ewma = $ewma * (1 - META_LEVELS_EWMA_ALPHA) + $share * META_LEVELS_EWMA_ALPHA;
      $ewma_by_day[$ts] = $ewma;
    }

    $sorted_ewma = array_values($ewma_by_day);
    sort($sorted_ewma);
    $peak = $sorted_ewma[ (int)floor(0.9 * (count($sorted_ewma) - 1)) ];

    $emerged = null;
    if ($peak > 0) {
      foreach ($ewma_by_day as $ts => $ewma_v) {
        if ($ewma_v >= $peak * 0.5) { $emerged = (int)$ts; break; }
      }
    }

    $wr = $pickban[$hid]['winrate_picked'] ?? 0;
    $pick_rate = $matches_total > 0 ? $overall_picks / $matches_total : 0;
    $ban_rate = $matches_total > 0 ? ($pickban[$hid]['matches_banned'] ?? 0) / $matches_total : 0;

    $emergence[$hid] = [
      'emerged' => $emerged,
      'peak_share' => $peak,
      'overall_wr' => $wr,
      'overall_picks' => $overall_picks,
      'pick_rate' => $pick_rate,
      'ban_rate' => $ban_rate,
      'prominence' => ($pick_rate + 0.5 * $ban_rate) * (1 + 4 * max(0, $wr - 0.5)),
    ];
  }

  return $emergence;
}

function meta_levels_eligible_pool(&$emergence, $mult = META_LEVELS_ELIGIBILITY_MULT) {
  $rates = array_column($emergence, 'pick_rate');
  if (empty($rates)) return [];
  sort($rates);
  $median = $rates[ (int)floor(count($rates) / 2) ];
  $floor = $median * $mult;

  $pool = [];
  foreach ($emergence as $hid => $e) {
    if ($e['pick_rate'] >= $floor) $pool[$hid] = true;
  }
  return $pool;
}

function meta_levels_windows(&$pool, &$emergence, $day_count) {
  $ranked = array_keys($pool);
  if (empty($ranked)) return [];

  usort($ranked, function($a, $b) use ($emergence) {
    $ea = $emergence[$a]['emerged'];
    $eb = $emergence[$b]['emerged'];
    if ($ea === null && $eb === null) return $emergence[$b]['prominence'] <=> $emergence[$a]['prominence'];
    if ($ea === null) return 1;
    if ($eb === null) return -1;
    if ($ea != $eb) return $ea <=> $eb;
    return $emergence[$b]['prominence'] <=> $emergence[$a]['prominence'];
  });

  $k = (int)ceil($day_count / META_LEVELS_BUCKETS_PER_LAYER);
  $k = min($k, (int)floor(count($ranked) / META_LEVELS_TARGET_WINDOW));
  $k = max(1, min($k, META_LEVELS_MAX_LAYERS));

  $per = (int)ceil(count($ranked) / $k);
  $windows = [];
  for ($i = 0; $i < $k; $i++) {
    $slice = array_slice($ranked, $i * $per, $per);
    if (empty($slice)) break;
    $windows[] = $slice;
  }

  return $windows;
}

function meta_levels_pick_combos_raw($core, &$hph, &$used) {
  if (empty($hph)) return [];

  $scores = [];
  foreach ($core as $hid) {
    if (empty($hph[$hid])) continue;
    foreach ($hph[$hid] as $oid => $p) {
      if ($oid === '_h' || isset($used[$oid]) || in_array($oid, $core)) continue;
      $p = meta_levels_hph_lookup($hph, $hid, $oid);
      if (!$p || $p['matches'] < META_LEVELS_MIN_PAIR_MATCHES) continue;
      if ($p['matches'] <= $p['exp'] || $p['wr_diff'] <= 0) continue; // no real synergy signal

      $score = ($p['matches'] - $p['exp']) * (1 + max(0, $p['wr_diff']) * 5);
      if (!isset($scores[$oid]) || $scores[$oid] < $score) $scores[$oid] = $score;
    }
  }

  return $scores;
}

function meta_levels_mean_share(&$shares, $hid, $t0, $t1) {
  if (empty($shares[$hid])) return null;
  $sum = 0; $n = 0;
  foreach ($shares[$hid] as $ts => $s) {
    if ($ts < $t0 || ($t1 !== null && $ts >= $t1)) continue;
    $sum += $s; $n++;
  }
  return $n > 0 ? $sum / $n : null;
}

function meta_levels_score_window($window, $prev_core, &$pool, &$emergence, &$hvh_idx, &$shares, $t0, $t1, &$last_core_layer, $w) {
  $window_set = array_flip($window);
  $prev_set = array_flip($prev_core);

  $counter_bonus = function($hid) use (&$hvh_idx, $prev_core) {
    if (empty($prev_core)) return 0;
    return meta_levels_counter_bonus(
      meta_levels_counter_record($hvh_idx, $hid, $prev_core, META_LEVELS_MIN_PAIR_MATCHES)
    );
  };

  $scores = [];
  foreach ($window as $hid) {
    if (isset($prev_set[$hid])) continue; // nothing answers itself
    $prominence = $emergence[$hid]['prominence'] ?? 0;
    if ($prominence <= 0) continue;
    $scores[$hid] = $prominence * (1 + $counter_bonus($hid));
  }

  if ($t0 === null) return $scores;

  $returns = [];
  foreach ($pool as $hid => $_) {
    if (isset($window_set[$hid]) || isset($prev_set[$hid])) continue;
    if (isset($last_core_layer[$hid]) && ($w - $last_core_layer[$hid]) < META_LEVELS_RETURN_COOLDOWN) continue;

    $prominence = $emergence[$hid]['prominence'] ?? 0;
    if ($prominence <= 0) continue;

    $window_share = meta_levels_mean_share($shares, $hid, $t0, $t1);
    $overall_share = meta_levels_mean_share($shares, $hid, 0, null);
    if ($window_share === null || !$overall_share) continue;
    if ($window_share < $overall_share * META_LEVELS_RETURN_RESURGENCE) continue;

    $cb = $counter_bonus($hid);
    if ($cb <= 0) continue;

    $returns[$hid] = $prominence * (1 + $cb) * 0.9;
  }

  arsort($returns);
  foreach (array_slice($returns, 0, META_LEVELS_MAX_RETURNS, true) as $hid => $s) {
    $scores[$hid] = $s;
  }

  return $scores;
}

function meta_levels_normalize($layers) {
  for ($pass = 0; $pass < 8; $pass++) {
    $changed = false;

    for ($i = 1; $i < count($layers); $i++) {
      $dupes = array_intersect($layers[$i]['core'], $layers[$i-1]['core']);
      if (!empty($dupes)) {
        $layers[$i]['core'] = array_values(array_diff($layers[$i]['core'], $dupes));
        $changed = true;
      }
    }

    for ($i = count($layers) - 1; $i >= 0; $i--) {
      if (count($layers[$i]['core']) >= META_LEVELS_MIN_CORE) continue;

      if ($i > 0) {
        $layers[$i-1]['core'] = array_values(array_unique(array_merge($layers[$i-1]['core'], $layers[$i]['core'])));
        array_splice($layers, $i, 1);
        $changed = true;
      } else if (count($layers) > 1 && empty($layers[$i]['core'])) {
        array_splice($layers, $i, 1);
        $changed = true;
      }
    }

    if (!$changed) break;
  }

  return array_values($layers);
}

function meta_levels_reasoning($layer_index, $core, $combo, $prev_core, &$hvh_idx) {
  $reasons = [];

  foreach ($core as $hid) {
    if ($layer_index === 0) {
      $reasons["tags_$hid"] = [ 'meta0_founder' ];
      continue;
    }

    $beats = [];
    foreach ($prev_core as $pid) {
      $m = meta_levels_matchup($hvh_idx, $hid, $pid);
      if ($m && $m['matches'] >= META_LEVELS_MIN_PAIR_MATCHES && $m['wr'] > 0.5) $beats[] = $pid;
    }
    if (!empty($beats)) $reasons["counters_$hid"] = $beats;
    $reasons["tags_$hid"] = in_array($hid, $prev_core) ? [ 'carried_over' ] : [];
  }

  foreach ($combo as $hid) {
    $reasons["tags_$hid"] = [ 'combo_piece' ];
  }

  return $reasons;
}

function meta_levels_project(&$layers, &$pool, &$emergence, &$hvh_idx, &$hph, $all_core) {
  $last_index = count($layers) - 1;
  if ($last_index < 0) return [];

  $last = $layers[$last_index];

  if (isset($last['loops_to'])) {
    $loop_start = $last['loops_to'];
    $period = $last_index - $loop_start;
    if ($period <= 0) $period = 1;

    $projections = [];
    for ($step = 1; $step <= META_LEVELS_MAX_PROJECTIONS; $step++) {
      $target = $loop_start + (($last_index + $step - $loop_start) % $period);
      $projections[] = [
        'method' => 'loop',
        'loop_period' => $period,
        'loop_start' => $loop_start,
        'core' => $layers[$target]['core'],
        'combo' => $layers[$target]['combo'],
      ];
    }
    return $projections;
  }

  $projections = [];
  $ref_core = $last['core'];
  $blocked = [];
  $block_from = max(0, count($layers) - META_LEVELS_PROJECTION_BLOCK_LAYERS);
  for ($i = count($layers) - 1; $i >= $block_from; $i--) {
    foreach ($layers[$i]['core'] as $h) $blocked[$h] = true;
  }

  for ($step = 0; $step < META_LEVELS_MAX_PROJECTIONS; $step++) {
    $min_pairs = max(1, META_LEVELS_PROJECTION_MIN_PAIR_MATCHES - $step);

    $scores = [];
    foreach ($pool as $hid => $_) {
      if (isset($blocked[$hid])) continue;
      $rec = meta_levels_counter_record($hvh_idx, $hid, $ref_core, $min_pairs);
      if ($rec === null || $rec['beat_share'] < 0.5) continue;
      $scores[$hid] = ($emergence[$hid]['prominence'] ?? 0) * (1 + meta_levels_counter_bonus($rec));
    }

    $core_proj = meta_levels_dynamic_cutoff($scores, META_LEVELS_CORE_RATIO, META_LEVELS_MAX_CORE, META_LEVELS_MIN_CORE);
    $method = 'trend';
    $loops_to = null;

    if (count($core_proj) < META_LEVELS_MIN_CORE) {
      $best = null; $best_score = -1;
      for ($i = 0; $i < $block_from; $i++) {
        if (!empty($projections) && $layers[$i]['core'] === end($projections)['core']) continue;
        $share = 0; $n = 0;
        foreach ($layers[$i]['core'] as $h) {
          $rec = meta_levels_counter_record($hvh_idx, $h, $ref_core, 1);
          if ($rec === null) continue;
          $share += $rec['beat_share']; $n++;
        }
        if (!$n) continue;
        $score = $share / $n;
        if ($score > $best_score) { $best_score = $score; $best = $i; }
      }

      if ($best === null) break;

      $core_proj = $layers[$best]['core'];
      $method = 'loop';
      $loops_to = $best;
    }

    $used_for_combo = $all_core;
    foreach ($core_proj as $h) $used_for_combo[$h] = true;
    $combo_proj = $loops_to !== null
      ? $layers[$loops_to]['combo']
      : meta_levels_dynamic_cutoff(
          meta_levels_pick_combos_raw($core_proj, $hph, $used_for_combo),
          META_LEVELS_COMBO_RATIO,
          META_LEVELS_MAX_COMBO
        );

    $projection = [
      'method' => $method,
      'core' => $core_proj,
      'combo' => $combo_proj,
    ];
    if ($loops_to !== null) $projection['loops_to'] = $loops_to;
    $projections[] = $projection;

    if ($method === 'trend') {
      foreach ($core_proj as $h) $blocked[$h] = true;
    }
    $ref_core = $core_proj;
  }

  return $projections;
}

function meta_levels_build(&$pickban, &$daily, &$hph, &$hvh, $matches_total) {
  $shares = [];
  $emergence = meta_levels_build_emergence($pickban, $daily, $matches_total, $shares);
  if (empty($emergence)) return [ 'layers' => [], 'projections' => [], 'emergence' => [] ];

  $hvh_idx = meta_levels_hvh_index($hvh);
  $pool = meta_levels_eligible_pool($emergence);

  $day_count = 0;
  foreach ($daily as $series) { $day_count = max($day_count, count($series)); }

  $windows = meta_levels_windows($pool, $emergence, $day_count);
  if (empty($windows)) return [ 'layers' => [], 'projections' => [], 'emergence' => $emergence ];

  // each window's slice of the timeline: from its earliest emergence to the next window's.
  $window_start = [];
  foreach ($windows as $w => $window) {
    $days = [];
    foreach ($window as $h) {
      if ($emergence[$h]['emerged'] !== null) $days[] = $emergence[$h]['emerged'];
    }
    $window_start[$w] = !empty($days) ? min($days) : null;
  }

  // pass 1 -- cores only. Combos are deliberately deferred until every core is known
  $layers = [];
  $prev_core = [];
  $last_core_layer = [];
  foreach ($windows as $w => $window) {
    $scores = meta_levels_score_window(
      $window, $prev_core, $pool, $emergence, $hvh_idx, $shares,
      $window_start[$w], $window_start[$w+1] ?? null, $last_core_layer, $w
    );

    $core = meta_levels_dynamic_cutoff($scores, META_LEVELS_CORE_RATIO, META_LEVELS_MAX_CORE, META_LEVELS_MIN_CORE);
    if (empty($core)) continue;

    $layers[] = [ 'core' => $core, 'combo' => [], 'reasoning' => [] ];
    foreach ($core as $hid) $last_core_layer[$hid] = $w;
    $prev_core = $core;
  }

  $layers = meta_levels_normalize($layers);
  if (empty($layers)) return [ 'layers' => [], 'projections' => [], 'emergence' => $emergence ];

  // pass 2 -- combos, loop annotation and reasoning, now that the core sequence is final.
  $all_core = [];
  foreach ($layers as $l) {
    foreach ($l['core'] as $h) $all_core[$h] = true;
  }

  foreach ($layers as $i => &$layer) {
    $used_for_combo = $all_core;
    $layer['combo'] = meta_levels_dynamic_cutoff(
      meta_levels_pick_combos_raw($layer['core'], $hph, $used_for_combo),
      META_LEVELS_COMBO_RATIO,
      META_LEVELS_MAX_COMBO
    );

    $layer['reasoning'] = meta_levels_reasoning(
      $i, $layer['core'], $layer['combo'], $i > 0 ? $layers[$i-1]['core'] : [], $hvh_idx
    );

    for ($j = 0; $j < $i; $j++) {
      if (count($layer['core']) < META_LEVELS_LOOP_MIN_CORE_SIZE || count($layers[$j]['core']) < META_LEVELS_LOOP_MIN_CORE_SIZE) continue;
      if (meta_levels_jaccard($layers[$j]['core'], $layer['core']) >= META_LEVELS_LOOP_SIMILARITY) {
        $layer['loops_to'] = $j;
        break;
      }
    }
  }
  unset($layer);

  $proj_pool = meta_levels_eligible_pool($emergence, META_LEVELS_PROJECTION_ELIGIBILITY);
  $projections = meta_levels_project($layers, $proj_pool, $emergence, $hvh_idx, $hph, $all_core);

  return [
    'layers' => $layers,
    'projections' => $projections,
    'emergence' => $emergence,
  ];
}

function meta_levels_from_report(&$report) {
  if (empty($report['hero_daily_wr']) || empty($report['pickban']) || empty($report['hvh']) || empty($report['hph'])) {
    return null;
  }

  $pickban = $report['pickban'];
  $daily = is_wrapped($report['hero_daily_wr']) ? unwrap_data($report['hero_daily_wr']) : $report['hero_daily_wr'];
  $hph = is_wrapped($report['hph']) ? unwrap_data($report['hph']) : $report['hph'];
  $hvh = is_wrapped($report['hvh']) ? unwrap_data($report['hvh']) : $report['hvh'];
  $matches_total = $report['random']['matches_total'] ?? 0;

  return meta_levels_build($pickban, $daily, $hph, $hvh, $matches_total);
}

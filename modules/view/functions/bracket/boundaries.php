<?php

function tb_peel_outlier_wildcard(array $phases): array {
  $out = [];

  foreach ($phases as $ph) {
    if (($ph['phase_type'] ?? '') !== 'group' || count($ph['series']) < 12) {
      $out[] = $ph;
      continue;
    }

    $deg = [];
    foreach ($ph['series'] as $s) {
      [$a, $b] = $s['teams'];
      $deg[$a][$b] = 1;
      $deg[$b][$a] = 1;
    }

    $counts = array_map('count', $deg);
    sort($counts);
    $median = $counts[intdiv(count($counts), 2)];

    if ($median < 8) {
      $out[] = $ph;
      continue;
    }

    $thr = max(2, intdiv($median, 3));
    $outliers = [];
    foreach ($deg as $t => $opps) {
      if (count($opps) <= $thr) {
        $outliers[$t] = true;
      }
    }

    if (count($outliers) < 2 || count($outliers) > count($deg) / 4) {
      $out[] = $ph;
      continue;
    }

    $wc = [];
    $rest = [];
    foreach ($ph['series'] as $s) {
      if (isset($outliers[$s['teams'][0]]) || isset($outliers[$s['teams'][1] ?? $s['teams'][0]])) {
        $wc[] = $s;
      } else {
        $rest[] = $s;
      }
    }

    if (count($wc) < 2 || count($wc) > count($ph['series']) * 0.12) {
      $out[] = $ph;
      continue;
    }

    $out[] = [
      'rounds' => tb_temporal_rounds($wc),
      'series' => $wc,
      'is_elim' => false,
      'phase_type' => 'wildcard',
    ];

    $wc_keys = array_flip(array_column($wc, 'key'));
    $rounds = [];
    foreach ($ph['rounds'] as $r) {
      $keep = array_values(array_filter($r['series'], fn($s) => !isset($wc_keys[$s['key']])));
      if ($keep) {
        $rounds[] = tb_make_round($keep);
      }
    }

    $ph['rounds'] = $rounds;
    $ph['series'] = $rest;
    $out[] = $ph;
  }

  return $out;
}

function tb_clean_group_block(array $series, array $allowed_teams = []): bool {
  if (!$series) {
    return false;
  }

  $comps = tb_find_groups($series);
  if (count($comps) < 2) {
    return false;
  }

  $sizes = array_map('count', $comps);
  if (min($sizes) < 3 || min($sizes) / max($sizes) < 0.7) {
    return false;
  }

  $t2g = [];
  foreach ($comps as $gi => $cts) {
    foreach ($cts as $t) {
      $t2g[$t] = $gi;
    }
  }

  $cross = 0;
  $tot = 0;
  $seen = [];
  foreach ($series as $s) {
    $p = $s['teams'];
    sort($p);
    $pk = $p[0] . '-' . ($p[1] ?? 0);

    if (isset($seen[$pk])) {
      continue;
    }

    $seen[$pk] = true;
    $tot++;

    if (($t2g[$s['teams'][0]] ?? -1) !== ($t2g[$s['teams'][1] ?? 0] ?? -2)) {
      $cross++;
    }
  }

  if ($tot === 0 || $cross / $tot > 0.05) {
    return false;
  }

  if ($allowed_teams && array_diff(tb_unique_teams($series), $allowed_teams)) {
    return false;
  }

  return true;
}

function tb_find_regroup_block(array $rounds): ?array {
  $n = count($rounds);
  for ($a = 3; $a <= $n - 2; $a++) {
    $prefix_series = tb_rounds_series(array_slice($rounds, 0, $a));

    if (count(tb_find_groups($prefix_series)) !== 1) {
      continue;
    }

    $prefix_teams = tb_unique_teams($prefix_series);
    $b = null;

    for ($e = $a + 1; $e < $n; $e++) {
      $blk = tb_rounds_series(array_slice($rounds, $a, $e - $a + 1));
      if (!tb_clean_group_block($blk, $prefix_teams)) {
        break;
      }
      $b = $e;
    }

    if ($b === null || $b - $a + 1 < 2) {
      continue;
    }

    $blk = tb_rounds_series(array_slice($rounds, $a, $b - $a + 1));
    $comps = tb_find_groups($blk);
    $pairs = [];

    foreach ($blk as $s) {
      $p = $s['teams'];
      sort($p);
      $pairs[$p[0] . '-' . ($p[1] ?? 0)] = true;
    }

    $cov_sum = 0;
    foreach ($comps as $cts) {
      $sz = count($cts);
      $need = $sz * ($sz - 1) / 2;
      if ($need <= 0) {
        continue;
      }
      $have = 0;
      for ($i = 0; $i < $sz; $i++) {
        for ($j = $i + 1; $j < $sz; $j++) {
          $x = min($cts[$i], $cts[$j]);
          $y = max($cts[$i], $cts[$j]);
          if (isset($pairs["$x-$y"])) {
            $have++;
          }
        }
      }
      $cov_sum += $have / $need;
    }

    if ($cov_sum / count($comps) < 0.8) {
      continue;
    }

    $block_teams = tb_unique_teams($blk);
    $bt = array_flip($block_teams);
    $prefix_pairs = [];
    foreach ($prefix_series as $s) {
      $u = $s['teams'][0];
      $v = $s['teams'][1] ?? -1;
      if (isset($bt[$u], $bt[$v])) {
        $p = [$u, $v];
        sort($p);
        $prefix_pairs[$p[0] . '-' . $p[1]] = true;
      }
    }

    $possible = count($block_teams) * (count($block_teams) - 1) / 2;
    if ($possible > 0 && count($prefix_pairs) / $possible > 0.5) {
      continue;
    }

    return [$a, $b];
  }

  return null;
}

function tb_groups_are_dense(array $series): bool {
  foreach (tb_find_groups($series) as $cts) {
    if (count($cts) < 3) {
      continue;
    }

    $set = array_flip($cts);
    $sc = 0;
    foreach ($series as $s) {
      if (isset($set[$s['teams'][0]]) && isset($set[$s['teams'][1] ?? -1])) {
        $sc++;
      }
    }

    if ($sc < count($cts)) {
      return false;
    }
  }

  return true;
}

function tb_group_stage_front(array $rounds): ?int {
  $n = count($rounds);
  if ($n < 2) {
    return null;
  }

  $all_series = tb_rounds_series($rounds);
  $all_teams = tb_unique_teams($all_series);
  $nt = count($all_teams);
  if ($nt >= 3) {
    $pairs = [];
    foreach ($all_series as $s) {
      $p = $s['teams'];
      sort($p);
      $pairs[$p[0] . '-' . ($p[1] ?? 0)] = true;
    }
    if (count($pairs) / ($nt * ($nt - 1) / 2) >= 0.85) {
      return null;
    }
  }

  $best = null;
  for ($e = 0; $e < $n - 1; $e++) {
    $blk = tb_rounds_series(array_slice($rounds, 0, $e + 1));
    if (tb_clean_group_block($blk) && tb_groups_are_dense($blk)) {
      $best = $e;
    } else if ($best !== null) {
      break;
    }
  }

  if ($best === null) {
    return null;
  }

  $blk = tb_rounds_series(array_slice($rounds, 0, $best + 1));
  $after = array_slice($rounds, $best + 1);
  $after_ser = $after ? tb_rounds_series($after) : [];

  if (!tb_after_looks_bracket($after, count(tb_unique_teams($blk)))) {
    return null;
  }

  return $best;
}

function tb_playoff_tail(array $rounds): ?int {
  $n = count($rounds);
  if ($n < 5) {
    return null;
  }

  $k = null;
  for ($i = $n - 1; $i >= 1; $i--) {
    foreach ($rounds[$i]['series'] as $s) {
      if (tb_is_tie($s)) {
        $k = $i + 1;
        break 2;
      }
    }
  }

  if ($k === null) {
    return null;
  }

  while ($k < $n - 3 && count($rounds[$k]['series']) < 2) {
    $k++;
  }
  if ($k < 2 || $k > $n - 3) {
    return null;
  }

  $tail = array_slice($rounds, $k);
  $tail_ser = tb_rounds_series($tail);
  if (count($tail_ser) < 4) {
    return null;
  }

  foreach ($tail_ser as $s) {
    if (tb_is_tie($s)) {
      return null;
    }
  }

  if (count(array_filter($tail_ser, fn($s) => ($s['bo'] ?? 0) >= 3)) < count($tail_ser) * 0.8) {
    return null;
  }
  if (!tb_is_elim_phase($tail)) {
    return null;
  }

  $pre = tb_rounds_series(array_slice($rounds, 0, $k));
  $pre_ties = count(array_filter($pre, fn($s) => tb_is_tie($s)));

  if ($pre_ties < 3 || $pre_ties < count($pre) * 0.2) {
    return null;
  }

  return $k;
}

function tb_wildcard_front(array $rounds): ?int {
  $n = count($rounds);
  if ($n < 4) {
    return null;
  }

  for ($k = 2; $k <= $n - 2; $k++) {
    $before = tb_rounds_series(array_slice($rounds, 0, $k));
    $after  = tb_rounds_series(array_slice($rounds, $k));
    $tb = tb_unique_teams($before);
    $ta = tb_unique_teams($after);

    if (count($tb) < 4) {
      continue;
    }

    if (count($ta) <= count($tb)) {
      continue;
    }

    if (count(array_intersect($tb, $ta)) > 2) {
      continue;
    }

    if (count($tb) - count(array_intersect($tb, $ta)) < 3) {
      continue;
    }

    if (count(tb_find_groups($before)) > 1) {
      continue;
    }

    if (tb_is_elim_phase(array_slice($rounds, 0, $k))) {
      continue;
    }

    return $k;
  }

  return null;
}

function tb_topcut_playoff_tail(array $rounds): ?int {
  $n = count($rounds);
  if ($n < 4) {
    return null;
  }

  $all_ser = tb_rounds_series($rounds);
  $field  = count(tb_unique_teams($all_ser));
  if ($field < 8) {
    return null;
  }

  for ($k = 2; $k <= $n - 2; $k++) {
    $tail  = array_slice($rounds, $k);
    $tser  = tb_rounds_series($tail);

    if (count($tser) < 6) {
      continue;
    }

    if (count(tb_unique_teams($tser)) > $field * 0.6) {
      continue;
    }

    $hser  = tb_rounds_series(array_slice($rounds, 0, $k));
    if (count(tb_unique_teams($hser)) < $field * 0.95) {
      continue;
    }

    if (tb_progression_is_group($tser)) {
      continue;
    }

    $br = tb_build_bracket($tail, []);
    if (!empty($br['unplaced'])) {
      continue;
    }

    if (!tb_is_valid_playoff($br)) {
      continue;
    }

    return $k;
  }

  return null;
}

function tb_split_group_rounds(array $rounds): array {
  if (!$rounds) {
    return [];
  }

  if (count($rounds) === 1) {
    return [
      [
        'rounds' => $rounds,
        'series' => $rounds[0]['series'],
        'is_elim' => false,
        'phase_type' => 'group',
      ],
    ];
  }

  // Split where a fresh cohort joins after a qualifier\
  $seen = [];
  foreach ($rounds as $i => $r) {
    $round_teams = []; $fresh = 0;
    foreach ($r['series'] as $s) {
      foreach ($s['teams'] as $t) {
        $round_teams[$t] = true;
        if (!isset($seen[$t])) {
          $fresh++;
        }
      }
    }

    foreach (array_keys($round_teams) as $t) {
      $seen[$t] = true;
    }

    if ($i >= 2 && $i <= count($rounds) - 2 && $fresh >= 4 && $fresh >= count($round_teams) * 0.5) {
      $first  = tb_unique_teams(tb_rounds_series(array_slice($rounds, 0, $i)));
      $second = tb_unique_teams(tb_rounds_series(array_slice($rounds, $i)));

      if ($first
        && $second && count(array_intersect($first, $second)) >= 4
        && count(array_diff($first, $second)) >= count($first) * 0.15
      ) {
        $first_phases = tb_split_group_rounds(array_slice($rounds, 0, $i));
        $second_phases = tb_split_group_rounds(array_slice($rounds, $i));
        if ($second_phases) {
          $second_phases[0]['no_merge'] = true;
        }

        return array_merge($first_phases, $second_phases);
      }
    }
  }

  $all_series = tb_rounds_series($rounds);
  $final_components = count(tb_find_groups($all_series));

  if ($final_components > 1) {
    return [
      [
        'rounds' => $rounds,
        'series' => $all_series,
        'is_elim' => false,
        'phase_type' => 'group',
      ],
    ];
  }

  $block = tb_find_regroup_block($rounds);
  if ($block !== null) {
    [$a, $b] = $block;
    $first  = array_slice($rounds, 0, $a);
    $second = array_slice($rounds, $a, $b - $a + 1);
    $tail   = array_slice($rounds, $b + 1);
    $mk = fn(array $rs, bool $nm = false) => array_filter([
      'rounds' => $rs,
      'series' => tb_rounds_series($rs),
      'is_elim' => false,
      'phase_type' => 'group',
      'no_merge' => $nm ?: null,
    ], fn($v) => $v !== null);
    $out = [$mk($first), $mk($second, true)];

    if ($tail) {
      $out[] = $mk($tail);
    }

    return $out;
  }

  $stats = array_map('tb_round_stats', $rounds);
  $n = count($rounds);
  $max_teams = max(array_column($stats, 'teams') ?: [1]);
  $bounds = [];
  for ($i = 1; $i < $n; $i++) {
    $p = $stats[$i - 1];
    $c = $stats[$i];
    $drop = $p['teams'] > 0 ? 1 - $c['teams'] / $p['teams'] : 0;
    $team_drop = $drop >= 0.35 && $c['series'] >= 2
        && $c['series'] <= $p['series'] * 0.75
        && $c['teams'] <= $max_teams * 0.65;
    
    if ($team_drop) {
      $bounds[] = $i;
    }
  }

  if (!$bounds) {
    return [
      [
        'rounds' => $rounds,
        'series' => $all_series,
        'is_elim' => false,
        'phase_type' => 'group',
      ],
    ];
  }

  $phases = [];
  $prev   = 0;
  foreach (array_unique($bounds) as $b) {
    if ($b > $prev) {
      $rds = array_slice($rounds, $prev, $b - $prev);
      $phases[] = [
        'rounds'     => $rds,
        'series'     => tb_rounds_series($rds),
        'is_elim'    => false,
        'phase_type' => 'group',
      ];
    }

    $prev = $b;
  }

  $rds = array_slice($rounds, $prev);
  if ($rds) {
    $phases[] = [
      'rounds'     => $rds,
      'series'     => tb_rounds_series($rds),
      'is_elim'    => false,
      'phase_type' => 'group',
    ];
  }

  return $phases;
}

function tb_rr_playoff_split(array $series): ?array {
  if (count($series) < 6) {
    return null;
  }

  usort($series, fn($a, $b) => $a['start'] <=> $b['start']);
  $teams = tb_unique_teams($series);
  $n = count($teams);

  if ($n < 4 || $n > 8) {
    return null;
  }

  $expected = $n * ($n - 1) / 2;

  $seen = [];
  $c = null;
  foreach ($series as $i => $s) {
    $p = $s['teams']; sort($p);
    $seen[$p[0] . '-' . $p[1]] = true;
    if (count($seen) >= $expected) {
      $c = $i + 1;
      break;
    }
  }

  if ($c === null) {
    return null;
  }

  $group = array_slice($series, 0, $c);
  $tail = array_slice($series, $c);
  if (count($tail) < 2) {
    return null;
  }

  $tail_teams = tb_unique_teams($tail);
  if (count($tail_teams) < 3) {
    return null;
  }

  $loss = [];
  foreach ($tail as $s) {
    if (($s['winner'] ?? null) === null) {
      return null;
    }

    $l = tb_loser($s);
    if ($l === null) {
      return null;
    }

    $loss[$l] = ($loss[$l] ?? 0) + 1;
  }
  if (count($tail) !== count($tail_teams) - 1) {
    return null;
  }

  $undefeated = array_filter($tail_teams, fn($t) => ($loss[$t] ?? 0) === 0);
  if (count($undefeated) !== 1) {
    return null;
  }

  foreach ($tail_teams as $t) {
    if (($loss[$t] ?? 0) > 1) {
      return null;
    }
  }

  return [
    'group' => $group,
    'playoff' => $tail,
  ];
}

<?php

function tb_temporal_rounds(array $series): array {
  if (!$series) {
    return [];
  }

  $rounds = [];
  $current = [];
  $seen_teams = [];
  $current_start = null;

  foreach ($series as $s) {
    $gap = $current_start === null ? 0 : $s['start'] - $current_start;
    $overlap = (bool)array_intersect($seen_teams, $s['teams']);

    $day_change = $current_start !== null
      && date('Y-m-d', $s['start']) !== date('Y-m-d', (int)$current_start)
      && $gap > 14400;
    $new_round = $current && ($gap > 100800 || ($gap > 28800 && $overlap) || $day_change);

    if ($new_round) {
      $rounds[] = tb_make_round($current);
      $current = [];
      $seen_teams = [];
      $current_start = null;
    }

    $current[] = $s;
    $seen_teams = array_values(array_unique(array_merge($seen_teams, $s['teams'])));
    $current_start ??= $s['start'];
  }

  if ($current) {
    $rounds[] = tb_make_round($current);
  }

  return $rounds;
}

function tb_make_round(array $series): array {
  return [
    'series' => $series,
    'start'  => (int)min(array_column($series, 'start')),
    'end'    => (int)max(array_column($series, 'end')),
  ];
}

function tb_round_stats(array $round): array {
  $series = $round['series'];
  $all    = array_merge(...array_map(fn($s) => $s['teams'], $series));
  $teams  = array_unique($all);
  $apps   = array_count_values($all);
  $bo3    = array_filter($series, fn($s) => $s['bo'] >= 3);
  $t      = count($teams);

  return [
    'teams'  => $t,
    'series' => count($series),
    'bo3r'   => count($series) ? count($bo3) / count($series) : 0,
    'spt'    => $t > 0 ? count($series) * 2 / $t : 0,
    'maxapp' => $apps ? max($apps) : 0,
  ];
}

function tb_after_looks_bracket(array $after_rounds, int $before_teams = 0): bool {
  if (!$after_rounds) {
    return false;
  }

  $after_series = tb_rounds_series($after_rounds);

  if (tb_elim_count($after_series) >= 2) {
    return true;
  }

  $after_teams = count(tb_unique_teams($after_series));
  if ($before_teams > 0 && $after_teams < $before_teams * 0.85) {
    return true;
  }

  $teams_per_round = array_map(fn($r) => tb_round_stats($r)['teams'], $after_rounds);

  return $teams_per_round && end($teams_per_round) < max($teams_per_round);
}

function tb_looks_like_bracket(array $rounds): bool {
  if (!$rounds) {
    return false;
  }

  if (count($rounds) === 1) {
    return true;
  }

  $all_s = tb_rounds_series($rounds);
  if (count(tb_find_groups($all_s)) !== 1) {
    return false;
  }

  $counts = array_map(fn($r) => count($r['series']), $rounds);
  return $counts[0] > $counts[count($counts) - 1];
}

function tb_progression_is_group(array $series): bool {
  usort($series, fn($a, $b) => $a['start'] <=> $b['start']);

  $first_loss_time = [];
  foreach ($series as $s) {
    $loser = tb_loser($s);
    if ($loser !== null && !isset($first_loss_time[$loser])) {
      $first_loss_time[$loser] = $s['start'];
    }
  }

  if (count($first_loss_time) < 3) {
    return false;
  }

  $many_after_loss = 0;
  foreach ($first_loss_time as $team => $loss_time) {
    $after_count = count(array_filter($series, fn($s) =>
      $s['start'] > $loss_time && in_array($team, $s['teams'])
    ));
    if ($after_count >= 3) {
      $many_after_loss++;
    }
  }

  return $many_after_loss / count($first_loss_time) >= 0.55;
}

function tb_trailing_playoff(array $series, array $teams): ?array {
  $rounds = tb_temporal_rounds($series);
  $n = count($rounds);
  if ($n < 4) {
    return null;
  }

  $boundary = null;
  for ($i = $n - 1; $i >= 0; $i--) {
    if (tb_count_tied($rounds[$i]['series']) > 0) {
      $boundary = $i + 1;
      break;
    }
  }
  
  if ($boundary === null || $boundary < 2 || $boundary > $n - 1) {
    return null;
  }

  $tail = array_slice($rounds, $boundary);
  $tail_ser = tb_rounds_series($tail);
  if (count($tail_ser) < 3) {
    return null;
  }

  $bo3 = count(array_filter($tail_ser, fn($s) => ($s['bo'] ?? 0) >= 3));
  if ($bo3 < count($tail_ser) * 0.6) {
    return null;
  }
  if (count(tb_find_groups($tail_ser)) !== 1) {
    return null;
  }

  if (!tb_is_elim_phase($tail)) {
    return null;
  }

  $br = tb_build_bracket($tail, $teams);
  if (!tb_is_valid_playoff($br)) {
    return null;
  }
  if (count($br['unplaced'] ?? []) > max(1, (int)floor(count($tail_ser) * 0.2))) {
    return null;
  }
  return $tail_ser;
}

function tb_count_genuine_ties(array $series): int {
  $cnt = 0;

  foreach ($series as $s) {
    if (!tb_is_tie($s)) continue;

    $adv = 0;
    foreach ($s['teams'] as $t) {
      foreach ($series as $o) {
        if ($o['start'] > $s['end'] && in_array($t, $o['teams'])) { $adv++; break; }
      }
    }

    if ($adv !== 1) {
      $cnt++;
    }
  }

  return $cnt;
}

function tb_is_elim_phase(array $rounds): bool {
  if (!$rounds) {
    return false;
  }

  $n = count($rounds);

  $all_series = tb_rounds_series($rounds);
  if (tb_progression_is_group($all_series)) {
    return false;
  }

  $stats = array_map('tb_round_stats', $rounds);
  $bo3cnt = count(array_filter($stats, fn($s) => $s['bo3r'] >= 0.5));
  $one = count(array_filter($stats, fn($s) => $s['maxapp'] <= 1));

  $shrink = 0;
  for ($i = 1; $i < $n; $i++) {
    if ($stats[$i]['teams'] < $stats[$i - 1]['teams']) {
      $shrink++;
    }
  }

  $first_teams = $stats[0]['teams'];
  $last_teams  = $stats[$n - 1]['teams'];

  if ($one === $n && $bo3cnt >= intdiv($n, 2) + 1 && ($n === 1 || $last_teams < $first_teams)) {
    return true;
  }

  if ($n > 1 && $bo3cnt >= intdiv($n, 2) && $last_teams < $first_teams) {
    return true;
  }

  return $n >= 2 && $shrink >= max(1, intdiv($n, 2));
}

function tb_is_group_stage(array $rounds): bool {
  if (!$rounds) {
    return false;
  }

  $r0_series = $rounds[0]['series'];
  if (!$r0_series) {
    return false;
  }

  $comps = tb_find_groups($r0_series);
  $n_comps = count($comps);
  if ($n_comps <= 1) {
    return false;
  }

  $n_teams = count(array_unique(array_merge(...array_map(fn($s) => $s['teams'], $r0_series))));
  return ($n_teams / $n_comps) > 2.0;
}

function tb_core_groups(array $series): array {
  $work = array_values($series);

  do {
    $changed   = false;
    $pair_count = [];
    foreach ($work as $s) {
      $p = $s['teams']; sort($p);
      $pair_count[implode('-', $p)] = ($pair_count[implode('-', $p)] ?? 0) + 1;
    }

    $base = count(tb_find_groups($work));
    foreach ($work as $i => $s) {
      $p = $s['teams']; sort($p);
      if (($pair_count[implode('-', $p)] ?? 0) !== 1) {
        continue;
      }

      $without = $work;
      unset($without[$i]);
      $without = array_values($without);

      if ($without && count(tb_find_groups($without)) > $base) {
        $work    = $without;
        $changed = true;
        break;
      }
    }
  } while ($changed && $work);

  return tb_find_groups($work ?: $series);
}

function tb_find_groups(array $series): array {
  $p = [];
  foreach ($series as $s) {
    foreach ($s['teams'] as $t) {
      $p[$t] ??= $t;
    }
    if (count($s['teams']) >= 2) {
      tb_uf_union($p, $s['teams'][0], $s['teams'][1]);
    }
  }

  $clusters = [];
  foreach (array_keys($p) as $t) {
    $clusters[tb_uf_find($p, $t)][] = $t;
  }

  return array_values($clusters);
}

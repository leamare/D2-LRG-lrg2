<?php

function tb_build_group(string $name, array $series, array $all_rounds): array {
  if (!$series) {
    return [
      'name' => $name,
      'format' => 'unknown',
      'standings' => [],
      'round_results' => [],
      'grid' => null,
      'teams' => [],
    ];
  }

  $group_teams = tb_unique_teams($series);

  $g_rounds = array_values(array_filter(array_map(function ($r) use ($group_teams) {
    $rs = array_values(array_filter($r['series'], fn($s) => empty(array_diff($s['teams'], $group_teams))));
    return $rs ? ['series' => $rs, 'start' => $r['start']] : null;
  }, $all_rounds)));

  $format = tb_infer_group_format($series, $group_teams, $g_rounds);
  $standings = tb_group_standings($series, $group_teams);

  $round_results = [];
  $team_seq = [];
  $chrono = $series;
  usort($chrono, fn($a, $b) => $a['start'] <=> $b['start']);
  foreach ($chrono as $s) {
    [$a, $b] = $s['teams'];
    $ri = max($team_seq[$a] ?? 0, $team_seq[$b] ?? 0);
    $sa = $s['score'][$a] ?? 0;
    $sb = $s['score'][$b] ?? 0;
    $mids = array_values($s['mids'] ?? []);

    $round_results[$ri][$a] = [
      'opp' => $b,
      'score' => "$sa-$sb",
      'win' => $sa > $sb,
      'draw' => $sa === $sb,
      'mids' => $mids,
    ];

    $round_results[$ri][$b] = [
      'opp' => $a,
      'score' => "$sb-$sa",
      'win' => $sb > $sa,
      'draw' => $sa === $sb,
      'mids' => $mids,
    ];

    $team_seq[$a] = $ri + 1;
    $team_seq[$b] = $ri + 1;
  }
  ksort($round_results);

  $grid = in_array($format, ['round_robin', 'mixed']) ? tb_rr_grid($series, $group_teams) : null;

  return [
    'name'          => $name,
    'format'        => $format,
    'teams'         => $group_teams,
    'standings'     => $standings,
    'round_results' => $round_results,
    'grid'          => $grid,
  ];
}

function tb_infer_group_format(array $series, array $team_ids, array $rounds): string {
  $n = count($team_ids);
  if ($n < 2) {
    return 'unknown';
  }

  $pairs  = [];
  $appear = [];
  foreach ($series as $s) {
    $p = $s['teams']; sort($p);
    $pairs[implode('-', $p)] = true;
    foreach ($s['teams'] as $t) {
      $appear[$t] = ($appear[$t] ?? 0) + 1;
    }
  }

  $expected = $n * ($n - 1) / 2;
  if ($expected > 0 && count($pairs) / $expected >= 0.75) {
    return 'round_robin';
  }

  $swiss_like = array_filter($rounds, fn($r) => tb_round_stats(tb_make_round($r['series']))['maxapp'] <= 1);
  if (count($rounds) >= 2 && count($swiss_like) >= count($rounds) * 0.6) {
    return count($rounds) >= 4 ? 'swiss' : 'short_swiss';
  }

  // Swiss whose temporal rounds aren't clean (e.g. ti14)
  $avg = $appear ? array_sum($appear) / count($appear) : 0;
  if ($n >= 8 && count($pairs) >= count($series) * 0.9 && $avg >= 3 && $avg <= $n * 0.6) {
    return $avg >= 4 ? 'swiss' : 'short_swiss';
  }

  return 'mixed';
}

function tb_group_standings(array $series, array $team_ids): array {
  $rows = [];
  foreach ($team_ids as $t) {
    $rows[$t] = [
      'team' => $t,
      'w' => 0,
      'd' => 0,
      'l' => 0,
      'mw' => 0,
      'ml' => 0,
    ];
  }

  foreach ($series as $s) {
    [$a, $b] = $s['teams'];
    if (!isset($rows[$a], $rows[$b])) {
      continue;
    }

    $sa = (int)($s['score'][$a] ?? 0);
    $sb = (int)($s['score'][$b] ?? 0);

    $rows[$a]['mw'] += $sa; $rows[$a]['ml'] += $sb;
    $rows[$b]['mw'] += $sb; $rows[$b]['ml'] += $sa;

    if ($sa === $sb) {
      $rows[$a]['d']++;
      $rows[$b]['d']++;
    } else if ($sa > $sb) {
      $rows[$a]['w']++;
      $rows[$b]['l']++;
    } else {
      $rows[$b]['w']++;
      $rows[$a]['l']++;
    }
  }

  usort($rows, fn($x, $y) =>
    [2 * $y['w'] + $y['d'], -$y['l'], $y['mw'] - $y['ml'], $y['mw']] <=>
    [2 * $x['w'] + $x['d'], -$x['l'], $x['mw'] - $x['ml'], $x['mw']]
  );
  return array_values($rows);
}

function tb_apply_tiebreakers(array $standings, array $tiebreakers, ?callable $tier_of = null): array {
  if (!$tiebreakers) {
    return $standings;
  }

  $pts = [];
  foreach ($standings as $r) {
    $pts[$r['team']] = 2 * $r['w'] + $r['d'];
  }

  $tie_wins = [];
  $adj = [];
  foreach ($tiebreakers as $s) {
    if (count($s['teams']) < 2) {
      continue;
    }

    [$a, $b] = $s['teams'];
    if (($pts[$a] ?? null) !== ($pts[$b] ?? null)) {
      continue;
    }

    $adj[$a][$b] = true;
    $adj[$b][$a] = true;
    if (($s['winner'] ?? null) !== null) {
      $tie_wins[$s['winner']] = ($tie_wins[$s['winner']] ?? 0) + 1;
    }
  }

  $seen = []; $comps = [];
  foreach (array_keys($adj) as $t) {
    if (isset($seen[$t])) {
      continue;
    }

    $stack = [$t];
    $comp = [];
    while ($stack) {
      $x = array_pop($stack);
      if (isset($seen[$x])) {
        continue;
      }
      $seen[$x] = true; $comp[] = $x;
      foreach (array_keys($adj[$x] ?? []) as $y) {
        if (!isset($seen[$y])) {
          $stack[] = $y;
        }
      }
    }
    $comps[] = $comp;
  }

  $pos_of = [];
  foreach ($standings as $i => $r) {
    $pos_of[$r['team']] = $i;
  }

  foreach ($comps as $comp) {
    $positions = [];
    foreach ($comp as $t) {
      if (isset($pos_of[$t])) {
        $positions[] = $pos_of[$t];
      }
    }

    if (count($positions) < 2) {
      continue;
    }
    sort($positions);
    $teams_here = array_map(fn($p) => $standings[$p], $positions);
    usort($teams_here, function ($x, $y) use ($tie_wins, $tier_of) {
      if ($tier_of) {
        $tc = $tier_of($x['team']) <=> $tier_of($y['team']);

        if ($tc !== 0) return $tc;
      }
      return ($tie_wins[$y['team']] ?? 0) <=> ($tie_wins[$x['team']] ?? 0);
    });

    foreach ($positions as $k => $p) {
      $standings[$p] = $teams_here[$k];
    }
  }

  return $standings;
}

function tb_rr_grid(array $series, array $team_ids): array {
  $grid = [];

  foreach ($series as $s) {
    [$a, $b] = $s['teams'];

    $sa = $s['score'][$a] ?? 0;
    $sb = $s['score'][$b] ?? 0;
    $mids = array_values($s['mids'] ?? []);

    $grid[$a][$b] = [
      'sc' => "$sa-$sb",
      'win' => $sa > $sb,
      'draw' => $sa === $sb,
      'mids' => $mids,
    ];
    
    $grid[$b][$a] = [
      'sc' => "$sb-$sa",
      'win' => $sb > $sa,
      'draw' => $sa === $sb,
      'mids' => $mids,
    ];
  }

  return $grid;
}

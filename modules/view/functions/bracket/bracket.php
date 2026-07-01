<?php

function tb_gf_reachable(array $br_series): array {
  if (count($br_series) < 2) {
    return array_column($br_series, 'key');
  }

  usort($br_series, fn($a, $b) => $a['start'] <=> $b['start']);

  $gf = end($br_series);
  $reach = [$gf['key'] => true];
  $queue = [$gf];

  while ($queue) {
    $s = array_pop($queue);
    foreach ($s['teams'] as $t) {
      $prev = null;
      foreach ($br_series as $c) {
        if ($c['start'] >= $s['start'] || !in_array($t, $c['teams'])) {
          continue;
        }
        if ($prev === null || $c['start'] > $prev['start']) {
          $prev = $c;
        }
      }
      if ($prev && empty($reach[$prev['key']])) {
        $reach[$prev['key']] = true;
        $queue[] = $prev;
      }
    }
  }

  return array_keys($reach);
}

function tb_lb_seeds(array $all): array {
  $by_start = array_values($all);
  usort($by_start, fn($a, $b) => $a['start'] <=> $b['start']);

  // (1) entrants
  $lost = [];
  $seen = [];
  $seeds = [];
  foreach ($by_start as $s) {
    if (count($s['teams']) >= 2) {
      [$a, $b] = $s['teams'];
      if (!isset($seen[$a]) && isset($lost[$b])) {
        $seeds[$a] = true;
      }
      if (!isset($seen[$b]) && isset($lost[$a])) {
        $seeds[$b] = true;
      }
    }

    foreach ($s['teams'] as $t) {
      $seen[$t] = true;
    }

    $l = tb_loser($s);
    if ($l) {
      $lost[$l] = true;
    }
  }

  // (2) parallel seed round
  if (count($all) >= 6) {
    $first = [];
    foreach ($by_start as $s) {
      foreach ($s['teams'] as $t) {
        $first[$t] ??= $s['key'];
      }
    }

    $opening = [];
    foreach ($all as $s) {
      if (count($s['teams']) < 2) {
        continue;
      }

      [$a, $b] = $s['teams'];
      if (($first[$a] ?? null) === $s['key'] && ($first[$b] ?? null) === $s['key']) {
        $opening[$s['key']] = $s;
      }
    }

    if (count($opening) >= 6) {
      $open_losers = [];
      foreach ($opening as $s) {
        $l = tb_loser($s);
        if ($l !== null) {
          $open_losers[$l] = true;
        }
      }

      $seed_series = [];
      foreach ($opening as $s) {
        $w = $s['winner'] ?? null;
        if (!$w) {
          continue;
        }

        $nxt = tb_next_series_for_team($w, $all, $s['end']);
        if ($nxt === null) {
          continue;
        }

        $opp = null;
        foreach ($all[$nxt]['teams'] as $t) {
          if ($t !== $w) {
            $opp = $t;
          }
        }

        if ($opp !== null && isset($open_losers[$opp])) {
          $seed_series[] = $s;
        }
      }
      
      $ub_opening = count($opening) - count($seed_series);
      if (count($seed_series) >= 2 && abs($ub_opening - count($seed_series)) <= 1) {
        foreach ($seed_series as $s) {
          foreach ($s['teams'] as $t) {
            $seeds[$t] = true;
          }
        }
      }
    }
  }

  return array_keys($seeds);
}

function tb_build_bracket(array $rounds, array $teams, array $lb_seeds = [], bool $allow_peel = true): array {
  $all = [];
  foreach ($rounds as $r) {
    foreach ($r['series'] as $s) {
      $all[$s['key']] = $s;
    }
  }

  if (!$all) {
    return [
      'type' => 'single_elimination',
      'ub_rounds' => [],
      'lb_rounds' => [],
      'grand_final' => null,
    ];
  }

  foreach ($all as $k => $s) {
    if ($s['winner'] !== null || count($s['teams']) < 2) {
      continue;
    }

    [$a, $b] = $s['teams'];
    $na = tb_next_series_for_team($a, $all, $s['end']);
    $nb = tb_next_series_for_team($b, $all, $s['end']);
    $adv = array_values(array_filter([$na !== null ? $a : null, $nb !== null ? $b : null]));

    if (count($adv) === 1) {
      $all[$k]['winner'] = $adv[0];
    } else if (count($adv) === 2) {
      // Incomplete upper-bracket final
      $a_rematch = in_array($b, $all[$na]['teams'] ?? [], true);
      $b_rematch = in_array($a, $all[$nb]['teams'] ?? [], true);
      $win = ($a_rematch && !$b_rematch) ? $a : (($b_rematch && !$a_rematch) ? $b : null);
      if ($win !== null) {
        $all[$k]['winner'] = $win;
        $all[$k]['flags']  = array_values(array_unique(array_merge($all[$k]['flags'] ?? [], ['outcome-fixed'])));
      }
    }
  }

  $unplaced = [];

  foreach ($all as $k => $s) {
    if (($s['winner'] ?? null) === null) {
      $unplaced[] = $s;
      unset($all[$k]);
    }
  }

  if ($allow_peel && count($all) > 2) {
    $team_series_count = [];
    foreach ($all as $s) {
      foreach ($s['teams'] as $t) {
        $team_series_count[$t] = ($team_series_count[$t] ?? 0) + 1;
      }
    }

    $candidates = [];
    $keep = 0;
    foreach ($all as $k => $s) {
      $connected = false;
      foreach ($s['teams'] as $t) {
        if (($team_series_count[$t] ?? 0) > 1) {
          $connected = true;
          break;
        }
      }

      if (!$connected) {
        $candidates[$k] = $s;
      } else {
        $keep++;
      }
    }
    if ($candidates && $keep >= 2) {
      foreach ($candidates as $k => $s) {
        $unplaced[] = $s;
        unset($all[$k]);
      }
    }
  }

  if (!$all) {
    return [
      'type' => 'single_elimination',
      'ub_rounds' => [],
      'lb_rounds' => [],
      'grand_final' => null,
      'unplaced' => $unplaced,
    ];
  }

  // Teams that seed straight into the lower bracket have no prior bracket loss
  $lb_seeds  = array_values(array_unique(array_merge($lb_seeds, tb_lb_seeds($all))));
  $seed_loss = array_fill_keys($lb_seeds, 1);

  $fwd = [];
  foreach ($all as $s) {
    $fwd[$s['key']] = [];
    foreach ($s['teams'] as $t) {
      $nxt = tb_next_series_for_team($t, $all, $s['end']);
      if ($nxt !== null && !in_array($nxt, $fwd[$s['key']])) {
        $fwd[$s['key']][] = $nxt;
      }
    }
  }

  $terminals = array_keys(array_filter($fwd, fn($v) => empty($v)));

  $root_id = null; $root_time = 0;
  foreach ($terminals as $tid) {
    if ($all[$tid]['start'] > $root_time) {
      $root_id = $tid;
      $root_time = $all[$tid]['start'];
    }
  }

  if ($root_id === null) {
    $root_id = array_key_last($all);
  }

  $depth = [$root_id => 0];
  $queue = [$root_id];
  while ($queue) {
    $curr = array_shift($queue);
    foreach ($all as $prev) {
      if (isset($depth[$prev['key']])) {
        continue;
      }
      
      if (in_array($curr, $fwd[$prev['key']])) {
        $depth[$prev['key']] = $depth[$curr] + 1;
        $queue[] = $prev['key'];
      }
    }
  }

  $all_team_ids = [];
  foreach ($all as $s) {
    foreach ($s['teams'] as $t) {
      $all_team_ids[$t] = true;
    }
  }

  $loss_check = $seed_loss;
  $is_double  = false;
  $chrono_all = array_values($all);
  usort($chrono_all, fn($a, $b) => $a['start'] <=> $b['start']);
  foreach ($chrono_all as $s) {
    foreach ($s['teams'] as $t) {
      if (($loss_check[$t] ?? 0) >= 1) {
        $is_double = true;
        break 2;
      }
    }

    $loser = tb_loser($s);
    if ($loser) {
      $loss_check[$loser] = ($loss_check[$loser] ?? 0) + 1;
    }
  }

  $all = tb_repair_outcomes($all, $is_double);
  $all = tb_repair_orphan_winners($all);
  $chrono_all = array_values($all);
  usort($chrono_all, fn($a, $b) => $a['start'] <=> $b['start']);

  $loss_at_start = [];
  $loss_temp = $seed_loss;
  foreach ($chrono_all as $s) {
    $la = $loss_temp[$s['teams'][0]] ?? 0;
    $lb = $loss_temp[$s['teams'][1] ?? $s['teams'][0]] ?? 0;
    $loss_at_start[$s['key']] = min($la, $lb) > 0 ? 1 : 0;

    $loser = tb_loser($s);
    if ($loser) {
      $loss_temp[$loser] = ($loss_temp[$loser] ?? 0) + 1;
    }
  }
  usort($chrono_all, function($a, $b) use ($loss_at_start) {
    if ($a['start'] !== $b['start']) {
      return $a['start'] <=> $b['start'];
    }
    return ($loss_at_start[$a['key']] ?? 0) <=> ($loss_at_start[$b['key']] ?? 0);
  });

  $loss_track  = $seed_loss;
  $ub_rows = [];

  $lb_rows = [];
  $gf_series = [];

  $current_ub = [];
  $current_lb = [];
  $current_gf = [];

  $round_teams = [];
  $round_end   = 0;

  $flush_round = function() use (&$ub_rows, &$lb_rows, &$gf_series, &$current_ub, &$current_lb, &$current_gf, &$round_teams, &$round_end, &$loss_track) {
    if ($current_ub) $ub_rows[] = $current_ub;
    if ($current_lb) $lb_rows[] = $current_lb;
    if ($current_gf) $gf_series = array_merge($gf_series, $current_gf);

    foreach (array_merge($current_ub, $current_lb, $current_gf) as $s) {
      $loser = tb_loser($s);
      if ($loser) {
        $loss_track[$loser] = ($loss_track[$loser] ?? 0) + 1;
      }
    }

    $current_ub = [];
    $current_lb = [];
    $current_gf = [];

    $round_teams = [];
    $round_end = 0;
  };

  foreach ($chrono_all as $s) {
    if (count($s['teams']) < 2) {
      continue;
    }

    [$ta, $tb_t] = $s['teams'];

    $gap = $round_end ? ($s['start'] - $round_end) : 0;
    $overlap = isset($round_teams[$ta]) || isset($round_teams[$tb_t]);

    if (($current_ub || $current_lb || $current_gf) && ($overlap || $gap > 100800)) {
      $flush_round();
    }

    $la = $loss_track[$ta]  ?? 0;
    $lb = $loss_track[$tb_t] ?? 0;

    if ($is_double && isset($depth[$s['key']]) && $depth[$s['key']] === 0) {
      $current_gf[] = $s;
    } elseif ($is_double && ($la > 0 || $lb > 0)) {
      $current_lb[] = $s;
    } else {
      $current_ub[] = $s;
    }

    $round_teams[$ta] = true;
    $round_teams[$tb_t] = true;
    $round_end = max($round_end, $s['end']);
  }

  $flush_round();

  if ($is_double && $ub_rows && $lb_rows) {
    $ub_before = $ub_rows;
    $lb_before = $lb_rows;
    $in_lb = [];
    foreach ($lb_rows as $row) {
      foreach ($row as $s) {
        $in_lb[$s['key']] = true;
      }
    }
    
    do {
      $moved = false;
      foreach ($ub_rows as $ri => $row) {
        foreach ($row as $idx => $s) {
          $w = $s['winner'] ?? null;
          if (!$w) {
            continue;
          }
          
          $nxt = tb_next_series_for_team($w, $all, $s['end']);
          if ($nxt !== null && isset($in_lb[$nxt])) {
            unset($ub_rows[$ri][$idx]);
            $lb_rows[] = [$s];

            $in_lb[$s['key']] = true;
            $moved = true;
          }
        }
        $ub_rows[$ri] = array_values($ub_rows[$ri]);
      }
      $ub_rows = array_values(array_filter($ub_rows));
    } while ($moved);

    if (!array_filter($ub_rows)) {
      $ub_rows = $ub_before;
      $lb_rows = $lb_before;
    }
  }

  if ($is_double && $gf_series) {
    $ub_final_winner = null;
    if ($ub_rows) {
      $last_ub = end($ub_rows);
      if (count($last_ub) === 1) {
        $ub_final_winner = $last_ub[0]['winner'] ?? null;
      }
    }

    if ($ub_final_winner !== null) {
      $filtered_gf = [];
      foreach ($gf_series as $s) {
        if (in_array($ub_final_winner, $s['teams'])) {
          $filtered_gf[] = $s;
        } else {
          $lb_rows[] = [$s];
        }
      }
      $gf_series = $filtered_gf;
    } else {
      foreach ($gf_series as $s) $lb_rows[] = [$s];
      $gf_series = [];
    }
  }

  $rebucket = function(array $rows, array $entry_floor = []): array {
    $flat = [];
    foreach ($rows as $row) {
      foreach ($row as $s) {
        $flat[] = $s;
      }
    }

    usort($flat, fn($a, $b) => $a['start'] <=> $b['start']);
    $team_round = [];
    $new_rows = [];

    foreach ($flat as $s) {
      $r = 0;
      foreach ($s['teams'] as $t) {
        if (isset($team_round[$t])) {
          $r = max($r, $team_round[$t] + 1);
        } else if (isset($entry_floor[$t])) {
          $r = max($r, $entry_floor[$t]);
        }
      }
      $new_rows[$r][] = $s;
      foreach ($s['teams'] as $t) {
        $team_round[$t] = $r;
      }
    }
    ksort($new_rows);
    return array_values($new_rows);
  };

  $align_back = function(array $rows): array {
    $n = count($rows);
    if ($n < 2) {
      return $rows;
    }
    
    for ($ri = $n - 2; $ri >= 0; $ri--) {
      foreach ($rows[$ri] as $idx => $s) {
        $w = $s['winner'] ?? null;
        if (!$w) {
          continue;
        }
        
        $nr = null;
        for ($j = $ri + 1; $j < $n && $nr === null; $j++) {
          foreach ($rows[$j] as $s2) {
            if (in_array($w, $s2['teams'])) {
              $nr = $j; 
              break;
            }
          }
        }
        if ($nr === null) {
          continue;
        }
        
        $target = $nr - 1;
        if ($target <= $ri) {
          continue;
        }
        
        $loser    = tb_loser($s);
        $conflict = false;
        if ($loser !== null) {
          for ($j = $ri + 1; $j <= $target && !$conflict; $j++) {
            foreach ($rows[$j] as $s2) {
              if (in_array($loser, $s2['teams'])) {
                $conflict = true;
                break;
              }
            }
          }
        }
        if ($conflict) {
          continue;
        }
        
        unset($rows[$ri][$idx]);
        $rows[$target][] = $s;
        usort($rows[$target], fn($a, $b) => $a['start'] <=> $b['start']);
      }
      $rows[$ri] = array_values($rows[$ri]);
    }
    return array_values(array_filter($rows, fn($r) => $r));
  };

  $reorder = function(array $rows): array {
    $n = count($rows);
    for ($ri = $n - 2; $ri >= 0; $ri--) {
      $keyed = [];
      foreach ($rows[$ri] as $idx => $s) {
        $w = $s['winner'] ?? null;
        $key = PHP_INT_MAX;
        if ($w !== null) {
          for ($j = $ri + 1; $j < $n && $key === PHP_INT_MAX; $j++) {
            foreach ($rows[$j] as $ti => $t) {
              if (in_array($w, $t['teams'])) {
                $key = ($ti + 0.5) / max(1, count($rows[$j]));

                break;
              }
            }
          }
        }
        $keyed[] = [$key, $idx, $s];
      }
      usort($keyed, fn($a, $b) => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));
      $rows[$ri] = array_column($keyed, 2);
    }

    return $rows;
  };

  $order_teams = function(array $rows): array {
    $slot = [];
    foreach ($rows as $ri => $row) {
      foreach ($row as $i => $s) {
        if (count($s['teams']) === 2) {
          [$a, $b] = $s['teams'];

          if (isset($slot[$a], $slot[$b]) && $slot[$b] < $slot[$a]) {
            $rows[$ri][$i]['teams'] = [$b, $a];
          }
        }

        foreach ($s['teams'] as $t) {
          $slot[$t] = $i;
        }
      }
    }
    return $rows;
  };

  if ($ub_rows) $ub_rows = $order_teams($reorder($align_back($rebucket($ub_rows))));

  $lb_entry_floor = [];
  foreach ($ub_rows as $ri => $row) {
    foreach ($row as $s) {
      $l = tb_loser($s);
      if ($l !== null) {
        $lb_entry_floor[$l] = $ri;
      }
    }
  }
  if ($lb_rows) {
    $lb_rows = $order_teams($reorder($align_back($rebucket($lb_rows, $lb_entry_floor))));
  }

  if ($gf_series && $ub_rows) {
    $last_ub = end($ub_rows);
    $ubw = count($last_ub) === 1 ? ($last_ub[0]['winner'] ?? null) : null;
    if ($ubw !== null) {
      foreach ($gf_series as $gi => $g) {
        if (count($g['teams']) === 2 && $g['teams'][1] === $ubw) {
          $gf_series[$gi]['teams'] = [$g['teams'][1], $g['teams'][0]];
        }
      }
    }
  }

  $n_ub = count($ub_rows);
  $se_narrows = $ub_rows && count(end($ub_rows)) === 1;
  $ub_named = [];
  foreach ($ub_rows as $ri => $series) {
    $name = $is_double
      ? tb_ub_round_name($ri, $n_ub, count($series))
      : ($se_narrows ? tb_se_round_name($ri, $n_ub, count($series)) : 'bracket_round ' . ($ri + 1));
    $ub_named[] = [
      'name' => $name,
      'series' => $series,
    ];
  }

  $n_lb = count($lb_rows);
  $lb_named = [];
  foreach ($lb_rows as $ri => $series) {
    $lb_named[] = [
      'name' => tb_lb_round_name($ri, $n_lb, count($series)),
      'series' => $series,
    ];
  }

  $ub_to_lb = [];
  if ($is_double) {
    foreach ($ub_named as $ri => $ub_round) {
      $ub_losers = [];
      foreach ($ub_round['series'] as $s) {
        $l = tb_loser($s);
        if ($l) {
          $ub_losers[] = $l;
        }
      }
      foreach ($lb_named as $li => $lb_round) {
        $lb_teams = array_unique(array_merge(...array_map(fn($s) => $s['teams'], $lb_round['series'])));
        if (array_intersect($ub_losers, $lb_teams)) {
          $ub_to_lb[$ri] = $li;
          break;
        }
      }
    }

    if ($ub_to_lb && $n_lb > 0) {
      $prev = -1;
      for ($i = 0; $i < $n_ub - 1; $i++) {
        if (isset($ub_to_lb[$i])) {
          $prev = $ub_to_lb[$i];
          continue;
        }

        $next = null;
        for ($j = $i + 1; $j < $n_ub; $j++) {
          if (isset($ub_to_lb[$j])) { 
            $next = $ub_to_lb[$j] - ($j - $i);
            break;
          }
        }
        $val = $next ?? ($prev + 1);
        $val = min(max($val, $prev, 0), $n_lb - 1);
        $ub_to_lb[$i] = $val;
        $prev = $val;
      }
      ksort($ub_to_lb);
    }
  }

  return [
    'type'        => $is_double ? 'double_elimination' : 'single_elimination',
    'ub_rounds'   => $ub_named,
    'lb_rounds'   => $lb_named,
    'grand_final' => $gf_series ? [
      'name' => 'bracket_gf',
      'series' => $gf_series,
    ] : null,
    'ub_to_lb'    => $ub_to_lb,
    'unplaced'    => $unplaced,
  ];
}

function tb_repair_outcomes(array $all, bool $is_double): array {
  $max_loss = $is_double ? 2 : 1;
  $loss_map = function (array $set): array {
    $L = [];
    foreach ($set as $s) { $l = tb_loser($s); if ($l) $L[$l] = ($L[$l] ?? 0) + 1; }
    return $L;
  };

  $applied = 0;
  for ($iter = 0; $iter < 3; $iter++) {
    $loss = $loss_map($all);
    $violators = array_filter($loss, fn($c) => $c > $max_loss);
    if (!$violators) {
      break;
    }

    $cands = [];
    foreach ($all as $k => $s) {
      $w = $s['winner'] ?? null;
      $l = tb_loser($s);
      if ($w === null || $l === null) {
        continue;
      }

      if (in_array('overridden', $s['flags'] ?? [], true)) {
        continue;
      }

      if (!isset($violators[$l])) {
        continue;
      }

      $sim = $loss; $sim[$l]--; $sim[$w] = ($sim[$w] ?? 0) + 1;
      $valid = true;
      foreach ($sim as $c) {
        if ($c > $max_loss) {
          $valid = false;
          break;
        }
      }

      if ($valid) {
        $cands[$k] = $s['start'];
      }
    }

    if (!$cands) {
      break;
    }

    asort($cands);

    $fk = array_key_first($cands);
    $all[$fk]['winner'] = tb_loser($all[$fk]);
    $all[$fk]['flags']  = array_values(array_unique(array_merge(
      $all[$fk]['flags'] ?? [], ['outcome-fixed']
    )));
    $applied++;
  }

  return $all;
}

// Connectivity failsafe
function tb_repair_orphan_winners(array $all): array {
  $plays = [];
  foreach ($all as $s) {
    foreach ($s['teams'] as $t) {
      $plays[$t][] = $s['start'];
    }
  }

  $later_count = function (int $team, int $ts) use ($plays): int {
    $n = 0;
    foreach ($plays[$team] ?? [] as $t) {
      if ($t > $ts) {
        $n++;
      }
    }

    return $n;
  };

  $cands = [];
  foreach ($all as $k => $s) {
    $w = $s['winner'] ?? null;
    $l = tb_loser($s);
    if ($w === null || $l === null) continue;
    if (in_array('overridden', $s['flags'] ?? [], true)) continue;
    if (count($plays[$w] ?? []) !== 1) continue;
    if ($later_count($w, $s['start']) !== 0) continue;
    if ($later_count($l, $s['start']) < 2) continue;

    $cands[$k] = $s['start'];
  }
  asort($cands);
  foreach (array_keys($cands) as $k) {
    $all[$k]['winner'] = tb_loser($all[$k]);
    $all[$k]['flags']  = array_values(array_unique(array_merge(
      $all[$k]['flags'] ?? [], ['outcome-fixed']
    )));
  }

  return $all;
}

function tb_next_series_for_team(int $team_id, array $series_by_id, int $after_ts): ?string {
  $best = null;
  $best_t = PHP_INT_MAX;
  foreach ($series_by_id as $s) {
    if ($s['start'] <= $after_ts) continue;
    if (!in_array($team_id, $s['teams'])) continue;
    if ($s['start'] < $best_t) {
      $best  = $s['key'];
      $best_t = $s['start'];
    }
  }

  return $best;
}

function tb_loser(array $s): ?int {
  if (!$s['winner']) {
    return null;
  }

  foreach ($s['teams'] as $t) {
    if ($t !== $s['winner']) {
      return $t;
    }
  }

  return null;
}

function tb_bracket_placements(array $br): array {
  $lb = $br['lb_rounds'] ?? [];
  $ub = $br['ub_rounds'] ?? [];
  $gf = $br['grand_final']['series'] ?? [];

  $all = [];
  foreach (['ub_rounds', 'lb_rounds'] as $rk) {
    foreach ($br[$rk] ?? [] as $r) {
      foreach ($r['series'] as $s) {
        foreach ($s['teams'] as $t) {
          if ($t) $all[$t] = true;
        }
      }
    }
  }

  foreach ($gf as $s) {
    foreach ($s['teams'] as $t) {
      if ($t) {
        $all[$t] = true;
      }
    }
  }

  if (!$all) return [];

  $groups = [];
  $placed = [];
  $add = function(array $ids) use (&$groups, &$placed) {
    $ids = array_values(array_filter($ids, fn($t) => $t && !isset($placed[$t])));
    if (!$ids) {
      return;
    }
    foreach ($ids as $t) {
      $placed[$t] = true;
    }
    $groups[] = $ids;
  };

  $gf_last = $gf ? end($gf) : null;
  $gf_decided = $gf_last && ($gf_last['winner'] ?? null) !== null;
  $se_final = (!$lb && $ub) ? end($ub)['series'] : [];
  $se_decided = count($se_final) === 1 && ($se_final[0]['winner'] ?? null) !== null;

  if ($gf_decided) {
    $add([$gf_last['winner']]);
    $add([tb_loser($gf_last)]);

    for ($i = count($lb) - 1; $i >= 0; $i--) {
      $add(array_map('tb_loser', $lb[$i]['series']));
    }
  } elseif ($se_decided) {
    $add([$se_final[0]['winner']]);

    for ($i = count($ub) - 1; $i >= 0; $i--) {
      $add(array_map('tb_loser', $ub[$i]['series']));
    }
  } else {
    $elim = [];
    for ($i = count($lb) - 1; $i >= 0; $i--) {
      $elim[] = array_map('tb_loser', $lb[$i]['series']);
    }
    if (!$lb) {
      for ($i = count($ub) - 1; $i >= 0; $i--) {
        $elim[] = array_map('tb_loser', $ub[$i]['series']);
      }
    }

    $elim_set = [];
    foreach ($elim as $g) {
      foreach ($g as $t) {
        if ($t) {
          $elim_set[$t] = true;
        }
      }
    }

    $survivors = array_keys(array_diff_key($all, $elim_set));
    if (!$survivors) {
      return [];
    }

    $add($survivors);
    foreach ($elim as $g) {
      $add($g);
    }
  }

  $add(array_keys(array_diff_key($all, $placed)));
  return $groups;
}

function tb_event_placements(array $stages): array {
  $br_idx = null;
  $br_groups = [];
  foreach ($stages as $i => $st) {
    if (($st['phase_type'] ?? '') === 'bracket' && !empty($st['bracket'])) {
      $p = tb_bracket_placements($st['bracket']);
      if ($p) {
        $br_idx = $i;
        $br_groups = $p;
      }
    }
  }

  if ($br_idx === null) {
    return [];
  }

  $last_stage = [];
  foreach ($stages as $i => $st) {
    foreach (tb_unique_teams($st['series'] ?? []) as $t) {
      $last_stage[$t] = $i;
    }
  }

  $groups = [];
  $placed = [];
  $add = function(array $ids) use (&$groups, &$placed) {
    $ids = array_values(array_filter($ids, fn($t) => $t && !isset($placed[$t])));
    if (!$ids) {
      return;
    }

    foreach ($ids as $t) {
      $placed[$t] = true;
    }
    $groups[] = $ids;
  };

  foreach ($br_groups as $g) {
    $add($g);
  }

  for ($i = $br_idx - 1; $i >= 0; $i--) {
    $elim = [];
    foreach (tb_unique_teams($stages[$i]['series'] ?? []) as $t) {
      if (($last_stage[$t] ?? -1) === $i) {
        $elim[] = $t;
      }
    }
    $add($elim);
  }

  return $groups;
}

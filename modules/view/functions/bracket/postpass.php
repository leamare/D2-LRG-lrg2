<?php

// Fold stray series back to group stage
function tb_fold_back_strays(array $stages, array $teams): array {
  $stray_group_series = [];
  $has_group = $has_main_bracket = $has_valid_playoff = false;

  foreach ($stages as $st) {
    if (($st['type'] ?? '') === 'group_stage') {
      $has_group = true;
    }

    $pt = $st['phase_type'] ?? '';

    if ($pt === 'bracket') {
      $has_main_bracket = true;
    }

    if (in_array($pt, ['bracket', 'seeding_bracket'], true) && tb_is_valid_playoff($st['bracket'] ?? [])) {
      $has_valid_playoff = true;
    }
  }

  // keep incomplete brackets from folding back
  $group_stage_count = 0;
  $group_max_bo      = 0;
  foreach ($stages as $st) {
    if (($st['type'] ?? '') === 'group_stage') {
      $group_stage_count++;
      foreach ($st['series'] ?? [] as $s) {
        $group_max_bo = max($group_max_bo, (int)($s['bo'] ?? 0));
      }
    }
  }

  $clean_single_group = $group_stage_count === 1 && $group_max_bo <= 2;
  if ($has_group) {
    $drop_seed = [];
    foreach ($stages as $si => &$st) {
      $pt = $st['phase_type'] ?? '';
      $br_max_bo = ($st['series'] ?? []) ? max(array_map(fn($s) => (int)($s['bo'] ?? 0), $st['series'])) : 0;
      $invalid_bracket = $pt === 'bracket' && !tb_is_valid_playoff($st['bracket'] ?? [])
        && !($br_max_bo >= 3 && $clean_single_group);
      
      if ($pt === 'seeding_bracket' && !$has_main_bracket && tb_is_valid_playoff($st['bracket'] ?? [])) {
        $st['phase_type'] = 'bracket';
        $st['name']       = 'bracket_playoffs';
      } else if (!$has_valid_playoff && (in_array($pt, ['seeding', 'seeding_bracket'], true) || $invalid_bracket)) {
        $stray_group_series = array_merge($stray_group_series, $st['series']);
        $drop_seed[$si] = true;
      }
    }
    unset($st);
    if ($drop_seed) {
      $stages = array_values(array_filter($stages, fn($i) => !isset($drop_seed[$i]), ARRAY_FILTER_USE_KEY));
    }
  }

  // tiebreakers detection
  $drop_stage = [];
  foreach ($stages as $bi => $bst) {
    if (($bst['phase_type'] ?? '') !== 'bracket' || empty($bst['bracket'])) continue;
    $gi = null;

    for ($k = $bi - 1; $k >= 0; $k--) {
      if (($stages[$k]['type'] ?? '') === 'group_stage') { $gi = $k; break; }
    }

    if ($gi === null) {
      continue;
    }

    $bteams = tb_unique_teams($bst['series']);
    $pts = [];
    foreach ($stages[$gi]['groups'] as $gr) {
      foreach ($gr['standings'] as $r) {
        $pts[$r['team']] = 2 * $r['w'] + $r['d'];
      }
    }

    if (count($pts) < 4 || count($bteams) < 2) {
      continue;
    }
    if (count($bteams) > count($pts) * 0.6) {
      continue;
    }

    arsort($pts);
    if (in_array(array_key_first($pts), $bteams, true)) {
      continue;
    }

    $bpts = [];
    foreach ($bteams as $t) {
      if (!isset($pts[$t])) {
        continue 2;
      }
      $bpts[] = $pts[$t];
    }
    if (max($bpts) - min($bpts) > 1) {
      continue;
    }

    foreach ($stages[$gi]['groups'] as &$gr) {
      $gset = array_flip($gr['teams'] ?? array_column($gr['standings'], 'team'));
      foreach ($bst['series'] as $s) {
        if (isset($gset[$s['teams'][0]]) || isset($gset[$s['teams'][1] ?? -1])) {
          $gr['tiebreakers'][] = $s;
        }
      }
    }
    unset($gr);
    $drop_stage[$bi] = true;
  }

  if ($drop_stage) {
    $stages = array_values(array_filter($stages, fn($i) => !isset($drop_stage[$i]), ARRAY_FILTER_USE_KEY));
  }

  // realign
  $leftover = $stray_group_series;
  foreach ($stages as &$st) {
    if (($st['phase_type'] ?? '') === 'bracket' && !empty($st['bracket']['unplaced'])) {
      $leftover = array_merge($leftover, $st['bracket']['unplaced']);
      $st['bracket']['unplaced'] = [];
    }
  }

  unset($st);

  if ($leftover) {
    $tgt = null; $best = -1;
    foreach ($stages as $i => $st) {
      if (($st['type'] ?? '') === 'group_stage' && count($st['series']) > $best) {
        $best = count($st['series']); $tgt = $i;
      }
    }

    if ($tgt !== null) {
      $merged = tb_dedup_series(array_merge($stages[$tgt]['series'], $leftover));
      usort($merged, fn($a, $b) => $a['start'] <=> $b['start']);
      $rebuilt = tb_analyze_phase(
        ['rounds' => tb_temporal_rounds($merged), 'series' => $merged],
        $teams,
        $stages[$tgt]['name'],
        false,
        false,
      );
      $rebuilt['phase_type'] = $stages[$tgt]['phase_type'];
      $stages[$tgt] = $rebuilt;
    } else {
      foreach ($stages as &$st) {
        if (($st['phase_type'] ?? '') === 'bracket' && !empty($st['bracket'])) {
          $st['bracket']['unplaced'] = $leftover;
          break;
        }
      }
      unset($st);
    }
  }

  return $stages;
}

// detect bracket within strays
function tb_pp_tiebreaker_playoff(array $stages, array $teams): array {
  $rebuilt = [];

  foreach ($stages as $st) {
    $tb = ($st['type'] ?? '') === 'group_stage' ? ($st['tiebreakers'] ?? []) : [];

    if ($tb && count(tb_unique_teams($tb)) >= 6) {
      $brk = tb_build_bracket(tb_temporal_rounds($tb), $teams);
      if (empty($brk['unplaced']) && !empty($brk['grand_final']) && tb_is_valid_playoff($brk)) {
        $tb_keys = array_flip(array_map(fn($s) => $s['key'], $tb));
        $rr_series = array_values(array_filter($st['series'], fn($s) => !isset($tb_keys[$s['key']])));
        $play = tb_analyze_phase(
          ['rounds' => tb_temporal_rounds($tb), 'series' => $tb],
          $teams,
          'bracket_main_event',
          true,
          true,
        );

        if (($play['type'] ?? '') === 'playoff') {
          $play['phase_type'] = 'bracket';
          $rebuilt[] = tb_analyze_phase(
            ['rounds' => tb_temporal_rounds($rr_series), 'series' => $rr_series],
            $teams,
            $st['name'],
            false,
            false,
          );

          $rebuilt[] = $play;
          continue;
        }
      }
    }

    $rebuilt[] = $st;
  }

  return $rebuilt;
}

function tb_pp_group_to_bracket(array $stages, array $teams): array {
  foreach ($stages as $si => $st) {
    if (($st['type'] ?? '') !== 'group_stage') {
      continue;
    }

    $follow_bracket = false;
    for ($j = $si + 1; $j < count($stages); $j++) {
      if (($stages[$j]['phase_type'] ?? '') === 'bracket') {
        $follow_bracket = true;
        break;
      }
    }
    
    $gs = $st['series'];
    if (count($gs) < 2 || tb_progression_is_group($gs)) {
      continue;
    }

    $gs_teams = count(tb_unique_teams($gs));
    if ($gs_teams >= 12 && count($gs) >= 0.6 * $gs_teams) {
      continue;
    }

    $sizes = array_map(fn($g) => count($g['teams'] ?? []), $st['groups'] ?? []);
    $maxg  = $sizes ? max($sizes) : 0;
    $minsz = $sizes ? min($sizes) : 0;

    $bracket_pieces = count($sizes) >= 2 && $minsz <= 2 && $maxg <= 4;
    if ($follow_bracket && !$bracket_pieces) continue;

    $br = tb_build_bracket(tb_temporal_rounds($gs), $teams);
    $placed = 0;
    foreach (['ub_rounds', 'lb_rounds'] as $rk) {
      foreach ($br[$rk] ?? [] as $r) {
        $placed += count($r['series']);
      }
    }

    if (!empty($br['grand_final'])) {
      $placed += count($br['grand_final']['series']);
    }

    $un = count($br['unplaced'] ?? []);
    $clean = $un === 0 && tb_is_valid_playoff($br);
    $pieces = count($sizes) >= 2 && $maxg <= 5 && $placed >= 2 && $placed > $un;

    if (!$clean && !$pieces && !$bracket_pieces) {
      continue;
    }

    $conv = tb_analyze_phase(
      ['rounds' => tb_temporal_rounds($gs), 'series' => $gs],
      $teams,
      'bracket_playoffs',
      true,
      true,
    );

    if (($conv['type'] ?? '') === 'playoff') {
      $conv['phase_type'] = 'bracket';
      $stages[$si] = $conv;
    }
  }

  return $stages;
}

function tb_pp_group_decider(array $stages, array $teams): array {
  $rebuilt = [];
  foreach ($stages as $si => $st) {
    if (($st['type'] ?? '') !== 'group_stage' || empty($st['decider']['series'])) {
      $rebuilt[] = $st;
      continue;
    }

    $dec = $st['decider']['series'];
    $st['decider'] = null;
    $rebuilt[] = $st;
    $follow_bracket = false;

    for ($j = $si + 1; $j < count($stages); $j++) {
      if (($stages[$j]['phase_type'] ?? '') === 'bracket') {
        $follow_bracket = true;
        break;
      }
    }

    if ($follow_bracket) {
      $rebuilt[] = [
        'type' => 'seeding',
        'name' => 'bracket_decider',
        'phase_type' => 'seeding',
        'series' => $dec,
      ];
    } else {
      $p_stage = tb_analyze_phase(
        ['rounds' => tb_temporal_rounds($dec), 'series' => $dec],
        $teams,
        'bracket_playoffs',
        true,
        true,
      );

      $p_stage['phase_type'] = ($p_stage['type'] ?? '') === 'playoff' ? 'bracket' : 'group';
      $rebuilt[] = $p_stage;
    }
  }

  return $rebuilt;
}

// merge several gs fragments
function tb_pp_merge_brackets(array $stages, array $teams): array {
  $br_idx = [];
  foreach ($stages as $i => $st) {
    if (($st['phase_type'] ?? '') === 'bracket') {
      $br_idx[] = $i;
    }
  }

  if (count($br_idx) < 2) {
    return $stages;
  }

  $merged = [];
  foreach ($br_idx as $i) $merged = array_merge($merged, $stages[$i]['series']);
  usort($merged, fn($a, $b) => $a['start'] <=> $b['start']);
  $reb = tb_analyze_phase(
    ['rounds' => tb_temporal_rounds($merged), 'series' => $merged],
    $teams,
    'bracket_main_event',
    true,
    true,
  );

  if (($reb['type'] ?? '') === 'playoff') {
    $reb['phase_type'] = 'bracket';
    $stages[$br_idx[0]] = $reb;
    $drop = array_flip(array_slice($br_idx, 1));
    $stages = array_values(array_filter($stages, fn($i) => !isset($drop[$i]), ARRAY_FILTER_USE_KEY));
  }

  return $stages;
}

// fold seed round back to group stage
function tb_pp_fold_seed_round(array $stages, array $teams): array {
  foreach ($stages as $si => $st) {
    if (($st['phase_type'] ?? '') !== 'bracket') {
      continue;
    }

    if ($si < 1 || ($stages[$si - 1]['type'] ?? '') !== 'group_stage') {
      continue;
    }

    $ub = $st['bracket']['ub_rounds'] ?? [];
    $lb = $st['bracket']['lb_rounds'] ?? [];

    if (count($ub) < 2 || empty($lb)) {
      continue;
    }

    $first_ub = $ub[0]['series'];
    if (count($first_ub) < 2 || count($first_ub) >= count($ub[1]['series'])) {
      continue;
    }

    $lb_teams = tb_unique_teams(tb_rounds_series($lb));
    $losers  = [];
    foreach ($first_ub as $s) {
      $l = tb_loser($s);
      if ($l !== null) {
        $losers[] = $l;
      }
    }

    if (!$losers || count(array_intersect($losers, $lb_teams)) / count($losers) > 0.2) {
      continue;
    }

    $move_keys = array_flip(array_map(fn($s) => $s['key'], $first_ub));
    $br_series = array_values(array_filter($st['series'], fn($s) => !isset($move_keys[$s['key']])));
    $grp_series = array_merge($stages[$si - 1]['series'], $first_ub);
    $reb_br = tb_analyze_phase(
      ['rounds' => tb_temporal_rounds($br_series), 'series' => $br_series],
      $teams,
      $st['name'],
      true,
      true,
    );
    if (($reb_br['type'] ?? '') !== 'playoff') {
      continue;
    }

    $reb_br['phase_type'] = 'bracket';
    $stages[$si] = $reb_br;
    $stages[$si - 1] = tb_analyze_phase(
      ['rounds' => tb_temporal_rounds($grp_series), 'series' => $grp_series],
      $teams,
      $stages[$si - 1]['name'],
      false,
      false,
    );
  }

  return $stages;
}

// fold late seeding back to group stage
function tb_pp_fold_late_seeding(array $stages, array $teams): array {
  $brk_min = null;
  $brk_idx = null;

  foreach ($stages as $i => $st) {
    if (($st['phase_type'] ?? '') !== 'bracket' || empty($st['series'])) {
      continue;
    }

    $m = min(array_map(fn($s) => $s['start'], $st['series']));
    if ($brk_min === null || $m < $brk_min) {
      $brk_min = $m;
      $brk_idx = $i;
    }
  }
  if ($brk_idx === null) {
    return $stages;
  }

  $fold  = [];
  $extra = [];
  foreach ($stages as $i => $st) {
    if (($st['type'] ?? '') !== 'seeding' || empty($st['series'])) {
      continue;
    }

    if (min(array_map(fn($s) => $s['start'], $st['series'])) > $brk_min) {
      $fold[$i] = true;
      $extra = array_merge($extra, $st['series']);
    }
  }

  if ($extra) {
    $merged = tb_dedup_series(array_merge($stages[$brk_idx]['series'], $extra));
    usort($merged, fn($a, $b) => $a['start'] <=> $b['start']);
    $reb = tb_analyze_phase(
      ['rounds' => tb_temporal_rounds($merged), 'series' => $merged],
      $teams,
      $stages[$brk_idx]['name'],
      true,
      true,
    );

    $reb['phase_type'] = 'bracket';
    $stages[$brk_idx] = $reb;
    $stages = array_values(array_filter($stages, fn($i) => !isset($fold[$i]), ARRAY_FILTER_USE_KEY));
  }
  
  return $stages;
}

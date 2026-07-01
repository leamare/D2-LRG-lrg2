<?php

function tb_is_valid_playoff(array $bracket): bool {
  $ub = $bracket['ub_rounds'] ?? [];
  if (!$ub) return false;

  $total = 0;
  foreach (['ub_rounds', 'lb_rounds'] as $rk) {
    foreach ($bracket[$rk] ?? [] as $rd) {
      $total += count($rd['series']);
    }
  }
  $total += count($bracket['grand_final']['series'] ?? []);

  if ($total < 2) {
    return false;
  }

  $ub_teams = [];
  foreach ($ub as $rd) {
    foreach ($rd['series'] as $s) {
      foreach ($s['teams'] as $t) {
        $ub_teams[$t] = true;
      }
    }
  }
  $n = count($ub_teams);

  if ($n < 2) {
    return false;
  }

  if (!empty($bracket['lb_rounds'])) {
    $all_teams = $ub_teams;
    foreach ($bracket['lb_rounds'] as $rd) {
      foreach ($rd['series'] as $s) {
        foreach ($s['teams'] as $t) {
          $all_teams[$t] = true;
        }
      }
    }

    foreach ($bracket['grand_final']['series'] ?? [] as $s) {
      foreach ($s['teams'] as $t) {
        $all_teams[$t] = true;
      }
    }

    if (count($all_teams) < 4) {
      return false;
    }
  }
  
  $is_de = !empty($bracket['grand_final']['series']) && !empty($bracket['lb_rounds']);
  if (!$is_de && (1 << count($ub)) < $n) {
    return false;
  }

  $last = !empty($bracket['grand_final'])
    ? $bracket['grand_final']['series']
    : (($bracket['lb_rounds'] ?? []) ? end($bracket['lb_rounds'])['series'] : end($ub)['series']);
  
  return count($last) === 1;
}

function tb_is_incoherent_aggregate(array $series): bool {
  if (count($series) < 60) return false;

  $teams = [];
  $months = [];
  $pair_meets = [];
  foreach ($series as $s) {
    $a = $s['teams'][0] ?? 0;
    $b = $s['teams'][1] ?? 0;

    if (!$a || !$b) {
      continue;
    }

    $teams[$a] = $teams[$b] = true;
    $months[date('Y-m', $s['start'])] = true;
    $pair = $a < $b ? "$a:$b" : "$b:$a";
    $pair_meets[$pair] = ($pair_meets[$pair] ?? 0) + 1;
  }

  if (count($teams) < 20) {
    return false;
  }

  if (count($months) < 3) {
    return false;
  }

  return ($pair_meets ? max($pair_meets) : 0) >= 5;
}

function tb_report_wants_month_grid(array $series, array $events): bool {
  if (count($series) < 2) return false;
  $starts = array_column($series, 'start');
  $first = min($starts);
  $last = max($starts);

  // ~3.5 months
  if (($last - $first) < 105 * 86400) {
    return false;
  }

  $active = [];
  foreach ($series as $s) {
    $active[date('Y-m', $s['start'])] = true;
  }

  $months = 0;
  for ($t = strtotime(date('Y-m-01', $first)); $t <= $last; $t = strtotime('+1 month', $t)) {
    $months++;
  }

  if ($months > 0 && count($active) / $months < 0.7) {
    return false;
  }

  if (tb_is_incoherent_aggregate($series)) {
    return true;
  }

  if (count($events) < 2) {
    return false;
  }

  $team_events = [];
  foreach ($events as $ev) {
    foreach (tb_unique_teams($ev['series']) as $t) {
      $team_events[$t] = ($team_events[$t] ?? 0) + 1;
    }
  }

  if (!$team_events) {
    return false;
  }

  $shared = count(array_filter($team_events, fn($c) => $c >= 2));

  return $shared / count($team_events) >= 0.3;
}

function tb_temporal_clusters(array $series, int $gap): array {
  usort($series, fn($a, $b) => $a['start'] <=> $b['start']);
  $clusters = [];
  $cur = [];
  $prev = null;
  foreach ($series as $s) {
    if ($prev !== null && ($s['start'] - $prev) > $gap) {
      $clusters[] = $cur;
      $cur = [];
    }
    $cur[] = $s;
    $prev = $s['start'];
  }

  if ($cur) {
    $clusters[] = $cur;
  }

  return $clusters;
}

// Whether a season-grid sub-event is a coherent single tournament
function tb_event_is_coherent(array $ev, array $analyzed): bool {
  foreach ($analyzed['stages'] ?? [] as $st) {
    if (($st['name'] ?? '') === 'bracket_form') {
      return false;
    }

    if (!empty($st['bracket']['lb_rounds'])) {
      $lb_losses = [];
      foreach ($st['bracket']['lb_rounds'] as $rd) {
        foreach ($rd['series'] as $s) {
          $l = tb_loser($s);
          if ($l !== null) {
            $lb_losses[$l] = ($lb_losses[$l] ?? 0) + 1;
          }
        }
      }
      
      if (count(array_filter($lb_losses, fn($c) => $c >= 2)) >= 2) {
        return false;
      }
    }
    
    if (($st['type'] ?? '') === 'group_stage') {
      $teams = 0;
      foreach ($st['groups'] ?? [] as $g) {
        $teams += count($g['standings'] ?? $g['teams'] ?? []);
      }

      if (count($st['tiebreakers'] ?? []) > max(4, $teams)) {
        return false;
      }
    }
  }

  return true;
}

function tb_analyze_event(array $event, array $teams, array $config = []): array {
  $series = $event['series'];
  usort($series, fn($a, $b) => $a['start'] <=> $b['start']);

  if (($event['mode'] ?? '') === 'interest') {
    return tb_analyze_interest($event, $teams);
  }

  // Multi-month aggregate
  if (($event['mode'] ?? '') === 'aggregate') {
    return tb_analyze_interest($event, $teams, 'bracket_form');
  }
  
  if (($config['months'] ?? null) !== false && empty($config['stages']) && tb_is_incoherent_aggregate($series)) {
    return tb_analyze_interest($event, $teams, 'bracket_form');
  }

  $hinted = !empty($config['stages']);
  $phases = $hinted
    ? tb_phases_from_hint($series, $config['stages'])
    : tb_refine_phases(tb_peel_outlier_wildcard(tb_detect_phases($series)), $series, $teams);

  $gs_idx = 0;

  $pure_group_cnt = count(array_filter($phases, fn($p) =>
    !($p['is_elim'] ?? false)
    && !in_array($p['phase_type'] ?? '', ['seeding', 'wildcard'], true)
  ));

  $stages = [];
  $pending_lb_seeds = [];

  foreach ($phases as $pi => $phase) {
    $is_elim = (bool)($phase['is_elim'] ?? false);
    $phase_type = $phase['phase_type'] ?? ($is_elim ? 'bracket' : 'group');

    if ($phase_type === 'seeding') {
      foreach ($phase['series'] as $s) {
        $l = tb_loser($s);
        if ($l) {
          $pending_lb_seeds[] = $l;
        }
      }
    }
    if ($phase_type === 'bracket' && $pending_lb_seeds) {
      $phase['lb_seeds'] = $pending_lb_seeds;
      $pending_lb_seeds = [];
    }

    if ($phase_type === 'elimination_round') {
      $stage_name = 'bracket_elim_round';
    } else if ($phase_type === 'seeding') {
      $stage_name = 'bracket_decider';
    } else if ($phase_type === 'wildcard') {
      $stage_name = 'bracket_wildcard';
    } else if ($phase_type === 'bracket') {
      $stage_name = 'bracket_main_event';
    } else {
      $gs_idx++;
      $stage_name = $pure_group_cnt > 1 ? "bracket_group_stage {$gs_idx}" : 'bracket_group_stage';
    }

    if ($phase_type === 'seeding' || $phase_type === 'wildcard') {
      $stage = [
        'type' => 'seeding',
        'name' => $stage_name,
        'series' => $phase['series'],
      ];
    } else {
      $allow_peel = $phase_type === 'bracket';
      $stage = tb_analyze_phase($phase, $teams, $stage_name, $is_elim, $allow_peel);

      if ($is_elim && ($stage['type'] ?? '') === 'group_stage') {
        $phase_type = 'group';
        $gs_idx++;
        $stage['name'] = $pure_group_cnt > 1 ? "bracket_group_stage {$gs_idx}" : 'bracket_group_stage';
      }
    }
    $stage['phase_type'] = $phase_type;

    $sb_stage = null;
    if (!$hinted && !empty($stage['seeding_bracket'])) {
      $sb = $stage['seeding_bracket'];
      unset($stage['seeding_bracket']);

      $sb_phase = [
        'rounds' => tb_temporal_rounds($sb),
        'series' => $sb,
        'is_elim' => true,
      ];

      $sb_stage = tb_analyze_phase($sb_phase, $teams, 'bracket_playin', true, false);
      $sb_stage['phase_type'] = 'seeding_bracket';
    }

    $pb_stage = null;
    if (!$hinted && !empty($stage['playoff_bracket'])) {
      $pb = $stage['playoff_bracket'];
      unset($stage['playoff_bracket']);

      $pb_phase = [
        'rounds' => tb_temporal_rounds($pb),
        'series' => $pb,
        'is_elim' => true,
      ];

      $pb_stage = tb_analyze_phase($pb_phase, $teams, 'bracket_main_event', true, true);
      $pb_stage['phase_type'] = 'bracket';
    }

    $stages[] = $stage;

    if ($sb_stage) {
      $stages[] = $sb_stage;
    }

    if ($pb_stage) {
      $stages[] = $pb_stage;
    }
  }

  // fold stray seeding / invalid brackets back into the group stage
  if (!$hinted) {
    $stages = tb_fold_back_strays($stages, $teams);
    $stages = tb_pp_tiebreaker_playoff($stages, $teams);
    $stages = tb_pp_group_to_bracket($stages, $teams);
    $stages = tb_pp_group_decider($stages, $teams);
    $stages = tb_pp_merge_brackets($stages, $teams);
    $stages = tb_pp_fold_seed_round($stages, $teams);
    $stages = tb_pp_fold_late_seeding($stages, $teams);
  }

  $stages = tb_color_group_stages($stages, $teams, $series);

  return [
    'name' => $event['name'],
    'report' => $event['report'],
    'stages' => $stages,
    'team_cards' => array_intersect_key($teams, array_flip(tb_unique_teams($series))),
  ];
}

// reclassify a "bracket" that is really a group stage
function tb_refine_phases(array $phases, array $series, array $teams): array {
  foreach ($phases as &$ph) {
    $ptype = $ph['phase_type'] ?? (($ph['is_elim'] ?? false) ? 'bracket' : 'group');
    if (!in_array($ptype, ['bracket', 'elimination_round'], true)) {
      continue;
    }

    $n = count($ph['series']);
    $tied = tb_count_tied($ph['series']);
    $genuine = tb_count_genuine_ties($ph['series']);
    $p_teams = count(tb_unique_teams($ph['series']));

    $pairs = [];
    foreach ($ph['series'] as $s) {
      $p = $s['teams'];
      sort($p);
      $pairs[$p[0] . '-' . ($p[1] ?? 0)] = true;
    }

    $expected_pairs = $p_teams > 1 ? $p_teams * ($p_teams - 1) / 2 : 0;
    $complete_rr = $p_teams >= 3 && $expected_pairs > 0 && count($pairs) / $expected_pairs >= 0.9;
    $looks_group =
      ($n >= 5 && tb_progression_is_group($ph['series']))
      || ($tied >= 2 && !tb_is_elim_phase($ph['rounds']))
      || ($n >= 5 && $tied >= 2 && $tied / $n >= 0.2)
      || ($tied === $n && $n >= 1)
      || ($genuine >= 1 && $n - $genuine < 2)
      || $complete_rr;

    if ($looks_group) {
      $ph['is_elim']    = false;
      $ph['phase_type'] = 'group';
    }
  }
  unset($ph);

  // Merge consecutive group stages if needed
  $kept = [];
  $last_group_k = null;
  $last_group_tms = [];
  foreach ($phases as $ph) {
    $ptype = $ph['phase_type'] ?? (($ph['is_elim'] ?? false) ? 'bracket' : 'group');
    if ($ptype !== 'group') { $kept[] = $ph; continue; }
    $tms = tb_unique_teams($ph['series']);

    $narrowing = !array_diff($tms, $last_group_tms) && count($tms) <= 0.7 * count($last_group_tms);
    if ($last_group_k !== null && !$narrowing && empty($ph['no_merge'])) {
      $kept[$last_group_k]['series'] = array_merge($kept[$last_group_k]['series'], $ph['series']);
      usort($kept[$last_group_k]['series'], fn($a, $b) => $a['start'] <=> $b['start']);
      $kept[$last_group_k]['rounds'] = tb_temporal_rounds($kept[$last_group_k]['series']);
      $last_group_tms = tb_unique_teams($kept[$last_group_k]['series']);
      continue;
    }
    $kept[] = $ph;
    $last_group_k = count($kept) - 1;
    $last_group_tms = $tms;
  }
  $phases = $kept;

  // round-robin group plus playoff tail.
  $has_real_bracket = false;
  foreach ($phases as $ph) {
    if (($ph['is_elim'] ?? false) && count($ph['series']) >= 4) {
      $has_real_bracket = true;
      break;
    }
  }
  if (!$has_real_bracket && ($tail = tb_trailing_playoff($series, $teams))) {
    $tail_keys = array_flip(array_column($tail, 'key'));
    $group_ser = array_values(array_filter($series, fn($s) => !isset($tail_keys[$s['key']])));
    if (count($group_ser) >= count($tail)) {
      $phases = [
        [
          'series' => $group_ser,
          'rounds' => tb_temporal_rounds($group_ser),
          'is_elim' => false,
          'phase_type' => 'group',
        ],
        [
          'series' => $tail,
          'rounds' => tb_temporal_rounds($tail),
          'is_elim' => true,
          'phase_type' => 'bracket',
        ],
      ];
    }
  }
  return $phases;
}

function tb_split_division_events(array $event, array $analyzed, array $teams): array {
  $br_stage = null;
  foreach ($analyzed['stages'] as $st) {
    if (($st['phase_type'] ?? '') === 'bracket' && count($st['series']) >= 12) {
      $br_stage = $st;
    }
  }
  if ($br_stage === null) {
    return [$analyzed];
  }

  $reach = array_flip(tb_gf_reachable($br_stage['series']));
  $in = array_values(array_filter($br_stage['series'], fn($s) => isset($reach[$s['key']])));
  $out = array_values(array_filter($br_stage['series'], fn($s) => !isset($reach[$s['key']])));
  if (count($in) < 6 || count($out) < 6) {
    return [$analyzed];
  }

  $out_team_set = array_flip(tb_unique_teams($out));
  $play_in = [];
  $in = array_values(array_filter($in, function ($s) use ($out_team_set, &$play_in) {
    $l = tb_loser($s);
    if ($l !== null && isset($out_team_set[$l])) {
      $play_in[] = $s;
      return false;
    }

    return true;
  }));

  $p_in  = tb_analyze_phase([
    'rounds' => tb_temporal_rounds($in),
    'series' => $in,
  ], $teams, 'bracket_main_event', true, true);

  $p_out = tb_analyze_phase([
    'rounds' => tb_temporal_rounds($out),
    'series' => $out,
  ], $teams, 'bracket_main_event', true, true);

  if (($p_in['type'] ?? '') !== 'playoff' || ($p_out['type'] ?? '') !== 'playoff'
    || empty($p_in['bracket']['grand_final']) || empty($p_out['bracket']['grand_final'])
    || !tb_is_valid_playoff($p_in['bracket']) || !tb_is_valid_playoff($p_out['bracket'])) {
    return [$analyzed];
  }
  $p_in['phase_type'] = 'bracket';
  $p_out['phase_type'] = 'bracket';

  $d1_br = array_flip(tb_unique_teams($in));
  $d2_br = array_flip(tb_unique_teams($out));
  $team_div = [];
  foreach ($analyzed['stages'] as $st) {
    if (($st['type'] ?? '') !== 'group_stage') continue;
    foreach ($st['groups'] as $g) {
      $c1 = 0; $c2 = 0;
      foreach ($g['teams'] ?? [] as $t) {
        if (isset($d1_br[$t])) {
          $c1++;
        }
        if (isset($d2_br[$t])) {
          $c2++;
        }
      }
      $gd = $c1 >= $c2 ? 1 : 2;
      foreach ($g['teams'] ?? [] as $t) {
        $team_div[$t] = $gd;
      }
    }
  }
  $ser_div = function (array $s) use ($team_div) {
    $c1 = 0; $c2 = 0;
    foreach ($s['teams'] as $t) {
      $d = $team_div[$t] ?? 0;
      if ($d === 1) {
        $c1++;
      } elseif ($d === 2) {
        $c2++;
      }
    }
    return $c1 >= $c2 ? 1 : 2;
  };

  $grp = [1 => [], 2 => []]; $seed = [1 => [], 2 => []];
  foreach ($analyzed['stages'] as $st) {
    if (($st['phase_type'] ?? '') === 'bracket') {
      continue;
    }

    $bucket = ($st['type'] ?? '') === 'group_stage' ? $grp : $seed;
    foreach ($st['series'] as $s) {
      $bucket[$ser_div($s)][] = $s;
    }

    if (($st['type'] ?? '') === 'group_stage') {
      $grp = $bucket;
    } else {
      $seed = $bucket;
    }
  }
  foreach ($play_in as $s) {
    $seed[$ser_div($s)][] = $s;
  }

  $mk = function (string $suffix, array $gser, array $sser, array $br_stg) use ($event, $teams) {
    $name   = $event['name'] . ', ' . $suffix;
    $stages = [];
    if ($gser) {
      $g_stage = tb_analyze_phase(
        ['rounds' => tb_temporal_rounds($gser), 'series' => $gser],
        $teams,
        'bracket_group_stage',
        false,
        false,
      );

      if (!empty($g_stage['decider']['series'])) {
        $sser = array_merge($sser, $g_stage['decider']['series']);
        $g_stage['decider'] = null;
      }
      $stages[] = $g_stage;
    }
    if ($sser) {
      $sser = tb_dedup_series($sser);
      $stages[] = [
        'type' => 'seeding',
        'name' => 'bracket_decider',
        'phase_type' => 'seeding',
        'series' => $sser,
      ];
    }
    
    $stages[] = $br_stg;
    $all = array_merge($gser, $sser, $br_stg['series']);
    $stages = tb_color_group_stages($stages, $teams, $all);
    return [
      'name' => $name,
      'report' => $event['report'],
      'stages' => $stages,
      'team_cards' => array_intersect_key($teams, array_flip(tb_unique_teams($all))),
    ];
  };
  $div = locale_string('bracket_division');
  return [
    $mk("$div 1", $grp[1], $seed[1], $p_in),
    $mk("$div 2", $grp[2], $seed[2], $p_out),
  ];
}
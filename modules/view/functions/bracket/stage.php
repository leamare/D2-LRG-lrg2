<?php

function tb_analyze_phase(array $phase, array $teams, string $name, bool $is_elim, bool $allow_peel = true): array {
  $series = $phase['series'];
  $rounds = $phase['rounds'];

  if ($is_elim) {
    $bracket = tb_build_bracket($rounds, $teams, $phase['lb_seeds'] ?? [], $allow_peel);

    $placed = 0;
    foreach (($bracket['ub_rounds'] ?? []) as $r) $placed += count($r['series']);
    foreach (($bracket['lb_rounds'] ?? []) as $r) $placed += count($r['series']);
    if ($bracket['grand_final']) $placed += count($bracket['grand_final']['series']);

    $min_placed = (tb_count_genuine_ties($series) === 0 && count($series) <= 2) ? 1 : 2;
    if ($placed >= $min_placed) {
      return [
        'type'    => 'playoff',
        'name'    => $name,
        'bracket' => $bracket,
        'series'  => $series,
      ];
    }
    $is_elim = false;
  }

  $snapshots = [];
  $cum_series = [];
  foreach ($rounds as $rnd) {
    $cum_series = array_merge($cum_series, $rnd['series']);
    $snapshots[] = tb_find_groups($cum_series);
  }

  $clusters   = $snapshots ? end($snapshots) : tb_find_groups($series);
  $count_freq  = [];
  $last_by_cnt  = [];

  foreach ($snapshots as $comps) {
    $n = count($comps);
    if ($n <= 1) continue;
    $sizes   = array_map('count', $comps);
    $min_size = min($sizes);
    $max_size = max($sizes);
    $balance = $max_size > 0 ? array_sum($sizes) / ($n * $max_size) : 0;
    if ($min_size >= 3 && $balance >= 0.7) {
      $count_freq[$n] = ($count_freq[$n] ?? 0) + 1;
      $last_by_cnt[$n] = $comps;
    }
  }

  if ($count_freq) {
    $cover = fn($cnt) => array_sum(array_map('count', $last_by_cnt[$cnt]));
    $best_count = array_key_first($count_freq);
    foreach ($count_freq as $cnt => $freq) {
      $bf = $count_freq[$best_count];
      if ($freq > $bf || 
        ($freq === $bf && $cover($cnt) > $cover($best_count)) || 
        ($freq === $bf && $cover($cnt) === $cover($best_count) && $cnt < $best_count)
      ) {
        $best_count = $cnt;
      }
    }
    $clusters = $last_by_cnt[$best_count];
  }

  if (count($clusters) > 1) {
    $t2g = [];
    foreach ($clusters as $gi => $cts) {
      foreach ($cts as $t) {
        $t2g[$t] = $gi;
      }
    }

    $cross = 0;
    $within = 0;
    $seen_pk = [];
    foreach ($series as $s) {
      $p = $s['teams']; sort($p); $pk = $p[0] . '-' . $p[1];
      if (isset($seen_pk[$pk])) continue;

      $seen_pk[$pk] = true;
      if (($t2g[$s['teams'][0]] ?? -1) !== ($t2g[$s['teams'][1]] ?? -2)) {
        $cross++;
      } else {
        $within++;
      }
    }

    if ($within + $cross > 0 && $cross / ($within + $cross) > 0.15) {
      $clusters = [array_values(tb_unique_teams($series))];
    }
  }

  if ((int)($phase['groups_hint'] ?? 0) === 1) {
    $clusters = [array_values(tb_unique_teams($series))];
  }

  $is_multi = count($clusters) > 1;
  $team_to_group = [];
  foreach ($clusters as $gi => $cluster_teams) {
    foreach ($cluster_teams as $t) {
      $team_to_group[$t] = $gi;
    }
  }

  $group_series = [];
  $cross_series = [];
  foreach ($series as $s) {
    $gs = array_unique(array_map(fn($t) => $team_to_group[$t] ?? -1, $s['teams']));
    if (count($gs) > 1) {
      $cross_series[] = $s;
    } else {
      $group_series[] = $s;
    }
  }

  usort($group_series, fn($a, $b) => $a['start'] <=> $b['start']);
  $seen_pairs = [];
  $rr_series = [];

  $tiebreak_series  = [];

  foreach ($group_series as $s) {
    $pair = $s['teams'];
    sort($pair);
    $key = $pair[0] . '-' . $pair[1];

    if (isset($seen_pairs[$key])) {
      if ($s['winner'] === null) continue;
      $tiebreak_series[] = $s;
    } else {
      $seen_pairs[$key] = true;
      $rr_series[]      = $s;
    }
  }

  $seed_bracket = [];
  if (count($tiebreak_series) >= 3) {
    $tb_clusters = [];
    foreach ($tiebreak_series as $s) {
      $li = count($tb_clusters) - 1;
      if ($li >= 0 && $s['start'] - end($tb_clusters[$li])['end'] < 50400) {
        $tb_clusters[$li][] = $s;
      } else {
        $tb_clusters[] = [$s];
      }
    }

    $take = [];
    for ($ci = count($tb_clusters) - 1; $ci >= 0; $ci--) {
      $cand = array_merge($tb_clusters[$ci], $take);
      $pairs  = [];
      $losses = [];
      $ok = true;

      foreach ($cand as $s) {
        $p = $s['teams'];
        sort($p);
        $pk = implode('-', $p);
        if (isset($pairs[$pk])) { $ok = false; break; }
        $pairs[$pk] = true;
        $l = tb_loser($s);
        if ($l !== null) {
          if (isset($losses[$l])) { $ok = false; break; }
          $losses[$l] = true;
        }
      }

      if (!$ok) break;
      $take = $cand;
    }

    $apps = array_count_values(array_merge(...array_map(fn($s) => $s['teams'], $take ?: [['teams' => []]])));
    $is_ladder = $apps && max($apps) >= 2;
    if (count($take) >= 4 && count(tb_unique_teams($take)) >= 4 && $is_ladder) {
      $seed_bracket = $take;
      $take_keys = array_flip(array_column($take, 'key'));
      $tiebreak_series = array_values(array_filter($tiebreak_series, fn($s) => !isset($take_keys[$s['key']])));
    }
  }

  $playoff_bracket = [];
  if (count($tiebreak_series) >= 4 && count(tb_unique_teams($tiebreak_series)) >= 4) {
    $tb_rounds = tb_temporal_rounds($tiebreak_series);

    $has_decider = (bool)array_filter($tiebreak_series, fn($s) => $s['bo'] >= 3);
    $connected = count(tb_find_groups($tiebreak_series)) === 1;
    if ($connected && $has_decider && tb_is_elim_phase($tb_rounds)
      && !tb_progression_is_group($tiebreak_series)) {
      $playoff_bracket = $tiebreak_series;
      $tiebreak_series = [];

      $p_start  = (int)min(array_column($playoff_bracket, 'start'));
      $p_teams  = array_flip(tb_unique_teams($playoff_bracket));
      $keep_rr  = [];
      foreach ($rr_series as $s) {
        if ($s['start'] >= $p_start
          && isset($p_teams[$s['teams'][0]], $p_teams[$s['teams'][1] ?? 0])) {
          $playoff_bracket[] = $s;
        } else {
          $keep_rr[] = $s;
        }
      }
      $rr_series = $keep_rr;
    }
  }

  $groups = [];
  foreach ($clusters as $gi => $cluster_teams) {
    $gs = array_values(array_filter($rr_series, fn($s) => empty(array_diff($s['teams'], $cluster_teams))));

    $tbs = array_values(array_filter($tiebreak_series, fn($s) => empty(array_diff($s['teams'], $cluster_teams))));
    $group = tb_build_group($is_multi ? 'bracket_group ' . chr(65 + $gi) : $name, $gs, $rounds);

    $fmt_hint = [
      'swiss' => 'swiss',
      'short_swiss' => 'short_swiss',
      'rr' => 'round_robin',
      'round_robin' => 'round_robin',
    ][$phase['format_hint'] ?? ''] ?? '';

    if ($fmt_hint) {
      $group['format'] = $fmt_hint;
    }
    $group['tiebreakers'] = $tbs;

    $group['grid'] = in_array($group['format'], ['round_robin', 'mixed'])
      ? tb_rr_grid($gs, $cluster_teams)
      : null;
    
    $groups[] = $group;
  }

  $decider = $cross_series ? ['series' => $cross_series] : null;

  return [
    'type'            => 'group_stage',
    'name'            => $name,
    'groups'          => $groups,
    'decider'         => $decider,
    'tiebreakers'     => $tiebreak_series,
    'seeding_bracket' => $seed_bracket,
    'playoff_bracket' => $playoff_bracket,
    'series'          => $series,
  ];
}

function tb_color_group_stages(array $stages, array $teams, array $series): array {
  foreach ($stages as $si => &$st) {
    if (($st['type'] ?? '') !== 'seeding') continue;

    $br_teams = [];
    for ($j = $si + 1; $j < count($stages); $j++) {
      if (($stages[$j]['phase_type'] ?? '') === 'bracket') {
        foreach ($stages[$j]['series'] as $s) {
          foreach ($s['teams'] as $t) {
            $br_teams[$t] = true;
          }
        }
      }
    }

    $losers = [];
    foreach ($st['series'] as $s) {
      $l = tb_loser($s);
      if ($l !== null) {
        $losers[$l] = true;
      }
    }

    if (!$losers) continue;

    $elim = 0;
    foreach (array_keys($losers) as $t) {
      if (!isset($br_teams[$t])) {
        $elim++;
      }
    }
    
    if ($elim >= count($losers) * 0.6) {
      $st['name'] = 'bracket_elim_round';
    }
  }
  unset($st);

  $er_team_set = [];
  $ub_team_set = [];
  $lb_team_set = [];
  foreach ($stages as $st) {
    if (in_array($st['phase_type'] ?? '', ['elimination_round', 'seeding_bracket'], true)) {
      foreach ($st['series'] as $ser) {
        foreach ($ser['teams'] as $t) {
          $er_team_set[$t] = true;
        }
      }
    }
    if (($st['phase_type'] ?? '') === 'bracket' && !empty($st['bracket'])) {
      $b = $st['bracket'];
      $first_side = [];
      $first_ts   = [];
      foreach (['ub_rounds' => 'ub', 'lb_rounds' => 'lb'] as $rk => $side) {
        foreach ($b[$rk] ?? [] as $round) {
          foreach ($round['series'] as $ser) {
            foreach ($ser['teams'] as $t) {
              if (!isset($first_ts[$t]) || $ser['start'] < $first_ts[$t]) {
                $first_ts[$t]   = $ser['start'];
                $first_side[$t] = $side;
              }
            }
          }
        }
      }

      foreach ($first_side as $t => $side) {
        if ($side === 'ub') {
          $ub_team_set[$t] = true;
        } else {
          $lb_team_set[$t] = true;
        }
      }
    }
  }

  $name_of  = fn(int $t): string => strtolower(trim($teams[$t]['name'] ?? $teams[$t]['tag'] ?? (string)$t));
  $by_name  = [];
  foreach (tb_unique_teams($series) as $t) {
    $by_name[$name_of($t)][] = $t;
  }
  $expand = function(array $set) use ($name_of, $by_name): array {
    foreach (array_keys($set) as $t) {
      foreach ($by_name[$name_of($t)] ?? [] as $sib) {
        $set[$sib] = true;
      }
    }
    return $set;
  };
  $er_team_set = $expand($er_team_set);
  $ub_team_set = $expand($ub_team_set);
  $lb_team_set = $expand($lb_team_set);

  $er_team_list = array_keys($er_team_set);
  $ub_team_list = array_keys($ub_team_set);
  $lb_team_list = array_keys($lb_team_set);

  $tier_of = function(int $t) use ($ub_team_set, $lb_team_set, $er_team_set): int {
    if (isset($ub_team_set[$t])) return 0;
    if (isset($lb_team_set[$t])) return 1;
    if (isset($er_team_set[$t])) return 2;
    return 3;
  };

  foreach ($stages as $si => &$st) {
    if ($st['type'] !== 'group_stage') continue;

    $next_group = null;
    for ($j = $si + 1; $j < count($stages); $j++) {
      $tj = $stages[$j]['type'] ?? '';
      if ($tj === 'group_stage') {
        $next_group = $stages[$j];
        break;
      }
      if ($tj === 'playoff') {
        break;
      }
    }

    if ($next_group) {
      $adv_set = [];
      foreach ($next_group['groups'] ?? [] as $g) {
        foreach ($g['teams'] ?? [] as $t) {
          $adv_set[$t] = true;
        }
      }
      $adv_set = $expand($adv_set);
      $st['ub_teams'] = array_keys($adv_set);
      $st['lb_teams'] = [];
      $st['er_teams'] = [];
      $adv_rank = fn(int $t) => isset($adv_set[$t]) ? 1 : 0;
      foreach ($st['groups'] as &$g) {
        usort($g['standings'], fn($x, $y) =>
          [2 * $y['w'] + $y['d'], -$y['l'], $adv_rank($y['team']), $y['mw'] - $y['ml'], $y['mw']] <=>
          [2 * $x['w'] + $x['d'], -$x['l'], $adv_rank($x['team']), $x['mw'] - $x['ml'], $x['mw']]
        );
      }
      unset($g);
    } else {
      $st['er_teams'] = $er_team_list;
      $st['ub_teams'] = $ub_team_list;
      $st['lb_teams'] = $lb_team_list;
      if ($ub_team_list || $lb_team_list) {
        foreach ($st['groups'] as &$g) {
          usort($g['standings'], fn($x, $y) =>
            [2 * $y['w'] + $y['d'], -$y['l'], -$tier_of($y['team']), $y['mw'] - $y['ml'], $y['mw']] <=>
            [2 * $x['w'] + $x['d'], -$x['l'], -$tier_of($x['team']), $x['mw'] - $x['ml'], $x['mw']]
          );
        }
        unset($g);
      }
    }

    $adv_all = array_flip(array_merge($st['ub_teams'], $st['lb_teams'], $st['er_teams']));
    $pts_of  = fn($r) => 2 * $r['w'] + $r['d'];
    $dq = [];
    foreach ($st['groups'] as $g) {
      $worst = null;
      foreach ($g['standings'] as $r) {
        if (isset($adv_all[$r['team']])) {
          $p = $pts_of($r);
          if ($worst === null || $p < $worst) {
            $worst = $p;
          }
        }
      }
      if ($worst === null) continue;

      foreach ($g['standings'] as $r) {
        if (!isset($adv_all[$r['team']]) && $pts_of($r) > $worst) {
          $dq[] = $r['team'];
        }
      }
    }
    $st['dq_teams'] = $dq;

    $ub_f = array_flip($st['ub_teams']); $lb_f = array_flip($st['lb_teams']); $er_f = array_flip($st['er_teams']);
    $st_tier = function (int $t) use ($ub_f, $lb_f, $er_f): int {
      if (isset($ub_f[$t])) return 0;
      if (isset($lb_f[$t])) return 1;
      if (isset($er_f[$t])) return 2;
      return 3;
    };
    foreach ($st['groups'] as &$g) {
      $g['standings'] = tb_apply_tiebreakers($g['standings'], $g['tiebreakers'] ?? [], $st_tier);
    }
    unset($g);
  }
  unset($st);

  return $stages;
}

function tb_analyze_interest(array $event, array $teams, string $stage_name = 'bracket_interest'): array {
  $series  = $event['series'];
  $team_ids = tb_unique_teams($series);

  $group = [
    'name'          => 'bracket_standings',
    'format'        => 'swiss',
    'teams'         => $team_ids,
    'standings'     => tb_group_standings($series, $team_ids),
    'round_results' => [],
    'grid'          => null,
    'tiebreakers'   => [],
  ];

  $months = [];
  foreach ($series as $s) {
    $months[date('Y-m', $s['start'])] = true;
  }
  ksort($months);
  
  $months = array_keys($months);
  $form   = [];

  foreach ($series as $s) {
    [$a, $b] = $s['teams'];
    $mk = date('Y-m', $s['start']);
    $sa = (int)($s['score'][$a] ?? 0);
    $sb = (int)($s['score'][$b] ?? 0);

    foreach ([$a, $b] as $t) {
      $form[$t][$mk] ??= ['w' => 0, 'l' => 0, 'd' => 0];
    }

    if ($sa === $sb) {
      $form[$a][$mk]['d']++;
      $form[$b][$mk]['d']++;
    } else if ($sa > $sb) {
      $form[$a][$mk]['w']++;
      $form[$b][$mk]['l']++;
    } else {
      $form[$b][$mk]['w']++;
      $form[$a][$mk]['l']++;
    }
  }

  $stage = [
    'type'        => 'group_stage',
    'name'        => $stage_name,
    'phase_type'  => 'group',
    'groups'      => [$group],
    'decider'     => null,
    'tiebreakers' => [],
    'er_teams'    => [],
    'ub_teams'    => [],
    'lb_teams'    => [],
    'form_months' => $months,
    'form'        => $form,
  ];

  return [
    'name'       => $event['name'],
    'report'     => $event['report'],
    'stages'     => [$stage],
    'team_cards' => array_intersect_key($teams, array_flip($team_ids)),
  ];
}

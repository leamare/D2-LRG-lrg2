<?php

// True when the last round of $group_rounds replays a pairing from an earlier round.
function tb_last_round_is_rematch(array $group_rounds): bool {
  $earlier_pairs = [];
  foreach (array_slice($group_rounds, 0, count($group_rounds) - 1) as $gr) {
    foreach ($gr['series'] as $gs) {
      $earlier_pairs[tb_pair_key($gs['teams'])] = true;
    }
  }

  foreach (end($group_rounds)['series'] as $s) {
    if (isset($earlier_pairs[tb_pair_key($s['teams'])])) {
      return true;
    }
  }

  return false;
}

function tb_detect_phases(array $series): array {
  $rr_split = tb_rr_playoff_split($series);
  if ($rr_split) {
    return [
      tb_phase(tb_temporal_rounds($rr_split['group']), 'group', $rr_split['group']),
      tb_phase(tb_temporal_rounds($rr_split['playoff']), 'bracket', $rr_split['playoff']),
    ];
  }

  $rounds = tb_temporal_rounds($series);
  $n = count($rounds);

  if ($n === 0) {
    return [];
  }

  if ($n === 1) {
    $all_series = $rounds[0]['series'];

    $n_groups = count(tb_find_groups($all_series));
    $is_elim  = $n_groups <= 1 || tb_is_elim_phase($rounds);
    return [tb_phase($rounds, $is_elim ? 'bracket' : 'group', $all_series)];
  }

  $wc_front = tb_wildcard_front($rounds);
  if ($wc_front !== null) {
    $wc_rounds = array_slice($rounds, 0, $wc_front);
    $rest = tb_rounds_series(array_slice($rounds, $wc_front));
    $rest_phases = tb_detect_phases($rest);

    foreach ($rest_phases as &$rp) {
      if (empty($rp['is_elim'])) {
        $rp['no_merge'] = true;
        break;
      }
    }
    unset($rp);

    return array_merge(
      [tb_phase($wc_rounds, 'group') + ['no_merge' => true]],
      $rest_phases
    );
  }

  $front_end = tb_group_stage_front($rounds);
  if ($front_end === null && count(tb_find_groups($series)) <= 1 && tb_is_elim_phase($rounds)) {
    $tie_tail = tb_playoff_tail($rounds);
    if ($tie_tail === null) {
      return [tb_phase($rounds, 'bracket', $series)];
    }

    return array_merge(
      tb_split_group_rounds(array_slice($rounds, 0, $tie_tail)),
      [tb_phase(array_slice($rounds, $tie_tail), 'bracket')]
    );
  }

  $candidates = tb_boundary_candidates($rounds, $n);

  $playoff_start = tb_pick_playoff_start($rounds, $candidates);

  if ($playoff_start === null) {
    $all_series = tb_rounds_series($rounds);
    $clusters  = tb_find_groups($all_series);
    if (count($clusters) <= 1) {
      $unique_teams = count(array_unique(array_merge(...array_map(fn($s) => $s['teams'], $all_series))));
      $avg_app      = $unique_teams > 0 ? (count($all_series) * 2.0 / $unique_teams) : 0;

      $bracket = tb_is_elim_phase($rounds)
        || (!tb_is_group_stage($rounds) && $avg_app < 3.0);
      if ($bracket) {
        return [tb_phase($rounds, 'bracket', $all_series)];
      }
    }
  }

  $regroup_block = tb_find_regroup_block($rounds);
  if ($regroup_block !== null) {
    $playoff_start = $regroup_block[1] + 1;
    if ($playoff_start >= count($rounds)) $playoff_start = null;
  }

  if ($regroup_block === null && $playoff_start === null
    && $front_end !== null && $front_end + 1 < count($rounds)) {
    $playoff_start = $front_end + 1;
  }

  // Second group stage that adds a fresh cohort after qualifiers (riyadh_2023):
  // when >=4 never-seen teams enter mid-event with carryover, defer the boundary
  // to the tie tail instead of swallowing the stage into the bracket.
  $seen = []; $cohort_at = null;
  foreach ($rounds as $i => $r) {
    $rt = []; $fresh = 0;
    foreach ($r['series'] as $s) foreach ($s['teams'] as $t) {
      $rt[$t] = true;
      if (!isset($seen[$t])) $fresh++;
    }
    foreach (array_keys($rt) as $t) $seen[$t] = true;
    if ($i >= 2 && $fresh >= 4 && $fresh >= count($rt) * 0.5) {
      $before = tb_unique_teams(tb_rounds_series(array_slice($rounds, 0, $i)));
      $after  = tb_unique_teams(tb_rounds_series(array_slice($rounds, $i)));
      if (count(array_intersect($before, $after)) >= 4
        && count(array_diff($before, $after)) >= count($before) * 0.15) { $cohort_at = $i; break; }
    }
  }
  if ($cohort_at !== null && ($playoff_start === null || $playoff_start <= $cohort_at)) {
    $tail = tb_playoff_tail($rounds);
    if ($tail !== null && $tail > $cohort_at && $tail < count($rounds)) $playoff_start = $tail;
  }

  $group_rounds   = $playoff_start !== null ? array_slice($rounds, 0, $playoff_start) : $rounds;
  $playoff_rounds = $playoff_start !== null ? array_slice($rounds, $playoff_start)     : [];

  if ($playoff_rounds) {
    $group_series_all = tb_rounds_series($group_rounds);
    $g_stats = tb_round_stats(['series' => $group_series_all, 'start' => 0, 'end' => 0]);
    if (count($group_series_all) <= 2 && $g_stats['maxapp'] <= 1
      && count(tb_find_groups($group_series_all)) <= 1
    ) {
      $playoff_rounds = array_merge($group_rounds, $playoff_rounds);
      $group_rounds   = [];
    }
  }

  $er_rounds = [];
  if ($playoff_rounds && count($group_rounds) > 1) {
    $playoff_team_set = tb_unique_teams(
      tb_rounds_series($playoff_rounds)
    );
    $er_team_seen = [];
    while (count($group_rounds) > 1) {
      $last_round  = end($group_rounds);
      $last_series = $last_round['series'];
      if (count($last_series) < 1) break;

      $rstats = tb_round_stats($last_round);
      if ($rstats['maxapp'] > 1) break;

      $round_teams = tb_unique_teams($last_series);
      if (array_intersect($round_teams, $er_team_seen)) break;
      if (tb_last_round_is_rematch($group_rounds)) break;

      $winners = []; $losers = [];
      foreach ($last_series as $s) {
        if ($s['winner']) {
          $winners[] = $s['winner'];
          foreach ($s['teams'] as $t) {
            if ($t !== $s['winner']) $losers[] = $t;
          }
        }
      }
      if (!$winners || !$losers) break;

      $w_in = count(array_intersect($winners, $playoff_team_set)) / count($winners);
      $l_in = count(array_intersect($losers,  $playoff_team_set)) / count($losers);
      if ($w_in >= 0.8 && $l_in <= 0.1) {
        array_unshift($er_rounds, array_pop($group_rounds));
        $er_team_seen = array_merge($er_team_seen, $round_teams);
      } else {
        break;
      }
    }
  }

  if ($playoff_rounds && !$er_rounds && count($group_rounds) > 1) {
    $playoff_team_set_x = tb_unique_teams(
      tb_rounds_series($playoff_rounds)
    );
    $tentative = [];
    while (count($group_rounds) > 1) {
      $last_round  = end($group_rounds);
      $last_series = $last_round['series'];
      if (count($last_series) < 1) break;
      if (tb_round_stats($last_round)['maxapp'] > 1) break;
      if (tb_last_round_is_rematch($group_rounds)) break;

      $tent_teams = $tentative
        ? tb_unique_teams(tb_rounds_series($tentative))
        : [];
      $future  = array_merge($playoff_team_set_x, $tent_teams);
      $winners = array_filter(array_column($last_series, 'winner'));
      if (!$winners) break;
      // Every winner of this round must already be a confirmed member of the
      // playoff/decider field
      if (array_diff($winners, $future)) break;

      array_unshift($tentative, array_pop($group_rounds));
    }
    if ($tentative) {
      $remain_series = $group_rounds
        ? tb_rounds_series($group_rounds)
        : [];
      $core_groups = $remain_series ? tb_core_groups($remain_series) : [];
      $keep_from   = count($tentative);
      if (count($core_groups) >= 2) {
        $team_comp = [];
        foreach ($core_groups as $gi => $g_teams) {
          foreach ($g_teams as $t) $team_comp[$t] = $gi;
        }
        for ($i = count($tentative) - 1; $i >= 0; $i--) {
          $cross = 0;
          $tot   = 0;
          foreach ($tentative[$i]['series'] as $s) {
            $tot++;
            $ca = $team_comp[$s['teams'][0]] ?? -1;
            $cb = $team_comp[$s['teams'][1] ?? 0] ?? -2;
            if ($ca < 0 || $cb < 0 || $ca !== $cb) $cross++;
          }
          if ($tot > 0 && $cross / $tot >= 0.8) $keep_from = $i;
          else break;
        }
      }
      for ($i = 0; $i < $keep_from; $i++) $group_rounds[] = $tentative[$i];
      $playoff_rounds = array_merge(array_slice($tentative, $keep_from), $playoff_rounds);
    }
  }

  $wildcard_phase = null;
  if (count($group_rounds) > 1 && count($group_rounds[0]['series']) <= 2) {
    $later_teams = tb_unique_teams(array_merge(
      tb_rounds_series(array_slice($group_rounds, 1)),
      tb_rounds_series($er_rounds),
      tb_rounds_series($playoff_rounds)
    ));
    $wc_ok = true;
    foreach ($group_rounds[0]['series'] as $s) {
      $w = $s['winner'];
      $l = tb_loser($s);
      if (!$w || !in_array($w, $later_teams) || ($l !== null && in_array($l, $later_teams))) {
        $wc_ok = false;
        break;
      }
    }
    if ($wc_ok) {
      $wc_round       = array_shift($group_rounds);
      $wildcard_phase = tb_phase([$wc_round], 'wildcard', $wc_round['series']);
    }
  }

  $phases = tb_split_group_rounds($group_rounds);
  if ($wildcard_phase) array_unshift($phases, $wildcard_phase);

  if ($er_rounds) {
    $phases[] = tb_phase($er_rounds, 'elimination_round');
  }

  if ($playoff_rounds) {
    $playoff_field_all = tb_unique_teams(
      tb_rounds_series($playoff_rounds)
    );
    $seeding_prefix   = [];
    $seeding_team_seen = [];
    while ($playoff_rounds) {
      $first_rnd    = $playoff_rounds[0];
      $first_teams  = array_unique(array_merge(...array_map(fn($s) => $s['teams'], $first_rnd['series'])));

      if (array_intersect($first_teams, $seeding_team_seen)) break;
      $later_slice  = array_slice($playoff_rounds, 1);
      $later_series = $later_slice ? tb_rounds_series($later_slice) : [];
      $later_teams  = $later_series ? array_unique(array_merge(...array_map(fn($s) => $s['teams'], $later_series))) : [];
      $all_advance  = !empty($first_teams) && empty(array_diff($first_teams, $later_teams));
      $is_majority  = !empty($later_teams) && (count($first_teams) / count($later_teams) >= 0.6);
      if ($all_advance && !$is_majority) {
        $seeding_prefix[]  = array_shift($playoff_rounds);
        $seeding_team_seen  = array_merge($seeding_team_seen, $first_teams);
      } else {
        break;
      }
    }

    $swept_refs = [];
    if ($seeding_prefix && $group_rounds) {
      $remain_series = tb_rounds_series($group_rounds);
      $core_groups   = tb_core_groups($remain_series);
      if (count($core_groups) >= 2) {
        $team_comp = [];
        foreach ($core_groups as $gi => $g_teams) {
          foreach ($g_teams as $t) $team_comp[$t] = $gi;
        }
        $pair_count = [];
        foreach ($remain_series as $s) {
          $pk = tb_pair_key($s['teams']);
          $pair_count[$pk] = ($pair_count[$pk] ?? 0) + 1;
        }
        $seed_start = min(array_column($seeding_prefix, 'start'));
        $seed_end   = max(array_column($seeding_prefix, 'end'));
        foreach ($group_rounds as $ri => $gr) {
          foreach ($gr['series'] as $si => $s) {
            if (($pair_count[tb_pair_key($s['teams'])] ?? 0) !== 1) continue;
            $ca = $team_comp[$s['teams'][0]] ?? -1;
            $cb = $team_comp[$s['teams'][1] ?? 0] ?? -2;
            if ($ca >= 0 && $cb >= 0 && $ca === $cb) continue;
            if (array_diff($s['teams'], $playoff_field_all)) continue;
            if (array_intersect($s['teams'], $seeding_team_seen)) continue;
            if ($s['end'] < $seed_start - 172800 || $s['start'] > $seed_end + 172800) continue;
            $swept_refs[] = [$ri, $si];
            $seeding_team_seen = array_merge($seeding_team_seen, $s['teams']);
          }
        }
      }
    }

    $swept_series = [];
    if ($seeding_prefix) {
      $coverage = count(array_unique($seeding_team_seen)) / max(1, count($playoff_field_all));
      if ($coverage < 0.85) {
        $playoff_rounds = array_merge($seeding_prefix, $playoff_rounds);
        $seeding_prefix = [];
        $swept_refs     = [];
      } else {
        foreach ($swept_refs as [$ri, $si]) {
          $swept_series[] = $group_rounds[$ri]['series'][$si];
          unset($group_rounds[$ri]['series'][$si]);
        }
        foreach ($group_rounds as $ri => $gr) {
          $group_rounds[$ri]['series'] = array_values($gr['series']);
        }
        $group_rounds = array_values(array_filter($group_rounds, fn($r) => $r['series']));

        $phases = tb_split_group_rounds($group_rounds);
        if ($wildcard_phase) array_unshift($phases, $wildcard_phase);
        if ($er_rounds) {
          $phases[] = tb_phase($er_rounds, 'elimination_round');
        }
      }
    }

    $seeding_all = $seeding_prefix;
    if ($swept_series) {
      $seeding_all[] = tb_make_round($swept_series);
      usort($seeding_all, fn($a, $b) => $a['start'] <=> $b['start']);
    }
    if ($seeding_all) {
      $seed_series = tb_rounds_series($seeding_all);
      usort($seed_series, fn($a, $b) => $a['start'] <=> $b['start']);

      if (count($seed_series) === 1 && tb_is_tie($seed_series[0])) {
        $playoff_rounds = array_merge($seeding_all, $playoff_rounds);
        usort($playoff_rounds, fn($a, $b) => $a['start'] <=> $b['start']);
      } else {
        $phases[] = tb_phase($seeding_all, 'seeding', $seed_series);
      }
    }
  }

  $second_group_phases = [];
  if (count($playoff_rounds) >= 4) {
    $field  = tb_unique_teams(tb_rounds_series($playoff_rounds));
    $n_field = count($field);
    $rr_rounds  = [];
    $seen_pairs = [];
    foreach ($playoff_rounds as $r) {
      if (count(tb_unique_teams($r['series'])) < $n_field * 0.7) break;
      if (tb_round_stats($r)['maxapp'] > 1) break;
      $fresh = true;
      foreach ($r['series'] as $s) {
        if (isset($seen_pairs[tb_pair_key($s['teams'])])) { $fresh = false; break; }
      }
      if (!$fresh) break;
      foreach ($r['series'] as $s) {
        $seen_pairs[tb_pair_key($s['teams'])] = true;
      }
      $rr_rounds[] = $r;
    }

    if (count($rr_rounds) >= 3 && count($playoff_rounds) > count($rr_rounds)) {
      $flat = tb_rounds_series($rr_rounds);
      usort($flat, fn($a, $b) => $a['start'] <=> $b['start']);
      $losses    = [];
      $no_elim    = false;
      foreach ($flat as $s) {
        foreach ($s['teams'] as $t) {
          if (($losses[$t] ?? 0) >= 2) { $no_elim = true; break 2; }
        }
        $l = tb_loser($s);
        if ($l !== null) $losses[$l] = ($losses[$l] ?? 0) + 1;
      }
      if ($no_elim) {
        $second_group_phases[] = tb_phase($rr_rounds, 'group', $flat);
        $playoff_rounds = array_slice($playoff_rounds, count($rr_rounds));
      }
    }
  }

  if ($playoff_rounds) {
    $phases[] = tb_phase($playoff_rounds, 'bracket');
  }

  $merged = tb_merge_adjacent_phases($phases, false, 259200);
  $merged = tb_merge_adjacent_phases($merged, true, 864000);

  if ($second_group_phases) {
    $bi = count($merged);
    foreach ($merged as $i => $ph) {
      if (($ph['phase_type'] ?? '') === 'bracket') { $bi = $i; break; }
    }
    array_splice($merged, $bi, 0, $second_group_phases);
  }

  $merged = array_values(array_filter($merged));

  if (count($merged) === 1 && empty($merged[0]['is_elim'])
    && ($cut = tb_topcut_playoff_tail($rounds)) !== null) {
    return array_merge(
      tb_split_group_rounds(array_slice($rounds, 0, $cut)),
      [tb_phase(array_slice($rounds, $cut), 'bracket')]
    );
  }

  return $merged;
}

// Merge adjacent phases of the same elim-kind and phase_type when the time gap
// between them is below $max_gap seconds.
function tb_merge_adjacent_phases(array $phases, bool $elim, int $max_gap): array {
  $merged = [];
  foreach ($phases as $ph) {
    $prev = $merged ? end($merged) : null;
    $same = $prev
      && (bool)$ph['is_elim'] === $elim
      && (bool)$prev['is_elim'] === $elim
      && ($prev['phase_type'] ?? '') === ($ph['phase_type'] ?? '')
      && ($elim || empty($ph['no_merge']));

    if ($same) {
      $prev_end   = max(array_column($prev['rounds'], 'end'));
      $next_start = min(array_column($ph['rounds'], 'start'));
      if ($next_start - $prev_end < $max_gap) {
        $last = &$merged[count($merged) - 1];
        $last['rounds'] = array_merge($last['rounds'], $ph['rounds']);
        $last['series'] = array_merge($last['series'], $ph['series']);
        unset($last);
        continue;
      }
    }

    $merged[] = $ph;
  }

  return $merged;
}

function tb_boundary_candidates(array $rounds, int $n): array {
  $candidates = [];
  for ($i = 1; $i < $n; $i++) {
    if ($rounds[$i]['start'] - $rounds[$i - 1]['end'] >= 259200) {
      $candidates[] = $i;
    }
  }

  $cum_series = [];
  $had_multi = false;
  $prev_n_comp = 0;
  $max_comp_size = 0;
  $min_comp_size = PHP_INT_MAX;

  for ($i = 0; $i < $n; $i++) {
    $cum_series = array_merge($cum_series, $rounds[$i]['series']);
    $groups = tb_find_groups($cum_series);
    $n_comp = count($groups);
    if ($n_comp > 1) {
      $had_multi = true;
      $sizes = array_map('count', $groups);
      $max_comp_size = max($max_comp_size, max($sizes));
      $min_comp_size = min($sizes);
    } else {
      if ($had_multi && $prev_n_comp > 1 && $max_comp_size >= 4 && $min_comp_size >= 3
        && $i > 0 && !in_array($i, $candidates)) {
        $teams = count(tb_unique_teams($cum_series));
        if (($teams > 0 ? count($cum_series) * 2.0 / $teams : 0) >= 3.5) {
          $candidates[] = $i;
        }
      }

      $max_comp_size = 0;
      $min_comp_size = PHP_INT_MAX;
    }

    $prev_n_comp = $n_comp;
  }

  sort($candidates);

  return $candidates;
}

function tb_pick_playoff_start(array $rounds, array $candidates): ?int {
  foreach (array_reverse($candidates) as $ci) {
    $after_rounds = array_slice($rounds, $ci);
    if (!$after_rounds) {
      continue;
    }

    $after_comps = count(tb_find_groups(tb_rounds_series($after_rounds)));
    $after_maxapp = array_map('tb_round_stats', $after_rounds)[0]['maxapp'] ?? 2;

    if ($after_comps !== 1 || $after_maxapp > 1) {
      continue;
    }

    $before_teams = $ci > 0 ? count(tb_unique_teams(tb_rounds_series(array_slice($rounds, 0, $ci)))) : 0;
    if (count($after_rounds) >= 2 && !tb_after_looks_bracket($after_rounds, $before_teams)) {
      continue;
    }

    $prev_candidates = array_filter($candidates, fn($c) => $c < $ci);
    if ($prev_candidates) {
      $prev_ci = max($prev_candidates);
      $prev_after = array_slice($rounds, $prev_ci);
      $prev_ok = $prev_after
        && count(tb_find_groups(tb_rounds_series($prev_after))) === 1
        && (array_map('tb_round_stats', $prev_after)[0]['maxapp'] ?? 2) <= 1;

      if ($prev_ok) {
        $mid_rounds = array_slice($rounds, $prev_ci, $ci - $prev_ci);
        $skip_later = $mid_rounds && tb_looks_like_bracket($mid_rounds);

        if (!$skip_later && $mid_rounds) {
          $mid_team_rounds = [];
          foreach ($mid_rounds as $mi => $mr) {
            foreach (tb_unique_teams($mr['series']) as $t) {
              $mid_team_rounds[$t][$mi] = true;
            }
          }

          $has_progression = false;
          foreach ($mid_team_rounds as $rs) {
            if (count($rs) >= 2) {
              $has_progression = true;
              break;
            }
          }

          if ($has_progression) {
            $mid_series = tb_rounds_series($mid_rounds);
            $mid_teams = count(tb_unique_teams($mid_series));
            $skip_later = ($mid_teams > 0 ? count($mid_series) * 2.0 / $mid_teams : 0) < 3.0;
          }
        }

        if ($skip_later) {
          continue;
        }
      }
    }
    return $ci;
  }

  // No clean cut found: accept the last candidate if its tail still reads as a bracket.
  if ($candidates) {
    $last_ci = end($candidates);
    $after = array_slice($rounds, $last_ci);
    $before = $last_ci > 0 ? count(tb_unique_teams(tb_rounds_series(array_slice($rounds, 0, $last_ci)))) : 0;
    if (count($after) < 2 || tb_after_looks_bracket($after, $before)) {
      return $last_ci;
    }
  }

  return null;
}


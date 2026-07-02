<?php

function tb_roster_sig(array $m, string $side): string {
  $tid = (int)($m['teams'][$side]['team_id'] ?? 0);
  $pids = [];
  foreach (($m['players'][$side] ?? []) as $p) {
    $pid = (int)($p['player_id'] ?? 0);
    if ($pid) {
      $pids[] = $pid;
    }
  }
  sort($pids);
  if (count($pids) >= 4) {
    return 'r:' . implode(',', $pids);
  }
  if ($tid) {
    return 't:' . $tid;
  }
  $nm = mb_strtolower((string)($m['teams'][$side]['team_name'] ?? ''));
  return $nm !== '' ? 'n:' . $nm : 'u:0';
}

function tb_match_pair_key(array $m): string {
  $a = tb_roster_sig($m, 'radiant');
  $b = tb_roster_sig($m, 'dire');
  return $a < $b ? "$a~$b" : "$b~$a";
}

function tb_meeting_team_sigs(array $m): array {
  return [tb_roster_sig($m, 'radiant'), tb_roster_sig($m, 'dire')];
}

function tb_team_played_other_between(string $sig, int $after, int $before, array $matches): bool {
  foreach ($matches as $om) {
    $d = (int)($om['date'] ?? 0);
    if ($d <= $after || $d >= $before) {
      continue;
    }
    [$ra, $da] = tb_meeting_team_sigs($om);
    if ($ra === $sig || $da === $sig) {
      return true;
    }
  }
  return false;
}

function tb_meeting_cluster_complete(array $ms): bool {
  if (count($ms) < 2) {
    return false;
  }
  $teams = array_values(tb_match_teams($ms[0]));
  if (count($teams) < 2) {
    return false;
  }
  [$ta, $tb] = $teams;
  $score = [$ta => 0, $tb => 0];
  foreach ($ms as $m) {
    $w = tb_match_winner($m);
    if ($w !== null && isset($score[$w])) {
      $score[$w]++;
    }
  }
  if ($score[$ta] === $score[$tb]) {
    return false;
  }
  $winner = $score[$ta] > $score[$tb] ? $ta : $tb;
  $bo = tb_infer_bo($score, count($ms), true);
  return $score[$winner] >= intdiv($bo, 2) + 1;
}

function tb_canonicalize_cluster_team_ids(array &$ms): void {
  $sig_to_tids = [];
  foreach ($ms as $m) {
    foreach (['radiant', 'dire'] as $side) {
      $sig = tb_roster_sig($m, $side);
      $tid = (int)($m['teams'][$side]['team_id'] ?? 0);
      if ($tid) {
        $sig_to_tids[$sig][$tid] = ($sig_to_tids[$sig][$tid] ?? 0) + 1;
      }
    }
  }
  $canon = [];
  foreach ($sig_to_tids as $sig => $cnts) {
    arsort($cnts);
    $canon[$sig] = (int)array_key_first($cnts);
  }
  foreach ($ms as &$m) {
    foreach (['radiant', 'dire'] as $side) {
      $sig = tb_roster_sig($m, $side);
      if (isset($canon[$sig])) {
        $m['teams'][$side]['team_id'] = $canon[$sig];
      }
    }
  }
  unset($m);
}

function tb_cluster_matches_by_meeting(array $matches): array {
  if (!$matches) {
    return [];
  }

  $by_pair = [];
  foreach ($matches as $m) {
    $by_pair[tb_match_pair_key($m)][] = $m;
  }

  $clusters = [];
  foreach ($by_pair as $pair_matches) {
    usort($pair_matches, fn($a, $b) => (int)($a['date'] ?? 0) <=> (int)($b['date'] ?? 0));

    $cur = [];
    $prev_date = null;
    $max_gap = 0;
    $game_cnt = 0;

    foreach ($pair_matches as $m) {
      $date = (int)($m['date'] ?? 0);
      $split = false;

      if ($prev_date !== null) {
        $gap = $date - $prev_date;
        $time_split = ($game_cnt < 2 && $gap > 14400)
          || ($game_cnt >= 2 && $gap > max($max_gap * 3, 14400));

        if ($time_split) {
          $split = true;
        }

        if (!$split) {
          [$sa, $sb] = tb_meeting_team_sigs($m);
          if (tb_team_played_other_between($sa, $prev_date, $date, $matches)
            || tb_team_played_other_between($sb, $prev_date, $date, $matches)) {
            $split = true;
          }
        }

        if (!$split && $cur && tb_meeting_cluster_complete($cur) && tb_match_winner($m) !== null) {
          $split = true;
        }
      }

      if ($split && $cur) {
        $clusters[] = $cur;
        $cur = [];
        $game_cnt = 0;
        $max_gap = 0;
      }

      $cur[] = $m;
      if ($prev_date !== null) {
        $max_gap = max($max_gap, $date - $prev_date);
      }
      $game_cnt++;
      $prev_date = $date;
    }

    if ($cur) {
      $clusters[] = $cur;
    }
  }

  usort($clusters, fn($a, $b) => (int)($a[0]['date'] ?? 0) <=> (int)($b[0]['date'] ?? 0));
  return $clusters;
}

function tb_build_series(array $matches, array $api_series, array $teams): array {
  usort(
    $matches,
    fn($a, $b) => (int)($a['date'] ?? 0) <=> (int)($b['date'] ?? 0)
  );

  $by_tag = [];
  foreach ($api_series as $s) {
    $tag = (string)($s['series_tag'] ?? $s['tag'] ?? $s['seriesid'] ?? '');
    if ($tag !== '') {
      $by_tag[$tag] = $s;
    }
  }

  $out = [];
  foreach (tb_cluster_matches_by_meeting($matches) as $ms) {
    tb_canonicalize_cluster_team_ids($ms);

    $tids = array_values(tb_match_teams($ms[0]));
    if (count($tids) < 2) {
      continue;
    }

    $distinct_ids = [];
    $missing_id = false;
    foreach ($ms as $m) {
      foreach (['radiant', 'dire'] as $sd) {
        $id = (int)($m['teams'][$sd]['team_id'] ?? 0);
        if ($id) {
          $distinct_ids[$id] = true;
        } else {
          $missing_id = true;
        }
      }
    }

    $id_anomaly = $missing_id || count($distinct_ids) !== 2;

    [$ta, $tb] = $tids;
    $score = [$ta => 0, $tb => 0];

    $name_of = fn($id) => mb_strtolower((string)($teams[$id]['name'] ?? $id));
    $canon  = [$name_of($ta) => $ta, $name_of($tb) => $tb];

    foreach ($ms as $m) {
      $w = tb_match_winner($m);
      if ($w !== null && !isset($score[$w])) {
        $w = $canon[$name_of($w)] ?? null;
      }
      if ($w !== null && isset($score[$w])) {
        $score[$w]++;
      }
    }

    $group_tags = [];
    foreach ($ms as $m) {
      $tag = (string)($m['series_tag'] ?? '');
      if ($tag !== '') {
        $group_tags[$tag] = true;
      }
    }

    $api_entries = [];
    foreach (array_keys($group_tags) as $tg) {
      if (isset($by_tag[$tg])) {
        $api_entries[] = $by_tag[$tg];
      }
    }
    usort($api_entries, fn($x, $y) => ($x['start_date'] ?? 0) <=> ($y['start_date'] ?? 0));

    $api      = $api_entries ? end($api_entries) : [];
    $api_games = count($api['matches'] ?? []);

    if (!empty($api['scores'])) {
      $api_score = [];
      foreach ([$ta, $tb] as $id) {
        $v = $api['scores'][$id] ?? $api['scores'][(string)$id] ?? null;
        if ($v !== null) {
          $api_score[$id] = (int)$v;
        }
      }

      $map_decisive = $score[$ta] !== $score[$tb];
      $api_decisive = count($api_score) === 2 && $api_score[$ta] !== $api_score[$tb];
      if (count($api_score) === 2 && ($api_decisive || !$map_decisive)) {
        foreach ($api_score as $id => $v) {
          $score[$id] = $v;
        }
      }
    }

    $winner = $score[$ta] !== $score[$tb] ? (($score[$ta] > $score[$tb] ? $ta : $tb)) : null;
    $bo     = tb_infer_bo($score, $api_games ?: count($ms), $winner !== null);
    $start  = (int)min(array_column($ms, 'date'));
    $end    = (int)max(array_column($ms, 'date'));
    $rmap   = array_count_values(array_map(fn($m) => (int)($m['region'] ?? 0), $ms));
    arsort($rmap);

    $lmap   = array_count_values(array_map(fn($m) => (int)($m['lid'] ?? 0), $ms));
    arsort($lmap);

    $flags = [];
    $wins_needed = intdiv($bo, 2) + 1;
    if ($winner !== null && $score[$winner] < $wins_needed) $flags[] = 'incomplete';

    $key = tb_series_key($ms[0]);
    foreach ($ms as $m) {
      $k = tb_series_key($m);
      if ($k !== $key) {
        $key = 'meet:' . tb_match_pair_key($ms[0]) . ':' . $start;
        break;
      }
    }

    $out[] = [
      'key'    => $key,
      'pair_key' => tb_match_pair_key($ms[0]),
      'teams'  => $tids,
      'score'  => $score,
      'winner' => $winner,
      'bo'     => $bo,
      'start'  => $start,
      'end'    => $end,
      'region' => (int)array_key_first($rmap),
      'lid'    => (int)array_key_first($lmap),
      'flags'  => $flags,
      'games'  => count($ms),
      'mids'   => array_values(array_filter(array_column($ms, 'match_id'))),
      'id_anomaly' => $id_anomaly,
      'tags'   => array_keys($group_tags),
    ];
  }

  usort($out, fn($a, $b) => $a['start'] <=> $b['start']);

  $merged     = [];
  $last_by_pair = [];
  $last_by_team = [];
  foreach ($out as $s) {
    $p = $s['teams']; sort($p);
    $pk = $s['pair_key'] ?? implode('-', $p);
    $pi = $last_by_pair[$pk] ?? null;
    if ($pi !== null) {
      $prev        = $merged[$pi];
      $gap         = $s['start'] - $prev['end'];
      $no_intervene = ($last_by_team[$p[0]] ?? null) === $pi && ($last_by_team[$p[1]] ?? null) === $pi;

      $full    = fn($x) => $x['winner'] !== null && ($x['games'] ?? 0) >= 2
                && max($x['score']) >= intdiv($x['bo'], 2) + 1;
      $prev_tie = tb_is_tie($prev);
      if ($gap < 86400 && $no_intervene && ($prev['winner'] !== null || $prev_tie)
        && !($full($prev) && $full($s))) {
        $merged[$pi]['end']   = max($prev['end'], $s['end']);
        $merged[$pi]['games'] += $s['games'];
        $merged[$pi]['mids']   = array_merge($prev['mids'], $s['mids']);
        foreach ($s['score'] as $t => $v) {
          $merged[$pi]['score'][$t] = ($merged[$pi]['score'][$t] ?? 0) + $v;
        }
        $sc = $merged[$pi]['score'];
        $kk = array_keys($sc);
        $merged[$pi]['winner'] = ($sc[$kk[0]] ?? 0) !== ($sc[$kk[1]] ?? 0)
          ? (($sc[$kk[0]] ?? 0) > ($sc[$kk[1]] ?? 0) ? $kk[0] : $kk[1]) : null;
        $merged[$pi]['bo'] = tb_infer_bo($sc, $merged[$pi]['games'], $merged[$pi]['winner'] !== null);
        continue;
      }
    }
    $merged[] = $s;
    $i = count($merged) - 1;
    $last_by_pair[$pk]   = $i;
    $last_by_team[$p[0]] = $i;
    $last_by_team[$p[1]] = $i;
  }

  return tb_dedup_series($merged);
}

function tb_dedup_series(array $series): array {
  $order = $series;
  usort($order, fn($a, $b) => count($b['mids'] ?? []) <=> count($a['mids'] ?? []));
  $kept = [];
  $kept_sets = [];

  foreach ($order as $s) {
    $mids = array_values(array_unique(array_map('intval', $s['mids'] ?? [])));
    sort($mids);
    if ($mids) {
      $subsumed = false;
      foreach ($kept_sets as $set) {
        if (!array_diff($mids, $set)) {
          $subsumed = true;
          break;
        }
      }
      if ($subsumed) {
        continue;
      }
      $kept_sets[] = $mids;
    }
    $kept[] = $s;
  }
  usort($kept, fn($a, $b) => $a['start'] <=> $b['start']);
  return $kept;
}

function tb_series_key(array $m): string {
  if (!empty($m['series_id'])) {
    return 'sid' . (int)$m['series_id'];
  }

  if (!empty($m['series_tag'])) {
    return (string)$m['series_tag'];
  }

  $t = array_values(tb_match_teams($m));
  sort($t);

  return implode('_', $t) . '_' . intdiv((int)($m['date'] ?? 0), 21600);
}

function tb_match_teams(array $m): array {
  $ids = [];
  foreach (['radiant', 'dire'] as $s) {
    $id = (int)($m['teams'][$s]['team_id'] ?? 0);

    if ($id) $ids[$s] = $id;
  }

  return $ids;
}

function tb_match_winner(array $m): ?int {
  if (!isset($m['radiant_win'])) return null;

  $side = $m['radiant_win'] ? 'radiant' : 'dire';

  return (int)($m['teams'][$side]['team_id'] ?? 0) ?: null;
}

function tb_infer_bo(array $score, int $games, bool $decided): int {
  $top = max($score ?: [0]);

  if (!$decided && $games === 2) return 2;
  if ($top >= 3) return 5;
  if ($top >= 2) return max(3, $games);

  return max(1, $games);
}

function tb_is_tie(array $s): bool {
  $sc = array_values($s['score'] ?? []);

  return count($sc) === 2 && $sc[0] === $sc[1];
}

function tb_count_tied(array $series): int {
  return count(array_filter($series, 'tb_is_tie'));
}

function tb_elim_count(array $series): int {
  if (count($series) < 3) {
    return 0;
  }

  $last_app = [];
  foreach ($series as $s) {
    foreach ($s['teams'] as $t) {
      $last_app[$t] = max($last_app[$t] ?? 0, $s['start']);
    }
  }

  $e = 0;
  foreach ($series as $s) {
    if (empty($s['winner'])) continue;

    $loser = $s['teams'][0] === $s['winner'] ? ($s['teams'][1] ?? 0) : $s['teams'][0];

    if (!$loser) continue;

    if (($last_app[$s['winner']] > $s['start']) && ($last_app[$loser] <= $s['start'])) {
      $e++;
    }
  }

  return $e;
}

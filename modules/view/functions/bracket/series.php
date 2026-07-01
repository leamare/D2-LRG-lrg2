<?php

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

  $groups = [];
  $group_tags = [];

  foreach ($matches as $m) {
    $key = tb_series_key($m);
    $groups[$key][] = $m;
    $tag = (string)($m['series_tag'] ?? '');
    if ($tag !== '') {
      $group_tags[$key][$tag] = true;
    }
  }

  $out = [];
  foreach ($groups as $key => $ms) {
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

    $api_entries = [];
    foreach (array_keys($group_tags[$key] ?? []) as $tg) {
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

    // 'incomplete' = a winner with fewer map wins than the best-of needs (a missing
    // decider). A 'tech-loss' / 'overridden' flag is set elsewhere when a structured
    // signal exists; LRG2 has no per-match description text to scan here.
    $flags = [];
    $wins_needed = intdiv($bo, 2) + 1;
    if ($winner !== null && $score[$winner] < $wins_needed) $flags[] = 'incomplete';

    $out[] = [
      'key'    => $key,
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
      'tags'   => array_keys($group_tags[$key] ?? []),
    ];
  }

  usort($out, fn($a, $b) => $a['start'] <=> $b['start']);

  $merged     = [];
  $last_by_pair = [];
  $last_by_team = [];
  foreach ($out as $s) {
    $p = $s['teams']; sort($p);
    $pk = implode('-', $p);
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
        // One series split across two groups (e.g. a bo3+ straddling a 6h series
        // bucket). Combine the games and SUM the fragment scores, so the merged
        // series' score/winner/bo always reflect all of its games — never just the
        // last fragment (which left a 3-game series showing 1-0 bo1).
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

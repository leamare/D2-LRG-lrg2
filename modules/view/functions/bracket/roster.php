<?php

function tb_merge_roster_aliases(array &$matches, int $min_shared = 4): array {
  $team_players  = [];
  $team_match_cnt = [];
  $team_name_cnt  = [];

  foreach ($matches as $m) {
    foreach (['radiant', 'dire'] as $side) {
      $tid = (int)($m['teams'][$side]['team_id'] ?? 0);

      if (!$tid) {
        continue;
      }

      $team_match_cnt[$tid] = ($team_match_cnt[$tid] ?? 0) + 1;
      $nm = (string)($m['teams'][$side]['team_name'] ?? '');

      if ($nm !== '') {
        $team_name_cnt[$tid][$nm] = ($team_name_cnt[$tid][$nm] ?? 0) + 1;
      }

      foreach (($m['players'][$side] ?? []) as $p) {
        $pid = (int)($p['player_id'] ?? 0);
        if ($pid) {
          $team_players[$tid][$pid] = ($team_players[$tid][$pid] ?? 0) + 1;
        }
      }
    }
  }

  if (count($team_players) < 2) {
    return [];
  }

  $core = [];
  foreach ($team_players as $tid => $pc) {
    arsort($pc);
    $core[$tid] = array_slice(array_keys($pc), 0, 5);
  }

  $player_teams = [];
  foreach ($core as $tid => $pids) {
    foreach ($pids as $pid) {
      $player_teams[$pid][] = $tid;
    }
  }
  $shared = [];
  foreach ($player_teams as $tids) {
    $tids = array_values(array_unique($tids));
    $n = count($tids);
    for ($i = 0; $i < $n; $i++) {
      for ($j = $i + 1; $j < $n; $j++) {
        $a = $tids[$i]; $b = $tids[$j];
        $k = $a < $b ? "$a-$b" : "$b-$a";
        $shared[$k] = ($shared[$k] ?? 0) + 1;
      }
    }
  }

  $parent = [];
  foreach (array_keys($team_players) as $tid) {
    $parent[$tid] = $tid;
  }
  foreach ($shared as $k => $cnt) {
    if ($cnt < $min_shared) {
      continue;
    }
    [$a, $b] = array_map('intval', explode('-', $k));
    tb_uf_union($parent, $a, $b);
  }

  $members = [];

  foreach (array_keys($team_players) as $tid) {
    $members[tb_uf_find($parent, $tid)][] = $tid;
  }

  $canon = [];
  $names = [];

  foreach ($members as $grp) {
    if (count($grp) < 2) {
      continue;
    }

    usort($grp, fn($a, $b) => ($team_match_cnt[$b] ?? 0) <=> ($team_match_cnt[$a] ?? 0));

    $c = $grp[0];
    $nc = $team_name_cnt[$c] ?? [];
    arsort($nc);

    if ($nc) {
      $names[$c] = array_key_first($nc);
    }
    foreach ($grp as $tid) {
      $canon[$tid] = $c;
    }
  }

  if (!$canon) {
    return [];
  }

  foreach ($matches as &$m) {
    foreach (['radiant', 'dire'] as $side) {
      $tid = (int)($m['teams'][$side]['team_id'] ?? 0);
      if (!isset($canon[$tid]) || $canon[$tid] === $tid) {
        continue;
      }

      $m['teams'][$side]['team_id'] = $canon[$tid];
      if (isset($names[$canon[$tid]])) {
        $m['teams'][$side]['team_name'] = $names[$canon[$tid]];
      }
    }
  }

  unset($m);

  return $canon;
}

function tb_fix_split_rosters(array &$matches): void {
  $team_players = [];
  $team_match_cnt = [];
  $names = [];

  foreach ($matches as $m) {
    foreach (['radiant', 'dire'] as $side) {
      $tid = (int)($m['teams'][$side]['team_id'] ?? 0);

      if (!$tid) continue;

      $team_match_cnt[$tid] = ($team_match_cnt[$tid] ?? 0) + 1;
      $names[$tid] ??= (string)($m['teams'][$side]['team_name'] ?? '');

      foreach (($m['players'][$side] ?? []) as $p) {
        $pid = (int)($p['player_id'] ?? 0);
        if ($pid) {
          $team_players[$tid][$pid] = ($team_players[$tid][$pid] ?? 0) + 1;
        }
      }
    }
  }

  $core = [];
  foreach ($team_players as $tid => $pc) {
    if (($team_match_cnt[$tid] ?? 0) < 3) {
      continue;
    }

    arsort($pc);
    $core[$tid] = array_slice(array_keys($pc), 0, 5);
  }

  if (count($core) < 2) {
    return;
  }

  $pair_dates = [];
  foreach ($matches as $m) {
    $a = (int)($m['teams']['radiant']['team_id'] ?? 0);
    $b = (int)($m['teams']['dire']['team_id'] ?? 0);

    if (!$a || !$b) continue;

    $k = $a < $b ? "$a-$b" : "$b-$a";
    $pair_dates[$k][] = (int)($m['date'] ?? 0);
  }

  $has_sibling = function (int $x, int $y, int $when) use ($pair_dates): bool {
    $k = $x < $y ? "$x-$y" : "$y-$x";

    foreach ($pair_dates[$k] ?? [] as $d) {
      if (abs($d - $when) <= 259200) {
        return true;
      }
    }

    return false;
  };

  foreach ($matches as &$m) {
    $opp_of = [
      'radiant' => (int)($m['teams']['dire']['team_id'] ?? 0),
      'dire'    => (int)($m['teams']['radiant']['team_id'] ?? 0),
    ];

    foreach (['radiant', 'dire'] as $side) {
      $tid = (int)($m['teams'][$side]['team_id'] ?? 0);
      if (!$tid || !isset($core[$tid])) {
        continue;
      }

      $game = array_filter(array_map(fn($p) => (int)($p['player_id'] ?? 0), $m['players'][$side] ?? []));
      if (count($game) < 4) {
        continue;
      }

      if (count(array_intersect($game, $core[$tid])) >= 3) {
        continue;
      }

      $best = null; $best_share = 0;
      foreach ($core as $otid => $roster) {
        if ($otid === $tid) continue;
        $sh = count(array_intersect($game, $roster));
        if ($sh > $best_share) { $best_share = $sh; $best = $otid; }
      }

      $opp = $opp_of[$side];

      if ($best !== null && $best_share >= 4
        && $opp && $opp !== $best
        && $has_sibling($best, $opp, (int)($m['date'] ?? 0))
      ) {
        $m['teams'][$side]['team_id'] = $best;
        if (!empty($names[$best])) {
          $m['teams'][$side]['team_name'] = $names[$best];
        }
      }
    }
  }

  unset($m);
}

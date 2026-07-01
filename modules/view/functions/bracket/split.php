<?php

function tb_split_events(array $series, array $desc, array $config = []): array {
  if (!$series) return [];

  // Forced division split via hinting
  if (!empty($config['divisions'])) {
    $base = $desc['name'] ?? $desc['tag'];
    $tag  = $desc['tag'];
    $out  = [];
    foreach (tb_divide_series($series, $config) as $name => $sub) {
      if ($name === '') {
        $out = array_merge($out, tb_split_events($sub, $desc));
      } else {
        $out[] = ['name' => "$base, $name", 'report' => $tag, 'series' => $sub];
      }
    }
    return $out;
  }

  if (!empty($desc['interest'])) {
    $set = array_flip(array_map('intval', (array)$desc['interest']));
    $ivi = array_values(array_filter($series, fn($s) =>
      isset($set[$s['teams'][0]]) && isset($set[$s['teams'][1] ?? 0])));
    if (count($ivi) >= 4) {
      return [[
        'name'   => $desc['name'] ?? strtoupper($desc['tag'] ?? 'Recap'),
        'report' => $desc['tag'] ?? '',
        'series' => $ivi,
        'mode'   => 'interest',
      ]];
    }
  }

  usort($series, fn($a, $b) => $a['start'] <=> $b['start']);
  $units = tb_partition_units($series);

  $refined = [];
  foreach ($units as $u) {
    $refined = array_merge($refined, tb_split_unit_by_lid($u));
  }
  $units = $refined;

  $events = tb_name_units($units, $desc);

  // Multi-event reports that span multiple months turn into performance grid
  $want_grid = array_key_exists('months', $config)
    ? (bool)$config['months']
    : tb_report_wants_month_grid($series, $events);
  
  if ($want_grid && empty($config['stages'])) {
    $base = $desc['name'] ?? $desc['tag'];
    $tag  = $desc['tag'];
    $out  = [[
      'name'   => $base,
      'report' => $tag,
      'series' => $series,
      'mode'   => 'aggregate',
    ]];
    $seen = [];
    foreach ($events as $ev) {
      if (tb_is_incoherent_aggregate($ev['series'])) {
        foreach (tb_temporal_clusters($ev['series'], 4 * 86400) as $cl) {
          usort($cl, fn($a, $b) => $a['start'] <=> $b['start']);
          $label = locale_month_year($cl[0]['start']);
          $seen[$label] = ($seen[$label] ?? 0) + 1;
          if ($seen[$label] > 1) $label .= ' #' . $seen[$label];
          $out[] = [
            'name'   => "$base, $label",
            'report' => $tag,
            'series' => $cl,
            'mode'   => 'aggregate_sub',
          ];
        }
      } else {
        $ev['mode'] = 'aggregate_sub';
        $out[] = $ev;
      }
    }
    return $out;
  }

  return $events;
}

function tb_split_unit_by_lid(array $series): array {
  $by_lid = [];
  foreach ($series as $s) {
    $by_lid[(int)($s['lid'] ?? 0)][] = $s;
  }
  $non_zero = array_filter(array_keys($by_lid), fn($l) => $l !== 0);

  if (count($non_zero) < 2) {
    return [$series];
  }

  $out = [];

  uasort($by_lid, fn($a, $b) => min(array_column($a, 'start')) <=> min(array_column($b, 'start')));
  foreach ($by_lid as $grp) {
    foreach (tb_partition_units($grp) as $sub) {
      $out[] = $sub;
    }
  }
  return $out;
}

function tb_partition_units(array $series): array {
  if (count($series) < 2) {
    return $series ? [$series] : [];
  }

  usort($series, fn($a, $b) => $a['start'] <=> $b['start']);

  $chunks = [];
  $cur = [];
  $last_end = null;

  foreach ($series as $s) {
    if ($last_end !== null && $s['start'] - $last_end > 18 * 86400) {
      $chunks[] = $cur;
      $cur = [];
    }
    $cur[]   = $s;
    $last_end = max($last_end ?? 0, $s['end']);
  }
  $chunks[] = $cur;

  if (count($chunks) > 1) {
    $merged = [array_shift($chunks)];
    foreach ($chunks as $c) {
      $prev = tb_unique_teams(end($merged));
      $teams  = tb_unique_teams($c);
      $shared = count(array_intersect($teams, $prev));
      $subset = $teams && $shared >= count($teams) * 0.8 && count($teams) < count($prev);
      if ($subset) {
        $merged[count($merged) - 1] = array_merge(end($merged), $c);
      } else {
        $merged[] = $c;
      }
    }
    $chunks = $merged;
  }

  if (count($chunks) > 1) {
    $out = [];
    foreach ($chunks as $c) {
      $out = array_merge($out, tb_partition_units($c));
    }
    return $out;
  }

  $components = tb_find_groups($series);
  if (count($components) > 1 && tb_should_split_components($series, $components)) {
    $team_to_comp = [];
    foreach ($components as $ci => $team_ids) {
      foreach ($team_ids as $tid) {
        $team_to_comp[$tid] = $ci;
      }
    }

    $comp_series_count = [];
    foreach ($series as $s) {
      $ci = $team_to_comp[$s['teams'][0]] ?? 0;
      $comp_series_count[$ci] = ($comp_series_count[$ci] ?? 0) + 1;
    }

    $groups = [];
    foreach ($series as $s) {
      $ci = $team_to_comp[$s['teams'][0]] ?? 0;
      $n_teams  = count($components[$ci] ?? []);
      $n_series = $comp_series_count[$ci] ?? 0;

      if ($n_teams < 3 || ($n_teams < 4 && $n_series < 3)) {
        continue;
      }
      $groups[$ci][] = $s;
    }

    if (count($groups) > 1) {
      $out = [];
      foreach ($groups as $g) {
        $out = array_merge($out, tb_partition_units(array_values($g)));
      }
      return $out;
    }
  }

  $span   = max(array_column($series, 'end')) - min(array_column($series, 'start'));
  $n_teams = count(tb_unique_teams($series));
  if ($n_teams > 40 && $span > 35 * 86400) {
    $best_gap = 0; $best_idx = -1; $last_end = null;
    foreach ($series as $i => $s) {
      if ($last_end !== null && $s['start'] - $last_end > $best_gap) {
        $best_gap = $s['start'] - $last_end; $best_idx = $i;
      }
      $last_end = max($last_end ?? 0, $s['end']);
    }
    if ($best_gap > 3 * 86400 && $best_idx > 0) {
      return array_merge(
        tb_partition_units(array_slice($series, 0, $best_idx)),
        tb_partition_units(array_slice($series, $best_idx))
      );
    }
  }

  return [$series];
}

function tb_should_split_components(array $series, array $components): bool {
  $team_to_comp = [];
  foreach ($components as $ci => $team_ids) {
    foreach ($team_ids as $tid) {
      $team_to_comp[$tid] = $ci;
    }
  }

  $comp_regions = [];
  $comp_times = [];
  $comp_series  = [];
  foreach ($series as $s) {
    $ci = $team_to_comp[$s['teams'][0]] ?? 0;
    $comp_regions[$ci][] = (int)$s['region'];
    $comp_times[$ci][]   = $s['start'];
    $comp_times[$ci][]   = $s['end'];
    $comp_series[$ci][]  = $s;
  }

  $dom_regions = [];
  foreach ($comp_regions as $ci => $regs) {
    $rc = array_count_values(array_filter($regs));
    arsort($rc);
    $dom_regions[$ci] = array_key_first($rc) ?? 0;
  }

  $has_diff_regions = count(array_unique(array_values($dom_regions))) > 1
           && !in_array(0, array_values($dom_regions));

  $ranges = [];
  foreach ($comp_times as $ci => $ts) {
    $ranges[$ci] = [min($ts), max($ts)];
  }
  
  $max_gap = 0;
  $rlist  = array_values($ranges);
  for ($i = 0; $i < count($rlist) - 1; $i++) {
    for ($j = $i + 1; $j < count($rlist); $j++) {
      $gap = max(0, max($rlist[$i][0], $rlist[$j][0]) - min($rlist[$i][1], $rlist[$j][1]));
      $max_gap = max($max_gap, $gap);
    }
  }

  $full_tournaments = 0;
  foreach ($components as $ci => $team_ids) {
    if (count($team_ids) >= 5 && tb_elim_count($comp_series[$ci] ?? []) >= 4) {
      $full_tournaments++;
    }
  }
  $parallel_tournaments = $full_tournaments >= 2;

  $sizes = array_map('count', $components);
  $big_enough = array_filter($sizes, fn($n) => $n >= 4);
  $uneven_divisions = count($big_enough) >= 3
    && count($big_enough) % 2 === 1
    && max($sizes) >= 2 * min($big_enough)
    && (max($sizes) - min($big_enough)) >= 10;

  return $has_diff_regions || $max_gap > 30 * 86400 || $parallel_tournaments || $uneven_divisions;
}

function tb_name_units(array $units, array $desc): array {
  $base = $desc['name'] ?? $desc['tag'];
  $tag  = $desc['tag'];
  if (!$units) {
    return [];
  }

  $tickets = $desc['tickets'] ?? [];

  $meta = [];
  foreach ($units as $u) {
    $rc = array_count_values(array_map(fn($s) => (int)$s['region'], $u));
    unset($rc[0]);
    arsort($rc);
    $lc = array_count_values(array_map(fn($s) => (int)($s['lid'] ?? 0), $u));
    unset($lc[0]);
    arsort($lc);
    $lid = (int)(array_key_first($lc) ?? 0);
    $meta[] = [
      'series' => $u,
      'region' => (int)(array_key_first($rc) ?? 0),
      'start'  => (int)min(array_column($u, 'start')),
      'end'    => (int)max(array_column($u, 'end')),
      'ticket' => $tickets[$lid]['name'] ?? null,
    ];
  }

  if (count($meta) === 1) {
    return [['name' => $base, 'report' => $tag, 'series' => $meta[0]['series']]];
  }

  $have_tickets = false;
  foreach ($meta as $m) {
    if ($m['ticket'] !== null) {
      $have_tickets = true;
      break;
    }
  }

  $events = [];
  if ($have_tickets) {
    $groups = [];
    foreach ($meta as $i => $m) {
      $groups[$m['ticket'] ?? $base][] = $i;
    }
    foreach ($groups as $label => $idxs) {
      $region_varies = count(array_unique(array_filter(array_map(fn($i) => $meta[$i]['region'], $idxs)))) > 1;
      $month_varies  = count(array_unique(array_map(fn($i) => date('M Y', $meta[$i]['start']), $idxs))) > 1;
      foreach ($idxs as $i) {
        $name = $label;
        if ($region_varies && $meta[$i]['region']) {
          $name .= ', ' . locale_string("region".$meta[$i]['region']);
        }
        if ($month_varies) {
          $name .= ', ' . locale_month_year($meta[$i]['start']);
        }
        $events[$i] = [
          'name' => $name,
          'report' => $tag,
          'series' => $meta[$i]['series'],
          '_start' => $meta[$i]['start'],
          '_end'   => $meta[$i]['end'],
        ];
      }
    }
    ksort($events);
    $events = array_values($events);
  } else {
    foreach ($meta as $m) {
      $name = $base;
      if ($m['region']) {
        $name .= ', ' . locale_string("region".$m['region']);
      }
      $name .= ', ' . locale_month_year($m['start']);
      $events[] = [
        'name' => $name,
        'report' => $tag,
        'series' => $m['series'],
        '_start' => $m['start'],
        '_end' => $m['end'],
      ];
    }
  }

  $by_name = [];
  foreach ($events as $i => $e) {
    $by_name[$e['name']][] = $i;
  }
  foreach ($by_name as $idxs) {
    if (count($idxs) < 2) {
      continue;
    }
    usort($idxs, fn($a, $b) => (($events[$a]['_start'] ?? 0) <=> ($events[$b]['_start'] ?? 0))
      ?: (count($events[$b]['series']) <=> count($events[$a]['series'])));
    foreach ($idxs as $n => $i) {
      $events[$i]['name'] .= ', ' . locale_string("bracket_division") . ' ' . ($n + 1);
    }
  }

  usort($events, fn($a, $b) => (($a['_start'] ?? 0) <=> ($b['_start'] ?? 0))
    ?: (count($b['series']) <=> count($a['series'])));

  foreach ($events as &$e) {
    unset($e['_start'], $e['_end']);
  }
  unset($e);
  return $events;
}

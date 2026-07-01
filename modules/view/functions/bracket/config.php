<?php

function tb_apply_overrides(array $series, array $config): array {
  $ov = $config['overrides'] ?? [];
  if (!$ov) return $series;

  foreach ($series as &$s) {
    $win = $ov[$s['key']] ?? null;

    if ($win === null) foreach ($s['tags'] ?? [] as $tag) {
      if (isset($ov[$tag])) { $win = $ov[$tag]; break; }
    }

    if ($win === null || !in_array($win, $s['teams'], true)) continue;

    $s['winner'] = $win;
    $s['flags']  = array_values(array_unique(array_merge(
      array_filter($s['flags'] ?? [], fn($f) => $f !== 'incomplete'),
      ['overridden']
    )));
  }

  unset($s);
  return $series;
}

function tb_phases_from_hint(array $series, array $stages): array {
  $effective = array_values(array_filter($stages, fn($s) => ($s['kind'] ?? '') !== 'none'));
  $has_none   = count($effective) !== count($stages);

  if (!$effective) return tb_detect_phases($series);

  if (array_filter($effective, fn($s) => ($s['start'] ?? 0) || ($s['end'] ?? 0))) {
    $prev_end = 0;
    foreach ($effective as &$st) {
      if (!($st['start'] ?? 0)) $st['start'] = $prev_end;
      $prev_end = $st['end'] ?? 0;
    }
    unset($st);

    $phases = [];
    foreach ($effective as $st) {
      $lo = $st['start'] ?: 0;
      $hi = $st['end'] ?: PHP_INT_MAX;
      $seg = array_values(array_filter($series, fn($s) => $s['start'] >= $lo && $s['start'] < $hi));
      if (!$seg) continue;
      $phases[] = tb_hint_phase($st, $seg);
    }
    return $phases ?: tb_detect_phases($series);
  }

  if (count($effective) === 1) {
    return [tb_hint_phase($effective[0], $series)];
  }

  $detected = tb_detect_phases($series);
  if (count($detected) < count($effective)) {
    $detected = tb_split_rounds_by_gaps(tb_temporal_rounds($series), count($effective));
  }

  foreach ($detected as $i => &$ph) {
    if (!isset($effective[$i])) break;
    $st = $effective[$i];
    $ph['is_elim']     = $st['kind'] === 'bracket';
    $ph['phase_type']  = $ph['is_elim'] ? 'bracket' : 'group';
    $ph['format_hint'] = $st['format'];
    $ph['groups_hint'] = $st['groups'] ?? 0;
  }

  unset($ph);

  if ($has_none && count($detected) > count($effective)) {
    $detected = array_slice($detected, 0, count($effective));
  }

  return $detected;
}

function tb_hint_phase(array $st, array $series): array {
  $is_elim = ($st['kind'] ?? '') === 'bracket';

  return [
    'rounds'      => tb_temporal_rounds($series),
    'series'      => $series,
    'is_elim'     => $is_elim,
    'phase_type'  => $is_elim ? 'bracket' : 'group',
    'format_hint' => $st['format'] ?? '',
    'groups_hint' => $st['groups'] ?? 0,
  ];
}

// Cut temporal rounds into $n contiguous segments at the $n-1 largest time gaps
function tb_split_rounds_by_gaps(array $rounds, int $n): array {
  $r = count($rounds);

  if ($n <= 1 || $r <= 1) {
    $series = $rounds ? array_merge(...array_map(fn($x) => $x['series'], $rounds)) : [];
    return [['rounds' => $rounds, 'series' => $series]];
  }
  $n = min($n, $r);

  $gaps = [];

  for ($i = 1; $i < $r; $i++) {
    $gaps[$i] = ($rounds[$i]['start'] ?? 0) - ($rounds[$i - 1]['end'] ?? 0);
  }

  arsort($gaps);
  $cuts = array_slice(array_keys($gaps), 0, $n - 1);
  sort($cuts);

  $phases = [];
  $start  = 0;
  foreach (array_merge($cuts, [$r]) as $cut) {
    $seg    = array_slice($rounds, $start, $cut - $start);
    $series = $seg ? array_merge(...array_map(fn($x) => $x['series'], $seg)) : [];

    if ($series) {
      $phases[] = ['rounds' => $seg, 'series' => $series];
    }

    $start = $cut;
  }

  return $phases;
}

// Partition series into forced divisions
function tb_divide_series(array $series, array $config): array {
  $divs = $config['divisions'] ?? [];
  if (empty($divs)) return [
    '' => $series,
  ];

  $buckets = [
    '' => [],
  ];

  foreach ($divs as $name => $d) {
    $buckets[$name] = [];
  }

  foreach ($series as $s) {
    $placed = false;

    foreach ($divs as $name => $d) {
      if (
        $d['teams'] &&
        !(in_array($s['teams'][0], $d['teams'], true) &&
        in_array($s['teams'][1] ?? 0, $d['teams'], true))
      ) {
        continue;
      }
      if ($d['start'] && $s['start'] < $d['start']) {
        continue;
      }
      if ($d['end']   && $s['start'] > $d['end']) {
        continue;
      }
      if ($d['days']  && !in_array(strtolower(date('D', $s['start'])), $d['days'], true)) {
        continue;
      }
      $buckets[$name][] = $s;
      $placed = true;
      break;
    }

    if (!$placed) $buckets[''][] = $s;
  }
  return array_filter($buckets, fn($b) => $b !== []);
}

// Normalize the report's "stages" into stage records. Each entry is an object
// {kind, format, teams, groups, until/from/dates}, or a short
// "kind[:format[:teams[:groups]]]" string ("de"/"se"/"rr"/"swiss" as shorthand).
function tb_normalize_stages(array $stages): array {
  $out = [];
  foreach ($stages as $s) {
    if (is_string($s)) {
      $p = array_map('trim', explode(':', $s));
      $kind = strtolower($p[0] ?? '');
      $format = strtolower($p[1] ?? '');
      if (in_array($kind, ['de', 'se'], true))        { $format = $kind; $kind = 'bracket'; }
      elseif (in_array($kind, ['rr', 'swiss'], true)) { $format = $kind; $kind = 'group'; }
      $s = ['kind' => $kind, 'format' => $format, 'teams' => (int)($p[2] ?? 0), 'groups' => (int)($p[3] ?? 0)];
    }

    $s = (array)$s;
    [$start, $end] = tb_date_frame($s);
    $out[] = [
      'kind'   => strtolower((string)($s['kind'] ?? 'group')),
      'format' => strtolower((string)($s['format'] ?? '')),
      'teams'  => (int)($s['teams'] ?? 0),
      'groups' => (int)($s['groups'] ?? 0),
      'start'  => $start,
      'end'    => $end,
    ];
  }
  return $out;
}

// Interpret until=/from=/dates= into [start, end] timestamps (0 = open), date-only are inclusive
function tb_date_frame(array $kv): array {
  $day = fn(string $s): int => ($t = strtotime(trim($s))) ? $t : 0;
  $start = 0; $end = 0;
  if (!empty($kv['dates']) && str_contains((string)$kv['dates'], '..')) {
    [$a, $b] = explode('..', (string)$kv['dates'], 2);
    $start = $day($a);
    $end   = ($e = $day($b)) ? $e + 86400 : 0;
  }
  if (!empty($kv['from']))  $start = $day($kv['from']);
  if (!empty($kv['until'])) $end   = ($e = $day($kv['until'])) ? $e + 86400 : 0;
  return [$start, $end];
}

// Normalize one division spec from the report JSON.
function tb_normalize_division(array $spec): array {
  $teams = $spec['teams'] ?? [];
  if (is_string($teams)) $teams = explode(',', $teams);
  $days = $spec['days'] ?? [];
  if (is_string($days)) $days = explode(',', $days);

  $start = $end = 0;
  $dates = $spec['dates'] ?? '';
  if (is_array($dates)) { $start = strtotime((string)($dates[0] ?? '')) ?: 0; $end = strtotime((string)($dates[1] ?? '')) ?: 0; }
  elseif (is_string($dates) && str_contains($dates, '..')) {
    [$a, $b] = explode('..', $dates, 2);
    $start = strtotime(trim($a)) ?: 0;
    $end   = strtotime(trim($b)) ?: 0;
  }

  return [
    'teams'        => array_values(array_filter(array_map('intval', (array)$teams))),
    'days'         => array_values(array_filter(array_map(fn($d) => strtolower(substr(trim($d), 0, 3)), (array)$days))),
    'start'        => (int)$start,
    'end'          => (int)$end,
    'qualifies_to' => (string)($spec['qualifies_to'] ?? ''),
  ];
}

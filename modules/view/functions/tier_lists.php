<?php

include_once(__DIR__ . "/ranking.php");

const TIER_LIST_TIERS = [ 'S', 'A', 'B', 'C', 'D', 'E' ];

const TIER_LIST_SHARES = [ 0.075, 0.14, 0.205, 0.235, 0.205, 0.14 ];
const TIER_LIST_MP_FLOOR = 0.9;
const TIER_LIST_MP_FULL = 2.0;
const TIER_LIST_POP_FLOOR = 0.55;
const TIER_LIST_META_BOOST_BASE = 3;
const TIER_LIST_META_BOOST_STEP = 5;
const TIER_LIST_META_COMBO_FACTOR = 0.5;
const TIER_LIST_MIN_MATCHES = 3;

function tier_list_normalize_ranks($wranks) {
  if (empty($wranks)) return [];
  $min = min($wranks);
  $max = max($wranks);

  $out = [];
  foreach ($wranks as $id => $w) {
    $out[$id] = ($max != $min) ? 100 * ($w - $min) / ($max - $min) : 50;
  }
  return $out;
}

function tier_list_meta_boosts($meta_levels) {
  if (empty($meta_levels['layers'])) return [];

  $layers = $meta_levels['layers'];
  $projections = $meta_levels['projections'] ?? [];
  $total = count($layers) + count($projections);
  if ($total < 1) return [];

  $boosts = [];
  $apply = function($ids, $weight, $factor) use (&$boosts) {
    foreach ($ids as $hid) {
      $b = (TIER_LIST_META_BOOST_BASE + TIER_LIST_META_BOOST_STEP * $weight) * $factor;
      if (!isset($boosts[$hid]) || $boosts[$hid] < $b) $boosts[$hid] = $b;
    }
  };

  $i = 0;
  foreach ($layers as $layer) {
    $weight = $total > 1 ? $i / ($total - 1) : 1;
    $apply($layer['core'], $weight, 1);
    $apply($layer['combo'], $weight, TIER_LIST_META_COMBO_FACTOR);
    $i++;
  }
  foreach ($projections as $p) {
    $weight = $total > 1 ? $i / ($total - 1) : 1;
    $apply($p['core'], $weight, 1);
    $apply($p['combo'] ?? [], $weight, TIER_LIST_META_COMBO_FACTOR);
    $i++;
  }

  return $boosts;
}

function tier_list_percentiles($scores) {
  if (empty($scores)) return [];

  $vals = array_values($scores);
  sort($vals);
  $n = count($vals);

  $first = []; $count = [];
  foreach ($vals as $i => $v) {
    $k = (string)$v;
    if (!isset($first[$k])) $first[$k] = $i;
    $count[$k] = ($count[$k] ?? 0) + 1;
  }

  $out = [];
  foreach ($scores as $id => $v) {
    $k = (string)$v;
    $mid = $first[$k] + ($count[$k] - 1) / 2;
    $out[$id] = $n > 1 ? 100 * $mid / ($n - 1) : 50;
  }
  return $out;
}

/**
 * Cut ranked entities into tiers.
 * $ranks:    id => score used for ordering (rank after any corrections)
 * $demoted:  ids to keep out of the tiers entirely
 * $display:  id => the rank to *report* per tier: the plain rank, before the meta-level and pickrate corrections, since that's the figure the rest of the report shows
 * Returns [ 'tiers' => [ 'S' => [ids], ..., 'not_meta' => [ids] ], 'ranges' => [ 'S' => [min,max], ... ] ]
 */
function tier_list_split($ranks, $demoted = [], $display = null) {
  $tiers = [];
  foreach (TIER_LIST_TIERS as $t) $tiers[$t] = [];
  $tiers['not_meta'] = [];

  if (empty($ranks)) return [ 'tiers' => $tiers, 'ranges' => [] ];
  if ($display === null) $display = $ranks;

  arsort($ranks);

  if (count($demoted) >= count($ranks)) $demoted = [];

  $eligible = [];
  foreach ($ranks as $id => $r) {
    if (isset($demoted[$id])) $tiers['not_meta'][] = $id;
    else $eligible[$id] = $r;
  }

  if (empty($eligible)) return [ 'tiers' => $tiers, 'ranges' => [] ];

  $n = count(TIER_LIST_TIERS);
  $pct = tier_list_percentiles($eligible);

  $floors = [];
  $cum = 0;
  for ($i = 0; $i < $n; $i++) {
    $cum += TIER_LIST_SHARES[$i] ?? (1 / $n);
    $floors[$i] = 100 * (1 - min(1, $cum));
  }
  $floors[$n - 1] = 0;

  foreach ($eligible as $id => $r) {
    $idx = $n - 1;
    for ($i = 0; $i < $n; $i++) {
      if ($pct[$id] >= $floors[$i]) { $idx = $i; break; }
    }
    $tiers[ TIER_LIST_TIERS[$idx] ][] = $id;
  }

  $display_vals = [];
  foreach ($eligible as $id => $r) $display_vals[] = $display[$id] ?? 0;
  sort($display_vals);
  $dn = count($display_vals);

  $quantile_at = function($percentile) use ($display_vals, $dn) {
    if ($dn <= 0) return 0;
    $idx = (int)round(($percentile / 100) * ($dn - 1));
    return $display_vals[ max(0, min($dn - 1, $idx)) ];
  };

  $ranges = [];
  foreach (TIER_LIST_TIERS as $i => $t) {
    if (empty($tiers[$t])) continue;
    $top_pct = $i === 0 ? 100 : $floors[$i - 1];
    $ranges[$t] = [ $quantile_at($floors[$i]), $quantile_at($top_pct) ];
  }

  return [ 'tiers' => $tiers, 'ranges' => $ranges ];
}

function tier_list_popularity_factor($mp_ratio) {
  $pop = min(1, max(0, $mp_ratio) / TIER_LIST_MP_FULL);
  return TIER_LIST_POP_FLOOR + (1 - TIER_LIST_POP_FLOOR) * $pop;
}

function tier_list_heroes_overall(&$report) {
  if (empty($report['pickban'])) return null;

  $context = $report['pickban'];
  $total_matches = $report['random']['matches_total'] ?? 1;

  $picks = array_column($context, 'matches_picked');
  sort($picks);
  $mp = $picks[ (int)floor(count($picks) / 2) ] ?? 1;
  if (!$mp) $mp = 1;

  compound_ranking($context, $total_matches);

  $wranks = [];
  foreach ($context as $hid => $el) $wranks[$hid] = $el['wrank'];
  $base = tier_list_normalize_ranks($wranks);

  $adj = [];
  $demoted = [];
  foreach ($context as $hid => $el) {
    $ratio = ($el['matches_picked'] ?? 0) / $mp;
    if ($ratio < TIER_LIST_MP_FLOOR) $demoted[$hid] = true;
    $adj[$hid] = ($base[$hid] ?? 0) * tier_list_popularity_factor($ratio);
  }

  $adj = tier_list_apply_meta_boost($adj, $report);

  $split = tier_list_split($adj, $demoted, $base);
  return [ 'tiers' => $split['tiers'], 'ranges' => $split['ranges'], 'ranks' => $adj, 'base' => $base ];
}

function tier_list_heroes_position(&$report, $core, $lane) {
  if (empty($report['hero_positions'][$core][$lane])) return null;

  $context = $report['hero_positions'][$core][$lane];

  $total_matches = 0;
  foreach ($context as $c) {
    if ($total_matches < ($c['matches_s'] ?? 0)) $total_matches = $c['matches_s'] ?? 0;
  }
  if (!$total_matches) return null;

  positions_ranking($context, $total_matches);

  $wranks = [];
  foreach ($context as $hid => $el) $wranks[$hid] = $el['wrank'];
  $base = tier_list_normalize_ranks($wranks);

  $picks = array_column($context, 'matches_s');
  sort($picks);
  $mp = $picks[ (int)floor(count($picks) / 2) ] ?? 1;
  if (!$mp) $mp = 1;

  $adj = [];
  $demoted = [];
  foreach ($context as $hid => $el) {
    $ratio = ($el['matches_s'] ?? 0) / $mp;
    if ($ratio < TIER_LIST_MP_FLOOR) $demoted[$hid] = true;
    $adj[$hid] = ($base[$hid] ?? 0) * tier_list_popularity_factor($ratio);
  }

  $adj = tier_list_apply_meta_boost($adj, $report);

  $split = tier_list_split($adj, $demoted, $base);
  return [ 'tiers' => $split['tiers'], 'ranges' => $split['ranges'], 'ranks' => $adj, 'base' => $base ];
}

function tier_list_hero_role_totals(&$report) {
  static $cache = null;
  if ($cache !== null) return $cache;

  $cache = [];
  if (empty($report['hero_positions'])) return $cache;

  foreach ($report['hero_positions'] as $core => $lanes) {
    if (!is_array($lanes)) continue;
    foreach ($lanes as $lane => $heroes) {
      if (!is_array($heroes)) continue;
      foreach ($heroes as $hid => $el) {
        $cache[$hid] = ($cache[$hid] ?? 0) + ($el['matches_s'] ?? 0);
      }
    }
  }

  return $cache;
}

function tier_list_apply_meta_boost($ranks, &$report) {
  static $boosts = null;

  if ($boosts === null) {
    $boosts = [];
    if (function_exists('meta_levels_from_report')) {
      $ml = meta_levels_from_report($report);
      if (!empty($ml)) $boosts = tier_list_meta_boosts($ml);
    }
  }

  if (empty($boosts)) return $ranks;

  foreach ($ranks as $hid => $r) {
    if (isset($boosts[$hid])) $ranks[$hid] = min(100, $r + $boosts[$hid]);
  }
  return $ranks;
}

function tier_list_player_hero_stats(&$report) {
  static $cache = null;
  if ($cache !== null) return $cache;

  $cache = [];
  if (empty($report['matches'])) return $cache;

  foreach ($report['matches'] as $mid => $lines) {
    if (!is_array($lines)) continue;
    $rw = $report['matches_additional'][$mid]['radiant_win'] ?? null;
    foreach ($lines as $l) {
      if (!isset($l['player'], $l['hero'])) continue;
      $pid = $l['player'];
      $hid = $l['hero'];
      if (!isset($cache[$hid][$pid])) $cache[$hid][$pid] = [ 'matches' => 0, 'wins' => 0 ];
      $cache[$hid][$pid]['matches']++;
      if ($rw !== null && $rw == ($l['radiant'] ?? null)) $cache[$hid][$pid]['wins']++;
    }
  }

  return $cache;
}

function tier_list_team_hero_stats(&$report) {
  static $cache = null;
  if ($cache !== null) return $cache;

  $cache = [];
  if (empty($report['teams'])) return $cache;

  foreach ($report['teams'] as $tid => $team) {
    if (empty($team['pickban'])) continue;
    foreach ($team['pickban'] as $hid => $st) {
      $m = $st['matches_picked'] ?? 0;
      if ($m <= 0) continue;
      $w = isset($st['wins_picked']) ? $st['wins_picked'] : round($m * ($st['winrate_picked'] ?? 0));
      $cache[$hid][$tid] = [ 'matches' => $m, 'wins' => $w ];
    }
  }

  return $cache;
}

function tier_list_entities_by_record($stats, $min_matches = TIER_LIST_MIN_MATCHES) {
  if (empty($stats)) return null;

  $total = 0;
  foreach ($stats as $s) $total += $s['matches'];
  if (!$total) return null;

  $wranks = [];
  $demoted = [];
  foreach ($stats as $id => $s) {
    if ($s['matches'] < $min_matches) { $demoted[$id] = true; }
    $wranks[$id] = wilson_rating($s['wins'], $s['matches'], 1 - ($s['matches'] / $total));
  }

  $ranks = tier_list_normalize_ranks($wranks);

  $split = tier_list_split($ranks, $demoted);
  return [ 'tiers' => $split['tiers'], 'ranges' => $split['ranges'], 'ranks' => $ranks, 'base' => $ranks ];
}

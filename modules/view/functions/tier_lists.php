<?php

include_once(__DIR__ . "/ranking.php");

const TIER_LIST_TIERS = [ 'S', 'A', 'B', 'C', 'D', 'E' ];

const TIER_LIST_SHARES = [ 0.075, 0.14, 0.205, 0.235, 0.205, 0.14 ];

// "Not meta" when picks are below this fraction of the median picks
// + contest rate is below the rate floor. Both have to hold
const TIER_LIST_PICKS_FLOOR = 0.9;
const TIER_LIST_CONTEST_RATE_FLOOR = 0.05;

// A hero at the bottom is "Not meta" when the gap in plain rank up to the next hero
// is more than this many times the typical gap
const TIER_LIST_RANK_GAP = 4;

// Score = 100 * (rank/100)^SKILL * (contest/max contest)^CONTEST
// the exponents sum up to 1
const TIER_LIST_SKILL_WEIGHT = 0.75;
const TIER_LIST_CONTEST_WEIGHT = 0.25;
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
 * $display:  id => the rank to *report* per tier
 * Returns [ 'tiers' => [ 'S' => [ids], ..., 'not_meta' => [ids] ],
 *           'ranges' => [ 'S' => [min,max], ... ] ]
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

/**
 * Blend the normalized rank (0..100) with how contested the entity was.
 * $base:    id => normalized rank
 * $contest: id => picks + bans
 * Returns id => score in 0..100.
 * Contest nudges the ordering
 */
function tier_list_contest_blend($base, $contest) {
  $vals = array_values($contest);
  sort($vals);
  $max = empty($vals) ? 0 : end($vals);

  $score = [];
  foreach ($base as $id => $b) {
    $c = $contest[$id] ?? 0;
    $rel = $max > 0 ? $c / $max : 0;
    $score[$id] = 100 * pow(max(0, $b) / 100, TIER_LIST_SKILL_WEIGHT) * pow($rel, TIER_LIST_CONTEST_WEIGHT);
  }

  return $score;
}

/**
 * Heroes that are barely picked and barely contested.
 */
function tier_list_low_presence($picks, $contest, $total) {
  $vals = array_values($picks);
  sort($vals);
  $median = $vals[ (int)floor(count($vals) / 2) ] ?? 0;

  $out = [];
  if ($median <= 0 || $total <= 0) return $out;

  foreach ($picks as $id => $p) {
    $rate = ($contest[$id] ?? 0) / $total;
    if ($p < TIER_LIST_PICKS_FLOOR * $median && $rate < TIER_LIST_CONTEST_RATE_FLOOR) $out[$id] = true;
  }

  return $out;
}

/**
 * The bottom of the plain-rank list
 */
function tier_list_rank_tail($display, $skip = []) {
  $ids = [];
  foreach ($display as $id => $r) {
    if (!isset($skip[$id])) $ids[$id] = $r;
  }
  if (count($ids) < 6) return [];

  asort($ids);
  $keys = array_keys($ids);
  $vals = array_values($ids);

  $steps = [];
  for ($i = 1; $i < count($vals); $i++) $steps[] = $vals[$i] - $vals[$i - 1];
  sort($steps);
  $typical = $steps[ (int)floor(count($steps) / 2) ] ?? 0;
  if ($typical <= 0) return [];

  // only the low end of the plain-rank list
  $tail = max(3, (int)ceil(count($vals) * (TIER_LIST_SHARES[ count(TIER_LIST_SHARES) - 1 ] ?? 0.14)));
  $limit = min(count($vals) - 1, $tail);

  $cut = null;
  for ($i = 0; $i < $limit; $i++) {
    if (($vals[$i + 1] - $vals[$i]) > $typical * TIER_LIST_RANK_GAP) $cut = $i;
  }

  if ($cut === null || $cut + 1 >= $tail / 2) return [];

  $out = [];
  for ($j = 0; $j <= $cut; $j++) $out[$keys[$j]] = true;
  return $out;
}

function tier_list_heroes_overall(&$report) {
  if (empty($report['pickban'])) return null;

  $context = $report['pickban'];
  $total_matches = $report['random']['matches_total'] ?? 1;

  compound_ranking($context, $total_matches);

  $wranks = [];
  $picks = [];
  $contest = [];
  foreach ($context as $hid => $el) {
    $wranks[$hid] = $el['wrank'];
    $picks[$hid] = $el['matches_picked'] ?? 0;
    $contest[$hid] = ($el['matches_picked'] ?? 0) + ($el['matches_banned'] ?? 0);
  }
  $base = tier_list_normalize_ranks($wranks);

  $adj = tier_list_contest_blend($base, $contest);
  $adj = tier_list_apply_meta_boost($adj, $report);

  $demoted = tier_list_low_presence($picks, $contest, $total_matches);
  $demoted += tier_list_rank_tail($base, $demoted);

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

  // bans aren't tracked per role
  $role_totals = tier_list_hero_role_totals($report);
  $picks = [];
  $contest = [];
  foreach ($context as $hid => $el) {
    $p = $el['matches_s'] ?? 0;
    $bans = $report['pickban'][$hid]['matches_banned'] ?? 0;
    $share = ($role_totals[$hid] ?? 0) > 0 ? $p / $role_totals[$hid] : 0;
    $picks[$hid] = $p;
    $contest[$hid] = $p + $bans * $share;
  }

  $adj = tier_list_contest_blend($base, $contest);
  $adj = tier_list_apply_meta_boost($adj, $report);

  $demoted = tier_list_low_presence($picks, $contest, $total_matches);
  $demoted += tier_list_rank_tail($base, $demoted);

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

/**
 * [ player id => [ hero id => [ matches, wins ] ] ]
 */
function tier_list_hero_stats_by_player(&$report) {
  static $cache = null;
  if ($cache !== null) return $cache;

  $cache = [];
  foreach (tier_list_player_hero_stats($report) as $hid => $players) {
    foreach ($players as $pid => $st) $cache[$pid][$hid] = $st;
  }

  return $cache;
}

/**
 * Heroes of a single team: [ stats, games ] for tier_list_entity_heroes().
 * Contest is the team's picks plus the bans opponents spent on the hero against this team.
 * $team is a report['teams'][tid]-like array.
 */
function tier_list_team_heroes_stats($team) {
  $stats = [];
  foreach (($team['pickban'] ?? []) as $hid => $st) {
    $m = (int)($st['matches_picked'] ?? 0);
    if ($m <= 0) continue;
    $w = isset($st['wins_picked']) ? $st['wins_picked'] : round($m * ($st['winrate_picked'] ?? 0));
    $stats[$hid] = [
      'matches' => $m,
      'wins' => (int)$w,
      'bans' => (int)($team['pickban_vs'][$hid]['matches_banned'] ?? 0),
    ];
  }

  return [ $stats, (int)($team['matches_total'] ?? 0) ];
}

/**
 * Tier list of the heroes of one player/team
 * $stats: hero id => [ 'matches', 'wins', optional 'bans' ]
 * $games: how many matches the entity played
 */
function tier_list_entity_heroes($stats, $games) {
  if (empty($stats)) return null;

  $max_m = 0;
  foreach ($stats as $s) $max_m = max($max_m, $s['matches']);
  $games = max($games, $max_m);
  if (!$games) return null;

  // small samples shouldn't lose every hero to the minimum
  $min_matches = max(1, min(TIER_LIST_MIN_MATCHES, (int)floor($games / 5)));

  $wranks = [];
  $contest = [];
  $demoted = [];
  foreach ($stats as $hid => $s) {
    $wranks[$hid] = wilson_rating($s['wins'], $s['matches'], 1 - ($s['matches'] / $games));
    $contest[$hid] = $s['matches'] + ($s['bans'] ?? 0);
    if ($s['matches'] < $min_matches) $demoted[$hid] = true;
  }

  $base = tier_list_normalize_ranks($wranks);
  $score = tier_list_contest_blend($base, $contest);

  $split = tier_list_split($score, $demoted, $base);
  return [ 'tiers' => $split['tiers'], 'ranges' => $split['ranges'], 'ranks' => $score, 'base' => $base ];
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

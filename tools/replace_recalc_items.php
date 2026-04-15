<?php
/**
 * replace_recalc_items.php
 *
 * Usage: php replace_recalc_items.php A.json B.json [output.json]
 *
 * Takes items-related sections from report B and rescales them to match
 * report A's sample size, then merges them into A with these rules:
 *
 *   1. Entity only in A        → keep A's original data unchanged
 *   2. Entity only in B        → scale B's data to A's sample size and insert
 *   3. Entity in both A and B  → run a similarity test:
 *        if A's rates are statistically consistent with B's (within 2σ of B's
 *        Binomial SE), prefer A's original data;
 *        otherwise use Bayesian-scaled B data
 *
 * Sections replaced: items (stats, combos, progr, progrole, records, pi, ph,
 *                           enchantments), starting_items, skill_builds.
 *
 * Scaling model (Bayesian Beta-Binomial shrinkage):
 *   For a count k observed over n trials in B, the Laplace-smoothed rate is:
 *     p = (k + 1) / (n + 2)
 *   When projecting to n_a observations in A:
 *     k_new = round(p × n_a)
 *
 *   This naturally suppresses extreme values (0% / 100%) when n is small,
 *   and is essentially transparent when n is large.
 *
 * A settings flag is written: $report['settings']['items_recalc'] = true.
 */

ini_set('memory_limit', '8G');

require_once __DIR__.'/../modules/commons/wrap_data.php';

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------

$file_a  = $argv[1] ?? null;
$file_b  = $argv[2] ?? null;
$outfile = $argv[3] ?? null;

if (!$file_a || !$file_b) {
  die("Usage: php replace_recalc_items.php A.json B.json [output.json]\n");
}
foreach ([[$file_a, 'A'], [$file_b, 'B']] as [$f, $label]) {
  if (!is_file($f)) die("[F] $label report not found: $f\n");
}

// Default: overwrite A in-place, back up original as .old
$explicit_outfile = $outfile;
$outfile = $outfile ?: $file_a;

echo "[I] Loading A: $file_a\n";
$rep_a = json_decode((string)file_get_contents($file_a), true);
if (!$rep_a) die("[F] Could not parse A report.\n");

echo "[I] Loading B: $file_b\n";
$rep_b = json_decode((string)file_get_contents($file_b), true);
if (!$rep_b) die("[F] Could not parse B report.\n");

// ---------------------------------------------------------------------------
// Sample-size helpers
// ---------------------------------------------------------------------------

/** Extract per-hero match counts from hero_summary; key 'total' = grand sum. */
function get_hero_counts(array $report): array {
  $hs_raw = $report['hero_summary'] ?? null;
  if (!$hs_raw) {
    return ['total' => count($report['matches'] ?? [])];
  }
  $hs = unwrap_data($hs_raw);
  $counts = [];
  $total  = 0;
  foreach ($hs as $hid => $row) {
    if (!$row) continue;
    $m = (int)($row['matches_s'] ?? 0);
    $counts[(string)$hid] = $m;
    $total += $m;
  }
  $counts['total'] = $total;
  return $counts;
}

$hero_a = get_hero_counts($rep_a);
$hero_b = get_hero_counts($rep_b);

$total_a = $hero_a['total'];
$total_b = $hero_b['total'];

if ($total_b <= 0) die("[F] Report B has zero total hero-appearances; cannot scale.\n");

echo "[I] A total hero-appearances: $total_a\n";
echo "[I] B total hero-appearances: $total_b\n";

/**
 * Look up a hero count from the $counts map, tolerating string/int key mismatch.
 */
function hero_n(int|string $hid, array $counts): int {
  $s = (string)$hid;
  if (array_key_exists($s, $counts)) return (int)$counts[$s];
  if (is_numeric($hid) && array_key_exists((int)$hid, $counts)) return (int)$counts[(int)$hid];
  return 0;
}

/**
 * Resolve how many hero-appearances hero $hid has in A, using global ratio as
 * fallback for heroes absent from A's hero_summary.
 */
function hero_target(int|string $hid, array $hero_a, array $hero_b): int {
  $n_b = hero_n($hid, $hero_b);
  if ($n_b <= 0) return 0;
  $n_a = hero_n($hid, $hero_a);
  if ($n_a > 0) return $n_a;
  // hero absent from A → fall back to global ratio
  $g_a = (int)$hero_a['total'];
  $g_b = (int)$hero_b['total'];
  return $g_b > 0 ? max(0, (int)round($n_b * $g_a / $g_b)) : 0;
}

// ---------------------------------------------------------------------------
// Core scaling primitives
// ---------------------------------------------------------------------------

/** Laplace-smoothed rate: p = (k + alpha) / (n + 2*alpha). */
function smooth_rate(int $k, int $n, float $alpha = 1.0): float {
  return ($k + $alpha) / ($n + 2.0 * $alpha);
}

/** Scale a count k from n_b denominator to n_a. */
function scale_count(int $k, int $n_b, int $n_a, float $alpha = 1.0): int {
  if ($n_a <= 0) return 0;
  return max(0, (int)round(smooth_rate($k, $n_b, $alpha) * $n_a));
}

/** Smooth a rate value (not count) when no sub-count is available. */
function smooth_rate_value(float $rate, int $n_obs, float $alpha = 1.0): float {
  return smooth_rate((int)round($rate * $n_obs), $n_obs, $alpha);
}

// ---------------------------------------------------------------------------
// Similarity test
//
// Returns true when A's rates are within 2 standard errors of B's estimates,
// meaning A's data is statistically consistent with B and we should keep A.
// ---------------------------------------------------------------------------

function item_stats_similar(array $a_item, array $b_item, int $n_b): bool {
  $prate_a = (float)($a_item['prate']   ?? 0);
  $prate_b = (float)($b_item['prate']   ?? 0);
  $purch_b = max(1, (int)($b_item['purchases'] ?? 0));

  // 2σ CI on B's prate using hero match count as denominator
  $prate_se = $n_b > 0 ? sqrt($prate_b * (1.0 - $prate_b) / $n_b) : 1.0;
  if (abs($prate_a - $prate_b) > 2.0 * $prate_se) return false;

  // 2σ CI on B's winrate using purchase count as denominator
  $wr_a  = (float)($a_item['winrate'] ?? 0);
  $wr_b  = (float)($b_item['winrate'] ?? 0);
  $wr_se = sqrt($wr_b * (1.0 - $wr_b) / $purch_b);
  if (abs($wr_a - $wr_b) > 2.0 * $wr_se) return false;

  return true;
}

// ---------------------------------------------------------------------------
// Section: items/stats
//
// Merges A and B with the three-rule strategy; re-wraps into original format.
// ---------------------------------------------------------------------------

/**
 * Scale a single item stat dict (with field names as keys) from n_b → n_a.
 */
function scale_single_item(array $item, int $n_b, int $n_a): array {
  $purchases_b  = max(0, (int)($item['purchases']  ?? 0));
  $wins_b       = max(0, (int)($item['wins']       ?? 0));
  $matchcount_b = max(0, (int)($item['matchcount'] ?? 0));

  $purchases_a  = scale_count($purchases_b,  $n_b, $n_a);
  $matchcount_a = scale_count($matchcount_b, $n_b, $n_a);
  $wins_a       = $purchases_a > 0 ? scale_count($wins_b, $purchases_b, $purchases_a) : 0;

  $winrate_a = $purchases_a > 0 ? round($wins_a / $purchases_a, 4) : 0.0;
  $prate_a   = $n_a > 0 ? round($matchcount_a / $n_a, 4) : 0.0;

  $wo_matches_b = max(0, $n_b - $matchcount_b);
  $wo_wr_b      = (float)($item['wo_wr'] ?? 0);
  $wo_wins_b    = (int)round($wo_wr_b * $wo_matches_b);
  $wo_matches_a = max(0, $n_a - $matchcount_a);
  $wo_wins_a    = $wo_matches_a > 0 ? scale_count($wo_wins_b, $wo_matches_b, $wo_matches_a) : 0;
  $wo_wr_a      = $wo_matches_a > 0 ? round($wo_wins_a / $wo_matches_a, 4) : 0.0;

  $early_wr_a = round(smooth_rate_value((float)($item['early_wr'] ?? 0), $purchases_b), 4);
  $late_wr_a  = round(smooth_rate_value((float)($item['late_wr']  ?? 0), $purchases_b), 4);

  $q1 = (float)($item['q1'] ?? 0);
  $q3 = (float)($item['q3'] ?? 0);
  $min_span_min = max(1, (abs($q3) - abs($q1)) / 60.0);
  $grad_a = round(($late_wr_a - $early_wr_a) / $min_span_min, 4);

  return array_merge($item, [
    'purchases'  => $purchases_a,
    'wins'       => $wins_a,
    'matchcount' => $matchcount_a,
    'winrate'    => $winrate_a,
    'prate'      => $prate_a,
    'wo_wr'      => $wo_wr_a,
    'early_wr'   => $early_wr_a,
    'late_wr'    => $late_wr_a,
    'grad'       => $grad_a,
  ]);
}

function scale_items_stats(array $b_raw, ?array $a_raw, array $hero_a, array $hero_b): array {
  if (empty($b_raw['head']) || !is_array($b_raw['head'][0])) return $b_raw;

  // Decode both into {hero_id: {item_id: {field: val}}}
  $b_stats = unwrap_data($b_raw);
  $a_stats = $a_raw ? unwrap_data($a_raw) : [];

  $all_heroes = array_unique(array_merge(array_keys($b_stats), array_keys($a_stats)));
  $merged     = [];

  foreach ($all_heroes as $hid) {
    $b_hero = $b_stats[$hid] ?? [];
    $a_hero = $a_stats[$hid] ?? [];

    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);

    $all_items  = array_unique(array_merge(array_keys($b_hero), array_keys($a_hero)));
    $merged_hero = [];

    foreach ($all_items as $iid) {
      $b_item = $b_hero[$iid] ?? null;
      $a_item = $a_hero[$iid] ?? null;

      if ($b_item === null) {
        // Rule 1: only in A → keep A
        $merged_hero[$iid] = $a_item;
      } elseif ($a_item === null || $n_b <= 0 || $n_a <= 0) {
        // Rule 2: only in B (or no scale info) → scale B
        $merged_hero[$iid] = ($n_b > 0 && $n_a > 0) ? scale_single_item($b_item, $n_b, $n_a) : $b_item;
      } else {
        // Rule 3: both exist → similarity test
        $merged_hero[$iid] = item_stats_similar($a_item, $b_item, $n_b)
          ? $a_item
          : scale_single_item($b_item, $n_b, $n_a);
      }
    }

    $merged[$hid] = $merged_hero;
  }

  return wrap_data($merged, true, true, true);
}

// ---------------------------------------------------------------------------
// Section: items/combos
// ---------------------------------------------------------------------------

function scale_items_combos(array $b_raw, ?array $a_raw, int $n_b_total, int $n_a_total): array {
  if (empty($b_raw)) return $b_raw;

  $b_combos = unwrap_data($b_raw);
  $a_combos = $a_raw ? unwrap_data($a_raw) : [];

  // Start from A's data; inject/update from B where B has a notably higher pair rate.
  $merged = $a_combos;

  foreach ($b_combos as $iid1 => $pairs) {
    if (!is_array($pairs)) continue;
    foreach ($pairs as $iid2 => $b_data) {
      if (!is_array($b_data) || !isset($b_data['matches'])) continue;

      $m_b    = max(0, (int)$b_data['matches']);
      $rate_b = $n_b_total > 0 ? $m_b / $n_b_total : 0.0;
      $se_b   = $n_b_total > 0 ? sqrt(max(0.0, $rate_b * (1.0 - $rate_b)) / $n_b_total) : 1.0;

      if (isset($a_combos[$iid1][$iid2])) {
        $m_a_ex  = max(0, (int)($a_combos[$iid1][$iid2]['matches'] ?? 0));
        $rate_a  = $n_a_total > 0 ? $m_a_ex / $n_a_total : 0.0;
        // Keep A unless B's rate is notably higher (more than 1 SE above A)
        if ($rate_b <= $rate_a + $se_b) {
          $merged[$iid1][$iid2] = $a_combos[$iid1][$iid2];
          continue;
        }
      }

      // Missing in A, or B rate clearly higher → scale B
      $w_b = max(0, (int)$b_data['wins']);
      $e_b = max(0, (int)($b_data['exp'] ?? 0));
      $m_a = scale_count($m_b, $n_b_total, $n_a_total);
      $w_a = $m_a > 0 ? scale_count($w_b, $m_b, $m_a) : 0;
      $e_a = $m_a > 0 ? scale_count($e_b, $m_b, $m_a) : 0;
      $merged[$iid1][$iid2] = array_merge($b_data, [
        'matches' => $m_a,
        'wins'    => $w_a,
        'exp'     => $e_a,
      ]);
    }
  }

  return wrap_data($merged, true, true, true);
}

// ---------------------------------------------------------------------------
// Section: items/progr and items/progrole
// ---------------------------------------------------------------------------

/**
 * Merge B's progression pairs into A's, preferring A when the pair exists in both.
 * B-only pairs are scaled from n_b → n_a.
 */
function scale_progr_pairs(array $b_pairs, array $a_pairs_by_key, int $n_b, int $n_a): array {
  $out      = [];
  $b_keys   = [];

  foreach ($b_pairs as $pair) {
    if (empty($pair)) continue;
    $key = ($pair['item1'] ?? '').'-'.($pair['item2'] ?? '');
    $b_keys[] = $key;

    $t_b    = max(0, (int)($pair['total'] ?? 0));
    $w_b    = max(0, (int)($pair['wins']  ?? 0));
    $rate_b = $n_b > 0 ? $t_b / $n_b : 0.0;
    $se_b   = $n_b > 0 ? sqrt(max(0.0, $rate_b * (1.0 - $rate_b)) / $n_b) : 1.0;

    if (isset($a_pairs_by_key[$key])) {
      $t_a_ex  = max(0, (int)($a_pairs_by_key[$key]['total'] ?? 0));
      $rate_a  = $n_a > 0 ? $t_a_ex / $n_a : 0.0;
      // Keep A unless B's pair rate is notably higher
      if ($rate_b <= $rate_a + $se_b) {
        $out[] = $a_pairs_by_key[$key];
        continue;
      }
    }

    // Missing in A or B rate clearly higher → scale B
    $t_a = scale_count($t_b, $n_b, $n_a);
    $w_a = $t_a > 0 ? scale_count($w_b, $t_b, $t_a) : 0;
    $out[] = array_merge($pair, [
      'total'   => $t_a,
      'wins'    => $w_a,
      'winrate' => $t_a > 0 ? round($w_a / $t_a, 4) : 0.0,
    ]);
  }

  // A-only pairs
  foreach ($a_pairs_by_key as $key => $a_pair) {
    if (!in_array($key, $b_keys, true)) {
      $out[] = $a_pair;
    }
  }

  return $out;
}

function index_progr_pairs(array $pairs): array {
  $idx = [];
  foreach ($pairs as $p) {
    $key = ($p['item1'] ?? '').'-'.($p['item2'] ?? '');
    $idx[$key] = $p;
  }
  return $idx;
}

function scale_items_progr(array $b_raw, ?array $a_raw, array $hero_a, array $hero_b): array {
  if (empty($b_raw)) return $b_raw;
  $b_progr = unwrap_data($b_raw);
  $a_progr = $a_raw ? unwrap_data($a_raw) : [];

  $all_heroes = array_unique(array_merge(array_keys($b_progr), array_keys($a_progr)));

  foreach ($all_heroes as $hid) {
    $b_pairs = $b_progr[$hid] ?? null;
    $a_pairs = $a_progr[$hid] ?? [];

    if ($b_pairs === null) {
      $b_progr[$hid] = $a_pairs;
      continue;
    }

    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);
    if ($n_b <= 0 || $n_a <= 0) continue;

    $b_progr[$hid] = scale_progr_pairs($b_pairs, index_progr_pairs($a_pairs), $n_b, $n_a);
  }

  return wrap_data($b_progr, true, true, true);
}

function scale_items_progrole(array $b_raw, ?array $a_raw, array $hero_a, array $hero_b): array {
  if (empty($b_raw)) return $b_raw;

  $keys   = $b_raw['keys'] ?? [];
  $b_data = $b_raw['data'] ?? [];
  $a_data = ($a_raw && isset($a_raw['data'])) ? $a_raw['data'] : [];

  foreach ($b_data as $hid => &$roles) {
    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);
    if ($n_b <= 0 || $n_a <= 0) continue;

    $a_hero_roles = $a_data[$hid] ?? [];

    foreach ($roles as $roleid => &$pairs_encoded) {
      $decode_pair = function(array $elem) use ($keys): array {
        return !empty($keys) ? array_combine($keys, $elem) : $elem;
      };
      $encode_pair = function(array $p) use ($keys): array {
        if (empty($keys)) return $p;
        $out = [];
        foreach ($keys as $k) { $out[] = $p[$k] ?? null; }
        return $out;
      };

      $b_pairs = array_map($decode_pair, $pairs_encoded);
      $a_pairs = array_map($decode_pair, $a_hero_roles[$roleid] ?? []);

      $scaled        = scale_progr_pairs($b_pairs, index_progr_pairs($a_pairs), $n_b, $n_a);
      $pairs_encoded = array_map($encode_pair, $scaled);
    }
  }

  // Carry heroes/roles only in A
  foreach ($a_data as $hid => $roles) {
    if (!isset($b_data[$hid])) {
      $b_data[$hid] = $roles;
    } else {
      foreach ($roles as $rid => $pairs) {
        if (!isset($b_data[$hid][$rid])) $b_data[$hid][$rid] = $pairs;
      }
    }
  }

  return ['keys' => $keys, 'data' => $b_data];
}

// ---------------------------------------------------------------------------
// Section: items/enchantments
// ---------------------------------------------------------------------------

function scale_enchantments(array $b_enc, ?array $a_enc, array $hero_a, array $hero_b): array {
  $a_enc = $a_enc ?? [];

  $all_heroes = array_unique(array_merge(array_keys($b_enc), array_keys($a_enc)));

  foreach ($all_heroes as $hid) {
    $a_hero = $a_enc[$hid] ?? [];

    if (!isset($b_enc[$hid])) {
      $b_enc[$hid] = $a_hero; // A-only hero
      continue;
    }

    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);
    if ($n_b <= 0 || $n_a <= 0) continue;

    // Iterate $b_enc[$hid] directly by reference — $b_hero = $b_enc[$hid] would be a
    // copy and modifications via &$b_items would never write back.
    foreach ($b_enc[$hid] as $cat_id => &$b_items) {
      if (empty($b_items)) continue;
      $a_items = $a_hero[$cat_id] ?? [];

      // The real denominator per category is cat_total (= matches + matches_wo for any item),
      // not the hero's total match count. For tier categories it's the sum of all picks in
      // that tier; for category 0 it's the hero's total enchantment pickups.
      $n_enc_b = 0;
      foreach ($b_items as $idata) {
        if (isset($idata['matches_wo'])) {
          $n_enc_b = (int)($idata['matches'] ?? 0) + (int)($idata['matches_wo'] ?? 0);
          break;
        }
      }
      if ($n_enc_b <= 0) {
        // fallback: sum of matches in this category
        foreach ($b_items as $idata) { $n_enc_b += (int)($idata['matches'] ?? 0); }
      }
      if ($n_enc_b <= 0) continue;

      // Always scale proportionally to A's match count.
      // Never derive from A's existing enchantment data which may be stale/wrong.
      $n_enc_a = $n_b > 0 ? (int)round($n_enc_b * $n_a / $n_b) : 0;
      if ($n_enc_a <= 0) continue;

      foreach ($b_items as $iid => &$idata) {
        $a_idata = $a_items[$iid] ?? null;

        $m_b   = max(0, (int)($idata['matches'] ?? 0));
        $w_b   = max(0, (int)($idata['wins']    ?? 0));
        $wo_b  = max(0, (int)($idata['matches_wo'] ?? max(0, $n_enc_b - $m_b)));
        $wo_wr_b = (float)($idata['wr_wo'] ?? 0);
        $wo_wins_b = (int)round($wo_wr_b * $wo_b);

        if ($a_idata !== null) {
          $m_a   = max(0, (int)($a_idata['matches'] ?? 0));
          $wr_a  = (float)($a_idata['wr'] ?? 0);
          $wr_b  = $m_b > 0 ? $w_b / $m_b : 0.0;
          $wr_se = $m_b > 0 ? sqrt($wr_b * (1.0 - $wr_b) / $m_b) : 1.0;
          $expected_m = scale_count($m_b, $n_enc_b, $n_enc_a);
          if (abs($wr_a - $wr_b) <= 2.0 * $wr_se && abs($m_a - $expected_m) <= max(1, $expected_m * 0.25)) {
            continue; // similar → keep A
          }
        }

        $m_a  = scale_count($m_b, $n_enc_b, $n_enc_a);
        $w_a  = $m_a > 0 ? scale_count($w_b, $m_b, $m_a) : 0;
        $wo_a = max(0, $n_enc_a - $m_a);
        $wo_wins_a = ($wo_a > 0 && $wo_b > 0) ? scale_count($wo_wins_b, $wo_b, $wo_a) : 0;

        $idata['matches']    = $m_a;
        $idata['wins']       = $w_a;
        $idata['wr']         = $m_a > 0 ? round($w_a / $m_a, 4) : 0.0;
        $idata['matches_wo'] = $wo_a;
        $idata['wr_wo']      = $wo_a > 0 ? round($wo_wins_a / $wo_a, 4) : 0.0;
      }

      // A-only items in this category
      foreach ($a_items as $iid => $a_idata) {
        if (!isset($b_items[$iid])) $b_items[$iid] = $a_idata;
      }
    }
    unset($b_items);

    // A-only categories
    foreach ($a_hero as $cat_id => $a_items) {
      if (!isset($b_enc[$hid][$cat_id])) $b_enc[$hid][$cat_id] = $a_items;
    }
  }

  return $b_enc;
}

// ---------------------------------------------------------------------------
// Section: starting_items
//
// ALL three sub-sections are wrap_data structures.
//   matches[role]       = wrap_data({hid: {m,wr,l}},  with_keys, deep, explicit)
//   items[role][hid]    = wrap_data({iid: {matches,wins,lane_wins,freq}}, ...) with 'head' REMOVED
//                         (head is stored once at items_head)
//   builds[role]        = wrap_data({hid: [build_obj,...]}, with_keys, deep, explicit)
//                         (deeply wrapped since build values are arrays)
// After unwrap_data:
//   matches[role]    → {hid: {m,wr,l}}
//   items[role][hid] → {iid: {matches,wins,lane_wins,freq}}  (restored by prepending items_head)
//   builds[role]     → {hid: {idx: build_obj}}  (builds indexed by position)
// ---------------------------------------------------------------------------

function scale_starting_items(array $b_si, ?array $a_si, array $hero_a, array $hero_b): array {
  $a_si = $a_si ?? [];
  $items_head = $b_si['items_head'] ?? ['matches', 'wins', 'lane_wins', 'freq'];

  // Helper: unwrap a wrapped per-role dict (matches or builds).
  $uw = fn(?array $w): array => (is_array($w) && !empty($w)) ? (unwrap_data($w) ?: []) : [];

  // Helper: unwrap a per-hero items entry (stored without 'head'; head in items_head).
  $uw_items = function(?array $w) use ($items_head): array {
    if (!is_array($w) || empty($w)) return [];
    return unwrap_data(array_merge(['head' => $items_head], $w)) ?: [];
  };

  // Helper: re-wrap a per-hero items dict and remove 'head' (stored centrally).
  $rw_items = function(array $items) use ($items_head): array {
    if (empty($items)) return [];
    $w = wrap_data($items, true, true, true);
    unset($w['head']);
    return $w;
  };

  // --- Build per-role hero match counts from UNWRAPPED matches ---
  $role_hero_b        = [];
  $role_hero_a        = [];
  $unwrapped_matches  = []; // store for later use in builds section

  foreach (($b_si['matches'] ?? []) as $role => $b_matches_wrapped) {
    $b_role  = $uw($b_matches_wrapped);  // {hid: {m, wr, l}}
    $a_role  = $uw($a_si['matches'][$role] ?? null);
    $unwrapped_matches[$role] = ['b' => $b_role, 'a' => $a_role];

    if (empty($b_role)) continue;
    $role_hero_b[$role] = [];
    $role_hero_a[$role] = [];
    foreach ($b_role as $hid => $row) {
      if (!is_array($row)) continue;
      $n_b = max(0, (int)($row['m'] ?? 0));
      $role_hero_b[$role][(string)$hid] = $n_b;
      $a_row = $a_role[$hid] ?? null;
      if (is_array($a_row) && (int)($a_row['m'] ?? 0) > 0) {
        $role_hero_a[$role][(string)$hid] = (int)$a_row['m'];
      } else {
        $h_b = hero_n($hid, $hero_b);
        $h_a = hero_target($hid, $hero_a, $hero_b);
        $role_hero_a[$role][(string)$hid] = $h_b > 0 ? max(0, (int)round($n_b * $h_a / $h_b)) : 0;
      }
    }
  }

  $out = $b_si;
  $out_matches_plain = []; // {role: {hid: row}}; populated by the matches section, used in builds

  // --- matches (unwrap → scale → re-wrap) ---
  if (!empty($b_si['matches'])) {
    $out_matches_wrapped = [];
    foreach ($b_si['matches'] as $role => $b_matches_wrapped) {
      $b_role = $unwrapped_matches[$role]['b'] ?? [];
      $a_role = $unwrapped_matches[$role]['a'] ?? [];
      $out_role = [];

      foreach ($b_role as $hid => $row) {
        if (!is_array($row)) continue;
        $hid_s = (string)$hid;
        $n_b   = $role_hero_b[$role][$hid_s] ?? 0;
        $n_a   = $role_hero_a[$role][$hid_s] ?? 0;
        $a_row = $a_role[$hid] ?? null;

        if (is_array($a_row) && (int)($a_row['m'] ?? 0) > 0) {
          $out_role[$hid] = $a_row; continue;
        }
        if ($n_b <= 0 || $n_a <= 0) {
          $out_role[$hid] = $row; continue;
        }
        $new_row        = $row;
        $new_row['m']   = $n_a;
        $new_row['wr']  = round(smooth_rate_value((float)($row['wr'] ?? 0), $n_b), 4);
        if (isset($row['l'])) $new_row['l'] = scale_count((int)$row['l'], $n_b, $n_a);
        $out_role[$hid] = $new_row;
      }
      foreach ($a_role as $hid => $a_row) {
        if (!isset($out_role[$hid]) && is_array($a_row)) $out_role[$hid] = $a_row;
      }
      $out_matches_plain[$role]   = $out_role;
      $out_matches_wrapped[$role] = !empty($out_role) ? wrap_data($out_role, true, true, true) : $b_matches_wrapped;
    }
    $out['matches'] = $out_matches_wrapped;
  }

  // --- items (unwrap → scale → re-wrap, no similarity check) ---
  if (!empty($b_si['items'])) {
    $out_items_all = [];
    foreach ($b_si['items'] as $role => $b_role_heroes) {
      if (!is_array($b_role_heroes)) continue;
      $out_role = [];
      foreach ($b_role_heroes as $hid => $b_hero_wrapped) {
        $hid_s = (string)$hid;
        $n_b = $role_hero_b[$role][$hid_s] ?? 0;
        $n_a = $role_hero_a[$role][$hid_s] ?? 0;
        if ($n_b <= 0) $n_b = hero_n($hid, $hero_b);
        if ($n_a <= 0) $n_a = hero_target($hid, $hero_a, $hero_b);

        $b_items = $uw_items($b_hero_wrapped);        // {iid: {matches,wins,lane_wins,freq}}
        $a_items = $uw_items($a_si['items'][$role][$hid] ?? null);

        $out_hero = [];
        foreach ($b_items as $iid => $iv) {
          if (!is_array($iv)) continue;
          if ($n_b <= 0 || $n_a <= 0) { $out_hero[$iid] = $iv; continue; }
          $m_b  = max(0, (int)($iv['matches']   ?? 0));
          $w_b  = max(0, (int)($iv['wins']       ?? 0));
          $lw_b = max(0, (int)($iv['lane_wins']  ?? 0));
          $m_a  = scale_count($m_b, $n_b, $n_a);
          $w_a  = $m_a > 0 ? scale_count($w_b,  $m_b, $m_a) : 0;
          $lw_a = $m_a > 0 ? scale_count($lw_b, $m_b, $m_a) : 0;
          $out_hero[$iid] = array_merge($iv, [
            'matches'   => $m_a,
            'wins'      => $w_a,
            'lane_wins' => $lw_a,
            'freq'      => $n_a > 0 ? round($m_a / $n_a, 4) : 0.0,
          ]);
        }
        foreach ($a_items as $iid => $a_iv) {
          if (!isset($out_hero[$iid]) && is_array($a_iv)) $out_hero[$iid] = $a_iv;
        }
        $out_role[$hid] = !empty($out_hero) ? $rw_items($out_hero) : $b_hero_wrapped;
      }
      foreach (($a_si['items'][$role] ?? []) as $hid => $a_w) {
        if (!isset($out_role[$hid]) && is_array($a_w)) $out_role[$hid] = $a_w;
      }
      $out_items_all[$role] = $out_role;
    }
    $out['items'] = $out_items_all;
  }

  // --- builds (unwrap → scale → re-wrap) ---
  // After unwrap_data, builds[role] → {hid: {idx: build_obj}}
  if (!empty($b_si['builds'])) {
    $out_builds_all = [];
    foreach ($b_si['builds'] as $role => $b_builds_wrapped) {
      $b_role_builds = $uw($b_builds_wrapped);          // {hid: {idx: build_obj}}
      $a_role_builds = $uw($a_si['builds'][$role] ?? null);
      $out_role      = [];

      foreach ($b_role_builds as $hid => $b_hero_builds) {
        if (!is_array($b_hero_builds)) continue;
        $hid_s  = (string)$hid;
        $n_b    = $role_hero_b[$role][$hid_s] ?? 0;
        $n_a    = $role_hero_a[$role][$hid_s] ?? 0;

        // lim_a from the already-scaled matches row (plain, stored above)
        $lim_a  = 1;
        $mr_row = $out_matches_plain[$role][$hid] ?? null;
        if (is_array($mr_row)) $lim_a = max(1, (int)($mr_row['l'] ?? 1));

        // Index A's builds by fingerprint
        $a_hero_builds = $a_role_builds[$hid] ?? [];
        $a_by_tag = [];
        foreach ($a_hero_builds as $ab) {
          if (!is_array($ab) || empty($ab['build'])) continue;
          $a_by_tag[implode(',', $ab['build'])] = $ab;
        }

        if ($n_b <= 0) $n_b = hero_n($hid, $hero_b);
        if ($n_a <= 0) $n_a = hero_target($hid, $hero_a, $hero_b);

        if ($n_b <= 0 || $n_a <= 0) {
          $out_role[$hid] = array_values(array_filter(
            array_values($a_by_tag), fn($ab) => (int)($ab['matches'] ?? 0) >= $lim_a));
          continue;
        }

        $builds_out = [];
        foreach ($b_hero_builds as $bld) {
          if (!is_array($bld) || empty($bld)) continue;
          $tag = implode(',', $bld['build'] ?? []);

          if ($n_a >= 30 && count($bld['build'] ?? []) <= 1) continue;

          $m_b        = max(0, (int)($bld['matches'] ?? 0));
          $expected_a = scale_count($m_b, $n_b, $n_a);

          $a_bld = $a_by_tag[$tag] ?? null;
          if ($a_bld !== null) {
            $m_a_actual = max(0, (int)($a_bld['matches'] ?? 0));
            if ($m_a_actual >= $lim_a && ($expected_a <= 0 || $m_a_actual >= (int)($expected_a * 0.4))) {
              $builds_out[] = $a_bld; continue;
            }
          }

          $m_a = $expected_a;
          if ($m_a < $lim_a) continue;
          $w_b  = max(0, (int)($bld['wins']      ?? 0));
          $lw_b = max(0, (int)($bld['lane_wins'] ?? 0));
          $w_a  = $m_a > 0 ? scale_count($w_b,  $m_b, $m_a) : 0;
          $lw_a = $m_a > 0 ? scale_count($lw_b, $m_b, $m_a) : 0;
          $builds_out[] = array_merge($bld, [
            'matches'   => $m_a,  'wins'    => $w_a,  'lane_wins' => $lw_a,
            'winrate'   => $m_a > 0 ? round($w_a  / $m_a, 4) : 0.0,
            'lane_wr'   => $m_a > 0 ? round($lw_a / $m_a, 4) : 0.0,
            'ratio'     => $n_a > 0 ? round($m_a  / $n_a, 4) : 0.0,
          ]);
        }

        // A-only builds
        $used = array_map(fn($ob) => implode(',', $ob['build'] ?? []), $builds_out);
        foreach ($a_by_tag as $tag => $ab) {
          if (!in_array($tag, $used, true) && (int)($ab['matches'] ?? 0) >= $lim_a)
            $builds_out[] = $ab;
        }
        $out_role[$hid] = $builds_out;
      }
      // A-only heroes
      foreach ($a_role_builds as $hid => $ab_list) {
        if (!isset($out_role[$hid]) && is_array($ab_list))
          $out_role[$hid] = array_values(array_filter(array_values($ab_list), 'is_array'));
      }
      // Re-wrap: {hid: [b0,b1,...]} → same deeply-wrapped format as original
      $out_builds_all[$role] = !empty($out_role) ? wrap_data($out_role, true, true, true) : $b_builds_wrapped;
    }
    $out['builds'] = $out_builds_all;
  }

  // --- consumables (wrapped structure) ---
  if (!empty($b_si['consumables'])) {
    foreach ($b_si['consumables'] as $blk => &$b_roles) {
      foreach ($b_roles as $role => &$b_hero_map) {
        foreach ($b_hero_map as $hid => &$b_wrapped) {
          $hid_s = (string)$hid;
          $n_b = $role_hero_b[$role][$hid_s] ?? 0;
          $n_a = $role_hero_a[$role][$hid_s] ?? 0;
          // Fallback to global hero counts if role-specific are unavailable
          if ($n_b <= 0) $n_b = hero_n($hid, $hero_b);
          if ($n_a <= 0) $n_a = hero_target($hid, $hero_a, $hero_b);
          if ($n_b <= 0 || $n_a <= 0) continue;

          $cons_head = $b_si['cons_head'] ?? ['min', 'q1', 'med', 'q3', 'max', 'total', 'matches'];
          $b_cons = is_wrapped($b_wrapped)
            ? unwrap_data($b_wrapped)
            : unwrap_data(['head' => $cons_head, 'data' => array_values($b_wrapped), 'keys' => array_keys($b_wrapped)]);

          $a_cons = [];
          $a_cw   = $a_si['consumables'][$blk][$role][$hid] ?? null;
          if ($a_cw !== null) {
            $a_cons = is_wrapped($a_cw)
              ? unwrap_data($a_cw)
              : unwrap_data(['head' => $cons_head, 'data' => array_values($a_cw), 'keys' => array_keys($a_cw)]);
          }

          // Always scale B → A (no similarity check; freq is scale-invariant and
          // would falsely keep stale A data). A-only items are added below.
          $b_cons_out = [];
          foreach ($b_cons as $iid => $cv) {
            if (!$cv) continue;
            $m_b = max(0, (int)($cv['matches'] ?? 0));
            $t_b = max(0, (int)($cv['total']   ?? 0));
            $m_a = scale_count($m_b, $n_b, $n_a);
            $t_a = $m_a > 0 && $m_b > 0 ? scale_count($t_b, $m_b, $m_a) : 0;
            $b_cons_out[$iid] = array_merge($cv, ['matches' => $m_a, 'total' => $t_a]);
          }
          $b_cons = $b_cons_out;

          // A-only consumable items
          foreach ($a_cons as $iid => $a_cv) {
            if (!isset($b_cons[$iid]) && $a_cv) $b_cons[$iid] = $a_cv;
          }

          $b_wrapped = wrap_data($b_cons, true, true, true);
        }
      }
    }
    $out['consumables'] = $b_si['consumables'];

    // Carry A-only consumable heroes/roles not present in B
    foreach (($a_si['consumables'] ?? []) as $blk => $a_roles) {
      foreach ($a_roles as $role => $a_hero_map) {
        foreach ($a_hero_map as $hid => $a_cw) {
          if (!isset($out['consumables'][$blk][$role][$hid])) {
            $out['consumables'][$blk][$role][$hid] = $a_cw;
          }
        }
      }
    }
  } elseif (!empty($a_si['consumables'])) {
    $out['consumables'] = $a_si['consumables'];
  }

  return $out;
}

// ---------------------------------------------------------------------------
// skill_builds (generic walker)
// ---------------------------------------------------------------------------

function scale_skill_builds(array $b_sb, ?array $a_sb, array $hero_a, array $hero_b): array {
  $a_sb = $a_sb ?? [];
  foreach ($b_sb as $hid => &$hdata) {
    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);
    if ($n_b <= 0 || $n_a <= 0) continue;

    array_walk_recursive($hdata, function (&$val, $key) use ($n_b, $n_a) {
      if (is_int($val) && in_array($key, ['matches', 'wins', 'count', 'total'], true)) {
        $val = scale_count($val, $n_b, $n_a);
      }
    });
  }
  // A-only heroes
  foreach ($a_sb as $hid => $hdata) {
    if (!isset($b_sb[$hid])) $b_sb[$hid] = $hdata;
  }
  return $b_sb;
}

// ---------------------------------------------------------------------------
// Run scaling + merging for each section
// ---------------------------------------------------------------------------

$sections_replaced = [];

// --- items ---
if (!empty($rep_b['items'])) {
  $items_b   = $rep_b['items'];
  $items_a   = $rep_a['items'] ?? [];
  $items_out = $items_a;

  if (!empty($items_b['stats'])) {
    echo "[I] Scaling/merging items/stats…\n";
    $items_out['stats'] = scale_items_stats(
      $items_b['stats'],
      $items_a['stats'] ?? null,
      $hero_a, $hero_b
    );
  }

  // pi and ph: purchase timing distributions — merge B over A, keep A when B has nothing
  foreach (['pi', 'ph'] as $k) {
    if (!empty($items_b[$k])) {
      $b_d = unwrap_data($items_b[$k]);
      $a_d = !empty($items_a[$k]) ? unwrap_data($items_a[$k]) : [];
      foreach ($a_d as $iid => $val) {
        if (!isset($b_d[$iid])) $b_d[$iid] = $val;
      }
      $items_out[$k] = wrap_data($b_d, true, true, true);
      echo "[I] Copying/merging items/$k from B (distribution-invariant)\n";
    }
  }

  if (!empty($items_b['combos'])) {
    echo "[I] Scaling/merging items/combos…\n";
    $items_out['combos'] = scale_items_combos(
      $items_b['combos'],
      $items_a['combos'] ?? null,
      $total_b, $total_a
    );
  }

  if (!empty($items_b['progr'])) {
    echo "[I] Scaling/merging items/progr…\n";
    $items_out['progr'] = scale_items_progr(
      $items_b['progr'],
      $items_a['progr'] ?? null,
      $hero_a, $hero_b
    );
  }

  if (!empty($items_b['progrole'])) {
    echo "[I] Scaling/merging items/progrole…\n";
    $keys_b = $items_b['progrole']['keys'] ?? [];
    $items_out['progrole'] = scale_items_progrole(
      $items_b['progrole'],
      $items_a['progrole'] ?? null,
      $hero_a, $hero_b
    );
    if (empty($items_out['progrole']['keys'])) {
      $items_out['progrole']['keys'] = $keys_b;
    }
  }

  // records: merge A and B; A's entries take precedence for shared item keys
  if (!empty($items_b['records']) || !empty($items_a['records'])) {
    $b_rec = $items_b['records'] ?? [];
    $a_rec = $items_a['records'] ?? [];
    if (is_wrapped($b_rec) && !empty($a_rec)) {
      $b_rd = unwrap_data($b_rec);
      $a_rd = is_wrapped($a_rec) ? unwrap_data($a_rec) : $a_rec;
      // A overrides B for shared keys; B-only entries are included
      $items_out['records'] = wrap_data($a_rd + $b_rd, true, true, true);
    } elseif (!empty($a_rec)) {
      // Both plain associative or A only
      $items_out['records'] = is_array($b_rec) ? ($a_rec + $b_rec) : $a_rec;
    } else {
      $items_out['records'] = $b_rec;
    }
    echo "[I] Merging items/records from A and B\n";
  }

  if (!empty($items_b['enchantments'])) {
    echo "[I] Scaling/merging items/enchantments…\n";
    $items_out['enchantments'] = scale_enchantments(
      $items_b['enchantments'],
      $items_a['enchantments'] ?? null,
      $hero_a, $hero_b
    );
  }

  $rep_a['items'] = $items_out;
  $sections_replaced[] = 'items';
}

// --- top-level enchantments ---
if (!empty($rep_b['enchantments'])) {
  echo "[I] Scaling/merging enchantments (top-level)…\n";
  $rep_a['enchantments'] = scale_enchantments(
    $rep_b['enchantments'],
    $rep_a['enchantments'] ?? null,
    $hero_a, $hero_b
  );
  $sections_replaced[] = 'enchantments';
}

// --- starting_items ---
if (!empty($rep_b['starting_items'])) {
  echo "[I] Scaling/merging starting_items…\n";
  $rep_a['starting_items'] = scale_starting_items(
    $rep_b['starting_items'],
    $rep_a['starting_items'] ?? null,
    $hero_a, $hero_b
  );
  foreach (['items_head', 'cons_head'] as $hk) {
    if (isset($rep_b['starting_items'][$hk])) {
      $rep_a['starting_items'][$hk] = $rep_b['starting_items'][$hk];
    }
  }
  $sections_replaced[] = 'starting_items';
}

// --- skill_builds ---
if (!empty($rep_b['skill_builds'])) {
  echo "[I] Scaling/merging skill_builds…\n";
  $rep_a['skill_builds'] = scale_skill_builds(
    $rep_b['skill_builds'],
    $rep_a['skill_builds'] ?? null,
    $hero_a, $hero_b
  );
  $sections_replaced[] = 'skill_builds';
}

if (empty($sections_replaced)) {
  die("[W] Report B has none of the target sections. Nothing to do.\n");
}

// ---------------------------------------------------------------------------
// Write settings flag
// ---------------------------------------------------------------------------

if (!isset($rep_a['settings'])) $rep_a['settings'] = [];
$rep_a['settings']['items_recalc'] = true;

// ---------------------------------------------------------------------------
// Save output
// ---------------------------------------------------------------------------

$json_out = json_encode($rep_a, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

// Back up A as .old when writing in-place
if (!$explicit_outfile && $outfile === $file_a) {
  $backup = $file_a.'.old';
  if (!rename($file_a, $backup)) die("[F] Could not rename $file_a → $backup\n");
  echo "[I] Backed up original A to: $backup\n";
}

file_put_contents($outfile, $json_out);

$sz = number_format(strlen($json_out) / 1024, 1);
echo "[S] Replaced sections: ".implode(', ', $sections_replaced)."\n";
echo "[S] Written to: $outfile ($sz KB)\n";

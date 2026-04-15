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

$outfile = $outfile ?: preg_replace('/\.json$/', '', $file_a).'.replaced.json';

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

  foreach ($b_combos as $iid1 => &$pairs) {
    if (!is_array($pairs)) continue;
    foreach ($pairs as $iid2 => &$b_data) {
      if (!is_array($b_data) || !isset($b_data['matches'])) continue;
      $a_data = $a_combos[$iid1][$iid2] ?? null;

      if ($a_data !== null) {
        // prefer A if its match count is proportionally similar (within 25%)
        $m_b = max(1, (int)$b_data['matches']);
        $m_a = max(0, (int)$a_data['matches']);
        $expected_a = (int)round($m_b * $n_a_total / $n_b_total);
        if ($expected_a > 0 && abs($m_a - $expected_a) / $expected_a <= 0.25) {
          $b_data = $a_data;
          continue;
        }
      }

      // scale B
      $m_b = max(0, (int)$b_data['matches']);
      $w_b = max(0, (int)$b_data['wins']);
      $e_b = max(0, (int)($b_data['exp'] ?? 0));

      $m_a = scale_count($m_b, $n_b_total, $n_a_total);
      $w_a = $m_a > 0 ? scale_count($w_b, $m_b, $m_a) : 0;
      $e_a = $m_a > 0 ? scale_count($e_b, $m_b, $m_a) : 0;

      $b_data['matches'] = $m_a;
      $b_data['wins']    = $w_a;
      $b_data['exp']     = $e_a;
    }
  }

  // Re-add any pairs only present in A (Rule 1)
  foreach ($a_combos as $iid1 => $pairs) {
    if (!is_array($pairs)) continue;
    foreach ($pairs as $iid2 => $a_data) {
      if (!isset($b_combos[$iid1][$iid2])) {
        $b_combos[$iid1][$iid2] = $a_data;
      }
    }
  }

  return wrap_data($b_combos, true, true, true);
}

// ---------------------------------------------------------------------------
// Section: items/progr and items/progrole
// ---------------------------------------------------------------------------

function scale_progr_pairs(array $pairs, array $a_pairs_by_key, int $n_b, int $n_a): array {
  $out = [];
  foreach ($pairs as &$pair) {
    if (empty($pair)) continue;
    $key = ($pair['item1'] ?? '').'-'.($pair['item2'] ?? '');
    $a_pair = $a_pairs_by_key[$key] ?? null;

    $t_b = max(0, (int)($pair['total'] ?? 0));
    $w_b = max(0, (int)($pair['wins']  ?? 0));

    if ($a_pair !== null) {
      $t_a = max(0, (int)($a_pair['total'] ?? 0));
      $expected = $n_b > 0 ? (int)round($t_b * $n_a / $n_b) : 0;
      // Within 30% of expected → prefer A
      if ($expected > 0 && abs($t_a - $expected) / $expected <= 0.30) {
        $out[] = $a_pair;
        continue;
      }
    }

    $t_a = scale_count($t_b, $n_b, $n_a);
    $w_a = $t_a > 0 ? scale_count($w_b, $t_b, $t_a) : 0;

    $pair['total']   = $t_a;
    $pair['wins']    = $w_a;
    $pair['winrate'] = $t_a > 0 ? round($w_a / $t_a, 4) : 0.0;
    $out[] = $pair;
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
      $b_progr[$hid] = $a_pairs; // only in A
      continue;
    }

    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);
    if ($n_b <= 0 || $n_a <= 0) continue;

    $a_idx = index_progr_pairs($a_pairs);

    // Also carry A-only pairs at the end
    $b_progr[$hid] = scale_progr_pairs($b_pairs, $a_idx, $n_b, $n_a);

    foreach ($a_pairs as $ap) {
      $key = ($ap['item1'] ?? '').'-'.($ap['item2'] ?? '');
      $found = false;
      foreach ($b_progr[$hid] as $bp) {
        if (($bp['item1'] ?? '').(($bp['item2'] ?? '') === ($ap['item1'] ?? '').($ap['item2'] ?? ''))) {
          $found = true; break;
        }
      }
      if (!$found) $b_progr[$hid][] = $ap;
    }
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
      $decode_pair = function(array $elem) use ($keys) {
        return !empty($keys) ? array_combine($keys, $elem) : $elem;
      };
      $encode_pair = function(array $p) use ($keys) {
        if (empty($keys)) return $p;
        $out = [];
        foreach ($keys as $k) { $out[] = $p[$k] ?? null; }
        return $out;
      };

      $b_pairs = array_map($decode_pair, $pairs_encoded);
      $a_pairs = array_map($decode_pair, $a_hero_roles[$roleid] ?? []);
      $a_idx   = index_progr_pairs($a_pairs);

      $scaled  = scale_progr_pairs($b_pairs, $a_idx, $n_b, $n_a);
      $pairs_encoded = array_map($encode_pair, $scaled);
    }
  }

  // Carry heroes/roles only in A
  foreach ($a_data as $hid => $roles) {
    if (!isset($b_data[$hid])) { $b_data[$hid] = $roles; }
    else {
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
    $b_hero = $b_enc[$hid] ?? null;
    $a_hero = $a_enc[$hid] ?? [];

    if ($b_hero === null) {
      $b_enc[$hid] = $a_hero; // only in A
      continue;
    }

    $n_b = hero_n($hid, $hero_b);
    $n_a = hero_target($hid, $hero_a, $hero_b);
    if ($n_b <= 0 || $n_a <= 0) continue;

    foreach ($b_hero as $cat_id => &$b_items) {
      if (empty($b_items)) continue;
      $a_items = $a_hero[$cat_id] ?? [];

      $cat_total_b = 0;
      foreach ($b_items as $idata) { $cat_total_b += max(0, (int)($idata['matches'] ?? 0)); }
      $cat_total_a = scale_count($cat_total_b, $n_b, $n_a);

      foreach ($b_items as $iid => &$idata) {
        $a_idata = $a_items[$iid] ?? null;

        $m_b = max(0, (int)($idata['matches'] ?? 0));
        $w_b = max(0, (int)($idata['wins']    ?? 0));

        if ($a_idata !== null) {
          $m_a = max(0, (int)($a_idata['matches'] ?? 0));
          $wr_a = (float)($a_idata['wr'] ?? 0);
          $wr_b = $m_b > 0 ? round($w_b / $m_b, 4) : 0.0;
          $wr_se = $m_b > 0 ? sqrt($wr_b * (1.0 - $wr_b) / $m_b) : 1.0;
          $expected_m = scale_count($m_b, $n_b, $n_a);
          if (abs($wr_a - $wr_b) <= 2.0 * $wr_se && abs($m_a - $expected_m) <= max(1, $expected_m * 0.25)) {
            // similar → keep A
            continue;
          }
        }

        $m_a  = scale_count($m_b, $n_b, $n_a);
        $w_a  = $m_a > 0 ? scale_count($w_b, $m_b, $m_a) : 0;
        $idata['matches'] = $m_a;
        $idata['wins']    = $w_a;
        $idata['wr']      = $m_a > 0 ? round($w_a / $m_a, 4) : 0.0;
        $idata['matches_wo'] = max(0, $cat_total_a - $m_a);

        $wo_b     = max(0, $cat_total_b - $m_b);
        $wo_wins_b = (int)round(($idata['wr_wo'] ?? 0) * $wo_b);
        $wo_a     = max(0, $cat_total_a - $m_a);
        $wo_wins_a = $wo_a > 0 ? scale_count($wo_wins_b, $wo_b, $wo_a) : 0;
        $idata['wr_wo'] = $wo_a > 0 ? round($wo_wins_a / $wo_a, 4) : 0.0;
      }

      // A-only items in this category
      foreach ($a_items as $iid => $a_idata) {
        if (!isset($b_items[$iid])) $b_items[$iid] = $a_idata;
      }
    }

    // A-only categories
    foreach ($a_hero as $cat_id => $a_items) {
      if (!isset($b_enc[$hid][$cat_id])) $b_enc[$hid][$cat_id] = $a_items;
    }
  }

  return $b_enc;
}

// ---------------------------------------------------------------------------
// Section: starting_items
// ---------------------------------------------------------------------------

function scale_starting_items(array $b_si, ?array $a_si, array $hero_a, array $hero_b): array {
  $a_si = $a_si ?? [];

  // Build per-role hero match counts for B (from starting_items/matches)
  $role_hero_b = [];
  $role_hero_a = [];

  if (!empty($b_si['matches'])) {
    foreach ($b_si['matches'] as $role => $wrapped) {
      $role_data = unwrap_data($wrapped);
      $role_hero_b[$role] = [];
      $role_hero_a[$role] = [];
      foreach ($role_data as $hid => $row) {
        if (!$row) continue;
        $n_b = max(0, (int)($row['m'] ?? 0));
        $role_hero_b[$role][(string)$hid] = $n_b;
        $h_b_total = hero_n($hid, $hero_b);
        $h_a_total = hero_target($hid, $hero_a, $hero_b);
        $scale     = $h_b_total > 0 ? $h_a_total / $h_b_total : 0.0;
        $role_hero_a[$role][(string)$hid] = max(0, (int)round($n_b * $scale));
      }
    }
  }

  $out = $b_si;

  // --- matches ---
  if (!empty($b_si['matches'])) {
    foreach ($b_si['matches'] as $role => &$wrapped) {
      $b_role = unwrap_data($wrapped);
      $a_role = [];
      if (!empty($a_si['matches'][$role])) {
        $a_role = unwrap_data($a_si['matches'][$role]);
      }

      foreach ($b_role as $hid => &$row) {
        if (!$row) continue;
        $hid_s = (string)$hid;
        $n_b = $role_hero_b[$role][$hid_s] ?? 0;
        $n_a = $role_hero_a[$role][$hid_s] ?? 0;
        if ($n_b <= 0) continue;

        $a_row = $a_role[$hid] ?? null;
        $a_wr  = (float)($a_row['wr'] ?? -1);
        $b_wr  = (float)($row['wr'] ?? 0);
        $wr_se = $n_b > 0 ? sqrt($b_wr * (1.0 - $b_wr) / $n_b) : 1.0;
        if ($a_row !== null && $a_wr >= 0 && abs($a_wr - $b_wr) <= 2.0 * $wr_se) {
          // similar → scale just m/l, keep A's wr
          $row['m']  = $n_a;
          $row['l']  = scale_count((int)($row['l'] ?? 0), $n_b, $n_a);
          $row['wr'] = $a_wr;
        } else {
          $row['m']  = $n_a;
          $row['l']  = scale_count((int)($row['l'] ?? 0), $n_b, $n_a);
          $row['wr'] = smooth_rate_value($b_wr, $n_b);
        }
      }

      // Carry A-only heroes
      foreach ($a_role as $hid => $a_row) {
        if (!isset($b_role[$hid]) && $a_row) $b_role[$hid] = $a_row;
      }
      $wrapped = wrap_data($b_role, true, true, true);
    }
    $out['matches'] = $b_si['matches'];
  }

  // --- items ---
  if (!empty($b_si['items'])) {
    $items_head = $b_si['items_head'] ?? ['matches', 'wins', 'lane_wins', 'freq'];

    foreach ($b_si['items'] as $role => &$hero_map) {
      foreach ($hero_map as $hid => &$wrapped) {
        $hid_s = (string)$hid;
        $n_b   = $role_hero_b[$role][$hid_s] ?? 0;
        $n_a   = $role_hero_a[$role][$hid_s] ?? 0;
        if ($n_b <= 0) continue;

        $b_items_u = is_wrapped($wrapped)
          ? unwrap_data($wrapped)
          : unwrap_data(['head' => $items_head, 'data' => array_values($wrapped), 'keys' => array_keys($wrapped)]);

        $a_items_u = [];
        if (!empty($a_si['items'][$role][$hid])) {
          $aw = $a_si['items'][$role][$hid];
          $a_items_u = is_wrapped($aw)
            ? unwrap_data($aw)
            : unwrap_data(['head' => $items_head, 'data' => array_values($aw), 'keys' => array_keys($aw)]);
        }

        foreach ($b_items_u as $iid => &$iv) {
          if (!$iv) continue;
          $a_iv = $a_items_u[$iid] ?? null;

          $m_b  = max(0, (int)($iv['matches']   ?? 0));
          $freq_b = $n_b > 0 ? $m_b / $n_b : 0.0;
          $freq_se = $n_b > 0 ? sqrt($freq_b * (1.0 - $freq_b) / $n_b) : 1.0;

          if ($a_iv !== null) {
            $freq_a = $n_a > 0 ? ((int)($a_iv['matches'] ?? 0)) / $n_a : 0.0;
            if (abs($freq_a - $freq_b) <= 2.0 * $freq_se) { $iv = $a_iv; continue; }
          }

          $w_b  = max(0, (int)($iv['wins']      ?? 0));
          $lw_b = max(0, (int)($iv['lane_wins'] ?? 0));
          $m_a  = scale_count($m_b, $n_b, $n_a);
          $w_a  = $m_a > 0 ? scale_count($w_b,  $m_b, $m_a) : 0;
          $lw_a = $m_a > 0 ? scale_count($lw_b, $m_b, $m_a) : 0;
          $iv['matches']   = $m_a;
          $iv['wins']      = $w_a;
          $iv['lane_wins'] = $lw_a;
          $iv['freq']      = $n_a > 0 ? round($m_a / $n_a, 4) : 0.0;
        }

        foreach ($a_items_u as $iid => $a_iv) {
          if (!isset($b_items_u[$iid]) && $a_iv) $b_items_u[$iid] = $a_iv;
        }

        $wrapped = wrap_data($b_items_u, true, true, true);
      }
    }
    $out['items'] = $b_si['items'];
  }

  // --- builds ---
  if (!empty($b_si['builds'])) {
    foreach ($b_si['builds'] as $role => &$b_wrapped) {
      $b_builds = unwrap_data($b_wrapped);

      foreach ($b_builds as $hid => &$builds_list) {
        if (empty($builds_list)) continue;
        $hid_s = (string)$hid;
        $n_b   = $role_hero_b[$role][$hid_s] ?? 0;
        $n_a   = $role_hero_a[$role][$hid_s] ?? 0;
        $lim_a = 1;
        if (!empty($out['matches'][$role])) {
          $mr = unwrap_data($out['matches'][$role]);
          $lim_a = (int)(($mr[$hid]['l'] ?? 1));
        }
        if ($n_b <= 0) continue;

        $a_builds_by_tag = [];
        if (!empty($a_si['builds'][$role])) {
          $a_builds = unwrap_data($a_si['builds'][$role]);
          foreach ($a_builds[$hid] ?? [] as $ab) {
            $tag = implode(',', $ab['build'] ?? []);
            $a_builds_by_tag[$tag] = $ab;
          }
        }

        $builds_out = [];
        foreach ($builds_list as &$bld) {
          if (empty($bld)) continue;
          $tag = implode(',', $bld['build'] ?? []);
          $a_bld = $a_builds_by_tag[$tag] ?? null;

          $m_b = max(0, (int)($bld['matches'] ?? 0));

          if ($a_bld !== null) {
            $ratio_b = $n_b > 0 ? $m_b / $n_b : 0.0;
            $ratio_se = $n_b > 0 ? sqrt($ratio_b * (1 - $ratio_b) / $n_b) : 1.0;
            $ratio_a = $n_a > 0 ? ((int)($a_bld['matches'] ?? 0)) / $n_a : 0.0;
            if (abs($ratio_a - $ratio_b) <= 2.0 * $ratio_se) {
              if ((int)($a_bld['matches'] ?? 0) >= $lim_a) { $builds_out[] = $a_bld; }
              continue;
            }
          }

          $m_a  = scale_count($m_b, $n_b, $n_a);
          if ($m_a < $lim_a) continue;
          $w_b  = max(0, (int)($bld['wins']      ?? 0));
          $lw_b = max(0, (int)($bld['lane_wins'] ?? 0));
          $w_a  = $m_a > 0 ? scale_count($w_b,  $m_b, $m_a) : 0;
          $lw_a = $m_a > 0 ? scale_count($lw_b, $m_b, $m_a) : 0;
          $bld['matches']   = $m_a;
          $bld['wins']      = $w_a;
          $bld['lane_wins'] = $lw_a;
          $bld['winrate']   = $m_a > 0 ? round($w_a  / $m_a, 4) : 0.0;
          $bld['lane_wr']   = $m_a > 0 ? round($lw_a / $m_a, 4) : 0.0;
          $bld['ratio']     = $n_a > 0 ? round($m_a  / $n_a, 4) : 0.0;
          $builds_out[] = $bld;
        }

        // A-only builds
        foreach ($a_builds_by_tag as $tag => $ab) {
          $found = false;
          foreach ($builds_out as $ob) {
            if (implode(',', $ob['build'] ?? []) === $tag) { $found = true; break; }
          }
          if (!$found && (int)($ab['matches'] ?? 0) >= $lim_a) $builds_out[] = $ab;
        }

        $builds_list = $builds_out;
      }
      $b_wrapped = wrap_data($b_builds, true, true, true);
    }
    $out['builds'] = $b_si['builds'];
  }

  // --- consumables ---
  if (!empty($b_si['consumables'])) {
    foreach ($b_si['consumables'] as $blk => &$b_roles) {
      foreach ($b_roles as $role => &$b_hero_map) {
        foreach ($b_hero_map as $hid => &$b_wrapped) {
          $hid_s = (string)$hid;
          $n_b = $role_hero_b[$role][$hid_s] ?? 0;
          $n_a = $role_hero_a[$role][$hid_s] ?? 0;
          if ($n_b <= 0) continue;

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

          foreach ($b_cons as $iid => &$cv) {
            if (!$cv) continue;
            $a_cv = $a_cons[$iid] ?? null;

            $m_b = max(0, (int)($cv['matches'] ?? 0));
            $freq_b = $n_b > 0 ? $m_b / $n_b : 0.0;
            $freq_se = $n_b > 0 ? sqrt($freq_b * (1.0 - $freq_b) / $n_b) : 1.0;

            if ($a_cv !== null) {
              $freq_a = $n_a > 0 ? ((int)($a_cv['matches'] ?? 0)) / $n_a : 0.0;
              if (abs($freq_a - $freq_b) <= 2.0 * $freq_se) { $cv = $a_cv; continue; }
            }

            $cv['matches'] = scale_count($m_b, $n_b, $n_a);
            $cv['total']   = scale_count(max(0, (int)($cv['total'] ?? 0)), $n_b, $n_a);
            // timing quantiles kept from B (distribution-invariant)
          }

          foreach ($a_cons as $iid => $a_cv) {
            if (!isset($b_cons[$iid]) && $a_cv) $b_cons[$iid] = $a_cv;
          }

          $b_wrapped = wrap_data($b_cons, true, true, true);
        }
      }
    }
    $out['consumables'] = $b_si['consumables'];
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

  // records: actual match-level records — keep A's if present, else B's
  if (!empty($items_b['records']) || !empty($items_a['records'])) {
    $items_out['records'] = $items_a['records'] ?? $items_b['records'];
    echo "[I] Keeping records from ".(!empty($items_a['records']) ? 'A' : 'B')."\n";
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
file_put_contents($outfile, $json_out);

$sz = number_format(strlen($json_out) / 1024, 1);
echo "[S] Replaced sections: ".implode(', ', $sections_replaced)."\n";
echo "[S] Written to: $outfile ($sz KB)\n";

<?php

/**
 * Compute volatility metrics from pairwise matchup data.
 *
 * Uses `diff` (reference entity's WR in this matchup minus its overall WR).
 * A negative diff means the opponent has the advantage against the reference entity.
 * If `diff` is absent from a pair, falls back to `winrate - 0.5`.
 *
 * @param array $pairs  map opponentId => ['matches'=>int, 'winrate'=>float(0..1), 'diff'=>?float]
 */
function rg_volatility_metrics(array $pairs): array {
  $rows = [];
  foreach ($pairs as $id => $line) {
    if (!is_array($line) || $id === '_h') continue;
    $matches = (int)($line['matches'] ?? 0);
    if ($matches <= 0) continue;
    $winrate = (float)($line['winrate'] ?? 0.5);
    $diff = array_key_exists('diff', $line) ? (float)$line['diff'] : ($winrate - 0.5);
    $rows[] = ['matches' => $matches, 'winrate' => $winrate, 'diff' => $diff];
  }

  $empty = [
    'relative' => 0.0, 'total' => 0.0, 'avg_advantage' => 0.0,
    'normalized_relative' => 0.0, 'normalized_total' => 0.0, 'normalized_avg_advantage' => 0.0,
    'q1_matches' => 0, 'sample' => 0,
  ];
  if (empty($rows)) return $empty;

  // Q1 match-count threshold for filtering
  $counts = array_column($rows, 'matches');
  sort($counts);
  $q1 = $counts[(int)floor((count($counts) - 1) * 0.25)] ?? 0;

  $filtered = array_values(array_filter($rows, fn($r) => $r['matches'] > $q1));
  if (empty($filtered)) $filtered = $rows;

  // Q1+ metrics
  $f_total = count($filtered);
  $f_adv  = 0;    // % of opponents with positive diff against this entity (our diff < 0)
  $f_good = 0;    // % of opponents with 50%+ WR against this entity
  $f_avg  = 0.0;  // mean diff across Q1+ opponents
  foreach ($filtered as $r) {
    if ($r['diff'] < 0) $f_adv++;
    if ($r['winrate'] < 0.5) $f_good++;
    $f_avg += $r['diff'];
  }

  // Normalized metrics: all opponents, weighted by their share of total matchup matches
  $total_matches = array_sum($counts);
  $n_adv  = 0.0;
  $n_good = 0.0;
  $n_avg  = 0.0;  // SUM(diff * pct_matches)
  foreach ($rows as $r) {
    $pct = $total_matches > 0 ? $r['matches'] / $total_matches : 0.0;
    if ($r['diff'] < 0) $n_adv += $pct;
    if ($r['winrate'] < 0.5) $n_good += $pct;
    $n_avg += $r['diff'] * $pct;
  }

  return [
    'relative'                 => round(100 * $f_adv / max(1, $f_total), 2),
    'total'                    => round(100 * $f_good / max(1, $f_total), 2),
    'avg_advantage'            => round(100 * $f_avg / max(1, $f_total), 2),
    'normalized_relative'      => round(100 * $n_adv, 2),
    'normalized_total'         => round(100 * $n_good, 2),
    'normalized_avg_advantage' => round(10000 * $n_avg, 2),
    'q1_matches'               => $q1,
    'sample'                   => $f_total,
  ];
}

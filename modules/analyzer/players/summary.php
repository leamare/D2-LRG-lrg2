<?php
$result["players_summary"] = rg_query_player_summary($conn, null);

// WRD@20 / STR@20 / WKS@20 / SPWK@20
if (($lg_settings['ana']['players_wards_summary'] ?? false) && ($schema['wards'] ?? false)) {
  $w_kills = $lg_settings['ana']['players_wards_summary_wk'] ?? true;

  $wsql = "SELECT wards.matchid, wards.playerid, wards.wards_log, wards.sentries_log, ".
      ($w_kills ? "wards.destroyed_log, " : "").
    "matches.duration
    FROM wards JOIN matches ON wards.matchid = matches.matchid ".
    (!$w_kills && !empty($players_interest) ? " WHERE wards.playerid IN (".implode(',', $players_interest).") " : "").
    "ORDER BY wards.matchid;";

  /** @var mysqli $conn */
  $wres = $conn->query($wsql);
  if ($wres === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $w_agg = [];
  $cur_match = null; $cur_rows = []; $cur_duration = 0;

  $flush = function() use (&$w_agg, &$cur_rows, &$cur_duration, $w_kills) {
    if (empty($cur_rows)) return;
    foreach (wards_at20_match_counts($cur_rows, $cur_duration, $w_kills) as $pid => $counts) {
      if (!isset($w_agg[$pid])) $w_agg[$pid] = ['w'=>0,'s'=>0,'k'=>0,'s_raw'=>0,'k_raw'=>0,'mtch'=>0];
      $w_agg[$pid]['w'] += $counts['w'];
      $w_agg[$pid]['s'] += $counts['s'];
      $w_agg[$pid]['k'] += $counts['k'];
      $w_agg[$pid]['s_raw'] += $counts['s_raw'];
      $w_agg[$pid]['k_raw'] += $counts['k_raw'];
      $w_agg[$pid]['mtch']++;
    }
    $cur_rows = [];
  };

  while ($wrow = $wres->fetch_assoc()) {
    if ($cur_match !== $wrow['matchid']) {
      $flush();
      $cur_match = $wrow['matchid'];
      $cur_duration = $wrow['duration'];
    }
    $cur_rows[] = $wrow;
  }
  $flush();
  $wres->free();

  foreach ($result['players_summary'] as $pid => &$row) {
    $d = $w_agg[$pid] ?? null;
    $has = $d && $d['mtch'] > 0;

    $row['wards_at20'] = $has ? $d['w'] / $d['mtch'] : null;
    $row['sentries_at20'] = $has ? $d['s'] / $d['mtch'] : null;

    if (!$w_kills) continue;

    $row['wards_killed_at20'] = $has ? $d['k'] / $d['mtch'] : null;
    $row['sentries_per_wardkill_at20'] = $has ? ($d['k_raw'] > 0 ? $d['s_raw'] / $d['k_raw'] : 0) : null;
  }
  unset($row);
}

$pl_numbers = [];
foreach ($result['players_summary'] as $line) {
  $mt = (int)$result['random']["matches_total"];
  $num = ceil(($line['matches_s'] * $line['matches_s']) / (2 * $mt));
  if ($num > 1) $pl_numbers[] = $num;
}

if (empty($pl_numbers)) $pl_numbers[] = 1;

$limiters_players_pvp = calculate_limiters($pl_numbers, null, $result['random']["matches_total"]);

$pl_numbers = [];
foreach ($result['players_summary'] as $line) {
  if ($line['matches_s'] > 1)
    $pl_numbers[] = $line['matches_s'];
}
if (empty($pl_numbers)) $pl_numbers[] = 1;

$limiters_players = calculate_limiters($pl_numbers, null, $result['random']["matches_total"]);
$limiters_players['limiter_higher'] = round($limiters_players['limiter_higher']);

$pl_limiter = round($limiters_players['limiter_higher'] * 0.75);

echo "[ ] Limiter Graph Players (PSummary PvP): {$limiters_players_pvp['limiter_higher']}\n";
echo "[ ] Limiter Players (PSummary PvP): $pl_limiter\n";
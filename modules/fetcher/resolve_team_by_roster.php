<?php

/**
 * Roster-based team ID recovery.
 *
 * Some matches arrive with a team ID missing on one side (Valve/OpenDota simply
 * do not report it). When that happens we look for a team that already has a
 * known roster fitting this lineup — at least $min_players of the five players
 * in a single match within a window around the match date — and use that team ID
 * instead. When nothing fits confidently the side stays without a team, as before.
 *
 * @param mysqli $conn
 * @param int[]  $playerids  Account IDs of the side with no team ID.
 * @return int|null Resolved team ID, or null when no roster matched.
 */
function lrg_resolve_team_by_roster(
  $conn,
  array $playerids,
  int $around_ts,
  int $min_players = 4,
  int $window_days = 180,
  ?int $exclude_matchid = null
): ?int {
  $playerids = array_values(array_unique(array_filter(
    array_map('intval', $playerids),
    // 4294967295 is the anonymous account placeholder
    fn($p) => $p > 0 && $p !== 4294967295
  )));
  if (count($playerids) < $min_players) return null;

  $from = $around_ts - $window_days * 86400;
  $to   = $around_ts + $window_days * 86400;
  $ids  = implode(',', $playerids);
  $excl = $exclude_matchid !== null ? " AND tm.matchid <> " . (int)$exclude_matchid : "";

  $sql = "SELECT teamid, SUM(matched >= $min_players) hits, MAX(matched) best, MAX(start_date) last_seen
    FROM (
      SELECT tm.teamid teamid, m.start_date start_date, COUNT(DISTINCT ml.playerid) matched
      FROM teams_matches tm
      JOIN matches m ON m.matchid = tm.matchid
      JOIN matchlines ml ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
        AND ml.playerid IN ($ids)
      WHERE tm.teamid > 0 AND m.start_date BETWEEN $from AND $to$excl
      GROUP BY tm.teamid, tm.matchid, m.start_date
    ) x
    GROUP BY teamid
    HAVING hits > 0
    ORDER BY hits DESC, best DESC, last_seen DESC
    LIMIT 1;";

  $res = $conn->query($sql);
  if (!$res) return null;
  if (!$res->num_rows) { $res->free(); return null; }
  $row = $res->fetch_assoc();
  $res->free();

  return (int)$row['teamid'] ?: null;
}

/**
 * Fill in team IDs for sides missing from $t_team_matches, in place.
 * No-op unless the report actually tracks teams.
 */
function lrg_fill_missing_team_matches(
  $conn,
  array &$t_team_matches,
  array $t_matchlines,
  int $match,
  int $start_date
): void {
  $known_sides = [];
  foreach ($t_team_matches as $tm) $known_sides[(int)$tm['is_radiant']] = true;
  if (isset($known_sides[0]) && isset($known_sides[1])) return;

  foreach ([0, 1] as $side) {
    if (isset($known_sides[$side])) continue;

    $roster = [];
    foreach ($t_matchlines as $ml) {
      if ((int)!empty($ml['isRadiant']) !== $side) continue;
      if (!empty($ml['playerid'])) $roster[] = (int)$ml['playerid'];
    }

    $teamid = lrg_resolve_team_by_roster($conn, $roster, $start_date, 4, 180, $match);
    if (empty($teamid)) continue;

    $t_team_matches[] = [
      'matchid'    => $match,
      'teamid'     => $teamid,
      'is_radiant' => $side,
    ];
    echo "..Resolved missing " . ($side ? "radiant" : "dire") . " team to $teamid by roster.";
  }
}

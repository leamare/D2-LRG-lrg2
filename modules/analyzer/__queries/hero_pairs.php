<?php

/**
 * @param array<int, array{matches_picked: int|float, winrate_picked: float}> $pickban
 * @param list<int>|null $cluster
 * @param list<int>|null $players
 * @return list<array{heroid1: mixed, heroid2: mixed, matches: mixed, wins: mixed, winrate: float, expectation: float|int, lane_rate: mixed, wr_diff: float}>
 */
function rg_query_hero_pairs(
  mysqli &$conn,
  array &$pickban,
  int|float $matches_total,
  int|float $limiter = 0,
  ?array $cluster = null,
  ?int $team = null,
  ?array $players = null
): array {
  global $players_interest;
  if (empty($players) && $team === null && !empty($players_interest)) {
    $players = $players_interest;
  }

  $result = [];
  $wheres = [];
  $lane_join = $team !== null ? 'JOIN' : 'LEFT JOIN';
  $match_count_expr = $team !== null
    ? 'COUNT(distinct fm1.matchid)'
    : 'COUNT(distinct matches.matchid)';

  if ($team !== null) {
    $wheres[] = 'teams_matches.teamid = ' . (int)$team;
  }
  if ($cluster !== null) {
    $wheres[] = 'matches.cluster IN (' . implode(',', $cluster) . ')';
  }
  if (!empty($players)) {
    $wheres[] = '( fm1.playerid in (' . implode(',', $players_interest) . ')
      and fm2.playerid in (' . implode(',', $players_interest) . ')
    )';
  }

  $teams_join = $team === null ? '' : '
        JOIN teams_matches ON fm1.matchid = teams_matches.matchid AND teams_matches.is_radiant = fm1.isRadiant';

  $sql = "SELECT fm1.heroid, fm2.heroid,
            {$match_count_expr} match_count,
            SUM(NOT matches.radiantWin XOR fm1.isRadiant) wins,
            SUM(fm1.lane = fm2.lane)/SUM(1) lane_rate
          FROM
            ( select m1.matchid, m1.heroid, am1.lane, m1.isRadiant, m1.playerid
              from matchlines m1 {$lane_join} adv_matchlines am1
              ON m1.matchid = am1.matchid AND m1.heroid = am1.heroid ) fm1
          JOIN
            ( select m2.matchid, m2.heroid, am2.lane, m2.isRadiant, m2.playerid
              from matchlines m2 {$lane_join} adv_matchlines am2
              ON m2.matchid = am2.matchid AND m2.heroid = am2.heroid ) fm2
          ON fm1.matchid = fm2.matchid and fm1.isRadiant = fm2.isRadiant and fm1.heroid < fm2.heroid
          JOIN matches ON fm1.matchid = matches.matchid{$teams_join}" .
          (!empty($wheres) ? ' WHERE ' . implode(' AND ', $wheres) : '') . "
        GROUP BY fm1.heroid, fm2.heroid
          HAVING match_count > {$limiter}
          ORDER BY match_count DESC, wins DESC;";

  if ($conn->multi_query($sql) === true) {
    // ok
  } else {
    die("[F] Unexpected problems when requesting database.\n" . $conn->error . "\n");
  }

  $query_res = $conn->store_result();
  $round = $team === null;

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if (!isset($pickban[$row[0]], $pickban[$row[1]])) {
      continue;
    }

    $expected_pair = $matches_total ? (($pickban[$row[0]]['matches_picked'] / $matches_total)
         * ($pickban[$row[1]]['matches_picked'] / $matches_total)
         * $matches_total) / 2
         : 0;
    $wr_diff = $row[3] / $row[2] - ($pickban[$row[0]]['winrate_picked'] + $pickban[$row[1]]['winrate_picked']) / 2;

    $result[] = [
      'heroid1' => $row[0],
      'heroid2' => $row[1],
      'matches' => $row[2],
      'winrate' => $round ? round($row[3] / $row[2], 4) : $row[3] / $row[2],
      'wins' => $row[3],
      'expectation' => $round ? round($expected_pair) : $expected_pair,
      'lane_rate' => $row[4],
      'wr_diff' => $round ? round($wr_diff, 5) : $wr_diff,
    ];
  }

  $query_res->free_result();

  return $result;
}

/**
 * @param list<array{heroid1: mixed, heroid2: mixed}> $hero_pairs
 * @return array<string, list<mixed>>
 */
function rg_query_hero_pairs_matches(mysqli &$conn, array &$hero_pairs): array
{
  $result = [];

  foreach ($hero_pairs as $pair) {
    $sql = 'SELECT m1.matchid
            FROM matchlines m1 JOIN matchlines m2
              ON m1.matchid = m2.matchid and m1.isRadiant = m2.isRadiant and m1.heroid < m2.heroid
            WHERE m1.heroid = ' . $pair['heroid1'] . ' AND m2.heroid = ' . $pair['heroid2'] . ';';

    $result[$pair['heroid1'] . '-' . $pair['heroid2']] = [];

    if ($conn->multi_query($sql) === true) {
      // ok
    } else {
      die("[F] Unexpected problems when requesting database.\n" . $conn->error . "\n");
    }

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $result[$pair['heroid1'] . '-' . $pair['heroid2']][] = $row[0];
    }

    $query_res->free_result();
  }

  return $result;
}

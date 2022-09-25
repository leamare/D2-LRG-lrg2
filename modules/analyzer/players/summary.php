<?php
$result["players_summary"] = rg_query_player_summary($conn, null);

$pl_numbers = [];
foreach ($result['players_summary'] as $line) {
  $pl_numbers[] = $line['matches_s'];
}

$limiters_players = calculate_limiters($pl_numbers, null, $result['random']["matches_total"]);
<?php
$result["players_summary"] = rg_query_player_summary($conn, null);

$pl_numbers = [];
foreach ($result['players_summary'] as $line) {
  $num = ceil(($line['matches_s'] * $line['matches_s']) / (2*$result['random']["matches_total"]));
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

$pl_limiter = $limiters_players['limiter_higher'];

echo "[ ] Limiter Graph Players (PSummary PvP): {$limiters_players_pvp['limiter_higher']}\n";
echo "[ ] Limiter Players (PSummary PvP): $pl_limiter\n";
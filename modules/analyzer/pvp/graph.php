<?php
$result["players_combo_graph"] = rg_query_player_graph(
  $conn,
  $result['players_summary'],
  $result['random']['matches_total'],
  $limiters_players_pvp['limiter_higher'], // has limiter_graph parameter, but it's way too low
  null
);

// would be nice to use pairs limiters like for heroes
// but it's probably out of the picture so we are left with estimates instead


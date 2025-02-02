<?php

echo "[S] Requested data for PLAYER LANING STATS - ";

$result["player_laning"] = wrap_data(
  rg_query_player_laning($conn, null, null),
  true,
  true,
  true
);

echo "\n";
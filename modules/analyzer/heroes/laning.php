<?php

echo "[S] Requested data for HERO LANING STATS - ";

$result["hero_laning"] = wrap_data(
  rg_query_hero_laning($conn, null, null),
  true,
  true,
  true
);

echo "\n";
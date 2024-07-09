<?php

echo "[S] Requested data for HERO LANING STATS VARIANTS";

$result["hero_laning_v"] = rg_query_hero_laning_variants($conn, null, null);

foreach ($result["hvh_v"] as $i => $stats) {
  $h1 = $stats['heroid1'];
  $h2 = $stats['heroid2'];

  $lane_m = ($result["hero_laning_v"][$h1][$h2] ?? [])['matches'] ?? 0;
  $lane_rate = $lane_m/$stats['matches'];
  $lane_wr = $lane_m ? $result["hero_laning_v"][$h1][$h2]['lane_wr'] : 0;

  $result["hvh_v"][$i]['lane_rate'] = $lane_rate;
  $result["hvh_v"][$i]['lane_wr'] = $lane_wr;
}

$result["hero_laning_v"] = wrap_data(
  $result["hero_laning_v"][0],
  true,
  true,
  true
);

echo "\n";
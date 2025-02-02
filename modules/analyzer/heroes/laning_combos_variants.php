<?php 

echo "[S] Requested data for HERO LANING COMBOS VARIANTS";

$lane_combos_v = rg_query_lane_combo_variants($conn);

foreach ($lane_combos_v as $i => $stats) {
  $h1 = $stats['heroid1'].'-'.$stats['variant1'];
  $h2 = $stats['heroid2'].'-'.$stats['variant2'];

  if (!isset($result["hph_v"][$h1][$h2])) continue;

  if ($result["hph_v"][$h1][$h2]['matches'] == -1) {
    $src =& $result["hph_v"][$h2][$h1];
  } else {
    $src =& $result["hph_v"][$h1][$h2];
  }

  $lane_m = $stats['matches'] ?? 0;
  $lane_rate = $lane_m/$src['matches'];
  $lane_wr = $lane_m ? $stats['lane_wins']/($lane_m*2) : 0;

  $src['lane_rate'] = $lane_rate;
  $src['lane_wr'] = $lane_wr;
}

echo "\n";
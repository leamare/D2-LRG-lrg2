<?php

$result["hero_summary_variants"] = [
  rg_query_hero_summary_variants($conn, 0, null)
];

if ($lg_settings['ana']['hero_positions']) {
  for ($i=1; $i<=5; $i++) {
    $result["hero_summary_variants"][] = rg_query_hero_summary_variants($conn, $i, null);
  }
} 

$result["hero_summary_variants"] = wrap_data(
  $result["hero_summary_variants"],
  true,
  true,
  true
);
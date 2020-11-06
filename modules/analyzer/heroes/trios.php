<?php

// $q_p = $limiter_graph / $result['random']['matches_total'];
// $q_p = pow(sqrt($q_p*2), 3);
// $q_p = round($q_p * $result['random']['matches_total']);

$result["hero_triplets"] = rg_query_hero_trios(
  $conn, 
  $result['pickban'], 
  $result['random']['matches_total'], 
  ceil($limiter_graph*0.25),
  null,
  null
);

if ($lg_settings['ana']['hero_triplets_matches']) {
  $result["hero_triplets_matches"] = rg_query_hero_trios_matches($conn, $result["hero_triplets"]);
}


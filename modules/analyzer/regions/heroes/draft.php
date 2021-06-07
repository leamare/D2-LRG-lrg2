<?php

$result["regions_data"][$region]["draft"] = rg_query_hero_draft($conn, $clusters);

if ($lg_settings['ana']['draft_tree']) {
  $result["regions_data"][$region]["draft_tree"] = rg_query_hero_draft_tree($conn, $result["regions_data"][$region]['settings']['limiter_graph']+1, $clusters);
}
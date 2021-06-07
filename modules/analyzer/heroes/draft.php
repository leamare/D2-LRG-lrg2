<?php

$result["draft"] = rg_query_hero_draft($conn);

if ($lg_settings['ana']['draft_tree']) {
  $result["draft_tree"] = rg_query_hero_draft_tree($conn, $limiter_graph+1);
}

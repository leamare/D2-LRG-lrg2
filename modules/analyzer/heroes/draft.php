<?php

$result["draft"] = rg_query_hero_draft($conn);

$result["draft_tree"] = rg_query_hero_draft_tree($conn, $limiter_graph+1);


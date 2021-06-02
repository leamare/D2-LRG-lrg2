<?php

$result["teams"][$id]["draft"] = rg_query_hero_draft($conn, null, $id);
$result["teams"][$id]["draft_tree"] = rg_query_hero_draft_tree($conn, 1, null, $id);


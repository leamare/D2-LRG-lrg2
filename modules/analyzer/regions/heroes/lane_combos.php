<?php

$result["regions_data"][$region]["hero_lane_combos"] = rg_query_lane_combos($conn, $result["regions_data"][$region]['settings']['limiter_graph']*0.5, $clusters);

?>
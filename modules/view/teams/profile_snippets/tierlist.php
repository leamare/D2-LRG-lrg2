<?php

// $res["team".$tid]['heroes']['tierlist']

include_once($root."/modules/view/generators/tier_list.php");

[ $tl_stats, $tl_games ] = tier_list_team_heroes_stats($context[$tid]);

$res["team".$tid]['heroes']['tierlist'] = rg_generator_tier_list_entity_heroes(
  "team$tid-heroes-tierlist", $tl_stats, $tl_games, "desc_tierlists_team_heroes"
);

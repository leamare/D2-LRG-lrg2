<?php 

// rules format: matchid::type:id:replacer::...
// types: player, pslot, team, side, cluster, hero, mmr
// - hero:<hero_id>:<account_id>  inject/remap a player account_id by hero (used by
//   league_monitor_v2 for ranked matches, where there is no pre-existing
//   playerid to key off of like `player` does). Values are Steam account_id
//   (32-bit), not full 64-bit SteamIDs — GetTopLiveGame only exposes account_id.
// - mmr:0:<value>                inject the ranked match's average mmr; the
//   id slot is unused (always "0") since there is only one mmr per match
const RULES_TYPES = [
  "player",
  "pslot",
  "team",
  "side",
  "cluster",
  "hero",
  "mmr",
];

function processRules(&$matchstring) {
  $match_rules = [];
  if (strpos($matchstring, "::")) {
    $rules_raw = explode("::", $matchstring);
    $matchstring = array_shift($rules_raw);
    $rules = [];
    foreach($rules_raw as $rule) {
      if (strpos($rule, ":") === FALSE) continue;
      $rule = explode(":", strtolower($rule) );
      if (sizeof($rule) < 3 || !in_array($rule[0], RULES_TYPES)) continue;

      if (!isset($match_rules[ $rule[0] ]))
        $match_rules[ $rule[0] ] = [];

      $match_rules[ $rule[0] ][ $rule[1] ] = $rule[2];
    }
  }

  return $match_rules;
}

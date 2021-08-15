<?php 

$endpoints['summary'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("teams", $mods)) {
    $context_k = array_keys($context['teams']);
    foreach($context_k as $team_id) {
      if (isset($report['teams_interest']) && !in_array($team_id, $report['teams_interest'])) continue;
      $t = [
        "team_id" => $team_id,
        "team_name" => team_name($team_id),
        "team_tag" => team_tag($team_id),
        "matches_total" => $report['teams'][$team_id]['matches_total'],
        "winrate" => round( $report['teams'][$team_id]['matches_total'] ? 
          $report['teams'][$team_id]['wins']*100/$report['teams'][$team_id]['matches_total']
          : 0,2)
      ];
      $res[] = array_merge($t, $report['teams'][$team_id]['averages']);
    }
    $res['__endp'] = "teams-summary";
  } else if (in_array("players", $mods)) {
    if (is_wrapped($context['players_summary'])) $context['players_summary'] = unwrap_data($context['players_summary']);
    if (isset($report['players_additional']) && isset($report['player_positions'])) {
      foreach($context['players_summary'] as $id => $player) {
        if (!isset($report['players_additional'][$id]['positions'])) continue;
        $position = reset($report['players_additional'][$id]['positions']);
        $position = $position["core"].".".$position["lane"];
        $context['players_summary'][$id]['common_position'] = $position;
      }
    }
    $res = $context['players_summary'];
    $res['__endp'] = "players-summary";
  } else if (in_array("heroes", $mods)) {
    if (is_wrapped($context['hero_summary'])) $context['hero_summary'] = unwrap_data($context['hero_summary']);
    $res = $context['hero_summary'];
    $res['__endp'] = "heroes-summary";
  } else {
    throw new \Exception("What kind of summary do you need?");
  }

  return $res;
};

<?php

function get_report_descriptor(&$report, $generate_endpoints = false) {
  //echo  $report['league_tag']."<br />";

  $desc = [
    "tag" => $report['league_tag'],
    "name" => $report['league_name'],
    "desc" => $report['league_desc'],
    "id" => $report['league_id'],
    "first_match" => $report['first_match'],
    "last_match" => $report['last_match'],
    "matches" => $report['random']['matches_total'],
    "ver" => $report['ana_version'],
    "days" => sizeof($report['days']),
    "anonymous_players" => empty($report['players_additional']),
    "matches_details" => !empty($report['players_additional']),
    "patches" => $report['versions'] ?? null
  ];

  if(isset($report['teams'])) {
    $desc["tvt"] = true;
    $desc["teams"] = [];
    foreach($report['teams'] as $tid => $team)
      $desc["teams"][] = $tid;
  } else {
    $desc["tvt"] = false;
    if(isset($report['players'])) {
      $desc["players"] = [];
      foreach($report['players'] as $pid => $player)
        $desc["players"][] = $pid;
    }
  }
  if(isset($report['regions_data'])) {
    $desc["regions"] = [];
    foreach($report['regions_data'] as $rid => $regions)
      $desc["regions"][] = $rid;
  }

  if(isset($report['settings']['custom_style'])) {
    $desc["style"] = $report['settings']['custom_style'];
  }
  if(isset($report['settings']['custom_logo'])) {
    $desc["logo"] = $report['settings']['custom_logo'];
  }
  $desc["sponsors"] = $report['sponsors'] ?? null;
  $desc["orgs"] = $report['orgs'] ?? null;
  $desc["links"] = $report['links'] ?? null;

  if (isset($report['provider_override']))
    $desc["provider_override"] = $report['provider_override'];

  if (isset($report['localized']))
    $desc["localized"] = $report['localized'];
  
  if (!$generate_endpoints)
    return $desc;

  $desc['endpoints'] = [];
  $desc['endpoints'][] = "overview";
  if (isset($report['records'])) $desc['endpoints'][] = "records";

  if (isset($report['pickban'])) $desc['endpoints'][] = "heroes-pickban";
  if (isset($report['draft'])) $desc['endpoints'][] = "heroes-draft";
  if (isset($report['averages_heroes']) || isset($report['haverages_heroes'])) $desc['endpoints'][] = "heroes-haverages";
  if (isset($report['hero_sides'])) $desc['endpoints'][] = "heroes-sides";
  if (isset($report['hero_pairs']) || isset($report['hph'])) $desc['endpoints'][] = "heroes-combos-pairs";
  if (isset($report['hero_pairs_matches'])) $desc['endpoints'][] = "heroes-combos-pairs_matches";
  if (isset($report['hero_triplets']) || isset($report['hero_trios'])) $desc['endpoints'][] = "heroes-combos-trios";
  if (isset($report['hero_triplets_matches']) || isset($report['hero_trios_matches'])) $desc['endpoints'][] = "heroes-combos-trios_matches";
  if (isset($report['hero_lane_combos'])) $desc['endpoints'][] = "heroes-combos-lane_combos";
  if (isset($report['hero_combos_graph']) || isset($report['hph'])) $desc['endpoints'][] = "heroes-combos-meta_graph";
  if (isset($report['hph'])) $desc['endpoints'][] = "heroes-hph";
  if (isset($report['hero_positions'])) $desc['endpoints'][] = "heroes-positions";
  if (isset($report['hero_positions_matches'])) $desc['endpoints'][] = "heroes-positions_matches";
  if (isset($report['hvh'])) $desc['endpoints'][] = "heroes-hvh";
  if (isset($report['hvh']) && compare_ver($desc['ver'], [2, 9, 1, 0, 0]) >= 0) {
    $desc['endpoints'][] = "heroes-counters-pairs";
    $desc['endpoints'][] = "heroes-counters-graph";
  }
  if (isset($report['hero_summary'])) $desc['endpoints'][] = "heroes-summary";
  if (isset($report['hero_laning'])) $desc['endpoints'][] = "heroes-laning";

  if (isset($report['players_draft'])) $desc['endpoints'][] = "players-draft";
  if (isset($report['averages_players']) || isset($report['haverages_players'])) $desc['endpoints'][] = "players-haverages";
  if (isset($report['player_sides'])) $desc['endpoints'][] = "players-sides";
  if (isset($report['player_pairs'])) $desc['endpoints'][] = "players-combos-pairs";
  if (isset($report['player_pairs_matches'])) $desc['endpoints'][] = "players-combos-pairs_matches";
  if (isset($report['player_triplets']) || isset($report['player_trios'])) $desc['endpoints'][] = "players-combos-trios";
  if (isset($report['player_triplets_matches']) || isset($report['player_trios_matches'])) $desc['endpoints'][] = "players-combos-trios_matches";
  if (isset($report['player_lane_combos'])) $desc['endpoints'][] = "players-combos-lane_combos";
  if (isset($report['players_combo_graph'])) $desc['endpoints'][] = "players-combos-party_graph";
  if (isset($report['player_positions'])) $desc['endpoints'][] = "players-positions";
  if (isset($report['player_positions_matches'])) $desc['endpoints'][] = "players-positions_matches";
  if (isset($report['pvp'])) $desc['endpoints'][] = "players-pvp";
  if (isset($report['players_summary'])) $desc['endpoints'][] = "players-summary";

  if (isset($report['items']) && !empty($report['items']['pi'])) {
    $desc['endpoints'][] = "items";
    $desc['endpoints'][] = "items-overview";
    if (isset($report['items']['stats'])) {
      $desc['endpoints'][] = "items-stats";
      // $desc['endpoints'][] = "items-boxplots";
      $desc['endpoints'][] = "items-heroes";
      // $desc['endpoints'][] = "items-heroboxplots";
    }
    if (isset($report['items']['combos'])) {
      $desc['endpoints'][] = "items-icombos";
    }
    if (isset($report['items']['records'])) {
      $desc['endpoints'][] = "items-irecords";
    }
    if (isset($report['items']['progr'])) {
      $desc['endpoints'][] = "items-progression";
      // $desc['endpoints'][] = "items-proglist";
    }
  }

  if ($desc['tvt']) {
    $desc['endpoints'][] = "teams-tvt-grid";
    $desc['endpoints'][] = "teams-profiles";
    $desc['endpoints'][] = "teams-cards";

    $team_ref = reset($report['teams']);
    if (isset($team_ref['draft_vs'])) $desc['endpoints'][] = "teams-team-vsdraft";
    if (isset($team_ref['draft'])) $desc['endpoints'][] = "teams-team-draft";
    if (isset($team_ref['hero_pairs'])) $desc['endpoints'][] = "teams-team-heroes-combos-pairs";
    if (isset($team_ref['hero_triplets'])) $desc['endpoints'][] = "teams-team-heroes-combos-trios";
    if (isset($team_ref['hero_graph'])) $desc['endpoints'][] = "teams-team-heroes-meta_graph";
    if (isset($team_ref['hero_positions'])) $desc['endpoints'][] = "teams-team-heroes-positions";
    if (isset($team_ref['players_draft'])) $desc['endpoints'][] = "teams-team-players-draft";

    //$reg_ref = reset($report['regions_data']);
    if (isset($report['regions_data'])) {
      $desc['endpoints'][] = "regions-teams-softgen";
    }
  }

  if ($desc['matches_details']) $desc['endpoints'][] = "matches";
  if (!$desc['anonymous_players']) $desc['endpoints'][] = "participants";

  return $desc;
}

?>

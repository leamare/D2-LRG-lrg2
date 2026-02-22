<?php 

#[Endpoint(name: 'items-stibuilds')]
#[Description('Starting item builds aggregated for hero/team/player')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Role code (core.lane)')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Player id')]
#[ReturnSchema(schema: 'StiBuildsResult')]
class StiBuilds extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (!isset($report['starting_items']) || empty($report['starting_items']['builds']) &&
    (empty($report['starting_items_players']) || empty($report['starting_items_players']['builds']))
  )
    throw new UserInputException("No starting item builds data");

  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;
  $selected_pid = $vars['playerid'] ?? 0;

  $context_info = null;

  if (isset($vars['playerid']) || in_array("players", $mods)) {
    if (!isset($report['starting_items_players']) || empty($report['starting_items_players']['builds']))
      throw new UserInputException("No starting item builds data for players");
    
    $report['starting_items_players']['builds'][$selected_rid] = unwrap_data($report['starting_items_players']['builds'][$selected_rid]);
    $data = $report['starting_items_players']['builds'][$selected_rid][$selected_pid] ?? null;

    if (empty($data)) {
      return [];
    }

    $report['starting_items_players']['matches'][$selected_rid] = unwrap_data($report['starting_items_players']['matches'][$selected_rid]);
    $matches = $report['starting_items_players']['matches'][$selected_rid][$selected_pid];

    $pbdata = $report['players_summary'][$selected_pid] ?? [];

    if (is_wrapped($report['player_laning'])) {
      $report['player_laning'] = unwrap_data($report['player_laning']);
    }
    $lanedata = $report['player_laning'][0][$selected_pid] ?? [];

    $context_info = [
      'matches' => $pbdata['matches_s'] ?? 0,
      'winrate' => $pbdata['winrate_s'] ?? 0,
      'lane_wr' => $lanedata['lane_wr'] ?? null,
      'role_matches' => null,
      'role_winrate' => null,
      'role_ratio' => null,
    ];

    if ($selected_rid != 0 && isset($report['player_positions'])) {
      if (is_wrapped($report['player_positions'])) {
        $report['player_positions'] = unwrap_data($report['player_positions']);
      }

      [$core, $lane] = explode('.', ROLES_IDS_SIMPLE[$selected_rid]);
      $posdata = $report['player_positions'][$core][$lane][$selected_hid] ?? [];

      $context_info['role_matches'] = $posdata['matches_s'];
      $context_info['role_winrate'] = $posdata['winrate_s'];
      $context_info['role_ratio'] = $posdata['matches_s']/$context_info['matches'];
    }
  } else {
    if (!isset($report['starting_items']) || empty($report['starting_items']['builds']))
      throw new UserInputException("No starting item builds data");

    $report['starting_items']['builds'][$selected_rid] = unwrap_data($report['starting_items']['builds'][$selected_rid]);
    $data = $report['starting_items']['builds'][$selected_rid][$selected_hid] ?? null;
  
    if (empty($data)) {
      return [];
    }
  
    $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);
    $matches = $report['starting_items']['matches'][$selected_rid][$selected_hid];

    $pbdata = $report['pickban'][$selected_hid] ?? [];

    if (is_wrapped($report['hero_laning'])) {
      $report['hero_laning'] = unwrap_data($report['hero_laning']);
    }
    $lanedata = $report['hero_laning'][0][$selected_hid] ?? [];

    $maindata = $report['random'] ?? $report['main'];

    $context_info = [
      'matches' => $pbdata['matches_picked'] ?? 0,
      'winrate' => $pbdata['winrate_picked'] ?? 0,
      'pickrate' => ($pbdata['matches_picked'] ?? 0)/$maindata['matches_total'],
      'lane_wr' => $lanedata['lane_wr'] ?? null,
      'role_matches' => null,
      'role_winrate' => null,
      'role_ratio' => null,
    ];

    if ($selected_rid != 0 && isset($report['hero_positions'])) {
      if (is_wrapped($report['hero_positions'])) {
        $report['hero_positions'] = unwrap_data($report['hero_positions']);
      }

      [$core, $lane] = explode('.', ROLES_IDS_SIMPLE[$selected_rid]);
      $posdata = $report['hero_positions'][$core][$lane][$selected_hid] ?? [];

      $context_info['role_matches'] = $posdata['matches_s'];
      $context_info['role_winrate'] = $posdata['winrate_s'];
      $context_info['role_ratio'] = $posdata['matches_s']/$context_info['matches'];
    }
  }

  return [
    'data' => $data,
    'matches' => $matches,
    'context' => $context_info,
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('StiBuildsResult', TypeDefs::obj([
    'data' => TypeDefs::arrayOf(TypeDefs::obj([])),
    'matches' => TypeDefs::obj([]),
    'context' => TypeDefs::obj([]),
  ]));
}
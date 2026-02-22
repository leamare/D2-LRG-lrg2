<?php 

#[Endpoint(name: 'items-stitems')]
#[Description('Starting items aggregated stats for hero/team/player')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Role code (core.lane)')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Player id')]
#[ReturnSchema(schema: 'StItemsResult')]
class StItems extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (!isset($report['starting_items']) || empty($report['starting_items']['items']) &&
    (empty($report['starting_items_players']) || empty($report['starting_items_players']['items']))
  )
    throw new UserInputException("No starting item items data");
  
  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;
  $selected_pid = $vars['playerid'] ?? 0;

  $context_info = null;

  if (isset($vars['playerid']) || (in_array("players", $mods) && !isset($vars['team']))) {
    if (empty($report['starting_items_players']) || empty($report['starting_items_players']['items'])) {
      throw new UserInputException("No starting item items data for players");
    }

    $data = $report['starting_items_players']['items'][$selected_rid][$selected_pid];
    
    if (empty($data)) {
      return [];
    }

    $data['head'] = $report['starting_items_players']['items_head'];
    $data = unwrap_data($data);
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
  } else if (isset($vars['team'])) {
    if (empty($report['starting_items_players']) || empty($report['starting_items_players']['items'])) {
      throw new UserInputException("No starting item items data for players");
    }
    if (empty($report['teams'])) {
      throw new UserInputException("No teams data");
    }

    $context =& $report['teams'];
    $tid = +$vars['team'];
    $sti_matches = unwrap_data($report['starting_items_players']['matches'][0]);

    $data = [];
    $matches_total = 0;

    foreach ($context[$tid]['active_roster'] as $pid) {
      $pl_data = $report['starting_items_players']['items'][0][$pid];
      $pl_data['head'] = $report['starting_items_players']['items_head'];
      $pl_data = unwrap_data($pl_data);

      $matches_total += $sti_matches[$pid]['m'];

      foreach ($pl_data as $iid => $item) {
        if (!isset($data[$iid])) $data[$iid] = [
          "matches" => 0,
          "wins" => 0,
          "lane_wins" => 0,
        ];

        $data[$iid]['matches'] += $item['matches'];
        $data[$iid]['wins'] += $item['wins'];
        $data[$iid]['lane_wins'] += $item['lane_wins'];
      }
    }

    foreach ($data as $iid => $item) {
      $data[$iid]['freq'] = $item['matches'] / $matches_total;
      $data[$iid]['winrate'] = $item['wins'] / $matches_total;
      $data[$iid]['lane_wr'] = $item['lane_wins'] / $matches_total;
    }

    $matches = [
      'm' => round($matches_total / 5),
      'wr' => $context[$tid]['wins'] / $context[$tid]['matches_total']
    ];
  } else {
    if (empty($report['starting_items']) || empty($report['starting_items']['items'])) {
      throw new UserInputException("No starting item items data");
    }

    $data = $report['starting_items']['items'][$selected_rid][$selected_hid];

    if (empty($data)) {
      return [];
    }
  
    $data['head'] = $report['starting_items']['items_head'];
    $data = unwrap_data($data);
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

  foreach ($data as $iid => $d) {
    $data[$iid]['item_id'] = floor($iid / 100);
    $data[$iid]['count'] = $iid % 100;
  }

  return [
    'data' => $data,
    'matches' => $matches,
    'context' => $context_info,
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('StItemsResult', TypeDefs::obj([
    'data' => TypeDefs::mapOf(TypeDefs::obj(['item_id' => TypeDefs::int(), 'count' => TypeDefs::int()])),
    'matches' => TypeDefs::obj([]),
    'context' => TypeDefs::obj([]),
  ]));
}
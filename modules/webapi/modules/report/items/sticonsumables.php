<?php 

#[Endpoint(name: 'items-sticonsumables')]
#[Description('Starting consumables aggregated stats for hero/team/player')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Role code (core.lane)')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Player id')]
#[ReturnSchema(schema: 'StiConsumablesResult')]
class StiConsumables extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (!isset($report['starting_items']) || empty($report['starting_items']['consumables']) &&
    (empty($report['starting_items_players']) || empty($report['starting_items_players']['consumables']))
  )
    throw new \Exception("No consumables data");

  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;
  $selected_pid = $vars['playerid'] ?? 0;

  $context_info = null;

  $data = [
    '5m' => null,
    '10m' => null,
    'all' => null,
  ];

  $matches = [];

  if (isset($vars['playerid']) || (in_array("players", $mods) && !isset($vars['team']))) {
    if (!isset($report['starting_items_players']) || empty($report['starting_items_players']['consumables']))
      throw new \Exception("No consumables data for players");

    foreach ($data as $blk => $d) {
      if (empty($report['starting_items_players']['consumables'][$blk][$selected_rid][$selected_pid])) {
        $data[$blk] = [];
        continue;
      }
      $report['starting_items_players']['consumables'][$blk][$selected_rid][$selected_pid]['head'] = $report['starting_items_players']['cons_head'];
      $data[$blk] = unwrap_data($report['starting_items_players']['consumables'][$blk][$selected_rid][$selected_pid]);
  
      foreach ($data[$blk] as $iid => $d) {
        $data[$blk][$iid]['mean'] = round($d['total'] / $d['matches'], 2);
      }
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
  } else if (isset($vars['team'])) {
    if (!isset($report['starting_items_players']) || empty($report['starting_items_players']['consumables']))
      throw new \Exception("No consumables data for players");
    if (!isset($report['teams']))
      throw new \Exception("No teams data");

    $context =& $report['teams'];
    $tid = $vars['team'];
    $pids = $context[$tid]['active_roster'];

    $sti_matches = unwrap_data($report['starting_items_players']['matches'][0]);

    $matches_total = 0;
  
    foreach ($pids as $pid) {
      $pl_data = [
        '5m' => [],
        '10m' => [],
        'all' => [],
      ];
      foreach ($pl_data as $blk => $d) {
        if (empty($report['starting_items_players']['consumables'][$blk][0][$pid])) {
          $pl_data[$blk] = [];
          continue;
        }
        $report['starting_items_players']['consumables'][$blk][0][$pid]['head'] = $report['starting_items_players']['cons_head'];
        $pl_data[$blk] = unwrap_data($report['starting_items_players']['consumables'][$blk][0][$pid]);
      }

      $matches_total += $sti_matches[$pid]['m'];

      foreach ($pl_data as $blk => $items) {
        foreach ($items as $iid => $item) {
          if (!isset($data[$blk][$iid])) $data[$blk][$iid] = [
            "min" => [],
            "q1" => [],
            "med" => [],
            "q3" => [],
            "max" => [],
            "total" => [],
            "matches" => [],
          ];

          foreach ($item as $k => $v) {
            $data[$blk][$iid][$k][] = $v;
          }
        }
      }
    }

    foreach ($data as $blk => $items) {
      foreach ($items as $iid => $item) {
        $data[$blk][$iid]['matches'] = round(array_sum($data[$blk][$iid]['matches']) / 5);
        $data[$blk][$iid]['total'] = array_sum($data[$blk][$iid]['total']);
        $data[$blk][$iid]['max'] = array_sum($data[$blk][$iid]['max']);
        $data[$blk][$iid]['q3'] = array_sum($data[$blk][$iid]['q3']);
        $data[$blk][$iid]['med'] = array_sum($data[$blk][$iid]['med']);
        $data[$blk][$iid]['q1'] = array_sum($data[$blk][$iid]['q1']);
        $data[$blk][$iid]['min'] = min($data[$blk][$iid]['min']);
        $data[$blk][$iid]['mean'] = round($data[$blk][$iid]['total'] / $matches_total, 2);
      }
    }

    $matches = [
      'm' => round($matches_total / 5),
      'wr' => $context[$tid]['wins'] / $context[$tid]['matches_total']
    ];
  } else {
    if (!isset($report['starting_items']) || empty($report['starting_items']['consumables']))
      throw new \Exception("No consumables data");

    if (is_wrapped($report['starting_items']['matches'][$selected_rid])) {
      $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);
    }

    $matches = $report['starting_items']['matches'][$selected_rid][$selected_hid];

    $matches_total = $matches['m'];

    foreach ($data as $blk => $d) {
      if (empty($report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid])) {
        $data[$blk] = [];
        continue;
      }
      $report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid]['head'] = $report['starting_items']['cons_head'];
      $data[$blk] = unwrap_data($report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid]);
  
      foreach ($data[$blk] as $iid => $d) {
        $data[$blk][$iid]['mean'] = round($d['total'] / $matches_total, 2);
      }
    }
    
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
  SchemaRegistry::register('StiConsumablesResult', TypeDefs::obj([
    'data' => TypeDefs::mapOf(TypeDefs::mapOf(TypeDefs::obj([]))),
    'matches' => TypeDefs::obj([]),
    'context' => TypeDefs::obj([]),
  ]));
}
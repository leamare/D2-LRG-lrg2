<?php 

$endpoints['items-stitems'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['starting_items']) || empty($report['starting_items']['items']) &&
    (empty($report['starting_items_players']) || empty($report['starting_items_players']['items']))
  )
    throw new \Exception("No starting item items data");
  
  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;
  $selected_pid = $vars['playerid'] ?? 0;

  if (isset($vars['playerid']) || (in_array("players", $mods) && !isset($vars['team']))) {
    if (empty($report['starting_items_players']) || empty($report['starting_items_players']['items'])) {
      throw new \Exception("No starting item items data for players");
    }

    $data = $report['starting_items_players']['items'][$selected_rid][$selected_pid];
    
    if (empty($data)) {
      return [];
    }

    $data['head'] = $report['starting_items_players']['items_head'];
    $data = unwrap_data($data);
    $matches = $report['starting_items_players']['matches'][$selected_rid][$selected_pid];
  } else if (isset($vars['team'])) {
    if (empty($report['starting_items_players']) || empty($report['starting_items_players']['items'])) {
      throw new \Exception("No starting item items data for players");
    }
    if (empty($report['teams'])) {
      throw new \Exception("No teams data");
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
      throw new \Exception("No starting item items data");
    }

    $data = $report['starting_items']['items'][$selected_rid][$selected_hid];

    if (empty($data)) {
      return [];
    }
  
    $data['head'] = $report['starting_items']['items_head'];
    $data = unwrap_data($data);
    $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);

    $matches = $report['starting_items']['matches'][$selected_rid][$selected_hid];
  }

  foreach ($data as $iid => $d) {
    $data[$iid]['item_id'] = floor($iid / 100);
    $data[$iid]['count'] = $iid % 100;
  }

  return [
    'data' => $data,
    'matches' => $matches,
  ];
};
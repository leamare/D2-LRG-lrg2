<?php 

$endpoints['items-stibuilds'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['starting_items']) || empty($report['starting_items']['builds']) &&
    (empty($report['starting_items_players']) || empty($report['starting_items_players']['builds']))
  )
    throw new \Exception("No starting item builds data");

  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;
  $selected_pid = $vars['playerid'] ?? 0;

  if (isset($vars['playerid']) || in_array("players", $mods)) {
    if (!isset($report['starting_items_players']) || empty($report['starting_items_players']['builds']))
      throw new \Exception("No starting item builds data for players");
    
    $report['starting_items_players']['builds'][$selected_rid] = unwrap_data($report['starting_items_players']['builds'][$selected_rid]);
    $data = $report['starting_items_players']['builds'][$selected_rid][$selected_pid] ?? null;

    if (empty($data)) {
      return [];
    }

    $report['starting_items_players']['matches'][$selected_rid] = unwrap_data($report['starting_items_players']['matches'][$selected_rid]);
    $matches = $report['starting_items_players']['matches'][$selected_rid][$selected_pid];
  } else {
    if (!isset($report['starting_items']) || empty($report['starting_items']['builds']))
      throw new \Exception("No starting item builds data");

    $report['starting_items']['builds'][$selected_rid] = unwrap_data($report['starting_items']['builds'][$selected_rid]);
    $data = $report['starting_items']['builds'][$selected_rid][$selected_hid] ?? null;
  
    if (empty($data)) {
      return [];
    }
  
    $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);
    $matches = $report['starting_items']['matches'][$selected_rid][$selected_hid];
  }

  return [
    'data' => $data,
    'matches' => $matches,
  ];

  return $res;
};
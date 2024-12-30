<?php 

$endpoints['items-stibuilds'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['starting_items']) || empty($report['starting_items']['builds']))
    throw new \Exception("No starting item builds data");

  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;

  $report['starting_items']['builds'][$selected_rid] = unwrap_data($report['starting_items']['builds'][$selected_rid]);
  $data = $report['starting_items']['builds'][$selected_rid][$selected_hid] ?? null;

  if (empty($data)) {
    return [];
  }

  $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);

  return [
    'data' => $data,
    'matches' => $report['starting_items']['matches'][$selected_rid][$selected_hid],
  ];

  return $res;
};
<?php 

$endpoints['items-sticonsumables'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['starting_items']) || empty($report['starting_items']['consumables']) &&
    (empty($report['starting_items_players']) || empty($report['starting_items_players']['consumables']))
  )
    throw new \Exception("No consumables data");

  $selected_rid = array_flip(ROLES_IDS_SIMPLE)[$vars['position'] ?? ''];
  $selected_hid = $vars['heroid'] ?? 0;
  $selected_pid = $vars['playerid'] ?? 0;

  $roles = array_keys($report['starting_items']['consumables']['all']);

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
        $data[$blk][$iid]['mean'] = round($data[$blk][$iid]['total'] / $data[$blk][$iid]['matches'], 2);
      }
    }

    $matches = [
      'm' => round($matches_total / 5),
      'wr' => $context[$tid]['wins'] / $context[$tid]['matches_total']
    ];
  } else {
    if (!isset($report['starting_items']) || empty($report['starting_items']['consumables']))
      throw new \Exception("No consumables data");

    foreach ($data as $blk => $d) {
      if (empty($report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid])) {
        $data[$blk] = [];
        continue;
      }
      $report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid]['head'] = $report['starting_items']['cons_head'];
      $data[$blk] = unwrap_data($report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid]);
  
      foreach ($data[$blk] as $iid => $d) {
        $data[$blk][$iid]['mean'] = round($d['total'] / $d['matches'], 2);
      }
    }
  
    $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);

    $matches = $report['starting_items']['matches'][$selected_rid][$selected_hid];
  }

  return [
    'data' => $data,
    'matches' => $matches,
  ];
};
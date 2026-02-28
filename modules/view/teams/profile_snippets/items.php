<?php 

$parent_module = $context_mod."team".$tid."-items-";
if ($mod == $context_mod."team".$tid."-items") $unset_module = true;

$res["team".$tid]['items'] = [];

if (isset($report['starting_items_players']['items'])) {
  $res["team".$tid]['items']['stitems'] = [];

  if (check_module($parent_module."stitems")) {
    $parent_module = $context_mod."team".$tid."-items-stitems-";
    if ($mod == $context_mod."team".$tid."-items-stitems") $unset_module = true;

    $res["team".$tid]['items']['stitems']['total'] = "";
    if(check_module($parent_module."total")) {
      $selected_pid = 0;
      $selected_tag = "total";
    }
    foreach ($context[$tid]['active_roster'] as $pid) {
      register_locale_string(player_name($pid), "playerid".$pid);
  
      $res["team".$tid]['items']['stitems']["playerid".$pid] = "";
  
      if(check_module($parent_module."playerid".$pid)) {
        $selected_tag = "playerid".$pid;
        $selected_pid = $pid;
      }
    }
    if (!isset($selected_pid)) {
      $selected_pid = 0;
      $selected_tag = "total";
    }

    $sti_matches = unwrap_data($report['starting_items_players']['matches'][0]);
  
    if ($selected_pid && isset($report['starting_items_players']['items'][0][$selected_pid])) {
      $data = $report['starting_items_players']['items'][0][$selected_pid];
      $data['head'] = $report['starting_items_players']['items_head'];
      $data = unwrap_data($data);
      $matches = $sti_matches[$selected_pid] ?? 0;
    } else {
      $data = [];
      $matches_total = 0;
  
      foreach ($context[$tid]['active_roster'] as $pid) {
        if (!isset($report['starting_items_players']['items'][0][$pid])) continue;
        $pl_data = $report['starting_items_players']['items'][0][$pid];
        $pl_data['head'] = $report['starting_items_players']['items_head'];
        $pl_data = unwrap_data($pl_data);

        $matches_total += $sti_matches[$pid]['m'] ?? 0;

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
    }

    $res["team".$tid]['items']['stitems'][$selected_tag] = rg_generator_stitems(
      "teams-stitems-team$tid-$selected_tag",
      false,
      $selected_pid,
      0,
      $data,
      $matches,
      false
    );
  }
}

if (isset($report['starting_items_players']['consumables'])) {
  $res["team".$tid]['items']['sticonsumables'] = [];

  if (check_module($parent_module."sticonsumables")) {
    $parent_module = $context_mod."team".$tid."-items-sticonsumables-";
    if ($mod == $context_mod."team".$tid."-items-sticonsumables") $unset_module = true;

    $res["team".$tid]['items']['sticonsumables']['total'] = "";
    if(check_module($parent_module."total")) {
      $selected_pid = 0;
      $selected_tag = "total";
    }
    foreach ($context[$tid]['active_roster'] as $pid) {
      register_locale_string(player_name($pid), "playerid".$pid);
  
      $res["team".$tid]['items']['sticonsumables']["playerid".$pid] = "";
  
      if(check_module($parent_module."playerid".$pid)) {
        $selected_tag = "playerid".$pid;
        $selected_pid = $pid;
      }
    }
    if (!isset($selected_pid)) {
      $selected_pid = 0;
      $selected_tag = "total";
    }

    $sti_matches = unwrap_data($report['starting_items_players']['matches'][0]);

    $data = [
      '5m' => [],
      '10m' => [],
      'all' => [],
    ];
  
    if ($selected_pid) {
      foreach ($data as $blk => $d) {
        if (empty($report['starting_items_players']['consumables'][$blk][0][$selected_pid])) {
          $data[$blk] = [];
          continue;
        }
        $report['starting_items_players']['consumables'][$blk][0][$selected_pid]['head'] = $report['starting_items_players']['cons_head'];
        $data[$blk] = unwrap_data($report['starting_items_players']['consumables'][$blk][0][$selected_pid]);
      }
      $matches = $sti_matches[$selected_pid] ?? [];
    } else {
      $matches_total = 0;
  
      foreach ($context[$tid]['active_roster'] as $pid) {
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

        $matches_total += $sti_matches[$pid]['m'] ?? 0;

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
        }
      }

      $matches = [
        'm' => round($matches_total / 5),
        'wr' => $context[$tid]['wins'] / $context[$tid]['matches_total']
      ];
    }

    $res["team".$tid]['items']['sticonsumables'][$selected_tag] = rg_generator_sticonsumables(
      "items-sticonsumables-team$tid-$selected_tag",
      false,
      $selected_pid,
      0,
      $data,
      $matches,
      false
    );
  }
}
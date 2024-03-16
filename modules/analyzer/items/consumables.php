<?php 

function sti_consumables_query($_isheroes, $_isroles, $_isLimitRoles, $si_matches = []) {
  global $conn, $__sttime;

  $_tag = $_isheroes ? "hero_id" : "playerid";

  echo "[ ] CONSUMABLES $_tag ";

  $r = [];

  resetbltime();

  // CONSUMABLES

  $si_cons = [ ];

  $_indexes_tbl = implode(
    " UNION ", 
    array_map(
      function($a) {
        return "SELECT $a AS idx";
      },
      range(0, 45)
    )
  );

  foreach ([ '5m', '10m', 'all' ] as $blk) {
    $si_cons[$blk] = array_fill(0, $_isroles ? 6 : 1, [ [] ]);

    $blk_q = "\"$blk\"";

    if ($_isroles) {
      $sql = <<<SQL
        SELECT 
          si.$_tag,
          si.item_id,
          am.`role`, 
          max(item_count) max_item_cnt,
          min(item_count) min_item_cnt,
          percentile_cont(item_count, 0.25) q1_cnt,
          median(item_count) med_cnt,
          percentile_cont(item_count, 0.75) q3_cnt,
          SUM(item_count) total_cnt,
          COUNT(DISTINCT si.matchid) mtchs,
          SUM(am.lane_won)/2 lane_wins
        FROM (
          SELECT 
            matchid, 
            $_tag, 
            JSON_UNQUOTE( JSON_EXTRACT( JSON_KEYS(JSON_EXTRACT(consumables, CONCAT('$."', $blk_q, '"'))), CONCAT("$[", idx, "]") ) ) item_id,
            JSON_EXTRACT(consumables, CONCAT('$."', $blk_q, '".', JSON_EXTRACT( JSON_KEYS(JSON_EXTRACT(consumables, CONCAT('$."', $blk_q, '"'))), CONCAT("$[", idx, "]") ), '') ) item_count,
            hero_id as hid
          FROM starting_items si 
          JOIN ( 
            $_indexes_tbl
          ) AS indexes
          WHERE idx < JSON_LENGTH(consumables, CONCAT('$."', $blk_q, '"'))
          ORDER BY 1, 2, 4 DESC
        ) si
          JOIN adv_matchlines am on si.matchid = am.matchid and am.heroid = si.hid
        GROUP BY 1, 2, 3
        ORDER BY 1, 2, 3
      SQL;

      if ($conn->multi_query($sql) === TRUE) echo "$blk+";
      else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n".$sql."\n");

      $query_res = $conn->store_result();

      for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
        [ $hid, $iid, $rid, $max_cnt, $min_cnt, $q1_cnt, $med_cnt, $q3_cnt, $total, $mtch ] = $row;

        if (!isset($si_cons[$blk][$rid])) continue;
        if (!isset($si_cons[$blk][$rid][$hid])) $si_cons[$blk][$rid][$hid] = [];
        $si_cons[$blk][$rid][$hid][$iid] = [
          'min' => +$min_cnt,
          'q1' => +$q1_cnt,
          'med' => +$med_cnt,
          'q3' => +$q3_cnt,
          'max' => +$max_cnt,
          'total' => +$total,
          'matches' => +$mtch,
        ];
      }

      $query_res->free_result();

      echo ' [ '.echobltime().' ] ';
    }

    $sql = <<<SQL
      SELECT 
        si.$_tag,
        si.item_id,
        max(item_count) max_item_cnt,
        min(item_count) min_item_cnt,
        percentile_cont(item_count, 0.25) q1_cnt,
        median(item_count) med_cnt,
        percentile_cont(item_count, 0.75) q3_cnt,
        SUM(item_count) total_cnt,
        COUNT(DISTINCT si.matchid) mtchs
      FROM (
        SELECT 
          matchid, 
          $_tag, 
          JSON_UNQUOTE( JSON_EXTRACT( JSON_KEYS(JSON_EXTRACT(consumables, CONCAT('$."', $blk_q, '"'))), CONCAT("$[", idx, "]") ) ) item_id,
          JSON_EXTRACT(consumables, CONCAT('$."', $blk_q, '".', JSON_EXTRACT( JSON_KEYS(JSON_EXTRACT(consumables, CONCAT('$."', $blk_q, '"'))), CONCAT("$[", idx, "]") ), '') ) item_count
        FROM starting_items si 
        JOIN ( 
          $_indexes_tbl
        ) AS indexes
        WHERE idx < JSON_LENGTH(consumables, CONCAT('$."', $blk_q, '"'))
        ORDER BY 1, 2, 4 DESC
      ) si
      GROUP BY 1, 2
      ORDER BY 1, 2
    SQL;

    if ($conn->multi_query($sql) === TRUE) echo "~ ";
    else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n".$sql."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      [ $hid, $iid, $max_cnt, $min_cnt, $q1_cnt, $med_cnt, $q3_cnt, $total, $mtch ] = $row;

      if (!isset($si_cons[$blk][0][$hid])) $si_cons[$blk][0][$hid] = [];
      $si_cons[$blk][0][$hid][$iid] = [
        'min' => +$min_cnt,
        'q1' => +$q1_cnt,
        'med' => +$med_cnt,
        'q3' => +$q3_cnt,
        'max' => +$max_cnt,
        'total' => +$total,
        'matches' => +$mtch,
      ];

      foreach ($si_cons[$blk] as $rid => $heroes) {
        $items_0 = [];

        foreach ($heroes as $hid => $items) {
          if (!$hid) continue;

          foreach ($items as $iid => $stats) {
            if (!isset($items_0[$iid])) {
              $items_0[$iid] = [
                'min' => 99999,
                'q1' => [],
                'med' => [],
                'q3' => [],
                'max' => 0,
                'total' => 0,
                'matches' => 0,
              ];
            }

            $items_0[$iid]['min'] = min($items_0[$iid]['min'], $stats['min']);
            $items_0[$iid]['max'] = max($items_0[$iid]['max'], $stats['max']);
            $items_0[$iid]['q1'][] = $stats['q1'];
            $items_0[$iid]['q3'][] = $stats['q3'];
            $items_0[$iid]['med'][] = $stats['med'];
            $items_0[$iid]['total'] += $stats['total'];
            $items_0[$iid]['matches'] += $stats['matches'];
          }
        }

        foreach($items_0 as $iid => $stats) {
          $stats['q1'] = round(array_sum($stats['q1'])/count($stats['q1']));
          $stats['q3'] = round(array_sum($stats['q3'])/count($stats['q3']));
          $stats['med'] = round(array_sum($stats['med'])/count($stats['med']));

          $si_cons[$blk][$rid][0][$iid] = $stats;
        }
      }
    }

    $query_res->free_result();

    echo ' [ '.echobltime().' ] ';
  }

    // filtering low roles

    if ($_isLimitRoles) {
      if (empty($si_matches)) {
        $si_matches = [];

        foreach ($si_cons['all'] as $rid => $heroes) {
          $si_matches[$rid] = [];
          foreach ($heroes as $hid => $items) {
            $si_matches[$rid][$hid] = [ 'm' => max(
              array_map(function($a) {
                return $a['matches'];
              }, $items)
            ) ];
          }
        }
      }

      foreach ($si_matches as $rid => $heroes) {
        if (is_wrapped($heroes)) {
          $heroes = $si_matches[$rid] = unwrap_data($heroes);
        }
        if (!$rid) continue;
        foreach ($heroes as $hid => $bs) {
          if ($si_matches[$rid][$hid]['m'] <= $si_matches[0][$hid]['m'] * 0.025) {
            foreach (array_keys($si_cons) as $blk) {
              unset($si_cons[$blk][$rid][$hid]);
            }
          }
        }
      }
    }

  // output

  $r = [
    'consumables' => $si_cons,
  ];

  foreach ($r['consumables'] as $blk => $roles) {
    foreach (range(0, 5) as $rid) {
      if (!isset($r['consumables'][$blk][$rid])) continue;

      foreach ($r['consumables'][$blk][$rid] as $hid => $items) {
        $r['consumables'][$blk][$rid][$hid] = wrap_data($items, true, true, true);
        unset($r['consumables'][$blk][$rid][$hid]['head']);
      }
    }
  }
  $r['cons_head'] = [ "min", "q1", "med", "q3", "max", "total", "matches" ];

  echo " [ ".echobltime()." ] \n";

  return $r;
}

if ($lg_settings['ana']['consumables']) {
  if (!isset($result['starting_items'])) $result['starting_items'] = [];

  foreach (sti_consumables_query(
    true, 
    $lg_settings['ana']['consumables_roles'], 
    $lg_settings['ana']['starting_builds_roles_limit'],
    $result['starting_items']['matches'] ?? []
  ) as $k => &$v)
    $result['starting_items'][$k] = $v;
}

if ($lg_settings['ana']['consumables_players'] && $lg_settings['ana']['players']) {
  if (!isset($result['starting_items_players'])) $result['starting_items_players'] = [];

  foreach (sti_consumables_query(
    false, 
    $lg_settings['ana']['consumables_players_roles'], 
    $lg_settings['ana']['starting_builds_roles_players_limit'],
    $result['starting_items_players']['matches'] ?? []
  ) as $k => &$v)
    $result['starting_items_players'][$k] = $v;
}
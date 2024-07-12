<?php 

// these could easily be replaced by classes
// but this is how we operate

const SI_BUILD_DUMMY = [
  "build" => null,
  "matches" => 0,
  "wins" => 0,
  "lane_wins" => 0,
];
const SI_ROLE_DUMMY = [
  "builds" => [],
  "matches" => 0,
  "wins" => 0,
  "lane_wins" => 0,
];
const SI_ITEM_DUMMY = [
  'matches' => 0,
  'wins' => 0,
  'lane_wins' => 0,
];

function sti_item_builds_query($_isheroes, $_isBuilds, $_isItems, $_isRolesItems, $_isRolesBuilds, $_islimit, $_isLimitRoles) {
  global $conn, $__sttime;

  $_tag = $_isheroes ? "hero_id" : "playerid";

  echo "[ ] STARTING ITEMS DATA - ";

  $r = [];

  resetbltime();

  // BUILDS QUERY

  $sql = <<<SQL
    SELECT 
      si.starting_items, 
      si.$_tag, 
      am.`role`, 
      SUM(m.radiantWin = ml.isRadiant) wins, 
      SUM(1) mtchs,
      SUM(am.lane_won)/2 lane_wins
    from starting_items si join matches m on m.matchid = si.matchid 
    join matchlines ml on si.matchid = ml.matchid and si.playerid = ml.playerid 
    join adv_matchlines am on am.matchid = ml.matchid and am.playerid = ml.playerid

    where am.`role` < 6

    group by 2, 3, 1
    order by 2 asc, 5 desc;
  SQL;

  if ($conn->multi_query($sql) === TRUE) echo "BUILDS $_tag ";
  else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n".$sql."\n");

  $si_res = array_fill(0, 6, [ SI_ROLE_DUMMY ]);

  $query_res = $conn->store_result();

  echo ' [ '.echobltime().' ] ';

  // BUILDS STATS

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    [ $build, $hid, $role, $wins, $mtchs, $lane_wins ] = $row;

    $build = (array)json_decode($build);
    $build = array_filter($build, function($el) {
      return +$el;
    });
    sort($build);

    if (!isset($si_res[$role][$hid])) $si_res[$role][$hid] = SI_ROLE_DUMMY;
    if (!isset($si_res[0][$hid])) $si_res[0][$hid] = SI_ROLE_DUMMY;

    $si_res[$role][$hid]['matches'] += $mtchs;
    $si_res[0][$hid]['matches'] += $mtchs;

    $si_res[$role][$hid]['wins'] += $wins;
    $si_res[0][$hid]['wins'] += $wins;

    $si_res[$role][$hid]['lane_wins'] += $lane_wins;
    $si_res[0][$hid]['lane_wins'] += $lane_wins;

    $si_res[$role][0]['matches'] += $mtchs;
    $si_res[0][0]['matches'] += $mtchs;
    
    $si_res[$role][0]['wins'] += $wins;
    $si_res[0][0]['wins'] += $wins;

    $si_res[$role][0]['lane_wins'] += $lane_wins;
    $si_res[0][0]['lane_wins'] += $lane_wins;

    if (empty($build)) {
      continue;
    }

    $build_tag = implode(',', $build);
    if (!isset($si_res[$role][$hid]['builds'][$build_tag])) {
      $si_res[$role][$hid]['builds'][$build_tag] = SI_BUILD_DUMMY;
      $si_res[$role][$hid]['builds'][$build_tag]['build'] = $build;
    }
    if (!isset($si_res[0][$hid]['builds'][$build_tag])) {
      $si_res[0][$hid]['builds'][$build_tag] = SI_BUILD_DUMMY;
      $si_res[0][$hid]['builds'][$build_tag]['build'] = $build;
    }
    if (!isset($si_res[$role][0]['builds'][$build_tag])) {
      $si_res[$role][0]['builds'][$build_tag] = SI_BUILD_DUMMY;
      $si_res[$role][0]['builds'][$build_tag]['build'] = $build;
    }
    if (!isset($si_res[0][0]['builds'][$build_tag])) {
      $si_res[0][0]['builds'][$build_tag] = SI_BUILD_DUMMY;
      $si_res[0][0]['builds'][$build_tag]['build'] = $build;
    }

    $si_res[$role][$hid]['builds'][$build_tag]['matches'] += $mtchs;
    $si_res[$role][$hid]['builds'][$build_tag]['wins'] += $wins;
    $si_res[$role][$hid]['builds'][$build_tag]['lane_wins'] += $lane_wins;

    $si_res[0][$hid]['builds'][$build_tag]['matches'] += $mtchs;
    $si_res[0][$hid]['builds'][$build_tag]['wins'] += $wins;
    $si_res[0][$hid]['builds'][$build_tag]['lane_wins'] += $lane_wins;

    $si_res[$role][0]['builds'][$build_tag]['matches'] += $mtchs;
    $si_res[$role][0]['builds'][$build_tag]['wins'] += $wins;
    $si_res[$role][0]['builds'][$build_tag]['lane_wins'] += $lane_wins;

    $si_res[0][0]['builds'][$build_tag]['matches'] += $mtchs;
    $si_res[0][0]['builds'][$build_tag]['wins'] += $wins;
    $si_res[0][0]['builds'][$build_tag]['lane_wins'] += $lane_wins;
  }

  $query_res->free_result();

  echo ' [ '.echobltime().' ] ';

  // INDIVIDUAL ITEMS STATS

  $si_items = [];
  $si_matches = [];

  foreach ($si_res as $rid => $heroes) {
    $si_items[$rid] = [];
    $si_matches[$rid] = [];

    foreach ($heroes as $hid => $role) {
      if (empty($role)) {
        $si_items[$rid][$hid] = null;
        continue;
      }

      if (!isset($si_items[$rid][$hid])) {
        $si_items[$rid][$hid] = [];
      }

      $maxtotal = $si_res[0][$hid]['matches'] ?? $si_matches[0][$hid]['m'];

      uasort($role['builds'], function($b, $a) {
        return $a['matches'] <=> $b['matches'];
      });
      
      $max = $role['matches'];

      // individual starting items

      $si_items[$rid][$hid] = [];
      foreach ($role['builds'] as $bt => $build) {
        $enc = [];
        foreach ($build['build'] as $iid) {
          $id = $iid * 100 + ($enc[$iid] ?? 1);
          $enc[$iid] = ($enc[$iid] ?? 1) + 1;
          if (!isset($si_items[$rid][$hid][$id])) {
            $si_items[$rid][$hid][$id] = SI_ITEM_DUMMY;
          }

          $si_items[$rid][$hid][$id]['matches'] += $build['matches'];
          $si_items[$rid][$hid][$id]['wins'] += $build['wins'];
          $si_items[$rid][$hid][$id]['lane_wins'] += $build['lane_wins'];
        }
      }
      foreach ($si_items[$rid][$hid] as $iid => $stats) {
        $si_items[$rid][$hid][$iid]['freq'] = round( 
          $si_items[$rid][$hid][$iid]['matches'] / $role['matches'],
          4
        );
      }
      uasort($si_items[$rid][$hid], function($b, $a) {
        return $a['matches'] <=> $b['matches'];
      });

      // rebuilding the builds

      $builds_new = [];
      foreach ($role['builds'] as $bt => $build) {
        $build['winrate'] = round( $build['wins']/$build['matches'], 4);
        $build['lane_wr'] = round( $build['lane_wins']/$build['matches'], 4);
        $build['ratio'] = round( $build['matches']/$role['matches'], 4);

        // if (!$_islimit || ($build['ratio'] > 0.015 && $role['matches'] > $maxtotal*0.025)) {  
          $builds_new[] = $build;
        // }
      }

      $si_matches[$rid][$hid] = [
        'm' => $si_res[$rid][$hid]['matches'],
        'wr' => $si_res[$rid][$hid]['matches'] ? round( $si_res[$rid][$hid]['wins']/$si_res[$rid][$hid]['matches'], 4) : 0,
      ];

      $si_res[$rid][$hid] = $builds_new;
    }
  }

  echo " [ ".echobltime()." ] \n";

  // filtering low builds

  if ($_islimit) {
    foreach ($si_res as $rid => $heroes) {
      foreach ($heroes as $hid => $bs) {
        if (empty($bs)) continue;
        $vals = array_map(function($a) {
          return $a['matches'];
        }, $bs);
        $limit = max( quantile($vals, 0.75), $si_matches[$rid][$hid]['m'] * 0.005 );
        $si_matches[$rid][$hid]['l'] = round($limit);
        $si_res[$rid][$hid] = array_filter($si_res[$rid][$hid], function($a) use (&$limit) {
          return $a['matches'] > $limit;
        });
      }
    }
  }

  // filtering low roles

  if ($_isLimitRoles) {
    foreach ($si_res as $rid => $heroes) {
      if (!$rid) continue;
      foreach ($heroes as $hid => $bs) {
        if ($si_matches[$rid][$hid]['m'] <= $si_matches[0][$hid]['m'] * 0.025) {
          unset($si_res[$rid][$hid]);
          unset($si_items[$rid][$hid]);
          continue;
        }
      }
    }
  }

  // output

  $r = [
    'items' =>   $si_items,
    'builds' =>  $si_res,
    'matches' => $si_matches,
  ];

  $r['items_head'] = [ "matches", "wins", "lane_wins", "freq" ];

  // Starting items and builds are calculated and fetched with roles in mind
  // so roles params are just unsetting some of them

  foreach(range(0, 5) as $rid) {
    if ($rid && !$_isRolesItems) {
      unset($r['items'][$rid]);
    } else {
      foreach ($r['items'][$rid] as $hid => $items) {
        if (empty($items)) {
          unset($r['items'][$rid][$hid]);
          continue;
        }
        $r['items'][$rid][$hid] = wrap_data($items, true, true, true);
        unset($r['items'][$rid][$hid]['head']);
      }
    }

    if ($rid && !$_isRolesBuilds) {
      unset($r['items'][$rid]);
    } else {
      foreach ($r['builds'][$rid] as $hid => $items) {
        if (empty($items)) {
          unset($r['builds'][$rid][$hid]);
        }
      }
      $r['builds'][$rid] = wrap_data($r['builds'][$rid], true, true, true);
    }

    if ($rid && !$_isRolesBuilds && !$_isRolesItems) {
      unset($r['matches'][$rid]);
      continue;
    }

    $r['matches'][$rid] = wrap_data($r['matches'][$rid], true, true, true);
  }

  if (!$_isBuilds) {
    unset($r['builds']);
  }
  if (!$_isItems) {
    unset($r['items']);
  }

  return $r;
}


if ($lg_settings['ana']['starting_items'] || $lg_settings['ana']['starting_builds']) {
  if (!isset($result['starting_items'])) $result['starting_items'] = [];

  $data = sti_item_builds_query(
    true, 
    $lg_settings['ana']['starting_builds'],
    $lg_settings['ana']['starting_items'],
    $lg_settings['ana']['starting_items_roles'],
    $lg_settings['ana']['starting_builds_roles'],
    $lg_settings['ana']['starting_builds_limit'],
    $lg_settings['ana']['starting_builds_roles_limit']
  );

  foreach ($data as $k => &$v)
    $result['starting_items'][$k] = $v;
}

if (($lg_settings['ana']['starting_items_players'] || $lg_settings['ana']['starting_builds_players']) && $lg_settings['ana']['players']) {
  if (!isset($result['starting_items_players'])) $result['starting_items_players'] = [];

  $data = sti_item_builds_query(
    false, 
    $lg_settings['ana']['starting_builds_players'],
    $lg_settings['ana']['starting_items_players'],
    $lg_settings['ana']['starting_items_players_roles'],
    $lg_settings['ana']['starting_builds_players_roles'],
    $lg_settings['ana']['starting_builds_players_limit'],
    $lg_settings['ana']['starting_builds_roles_players_limit']
  );

  foreach ($data as $k => &$v)
    $result['starting_items_players'][$k] = $v;
}
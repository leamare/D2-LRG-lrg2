<?php 

$endpoints['items-profile'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['stats']))
      throw new \Exception("No items stats data");

    if (!isset($vars['item'])) {
      throw new \Exception("Need to specify item");
    }

    $res['__endp'] = 'items-profiles';
    $res['__stopRepeater'] = ['team', 'playerid', 'heroid'];

    $item = (int)$vars['item'];

    if (empty($vars['gets']) || $vars['gets'] == '*') {
      // $vars['gets'] = [ 'total', 'heroes', 'heroes-matches', 'heroes-rank-top', 'heroes-rank-bot', 'records', 'records-best' ];
      $vars['gets'] = [
        'total',
        'heroes-prate',
        'heroes-rank-top',
        'heroes-rank-bot',
        'records-best'
      ];
    }

    $starting_iids = [];

    if (is_wrapped($report['items']['stats'])) {
      $report['items']['stats'] = unwrap_data($report['items']['stats']);
    }
  
    // Get starting items IDs
    if (isset($report['starting_items']['items'][0][0]['keys'])) {
      $starting_iids = array_unique(
        array_map(function($a) {
          return floor($a/100);
        }, $report['starting_items']['items'][0][0]['keys'])
      );
    }
  
    // Get consumables IDs
    $consumable_iids = [];
    if (isset($report['starting_items']['consumables'])) {
      $consumable_iids = $report['starting_items']['consumables']['all'][0][0]['keys'];
    }
  
    // Combine all item IDs
    $item_ids = array_unique(array_merge(
      array_keys($report['items']['stats']['total']), 
      $starting_iids,
      $consumable_iids
    ));

    $is_regular = in_array($item, array_keys($report['items']['stats']['total']));
    $is_starting = in_array($item, $starting_iids);
    $is_consumable = isset($report['starting_items']['consumables'])
      && in_array($item, $report['starting_items']['consumables']['all'][0][0]['keys']);

    foreach ($item_ids as $iid) {
      $item_names[$iid] = [
        'name' => $meta['items_full'][$iid]['localized_name'],
        'tag' => $meta['items_full'][$iid]['name'],
        'category' => null
      ];
  
      // Determine item category
      foreach ($meta['item_categories'] as $cat => $items) {
        if (in_array($iid, $items)) {
          $item_names[$iid]['category'] = $cat;
          break;
        }
      }
    }

    $res = [
      'info' => [
        'is_regular' => $is_regular,
        'is_starting' => $is_starting,
        'is_consumable' => $is_consumable,
      ],
      'meta' => $item_names[$item],
      'stats' => [
        'overview' => [],
      ],
    ];

    $stats = [];

    if ($is_regular) {
      $ranks = [];

      $ranking_sort = function($a, $b) {
        return items_ranking_sort($a, $b);
      };
  
      uasort($report['items']['stats']['total'], $ranking_sort);
  
      $increment = 100 / sizeof($report['items']['stats']['total']); $i = 0;
  
      foreach ($report['items']['stats']['total'] as $id => $el) {
        if(isset($last) && $el == $last) {
          $i++;
          $ranks[$id] = $last_rank ?? 0;
        } else
          $ranks[$id] = 100 - $increment*$i++;
        $last = $el;
        $last_rank = $ranks[$id];
      }
      unset($last);
  
      // REFERENCE TABLE
  
      $data = $report['items']['stats']['total'][$item];
      $data['rank'] = $ranks[$item];
      if ($data['grad'] < 0) {
        $data['critical'] = $data['q1'] - 60*($data['early_wr'] - 0.5)/$data['grad'];
      }
  
      // heroes with most purchases
  
      unset($report['items']['stats']['total']);
      $heroes = [];
  
      foreach ($report['items']['stats'] as $hero => $items) {
        if (!empty($items[$item]))
          $heroes[$hero] = $items[$item];
      }
  
      $ranks = [];
  
      $ranking_sort = function($a, $b) {
        return items_ranking_sort($a, $b);
      };
  
      uasort($heroes, $ranking_sort);
  
      $increment = 100 / sizeof($heroes); $i = 0;
  
      foreach ($heroes as $id => $el) {
        if(isset($last) && $el == $last) {
          $i++;
          $ranks[$id] = $last_rank;
        } else
          $ranks[$id] = 100 - $increment*$i++;
        $last = $el;
        $last_rank = $ranks[$id];
      }
      unset($last);
  
      foreach ($heroes as $id => $line) {
        $heroes[$id]['rank'] = $ranks[$id];
        $heroes[$id]['id'] = $id;
      }
  
      uasort($heroes, function($a, $b) {
        return $b['prate'] <=> $a['prate'];
      });
      $min_prate = $heroes[ array_keys($heroes)[floor(count($heroes)*0.75)] ]['prate'];
  
      $heroes_purchases = array_slice($heroes, 0, 8);

      $res['stats']['heroes_top_purchases'] = $heroes_purchases;
  
      $heroes = array_filter($heroes, function($a) use ($min_prate) {
        return $a['prate'] > $min_prate;
      });
  
      $group_size = min(floor(count($heroes)/2), 8);
  
      // best ranked heroes
  
      uasort($heroes, function($a, $b) {
        return $b['rank'] <=> $a['rank'];
      });
  
      $heroes_rank_top = array_slice($heroes, 0, $group_size);
  
      // worst ranked purchases
  
      uasort($heroes, function($a, $b) {
        return $a['rank'] <=> $b['rank'];
      });
  
      $heroes_rank_bot = array_slice($heroes, 0, $group_size);

      $res['stats']['heroes_ranks'] = [
        'best' => $heroes_rank_top,
        'worst' => $heroes_rank_bot,
      ];
  
      // best records
  
      if (isset($report['items']['records'])) {
        $records = [];
  
        if (is_wrapped($report['items']['records'])) {
          $report['items']['records'] = unwrap_data($report['items']['records']);
        }
  
        if (!empty($report['items']['records'][$item])) {
          foreach ($report['items']['records'][$item] as $hero => $line) {
            if (empty($line) || empty($line['match'])) continue;
            $line['hero'] = $hero;
            $records[] = $line;
          }
        }
  
        uasort($records, function($a, $b) {
          return $b['diff'] <=> $a['diff'];
        });
      
        $records_best = array_slice($records, 0, 8);

        $res['stats']['records'] = $records_best;
      }

      // main stats

      $stats['rank'] = round($data['rank'], 2);
      $purchases = (float)$data['purchases'];
      $matches = (float)$data['matchcount'];
      $stats['avg_purchases_per_game'] = $purchases / $matches;

      $stats['purchases'] = +$data['purchases'];
      $stats['matches'] = +$data['matchcount'];
      $stats['purchase_rate'] = $data['prate'];
      $stats['item_time_median_long'] = $data['median'];
      $stats['items_wr_gradient'] = $data['grad'];
      if ($data['grad'] < -0.01) {
        $stats['item_time_window_q1'] = $data['q1'];
        $stats['item_time_window_critical'] = $data['critical'];
        $stats['item_time_window_diff'] = $data['critical']-$data['q1'];
      } else {
        $stats['item_time_window_q1'] = $data['q1'];
        $stats['item_time_window_q3'] = $data['q3'];
        $stats['item_time_window_diff'] = $data['q3']-$data['q1'];
      }
      $stats['winrate'] = $data['winrate'];
      $stats['winrate_diff'] = $data['winrate'] - $data['wo_wr'];
      $stats['early_wr'] = $data['early_wr'];
      $stats['late_wr'] = $data['late_wr'];
    }

    if ($is_starting) {
      $sti_data = $report['starting_items']['items'][0][0];
      $sti_data['head'] = $report['starting_items']['items_head'];
      $sti_data = unwrap_data($sti_data);
      $report['starting_items']['matches'][0] = unwrap_data($report['starting_items']['matches'][0]);
  
      $item_sti = $sti_data[$item * 100 + 1];
  
      $heroes_sti = [];
  
      foreach ($report['starting_items']['items'][0] as $hero => $line) {
        if (!$hero) continue;
        $id = array_search($item * 100 + 1, $line['keys']);
        if ($id === false) continue;
        if (isset($line['data'][$id])) {
          $heroes_sti[$hero] = array_combine($report['starting_items']['items_head'], $line['data'][$id]);
        }
      }
  
      uasort($heroes_sti, function($a, $b) {
        return $b['matches'] <=> $a['matches'];
      });
  
      $heroes_sti_most_matches = array_slice(array_keys($heroes_sti), 0, 8);

      $res['stats']['heroes_starting_most_matches'] = $heroes_sti_most_matches;
  
      uasort($heroes_sti, function($a, $b) {
        return ($b['wins'] * 100 / $b['matches']) <=> ($a['wins'] * 100 / $a['matches']);
      });
  
      $heroes_sti_winrate = array_slice(array_keys($heroes_sti), 0, 8);

      $res['stats']['heroes_starting_best_winrate'] = $heroes_sti_winrate;

      // stats

      $stats['matches_starting'] = $item_sti['matches'];
      $stats['purchase_rate_starting'] = $item_sti['freq'] * 100;
      $stats['winrate_starting'] = $item_sti['wins'] / $item_sti['matches'];
      $stats['lane_wr_starting'] = $item_sti['lane_wins'] / $item_sti['matches'];

      // variants

      $variants = [];

      foreach ($report['starting_items']['items'][0][0]['keys'] as $i => $item_sti) {
        if (floor($item_sti / 100) != $item) continue;
        $variants[$item_sti] = array_combine($report['starting_items']['items_head'], $report['starting_items']['items'][0][0]['data'][$i]);
      }

      $res['stats']['variants'] = $variants;

      // builds

      if (isset($report['starting_items']['builds'])) {
        $builds = [];
  
        foreach ($report['starting_items']['builds'][0]['data'][0] as $build) {
          $build = array_combine($report['starting_items']['builds'][0]['head'][1], $build);
          if (!in_array($item, $build['build'])) continue;
          $builds[] = $build;
        }

        $res['stats']['starting_builds_featured_total'] = $builds;
      }
    }

    if ($is_consumable) {
      $rid_ref = 0;
      $hid_ref = 0;
  
      $cons_data = [
        '5m' => null,
        '10m' => null,
        'all' => null,
      ];
  
      $cons_hero_uses = [];
      $cons_role_uses = [];
    
      foreach ($cons_data as $blk => $d) {
        if (empty($report['starting_items']['consumables'][$blk][$rid_ref][$hid_ref])) {
          $cons_data[$blk] = array_combine($report['starting_items']['cons_head'], array_fill(0, count($report['starting_items']['cons_head']), 0));
          continue;
        }
        $report['starting_items']['consumables'][$blk][$rid_ref][$hid_ref]['head'] = $report['starting_items']['cons_head'];
        $cons_data[$blk] = unwrap_data($report['starting_items']['consumables'][$blk][$rid_ref][$hid_ref]);
  
        foreach ($report['starting_items']['consumables'][$blk][$rid_ref] as $hid => $line) {
          $index = array_search($item, $line['keys']);
          if ($index === false) continue;
  
          $cons_hero_uses[$hid] = array_combine($report['starting_items']['cons_head'], $line['data'][$index]);
          $cons_hero_uses[$hid]['mean'] = $cons_hero_uses[$hid]['total'] / $cons_hero_uses[$hid]['matches'];
        }
  
        foreach ($report['starting_items']['consumables'][$blk] as $rid => &$heroes) {
          $index = array_search($item, $heroes[0]['keys']);
          if ($index === false) continue;
  
          $cons_role_uses[$rid] = array_combine($report['starting_items']['cons_head'], $heroes[0]['data'][$index]);
          $cons_role_uses[$rid]['mean'] = $cons_role_uses[$rid]['total'] / $cons_role_uses[$rid]['matches'];
        }
      }
  
      uasort($cons_hero_uses, function($a, $b) {
        return $b['mean'] <=> $a['mean'];
      });
  
      uasort($cons_role_uses, function($a, $b) {
        return $b['mean'] <=> $a['mean'];
      });
  
      $cons_hero_uses_highest_mean = array_slice(array_keys($cons_hero_uses), 0, 8);
      $cons_role_most_uses = array_keys($cons_role_uses)[0];

      $res['stats']['heroes_consumable_most_uses'] = $cons_hero_uses_highest_mean;
      $res['stats']['heroes_consumable_best_winrate'] = $cons_role_most_uses;
  
      uasort($cons_hero_uses, function($a, $b) {
        return $a['mean'] <=> $b['mean'];
      });
  
      $cons_hero_uses_lowest_mean = array_slice(array_keys($cons_hero_uses), 0, 8);

      $res['stats']['heroes_consumable_worst_winrate'] = $cons_hero_uses_lowest_mean;

      // stats

      $stats['matches_cons_all'] = $cons_data['all'][$item]['matches'];
      $stats['median_cons_all'] = $cons_data['5m'][$item]['med'];
      $stats['q1_cons_all'] = $cons_data['5m'][$item]['q1'];
      $stats['q3_cons_all'] = $cons_data['5m'][$item]['q3'];
      $stats['consumable_role_uses_count'] = count($cons_role_uses);
      $stats['consumable_hero_uses_count'] = count($cons_hero_uses);

      // role uses

      $res['stats']['consumable_role_uses'] = [];

      foreach ($cons_role_uses as $role => $d) {
        if (!$role) continue;
        $res['stats']['consumable_role_uses'][$role] = $d;
      }
    }

    $res['stats']['overview'] = $stats;
    
    return $res;
};
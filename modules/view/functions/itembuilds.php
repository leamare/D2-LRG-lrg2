<?php

function calculate_favor_score($array) {
  $res = 0;
  $sz = count($array);

  foreach ($array as $i => $n) {
    $res += $n/($sz-$i);
  }

  return $res;
}

function traverse_build_tree(&$stats, &$tree, $m_lim, $m_role, $root = '0', $build = null) {
  // metadata usage for item categories
  global $meta;


  // initially this was made with an assumption that I will implement branching eventually
  // it didn't happen, but I believe I might do that one day
  if (!$build) {
    $build = [
      'path' => [],
      'favors' => [],
      'sit_raw' => [],
      'sit' => [],
      'alt' => [],
      'swap' => [],
      'swap_raw' => [],
      'lategamePoint' => null,
      'lategame' => [],
      'partial' => false,
    ];
    // neutrals, early are added later
  }

  $ord = 0; // current order round number
  $prev = 0; // primary item of the previous round

  while (true) {
    // exit only when everything else is done, and no primary item selected
    if ($root === null) break;

    $t = $tree[$root];
    if (empty($t['children'])) break; // nowhere to go

    // there will be a lot of such sorting by match numbers
    // initial sort is necessary to apply favor points transformations correctly
    uasort($t['children'], function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    // favor points assignment
    $items_list = array_keys($t['children']);
    $i_sz = count($items_list);
    foreach ($items_list as $i => $item) {
      if (!isset($build['favors'][$item])) $build['favors'][$item] = [];
      // using 1.5 as Favor Factor, all good values lie somewhere between 1 and 2
      $build['favors'][$item][] = pow($i/$i_sz, 1.5);
      $t['children'][$item]['matches'] *= 1 + calculate_favor_score($build['favors'][$item])/1.5;
    }

    // resorting items taking factored match numbers into account
    uasort($t['children'], function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    // situational items processing
    $situationals = [];
    $build_sz = count($build['path']) + 1;
    foreach ($t['children'] as $id => $h) {
      if (!isset($tree[$id])) {
        unset($t['children'][$id]);
        continue;
      }

      // assign median order for the item to check later if it actually belongs there
      if (!isset($tree[$id]['med_ord'])) {
        $local_ord = [];
        foreach ($tree[$id]['children'] as $child) {
          $local_ord[] = $child['min_ord'];
        }
        sort($local_ord);
        $tree[$id]['med_ord'] = !empty($local_ord) ? $local_ord[ floor(count($local_ord)/2) ] - 1 : $ord;
      }

      // early items might be very flexible with order swaps so need to skip the first bunch
      // this kind of check allows for items with earlier order to pop up
      // but not with the later order
      if ($ord < 3 && !empty($tree[$id]['children'])) {
        if ($tree[$id]['med_ord'] - $ord > 1 ) {
          $t['children'][$id]['skip'] = true;
          continue;
        }
      }

      // local limiter, use median limiter $m_lim otherwise
      $local_lim = $tree[$id]['matches'] ? round($tree[$id]['matches'] * 0.25) : $m_lim;

      // checking second order children
      foreach ($tree[$id]['children'] as $iid => $ch) {
        if ($iid == $id) {
          unset($tree[$id]['children'][$id]);
          continue;
        }
        // if they have enough matches, the item with all the corresponding data is recorded
        // to potential situationals
        if (isset($t['children'][$iid]) && $t['children'][$iid]['matches'] > $local_lim && $t['children'][$iid]['matches'] > $tree[$id]['children'][$iid]['matches']) {
          $situationals[$id] = [ 'child' => $iid, 'parent' => $root, 'order' => $build_sz, 'matches' => $t['children'][$id]['matches'] ];

          break;
        }
      }
    }

    $max_id = null;

    // second run through children
    foreach ($t['children'] as $id => $ch) {
      if (in_array($id, $build['path'])) continue;
      if ($ch['diff'] <= 0) continue;
      // if ($ord < 7 && $ch['min_ord'] - $ord > 2) continue;

      // maybe there is a better way to do this
      // but it prevents certain lategame items from "slipping" through
      // because of the "same timing" check
      // this check is much more important for early game items, and lategame point is usually 5 or 6
      // so I'm sticking to 5 as the lower limit for lategame point
      if ($root && $ord < 5 && abs($stats[$root]['median'] - $stats[$id]['median']) < 60) continue;

      if (!$max_id) {
        $max_id = $id;
        break;
      }
    }

    $prev = $root;
    $root = $max_id;

    if ($root) {
      // getting primary candidates for the next round
      $children = $tree[$root]['children'];
      uasort($children, function($a, $b) {
        return $b['matches'] <=> $a['matches'];
      });
      $primes = array_slice($children, 0, round(count($children) * .75), true);

      // checking situationals of this round
      // if an item's child is a prime for next round - the item is considered an alt
      // if an item is skippable it's recorded as situational
      foreach ($situationals as $item => $sit) {
        if (isset($primes[ $sit['child'] ])) {
          if (!isset($build['alt'][$root])) $build['alt'][$root] = [];
          $build['alt'][$root][] = $item;
        }
        if (!isset($build['sit_raw'][$item])) $build['sit_raw'][$item] = [];
        $build['sit_raw'][$item][] = $sit;
      }

      $children = array_keys($t['children']);

      // taking swaps into consideration
      $i = 0;
      foreach ($children as $item) {
        if ($item == $root) continue;
        if ($tree[$item]['skip'] ?? false) continue;

        // swaps
        if (isset($tree[$root]['children'][$item])) {
          $data = $t['children'][$item];
          $i++;
          if (($data['matches_orig'] ?? $data['matches']) > $m_lim * 10) $build['swap_raw'][] = [ $root, $item ];

          // passing match number from the previous round, recording the original number to avoid
          // second round swap increase
          if (isset($tree[$prev]['children'][$item]) && isset($tree[$prev]['children'][$item]['matches_orig'])) {
            $data['matches'] = $tree[$prev]['children'][$item]['matches_orig'];
          }

          if (!isset($tree[$root]['children'][$item]['matches_orig'])) {
            $tree[$root]['children'][$item]['matches_orig'] = $tree[$root]['children'][$item]['matches'];
            $tree[$root]['children'][$item]['matches'] = $tree[$root]['children'][$item]['matches_orig'] + round($data['matches']/($i+2));
          } else {
            $prev_add = $tree[$root]['children'][$item]['matches'] - $tree[$root]['children'][$item]['matches_orig'];
            $tree[$root]['children'][$item]['matches'] = $tree[$root]['children'][$item]['matches_orig'] + round($data['matches']/($i+2));
          }
        }
      }

      $build['path'][] = $root;
    }

    $ord++;
  }

  // post processing

  // checking swaps again, if they actually have similar order,
  // if they are both in the build
  foreach ($build['swap_raw'] as $i => [ $i1, $i2 ]) {
    if (!in_array($i2, $build['path'])) {
      continue;
    }
    $ord1 = array_search($i1, $build['path']);
    $ord2 = array_search($i2, $build['path']);
    if (abs($ord1 - $ord2) > 2) continue;

    $build['swap'][$i1] = $i2;
  }

  unset($build['swap_raw']);

  // group situational items by their order
  foreach ($build['sit_raw'] as $item => $cases) {
    if (in_array($item, $build['path'])) continue;

    $order_frequency = [];
    foreach ($cases as $case) {
      if (!isset($order_frequency[ $case['order'] ])) $order_frequency[ $case['order'] ] = 0;
      $order_frequency[ $case['order'] ] += $case['matches'];
    }

    arsort($order_frequency);

    $build['sit'][$item] = array_keys($order_frequency)[0];
  }

  unset($build['sit_raw']);

  asort($build['sit']);

  // group alternative items
  $alts_keys = array_keys($build['alt']);
  $sz = count($alts_keys);

  $alts_items = [];

  // this is a cleanup to remove items from alts
  // that were already used or something
  for ($i = 0; $i < $sz; $i++) {
    $item = $alts_keys[$i];
    $alts = $build['alt'][$item];

    foreach ($alts as $alt) {
      // timing for alts shouldn't be too different
      if (($stats[$item]['median'] - $stats[$alt]['median']) > 240) {
        unset($alts[ array_search($alt, $alts) ]);
        $alts = array_values($alts);
        continue;
      }

      $alts_items[] = $alt;

      // alt can't be a part of the main build
      if ($alt == $item || in_array($alt, $build['path'])) {
        unset($alts[ array_search($alt, $alts) ]);
        $alts = array_values($alts);
        continue;
      }

      // initially this was the place where branching happened
      // and it was the part of the main loop
      // now it just peeks forward slightly and looks for mutual alts
      for ($j = $i+1; $j < $sz; $j++) {
        $item2 = $alts_keys[$j];
        $alts2 = $build['alt'][$item2];

        if (in_array($alt, $alts2)) {
          if ($build['sit'][$alt]-1 == $j) {
            // if an item is already a "situational" or has a different order, it's removed from alts
            // situationals have a higher order priority
            unset($alts[ array_search($alt, $alts) ]);
            $alts = array_values($alts);
          } else {
            unset($alts2[ array_search($alt, $alts2) ]);
            $build['alt'][$item2] = array_values($alts2);
          }
        }
      }
    }
    if (empty($alts)) unset($build['alt'][$item]);
    else $build['alt'][$item] = $alts;
  }

  // recording all used items for the future stats injection
  $alts_items = array_unique($alts_items);
  foreach ($alts_items as $alt) {
    unset($build['sit'][$alt]);
  }
  $build['alts_items'] = $alts_items;

  // Detecting lategame point
  $first = $build['path'] ? $tree[ $build['path'][0] ]['matches'] : 0;
  $threshold = $first * 0.1;
  $lategame = null;

  // collecting number of matches per pair
  // to find our the exact lategame point

  $matches = [];
  for ($i = 1, $sz = count($build['path']); $i < $sz; $i++) {
    $val = $tree[ $build['path'][$i-1] ]['children'][ $build['path'][$i] ]['matches'];
    $matches[] = $val;
  }

  $threshold = $matches ? $matches[0] * 0.05 : 0;

  $deltas = [];
  for ($i = 1, $sz = count($matches); $i < $sz; $i++) {
    $val = $matches[$i-1] - $matches[$i];
    $deltas[] = $val;

    if (!$lategame && $i > 1 && $val > $threshold) {
      // 3 is added to correct for "lost" pairs while calculating deltas
      // 1 order round is lost due to using pairs (the first round doesn't have a starting pair)
      // 1 order round is lost due to using deltas between pairs
      // and 1 order round is added on top of that since usually "lategame" point is a smooth transition
      $lategame = $i+3;
    }
  }

  if ($lategame > 2) {
    $build['lategamePoint'] = min($lategame, count($build['path']));
  } else {
    $build['lategamePoint'] = count($build['path']);
  }

  // creating a list of possible lategame items

  $build['lategame'] = [];

  if ($lategame) {
    $sz = count($build['path']);
    for ($i = $lategame-2; $i < $sz; $i++) {
      if ($i > $lategame-2) $build['lategame'][] = $build['path'][$i];
      $local_lim = $tree[ $build['path'][$i] ]['matches'] ? round($tree[ $build['path'][$i] ]['matches'] * 0.05) : $m_lim;
      foreach ($tree[ $build['path'][$i] ]['children'] as $item => $ch) {
        if ($ch['matches'] < $local_lim) continue;
        if (isset($build['lategame'][$item]) || in_array($item, $build['path']) || (isset($build['sit'][$item]) && $build['sit'][$item] < $i-1)) continue;
        $build['lategame'][] = $item;
      }
    }
  }

  // creating build times based on pairs diff times

  $build_times = [];

  $build_times[] = $tree['0']['children'][ $build['path'][0] ]['diff'];
  $alts_keys = array_keys($build['path']);
  $sz = count($alts_keys);
  for ($i = 1; $i < $sz; $i++) {
    $build_times[] = $tree[ $build['path'][$i-1] ]['children'][ $build['path'][$i] ]['diff'];
  }

  $build['times'] = $build_times;

  return $build;
}

function traverse_build_tree_partial(&$stats, &$tree, $m_lim, $m_role) {
  // this method is largely based on traverse_build_tree
  // but is intended to be used only if there is not enough tree data to generate a build
  // so it uses stats instead

  $build = [
    'path' => [],
    'favors' => [],
    'sit_raw' => [],
    'sit' => [],
    'alt' => [],
    'swap' => [],
    'swap_raw' => [],
    'lategamePoint' => null,
    'lategame' => [],
    'alts_items' => [],
    'partial' => true,
  ];

  global $meta;

  // getting minimal number of matches to filter by
  uasort($stats, function($a, $b) {
    return $b['purchases'] <=> $a['purchases'];
  });
  $role_limit = ($m_role / reset($stats)['purchases']) * 0.1;
  
  // then sorting by median purchase time
  uasort($stats, function($a, $b) {
    return $a['median'] <=> $b['median'];
  });

  // only "big" items are allowed, everything else is injected with stats later
  $allowed_items = array_merge($meta['item_categories']['medium'], $meta['item_categories']['major']);

  $orders = [];

  // creating a list of potential primary items and major orders
  $last_time = null;
  $order = 0;
  foreach ($stats as $item => $st) {
    if (!in_array($item, $allowed_items)) continue;

    // not enough matches -> get out
    if ($stats[$item]['prate'] < $role_limit) {
      continue;
    }
    
    // if there is a partial tree - use it as a basis until it's done
    if (isset($tree[$item])) {
      if (!$tree[$item]['matches']) {
        foreach ($tree[$item]['parents'] as $parent) {
          $tree[$item]['matches'] += $tree[$parent]['children'][$item]['matches'];
        }
      }
      $stats[$item]['prate'] = $tree[$item]['matches']/$m_role;
    }

    if (!$last_time) {
      $order = 0;
      $last_time = $st['median'];
      $orders[$order] = [];
    }
    // if there is more than 2 minutes from previous item => increase order
    if ($st['median'] - $last_time > 120) {
      $order++;
      $orders[$order] = [];
      $last_time = $st['median'];
    }

    $orders[$order][] = $item;
  }

  $orders_prate = [];

  // orders are done, create a list of max purchase rate and potential primaries for every order
  foreach ($orders as &$order) {
    usort($order, function($a, $b) use (&$stats) {
      return $stats[$b]['prate'] <=> $stats[$a]['prate'];
    });

    $orders_prate[] = [ $order[0], $stats[ $order[0] ]['prate'], $stats[ $order[0] ]['median'] ];
  }

  $treecount = count($tree)-1;

  $build_order = 0;
  $last_order_prate = 0;
  $next = 0;
  for($i = 0, $sz = count($orders_prate); $i < $sz; $i++) {
    if ($next) {
      // Case 1: we have a preset next item (from tree)
      if (in_array($next, $orders[$i])) {
        // the item is in the order => it's automatically made primary
        $last_order_prate = $i;
    
        $primary = $next;
        $build['path'][] = $primary;
        unset($orders[$i][ array_search($primary, $orders[$i]) ]);

        // if there are children => make them next
        // else set next flag to 0
        if (!empty($tree[$primary]['children'])) {
          uasort($tree[$primary]['children'], function($a, $b) {
            return $b['matches'] <=> $a['matches'];
          });
          $next = array_keys($tree[$primary]['children'])[0];
        } else {
          $next = 0;
        }
      } else {
        // this order is situational, skip
        foreach ($orders[$i] as $ord) {
          $build['sit'][$ord] = $build_order;
          if ($build['lategamePoint']) {
            $build['lategame'][] = $ord;
          }
        }
        continue;
      }
    } else {
      $primary = 0;

      // if tree is not empty - use it as source for primary
      if ($treecount) {
        if (isset($tree[ $orders_prate[$i][0] ])) {
          $primary = $orders_prate[$i][0];
        } else {
          foreach ($orders[$i] as $ord) {
            if (isset($tree[$ord])) {
              $primary = $ord;
              break;
            }
          }
        }
      }
      
      if ($primary) {
        $last_order_prate = $i;
    
        $build['path'][] = $primary;
        unset($orders[$i][ array_search($primary, $orders[$i]) ]);

        // if there are potential children in the tree - use them as next
        if (!empty($tree[$primary]['children'])) {
          uasort($tree[$primary]['children'], function($a, $b) {
            return $b['matches'] <=> $a['matches'];
          });
          $next = array_keys($tree[$primary]['children'])[0];
        }
      } else {
        // no primary, using the highest prate item
        if ($i > 0 && $orders_prate[$last_order_prate][1] - $orders_prate[$i][1] > 0.15) {
          foreach ($orders[$i] as $ord) {
            $build['sit'][$ord] = $build_order;
            if ($build['lategamePoint']) {
              $build['lategame'][] = $ord;
            }
          }
          continue;
        } else {
          $last_order_prate = $i;
    
          $build['path'][] = $orders_prate[$i][0];
          $primary = $orders_prate[$i][0];
          unset($orders[$i][0]);
        }
      }
    }

    // all other items in the same order are alts
    if (!empty($orders[$i])) {
      $build['alt'][ $primary ] = [];
      foreach($orders[$i] as $ord) {
        $build['alt'][ $primary ][] = $ord;
        $build['alts_items'][] = $ord;
      }
    }

    // 30 min = 1800 sec
    // items past 30 min point or with low purchase rate are considered a lategame point
    if (!$build['lategamePoint'] && $build_order > 3 && ($orders_prate[$i][1] <= 0.25 || +$orders_prate[$i][2] > 1800)) {
      $build['lategamePoint'] = $build_order;
    }

    if ($build['lategamePoint']) {
      $build['lategame'][] = $primary;
      foreach($orders[$i] as $ord) {
        $build['lategame'][] = $ord;
      }
    }

    $build_order++;
  }

  if (!$build['lategamePoint']) $build['lategamePoint'] = count($build['path']);
  if ($build['lategamePoint'] < 5) $build['lategamePoint']++;

  $build['alts_items'] = array_unique($build['alts_items']);

  foreach ($build['path'] as $i => $item) {
    if ($i && $stats[ $item ]['q1'] < $stats[ $build['path'][$i-1] ]['q3']) {
      $build['swap'][ $build['path'][$i-1] ] = $item;
    }

    $build['times'][] = ($i ? $stats[ $item ]['q1']-$stats[ $build['path'][$i-1] ]['q1'] : $stats[ $item ]['q1'])/60;
  }

  $lasttime = $stats[ $item ]['q1'];
  $lastord = $i;

  if (empty($build['lategame'])) {
    foreach ($build['sit'] as $item => $ord) {
      if ($ord > $lastord) $build['lategame'][] = $item;
    }
  }

  return $build;
}

function inject_item_stats(&$build, &$stats, $hero) {
  global $meta;

  $lategame = $build['lategamePoint'];
  $lategame_time = round(array_sum(array_slice($build['times'], 0, $lategame)) * 60);

  // early game items

  $early_items = [];
  foreach($meta['item_categories']['early'] as $item) {
    if (in_array($item, $build['path']) || isset($build['sit'][$item])) continue;
    if (!isset($stats[$item])) continue;
    if ($stats[$item]['prate'] < 0.09 || $stats[$item]['median'] > 1200) continue;
    
    $order = 0; 
    for ($i = 0, $sum = $build['times'][0], $sz = count($build['times']); $i < $sz; $i++, $sum += $build['times'][$i] ?? 0) {
      if ($stats[$item]['median'] / 60 < $sum) {
        $order = $i;
        break;
      }
    }

    if (!isset($early_items[$order])) {
      $early_items[$order] = [];
    }

    $early_items[$order][ $item ] = [
      'time' => $stats[$item]['median'],
      'winrate' => $stats[$item]['wo_wr'],
      'prate' => $stats[$item]['prate']
    ];

    $items[] = $item;
  }
  ksort($early_items);

  $build['early'] = $early_items;
  foreach ($build['early'] as &$early_order) {
    uasort($early_order, function($a, $b) {
      return $a['time'] <=> $b['time'];
    });
  }

  // neutrals

  $build['neutrals'] = [];

  $neutrals_list = [];

  for ($i = 1; $i < 6; $i++) {
    $neutrals_list = array_merge($neutrals_list, $meta['item_categories']['neutral_tier_'.$i]);

    $tier = [];
    foreach ($meta['item_categories']['neutral_tier_'.$i] as $item) {
      if (empty($stats[$item])) continue;
      $tier[$item] = $stats[$item]['prate'];
    }

    if (empty($tier)) continue;

    $local_lim = max($tier) * 0.4;

    $tier = array_keys(
      array_filter($tier, function($a) use ($local_lim) {
        return $a > $local_lim;
      })
    );

    if (empty($tier)) continue;

    $build['neutrals'][$i] = $tier;

    usort($build['neutrals'][$i], function($a, $b) use (&$stats) {
      return $stats[ $b ]['winrate'] <=> $stats[ $a ]['winrate'];
    });
  }

  // other significant items

  $items = array_merge($items ?? [], $build['path'], array_keys($build['sit']), $build['lategame'], $build['alts_items'], $neutrals_list, $meta['item_categories']['early']);
  $items = array_unique($items);

  $significant = [];

  foreach ($stats as $item => $is) {
    // why .6% specifically? Because.
    if (empty($is) || in_array($item, $items) || $is['prate'] < 0.006) continue;

    if ($is['prate'] > 0.035 && $is['median'] > $lategame_time) {
      $build['lategame'][] = $item;
    } else {
      $significant[] = $item;
    }

    $items[] = $item;
  }

  $build['lategame'] = array_values(array_unique($build['lategame']));

  $build['significant'] = $significant;

  // items stats
  // only includes prate, median timing and wo_wr as the most useful stats for the build
  // critical times section will contain Q1-Q3 winrates and timings for cases when it's needed

  // $items = array_merge($items, $significant);

  $build['stats'] = [];

  $build['critical'] = [];

  foreach ($items as $item) {
    if (empty($stats[$item])) continue;

    $build['stats'][$item] = [
      'prate' => $stats[$item]['prate'],
      'med_time' => $stats[$item]['median'],
      'winrate' => $stats[$item]['winrate'],
      'wo_wr_incr' => $stats[$item]['winrate'] - $stats[$item]['wo_wr'], // would like to show increase there, but we can get it later, I guess
      // not going to pass hero stats here as well
      // or am I?
    ];

    if ($stats[$item]['median'] > 60 && ( $stats[$item]['grad'] < -0.01 || $stats[$item]['grad'] > 0.01 ) && !in_array($item, $neutrals_list)) {
      $build['critical'][$item] = [
        'q1' => $stats[$item]['q1'],
        'q3' => $stats[$item]['q3'],
        'grad' => $stats[$item]['grad'],
        'critical_time' => $stats[$item]['q1'] - 60*(($stats[$item]['early_wr'] - $hero['winrate_picked'])/$stats[$item]['grad']),
        'early_wr' => $stats[$item]['early_wr'],
        'early_wr_incr' => $stats[$item]['early_wr'] - $stats[$item]['wo_wr'],
      ];
    }
  }

  usort($build['significant'], function($a, $b) use (&$build) {
    return $build['stats'][ $a ]['med_time'] <=> $build['stats'][ $b ]['med_time'];
  });
}

function generate_item_builds(&$pairs, &$stats, $hero) {
  global $report;

  if (!empty($pairs)) {
    $m_lim = $pairs[ ceil(count($pairs)*0.5)-1 ]['total'];
  } else {
    $m_lim = 1;
  }

  $dummy = [
    'parents' => [],
    'children' => [],
    'matches' => 0
  ];

  $tree = [
    '0' => $dummy
  ];

  foreach ($pairs as $pair) {
    if (!isset($tree[ $pair['item1'] ])) {
      $tree[ $pair['item1'] ] = $dummy;
      $tree[ $pair['item1'] ]['time'] = $stats[ $pair['item1'] ]['median'];
    }
    if (!isset($tree[ $pair['item2'] ])) {
      $tree[ $pair['item2'] ] = $dummy;
      $tree[ $pair['item2'] ]['time'] = $stats[ $pair['item2'] ]['median'];
    }

    // populating root point
    if (($pair['avgord1'] ?? 0) <= 1) {
      if (!isset($tree[ '0' ]['children'][ $pair['item1'] ])) {
        $tree[ '0' ]['children'][ $pair['item1'] ] = [
          'diff' => $stats[ $pair['item1'] ]['median'] / 60,
          'matches' => 0,
          'winrate' => $stats[ $pair['item1'] ]['winrate'],
          'min_ord' => 0
        ];
      }
      $tree[ '0' ]['children'][ $pair['item1'] ]['matches'] += $pair['total'] * (1-($pair['avgord1'] ?? 0));
    }

    if (($pair['avgord2'] ?? 0) < 1) {
      if (!isset($tree[ '0' ]['children'][ $pair['item2'] ]) && isset($stats[ $pair['item2'] ])) {
        $tree[ '0' ]['children'][ $pair['item2'] ] = [
          'diff' => $stats[ $pair['item2'] ]['median'] / 60,
          'matches' => 0,
          'winrate' => $stats[ $pair['item2'] ]['winrate'],
          'min_ord' => 0
        ];
      }
      $tree[ '0' ]['children'][ $pair['item2'] ]['matches'] += $pair['total'] * (1-($pair['avgord2'] ?? 0));
    }

    if ($pair['min_diff'] > 0) {
      if (isset($tree[ $pair['item1'] ]['children'][ $pair['item2'] ])) continue;

      $tree[ $pair['item1'] ]['children'][ $pair['item2'] ] = [
        'diff' => $pair['min_diff'],
        'matches' => $pair['total'],
        'winrate' => $pair['winrate'],
        'min_ord' => floor($pair['avgord2'] ?? 0)
      ];
      $tree[ $pair['item2'] ]['parents'][] = $pair['item1'];
      
      $tree[ $pair['item1'] ]['matches'] += $pair['total'];
    } else {
      if (isset($tree[ $pair['item2'] ]['children'][ $pair['item1'] ])) continue;

      $tree[ $pair['item2'] ]['children'][ $pair['item1'] ] = [
        'diff' => $pair['min_diff'],
        'matches' => $pair['total'],
        'winrate' => $pair['winrate'],
        'min_ord' => floor($pair['avgord1'] ?? 0)
      ];
      $tree[ $pair['item1'] ]['parents'][] = $pair['item2'];

      $tree[ $pair['item2'] ]['matches'] += $pair['total'];
    }
  }

  foreach ($tree as $id => $t) {
    foreach ($tree[$id]['children'] as $iid => $ch) {
      if ($iid == $id) {
        unset($tree[$id]['children'][$id]);
        continue;
      }
    }
  }

  // Generating builds, going through the tree
  $build = [];

  if (count($tree['0']['children']) < 2 || count($tree) < 4) {
    $build = traverse_build_tree_partial($stats, $tree, $m_lim, $hero['role_matches']);
  } else {
    $build = traverse_build_tree($stats, $tree, $m_lim, $hero['role_matches']);
  }

  inject_item_stats($build, $stats, $hero);

  unset($build['favors']);
  unset($build['alts_items']);

  return [ $build, $tree ];
}
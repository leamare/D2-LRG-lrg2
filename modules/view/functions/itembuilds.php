<?php

function calculate_favor_score($array) {
  $res = 0;
  $sz = count($array);

  foreach ($array as $i => $n) {
    $res += $n/($sz-$i);
  }

  return $res;
}

function traverse_build_tree(&$stats, &$tree, &$hero, $m_lim, $m_role, $root = '0', $build = null) {
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
    ];
    // neutrals, early are added later
  }

  global $meta;

  $alt_roots = [];
  $ord = 0;

  $prev = 0;
  while (true) {
    if ($root === null) break;
    $t = $tree[$root];
    if (empty($t['children'])) break;

    uasort($t['children'], function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    // favor points assignment

    $items_list = array_keys($t['children']);
    $i_sz = count($items_list);
    foreach ($items_list as $i => $item) {
      if (!isset($build['favors'][$item])) $build['favors'][$item] = [];
      // using 1.5 as Favor Factor, it's best somewhere between 1 and 2
      $build['favors'][$item][] = pow($i/$i_sz, 1.5);
      $t['children'][$item]['matches'] *= 1 + calculate_favor_score($build['favors'][$item])/1.5;
    }

    // resorting items taking factored match numbers into account
    uasort($t['children'], function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $situationals = [];
    $build_sz = count($build['path']) + 1;
    foreach ($t['children'] as $id => $h) {
      if (!isset($tree[$id])) {
        unset($t['children'][$id]);
        continue;
      }

      if (!isset($tree[$id]['med_ord'])) {
        $local_ord = [];
        foreach ($tree[$id]['children'] as $child) {
          $local_ord[] = $child['min_ord'];
        }
        sort($local_ord);
        $tree[$id]['med_ord'] = !empty($local_ord) ? $local_ord[ floor(count($local_ord)/2) ] - 1 : $ord;
      }

      if ($ord < 3 && !empty($tree[$id]['children'])) {
        if ($tree[$id]['med_ord'] - $ord > 1 ) {
          $t['children'][$id]['skip'] = true;
          continue;
        }
      }

      $local_lim = $tree[$id]['matches'] ? round($tree[$id]['matches'] * 0.25) : $m_lim;

      foreach ($tree[$id]['children'] as $iid => $ch) {
        if ($iid == $id) {
          unset($tree[$id]['children'][$id]);
          continue;
        }
        if (isset($t['children'][$iid]) && $t['children'][$iid]['matches'] > $local_lim && $t['children'][$iid]['matches'] > $tree[$id]['children'][$iid]['matches']) {
          $situationals[$id] = [ 'child' => $iid, 'parent' => $root, 'order' => $build_sz, 'matches' => $t['children'][$id]['matches'] ];

          break;
        }
      }
    }

    $max_id = null;

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
      // primary candidates
      $children = $tree[$root]['children'];
      uasort($children, function($a, $b) {
        return $b['matches'] <=> $a['matches'];
      });
      $primes = array_slice($children, 0, round(count($children) * .75), true);

      foreach ($situationals as $item => $sit) {
        if (isset($primes[ $sit['child'] ])) {
          if (!isset($build['alt'][$root])) $build['alt'][$root] = [];
          $build['alt'][$root][] = $item;
        }
        if (!isset($build['sit_raw'][$item])) $build['sit_raw'][$item] = [];
        $build['sit_raw'][$item][] = $sit;
      }

      $children = array_keys($t['children']);

      // take swaps into consideration
      $i = 0;
      foreach ($children as $item) {
        if ($item == $root) continue;
        if ($tree[$item]['skip'] ?? false) continue;
        // swaps
        if (isset($tree[$root]['children'][$item])) {
          $data = $t['children'][$item];
          $i++;
          if (($data['matches_orig'] ?? $data['matches']) > $m_lim * 10) $build['swap_raw'][] = [ $root, $item ];

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
          // if (!isset($build['favors'][$item])) $build['favors'][$item] = [];
          // $build['favors'][$item][] = $i;
        } else {
          // $build['favors'][$item][] = 1;
        }
      }

      $build['path'][] = $root;
    }

    $ord++;
  }

  // post processing: TODO:
  // - items stats -> neutrals
  // - items stats -> early items
  // - situationals
  // - lategame list, lategame branches
  // - main item

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

  foreach ($build['sit_raw'] as $item => $cases) {
    if (in_array($item, $build['path'])) continue;

    // $build['sit'][$item] = array_reduce($cases, function($carry, $a) {
    //   return $carry += $a['order'];
    // }, 0) / count($cases);

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

  $alts_keys = array_keys($build['alt']);
  $sz = count($alts_keys);

  $alts_items = [];

  for ($i = 0; $i < $sz; $i++) {
    $item = $alts_keys[$i];
    $alts = $build['alt'][$item];

    foreach ($alts as $alt) {
      if (($stats[$item]['median'] - $stats[$alt]['median']) > 240) {
        unset($alts[ array_search($alt, $alts) ]);
        $alts = array_values($alts);
        continue;
      }

      $alts_items[] = $alt;

      if ($alt == $item || in_array($alt, $build['path'])) {
        unset($alts[ array_search($alt, $alts) ]);
        $alts = array_values($alts);
        continue;
      }

      for ($j = $i+1; $j < $sz; $j++) {
        $item2 = $alts_keys[$j];
        $alts2 = $build['alt'][$item2];

        if (in_array($alt, $alts2)) {
          if ($build['sit'][$alt]-1 == $j) {
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


  $build['lategamePoint'] = min($lategame, count($build['path']));

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

  // TODO: empty builds

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

function inject_item_stats(&$build, &$stats, $hero) {
}

function generate_item_builds(&$pairs, &$stats, $hero) {
  global $report;

  $m_lim = $pairs[ ceil(count($pairs)*0.5)-1 ]['total'];

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

    if ($pair['avgord1'] <= 1) {
      if (!isset($tree[ '0' ]['children'][ $pair['item1'] ])) {
        $tree[ '0' ]['children'][ $pair['item1'] ] = [
          'diff' => $stats[ $pair['item1'] ]['median'] / 60,
          'matches' => 0,
          'winrate' => $stats[ $pair['item1'] ]['winrate'],
          'min_ord' => 0
        ];
      }
      $tree[ '0' ]['children'][ $pair['item1'] ]['matches'] += $pair['total'] * (1-$pair['avgord1']);
    }

    if ($pair['avgord2'] < 1) {
      if (!isset($tree[ '0' ]['children'][ $pair['item2'] ])) {
        $tree[ '0' ]['children'][ $pair['item2'] ] = [
          'diff' => $stats[ $pair['item2'] ]['median'] / 60,
          'matches' => 0,
          'winrate' => $stats[ $pair['item2'] ]['winrate'],
          'min_ord' => 0
        ];
      }
      $tree[ '0' ]['children'][ $pair['item2'] ]['matches'] += $pair['total'] * (1-$pair['avgord2']);
    }

    if ($pair['min_diff'] > 0) {
      if (isset($tree[ $pair['item1'] ]['children'][ $pair['item2'] ])) continue;

      $tree[ $pair['item1'] ]['children'][ $pair['item2'] ] = [
        'diff' => $pair['min_diff'],
        'matches' => $pair['total'],
        'winrate' => $pair['winrate'],
        'min_ord' => floor($pair['avgord2'])
      ];
      $tree[ $pair['item2'] ]['parents'][] = $pair['item1'];
      
      $tree[ $pair['item1'] ]['matches'] += $pair['total'];
    } else {
      if (isset($tree[ $pair['item2'] ]['children'][ $pair['item1'] ])) continue;

      $tree[ $pair['item2'] ]['children'][ $pair['item1'] ] = [
        'diff' => $pair['min_diff'],
        'matches' => $pair['total'],
        'winrate' => $pair['winrate'],
        'min_ord' => floor($pair['avgord1'])
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

  $build = traverse_build_tree($stats, $tree, $m_lim, $hero['role_matches']);

  inject_item_stats($build, $stats, $hero);

  return [ $build, $tree ];
}
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
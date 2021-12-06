<?php


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


    $situationals = [];
    $build_sz = count($build['path']) + 1;
    foreach ($t['children'] as $id => $h) {

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


  return $build;
}


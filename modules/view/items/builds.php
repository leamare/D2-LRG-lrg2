<?php

$modules['items']['builds'] = [];

function rg_view_generate_items_builds() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;

  if($mod == $parent."builds") $unset_module = true;
  $parent_module = $parent."builds-";
  $res = [];

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }
  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [];

  $hnames = $meta["heroes"];
  $meta['item_categories'];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      $hero = $hid;
      $tag = "heroid$hid";
    }
  }

  $pairs = [];
  if (!isset($report['items']['progr'][$hero])) $report['items']['progr'][$hero] = [];
  foreach ($report['items']['progr'][$hero] as $v) {
    if (empty($v)) continue;
    if ($v['item1'] == $v['item2']) continue;
    $pairs[] = $v;
  }

  if (empty($pairs)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  usort($pairs, function($a, $b) {
    return $b['total'] <=> $a['total'];
  });

  $m_lim = $pairs[ ceil(count($pairs)*0.5)-1 ]['total'];

  $dummy = [
    'parents' => [],
    'children' => []
  ];

  $tree = [
    '0' => $dummy
  ];

  foreach ($pairs as $pair) {
    if (!isset($tree[ $pair['item1'] ])) {
      $tree[ $pair['item1'] ] = $dummy;
      $tree[ $pair['item1'] ]['time'] = $report['items']['stats'][$hero][ $pair['item1'] ]['median'];
    }
    if (!isset($tree[ $pair['item2'] ])) {
      $tree[ $pair['item2'] ] = $dummy;
      $tree[ $pair['item2'] ]['time'] = $report['items']['stats'][$hero][ $pair['item2'] ]['median'];
    }

    if ($pair['min_diff'] > 0) {
      if (isset($tree[ $pair['item1'] ]['children'][ $pair['item2'] ])) continue;

      $tree[ $pair['item1'] ]['children'][ $pair['item2'] ] = [
        'diff' => $pair['min_diff'],
        'matches' => $pair['total'],
        'winrate' => $pair['winrate']
      ];
      $tree[ $pair['item2'] ]['parents'][] = $pair['item1'];
    } else {
      if (isset($tree[ $pair['item2'] ]['children'][ $pair['item1'] ])) continue;

      $tree[ $pair['item2'] ]['children'][ $pair['item1'] ] = [
        'diff' => $pair['min_diff'],
        'matches' => $pair['total'],
        'winrate' => $pair['winrate']
      ];
      $tree[ $pair['item1'] ]['parents'][] = $pair['item2'];
    }
  }

  $early = [];

  foreach ($tree as $id => $t) {
    if ($id == '0') continue;
    $ks = array_keys($t['children']);

    for($i=0; isset($ks[$i]); $i++) {
      error_log(count($ks));
      $cid = $ks[$i];
      $ch = $tree[$id]['children'][$cid];
      if ($cid != 29 && in_array($cid, $meta['item_categories']['early']) && (in_array($cid, $meta['item_categories']['parts']))) {
        if (isset($tree[$cid]['children'][$id])) continue;
        $early[] = $cid;
        foreach ($tree[$cid]['children'] as $ccid => $ch2) {
          if (isset($tree[$id]['children'][$ccid])) {
            $tree[$id]['children'][$ccid]['matches'] = ($tree[$id]['children'][$ccid]['matches']+$ch2['matches'])/2;
          } else {
            $tree[$id]['children'][$ccid] = $ch2;
            if (!in_array($ccid, $ks)) $ks[] = $ccid;
          }
        }
        unset($tree[$id]['children'][$cid]);
      }
    }
  }

  $early = array_unique($early);
  $early_container = [];
  foreach($early as $item) {
    $early_container[$item] = $tree[$item];
    unset($tree[$item]);
  }

  foreach ($tree as $id => $t) {
    foreach ($tree[$id]['children'] as $iid => $ch) {
      if ($iid == $id) {
        unset($tree[$id]['children'][$id]);
        continue;
      }
    }
  }

  $min_minute = 1000;
  foreach ($tree as $id => $item) {
    if ($id == '0') continue;
    if ($min_minute > ceil($item['time']/60)) $min_minute = ceil($item['time']/60);
  }
  $min_time = ($min_minute+2)*60;

  foreach ($tree as $id => $item) {
    if ($id != '0' && (empty($item['parents']) || $item['time'] < $min_time)) {
      $tree[ '0' ]['children'][ $id ] = [
        'diff' => $report['items']['stats'][$hero][$id]['median'] / 60,
        'matches' => $report['items']['stats'][$hero][$id]['purchases'],
        'winrate' => $report['items']['stats'][$hero][$id]['winrate']
      ];
    }
  }

  
  $build = [];
  $sit = [];
  $_tid = '0';
  while (true) {
    if ($_tid === null) break;
    $t = $tree[$_tid];
    if (empty($t['children'])) break;
    // error_log(count($t['children']));

    // if (!empty($t['children'])) {
    //   $children = $t['children'];
    //   usort($children, function($b, $a) { return $a['matches'] <=> $b['matches']; });
    //   $m_lim = $children[ floor((count($children)-1)*0.5) ]['matches'];
    // } else {
    //   $m_lim = 0;
    // }

    $situationals = [];
    foreach ($t['children'] as $id => $h) {
      if (!isset($tree[$id])) {
        unset($t['children'][$id]);
        continue;
      }
      foreach ($tree[$id]['children'] as $iid => $ch) {
        if ($iid == $id) {
          unset($tree[$id]['children'][$id]);
          continue;
        }
        if (isset($t['children'][$iid]) && $t['children'][$iid]['matches'] > $m_lim && $t['children'][$iid]['matches'] > $tree[$id]['children'][$iid]['matches']) {
          // if ($_tid == 63 && $id == 127) echo $tree[$id]['children'][$iid]['matches'];
          // if ($_tid == 63 && $id == 127) echo item_name($_tid)." - ".item_name($id)."<br />";
          $t['children'][$iid]['matches'] += $tree[$id]['children'][$iid]['matches'];
          $sit[] = [ 'item' => $id, 'child' => $iid, 'parent' => $_tid ];
          break;
        }
      }
    }

    // foreach ($t['children'] as $id => $h) {
    //   if (isset($tree[$id]['children'][$_tid]) && $tree[$_tid]['time'] < $tree[$id]['time']) {
    //     $t['children'][$id]['matches'] = $tree[$id]['children'][$_tid];
    //   }
    // }

    uasort($t['children'], function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $max_id = null;

    if ($_tid == 11) var_dump($t['children']);

    foreach ($t['children'] as $id => $ch) {
      if (in_array($id, $build)) continue;
      if (($t['time'] ?? false) && $t['time']-60 > $tree[$id]['time']) continue;
      if (!$max_id) {
        $max_id = $id;
        break;
      }
    }

    $_tid = $max_id;
    if ($_tid) $build[] = $_tid;
  }

  // var_dump($tree);

  $res[$tag] .= "<div class=\"content-text\">";

  $res[$tag] .= $m_lim."<br />";

  foreach ($build as $item) $res[$tag] .= item_icon($item);

  $res[$tag] .= "<br /><br />";

  foreach ($early as $item) $res[$tag] .= item_icon($item);

  $res[$tag] .= "<br /><br />";

  $last = null;
  foreach ($build as $item) {
    $res[$tag] .= item_icon($item)." (".($last ? $tree[$last]['children'][$item]['matches'] : 0).") ";
    $last = $item;
  }

  $res[$tag] .= "<br /><br />";

  // foreach ($tree[127]['children'] as $id => $p)  {
  //   $res[$tag] .= item_icon($id)." m ".$p['matches']."<br />";
  // }

  $res[$tag] .= "<br /><br />";

  foreach ($sit as $p)  {
    // if ($p['parent'] == '0') continue;
    $id = $p['item'];
    if ($tree[ $p['parent'] ]['children'][$id]['matches'] < $m_lim) continue;
    // if ()
    $res[$tag] .= item_icon($p['parent'])." -> ".item_icon($id)." - ".$tree[ $p['parent'] ]['children'][$id]['matches']." :: then ".item_icon($p['child'])."<br />";
  }

  $res[$tag] .= "</div>";

  return $res;
}


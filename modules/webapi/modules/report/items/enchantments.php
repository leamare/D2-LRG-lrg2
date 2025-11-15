<?php 

$endpoints['items-enchantments'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['items']['enchantments']))
    throw new \Exception("No enchantments data");
  
  if (is_wrapped($report['items']['enchantments'])) {
    $report['items']['enchantments'] = unwrap_data($report['items']['enchantments']);
  }

  $selected_hid = $vars['heroid'] ?? 'total';
  
  if ($selected_hid !== 'total' && !isset($report['items']['enchantments'][$selected_hid])) {
    return [];
  }

  $data = $report['items']['enchantments'][$selected_hid] ?? [];


  $category_ids = array_keys($data);
  sort($category_ids);
  
  if (in_array(0, $category_ids)) {
    $category_ids = array_diff($category_ids, [0]);
    array_unshift($category_ids, 0);
  }

  $res = [];
  $res['hero'] = $selected_hid;
  $res['categories'] = [];

  global $meta;
  
  $category_names = [];
  foreach (array_keys($meta['item_categories']) as $i => $category_name) {
    if (strpos($category_name, 'enhancement_tier_') === 0) {
      $category_names[$i] = $category_name;
    }
  }
  $category_names[0] = 'enhancement_tier_total';

  foreach ($category_ids as $category_id) {
    if (empty($data[$category_id])) continue;

    $items = $data[$category_id];
    $cat_name = 'enhancement_tier_total';
    if ($category_id != 0 && isset($category_names[$category_id])) {
      $cat_name = $category_names[$category_id];
    }

    $category_data = [
      'id' => $category_id,
      'name' => $cat_name,
      'items' => []
    ];

    foreach ($items as $item_id => $item_data) {
      if (empty($item_data) || $item_data['matches'] + $item_data['matches_wo'] == 0) {
        continue;
      }
      
      $prate = $item_data['matches'] / ($item_data['matches'] + $item_data['matches_wo']);
      $wr_diff = $item_data['wr'] - $item_data['wr_wo'];
      
      $category_data['items'][] = [
        'item_id' => $item_id,
        'matches' => $item_data['matches'],
        'prate' => round($prate, 4),
        'wr' => round($item_data['wr'], 4),
        'wr_diff' => round($wr_diff, 4),
      ];
    }

    $res['categories'][] = $category_data;
  }

  return $res;
};


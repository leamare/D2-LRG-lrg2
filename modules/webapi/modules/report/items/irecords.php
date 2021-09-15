<?php 

$endpoints['items-records'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['records']))
    throw new \Exception("No items records data");

  if (is_wrapped($report['items']['records'])) {
    $report['items']['records'] = unwrap_data($report['items']['records']);
  }

  $res = [];

  if (isset($vars['item'])) {
    $item = $vars['item'];

    $res['item'] = $item;
    $res['records'] = [];
    if (empty($report['items']['records'][$item])) return $res;

    foreach ($report['items']['records'][$item] as $hero => $line) {
      if (empty($line) || empty($line['match'])) continue;
      if (!empty($report['match_participants_teams']))
        $line['match_card_min'] = match_card_min($line['match']);
      else 
        $line['match_card_min'] = null;
      $res['records'][$hero] = $line;
    }
  } else {
    if (is_wrapped($report['items']['ph'])) {
      $report['items']['ph'] = unwrap_data($report['items']['ph']);
    }
    
    if (!empty($vars['item_cat']) && !in_array('all', $vars['item_cat'])) {
      $items_allowed = [];
      $items_categories = [];
      $meta['item_categories'];
      foreach ($vars['item_cat'] as $ic) {
        if (!isset($meta['item_categories'][$ic])) continue;
        $items_categories[] = $ic;
        $items_allowed = array_merge($items_allowed, $meta['item_categories'][$ic]);
      }
      $items_allowed = array_unique($items_allowed);
    } else {
      $items_allowed = null;
      $items_categories = ['all'];
    }

    $overview = [];
    foreach ($report['items']['records'] as $iid => $lines) {
      if (!empty($items_allowed) && !in_array($iid, $items_allowed)) continue;
      foreach ($lines as $hero => $line) {
        if (empty($line) || empty($line['match'])) continue;
        $line['hero'] = $hero;
        $line['item'] = $iid;
        if (!empty($report['match_participants_teams']))
          $line['match_card_min'] = match_card_min($line['match']);
        else 
          $line['match_card_min'] = null;
        $overview[] = $line;
      }
    }
    uasort($overview, function($a, $b) {
      return $b['diff'] <=> $a['diff'];
    });
    $overview = array_slice($overview, 0, round(count($overview)*0.2));

    $res['item'] = null;
    $res['categories'] = $items_categories;
    $res['allowed_items'] = $items_allowed;
    $res['records'] = $overview;
  }

  return $res;
};
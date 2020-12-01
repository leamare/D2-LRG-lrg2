<?php

$modules['items']['icombos'] = [];

function rg_view_generate_items_icombos() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;

  if($mod == $parent."icombos") $unset_module = true;
  $parent_module = $parent."icombos-";
  $res = [];

  if (is_wrapped($report['items']['combos'])) {
    $report['items']['combos'] = unwrap_data($report['items']['combos']);
  }

  $res = [];

  $item_ids = array_keys($report['items']['combos']);
  $item_names = [];

  foreach ($item_ids as $item) {
    $item_names[ $item ] = [
      'name' => $meta['items_full'][$item]['localized_name'],
      'tag' => $meta['items_full'][$item]['name']
    ];
    $strings['en']["itemid".$item] = item_name($item);
  }

  uasort($item_names, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  foreach($item_names as $iid => $name) {
    $res["itemid".$iid] = "";

    if(check_module($parent_module."itemid".$iid)) {
      $item = $iid;
    }
  }

  // HEROES TABLE

  $res['itemid'.$item] .= "<table id=\"items-itemid$item\" class=\"list sortable\">";
  $res['itemid'.$item] .= "<thead><tr class=\"overhead\">".
      "<th width=\"12%\"></th>".
      "<th width=\"18%\">".locale_string("item")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("wr_diff")."</th>".
      "<th>".locale_string("time_diff")."</th>".
      "<th>".locale_string("expectation")."</th>".
      "<th>".locale_string("deviation")."</th>".
      "<th>".locale_string("deviation_pct")."</th>".
    "</tr></thead><tbody>";

  $items = [];
  foreach ($report['items']['combos'][$item] as $iid => $v) {
    if ($iid == '_h') continue;
    if (empty($v)) continue;
    if (!$v['matches'] && isset($report['items']['combos'][$iid][$item]) && $report['items']['combos'][$iid][$item]['matches']) {
      $v = $report['items']['combos'][$iid][$item];
      $v['time_diff'] = -$v['time_diff'];
    }
    $items[$iid] = $v;
  }

  uasort($items, function($a, $b) {
    return $b['matches'] <=> $a['matches'];
  });

  foreach ($items as $iid => $line) {
    $res['itemid'.$item] .= "<tr>".
      "<td>".item_icon($iid)."</td>".
      "<td>".item_name($iid)."</td>".
      "<td>".$line['matches']."</td>".
      "<td>".number_format(100*$line['wins']/$line['matches'], 2)."%</td>".
      "<td>".number_format(100*$line['wr_diff'], 2)."%</td>".
      "<td>".convert_time_seconds($line['time_diff'])."</td>".
      "<td>".$line['exp']."</td>".
      "<td>".($line['matches'] - $line['exp'])."</td>".
      "<td>".number_format(100*($line['matches'] - $line['exp'])/$line['matches'], 2)."%</td>".
    "</tr>";
  }

  $res['itemid'.$item] .= "</tbody></table>";

  return $res;
}


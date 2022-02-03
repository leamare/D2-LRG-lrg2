<?php

$modules['items']['irecords'] = '';

function rg_view_generate_items_irecords() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars;

  if($mod == $parent."irecords") $unset_module = true;
  $parent_module = $parent."irecords-";
  $res = [];

  if (is_wrapped($report['items']['records'])) {
    $report['items']['records'] = unwrap_data($report['items']['records']);
  }

  $item_ids = array_keys($report['items']['records']);
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

  $res['overview'] = '';
  if(check_module($parent_module."overview")) {
    $item = null;
    $tag = 'overview';
  }

  foreach($item_names as $iid => $name) {
    $res["itemid".$iid] = "";

    if(check_module($parent_module."itemid".$iid)) {
      $item = $iid;
      $tag = 'itemid'.$item;
    }
  }

  $meta['item_categories'];
  if (isset($_GET['item_cat']) && isset($meta['item_categories'][ $_GET['item_cat'] ])) {
    $cat = $_GET['item_cat'];
  } else {
    $cat = null;
  }

  $item_cats = [
    'major', 'medium', 'early', 
    // 'neutral_tier_1', 'neutral_tier_2', 'neutral_tier_3', 'neutral_tier_4', 'neutral_tier_5',
  ];

  if ($item) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_records_desc")."</div>";
    
    $res[$tag] .= "<table id=\"items-records-itemid$item\" class=\"list sortable\"><caption>".item_name($item)."</caption><thead>
    <tr>
      <th>".locale_string("match")."</th>
      <th colspan=\"2\">".locale_string("hero")."</th>
      <th>".locale_string("item_timing")."</th>
      <th>".locale_string("item_timing_diff")."</th>
    </tr></thead></tbody>";

    foreach ($report['items']['records'][$item] as $hero => $line) {
      if (empty($line)) continue;
      $res[$tag] .=  "<tr><td>".match_link($line['match'])."</td>".
        "<td>".hero_portrait($hero)."</td>".
        "<td>".hero_link($hero)."</td>".
        "<td data-sorter=\"time\">".convert_time_seconds($line['time'])."</td>".
        "<td data-sorter=\"time\">".convert_time_seconds($line['diff'])."</td>".
      "</tr>";
    }

    $res[$tag] .= "</tbody></table>";
  } else {
    if (is_wrapped($report['items']['ph'])) {
      $report['items']['ph'] = unwrap_data($report['items']['ph']);
    }

    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_records_overview_desc")."</div><br />";

    $res[$tag] .= "<div class=\"selector-modules-level-4\">".
      "<span class=\"selector\">".locale_string("items_category_selector").":</span> ".
      "<select onchange=\"select_modules_link(this);\" class=\"select-selectors select-selectors-level-4\">".
      "<option ".($cat === null ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_category_all")."</option>";
    foreach ($item_cats as $ic) {
      $res[$tag] .= "<option ".($cat == $ic ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."&item_cat=$ic\">".locale_string("items_category_$ic")."</option>";
    }
    $res[$tag] .= "</select></div>";


    $overview = [];
    foreach ($report['items']['records'] as $iid => $lines) {
      if ($cat && !in_array($iid, $meta['item_categories'][$cat])) continue;
      foreach ($lines as $hero => $line) {
        if (empty($line) || empty ($line['match'])) continue;
        $line['hero'] = $hero;
        $line['item'] = $iid;
        $overview[] = $line;
      }
    }
    uasort($overview, function($a, $b) {
      return $b['diff'] <=> $a['diff'];
    });
    $overview = array_slice($overview, 0, round(count($overview)*0.2));

    $res[$tag] .= "<table id=\"items-records-overview\" class=\"list sortable\"><thead>
    <tr>
      <th>".locale_string("match")."</th>
      <th colspan=\"2\">".locale_string("item")."</th>
      <th colspan=\"2\">".locale_string("hero")."</th>
      <th data-sorter=\"time\">".locale_string("item_timing")."</th>
      <th data-sorter=\"time\">".locale_string("item_timing_diff")."</th>
    </tr></thead></tbody>";

    foreach ($overview as $line) {
      $res[$tag] .= "<tr><td>".match_link($line['match'])."</td>".
        "<td>".item_icon($line['item'])."</td>".
        "<td>".item_link($line['item'])."</td>".
        "<td>".hero_portrait($line['hero'])."</td>".
        "<td>".hero_link($line['hero'])."</td>".
        "<td>".convert_time_seconds($line['time'])."</td>".
        "<td>".convert_time_seconds($line['diff'])."</td>".
      "</tr>";
    }

    $res[$tag] .= "</tbody></table>";
  }

  return $res;
}


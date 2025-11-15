<?php

include_once($root."/modules/view/functions/hero_name.php");

$modules['items']['enchantments'] = [];

function rg_view_generate_items_enchantments() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars;

  if($mod == $parent."enchantments") $unset_module = true;
  $parent_module = $parent."enchantments-";
  $res = [];

  if (is_wrapped($report['items']['enchantments'])) {
    $report['items']['enchantments'] = unwrap_data($report['items']['enchantments']);
  }
  
  $is_recalc = ($report['settings']['enchantments_recalc'] ?? false);

  $hnames = $meta["heroes"];
  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $selected_hid = null;
  $selected_tag = null;

  $carryon["/^items-enchantments-(heroid\d+|total)$/"] = "/^items-enchantments-(heroid\d+|total)/";

  $res['total'] = '';
  if(check_module($parent_module.'total')) {
    $selected_hid = 'total';
    $selected_tag = "total";
  }

  foreach ($hnames as $hid => $name) {
    register_locale_string(hero_name($hid), "heroid".$hid);

    $res["heroid".$hid] = isset($report['items']['enchantments'][$hid]) ? '' : "";

    if(check_module($parent_module."heroid".$hid)) {
      $selected_tag = "heroid".$hid;
      $selected_hid = $hid;
    }
  }

  if (!isset($selected_hid) || empty($report['items']['enchantments'][$selected_hid])) {
    $res[$selected_tag ?? 'total'] = "<div class=\"content-text\">".locale_string("stats_empty")."</div>";
    return $res;
  }

  $data = $report['items']['enchantments'][$selected_hid];

  $category_ids = array_keys($data);
  $tier_ids = array_filter($category_ids, function($id) { return $id != 0; });
  sort($tier_ids);
  $category_ids = array_merge([0], $tier_ids);

  $res[$selected_tag] = '';

  if ($is_recalc) {
    $res[$selected_tag] .= "<div class=\"content-text alert\">".
      locale_string("enchantments_recalc_notice").
    "</div>";
  }

  if ($selected_hid !== 'total') {
    $pbdata = $report['pickban'][$selected_hid] ?? [];
    $res[$selected_tag] .= "<table id=\"items-enchantments-$selected_tag-reference\" class=\"list\">";
    $res[$selected_tag] .= "<thead><tr>".
      "<th></th>".
      "<th>".locale_string("hero")."</th>".
      "<th>".locale_string("matches_picked")."</th>".
      "<th>".locale_string("winrate")."</th>".
    "</tr></thead><tbody>";
    $res[$selected_tag] .= "<tr>".
      "<td>".hero_portrait($selected_hid)."</td>".
      "<td>".hero_link($selected_hid)."</td>".
      "<td>".($pbdata['matches_picked'] ?? 0)."</td>".
      "<td>".number_format(($pbdata['winrate_picked'] ?? 0)*100, 2)."%</td>".
    "</tr></tbody></table><br />";
  }
  
  if (isset($data[0]) && !empty($data[0])) {
    $items = $data[0];

    uasort($items, function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $rows = "";
    foreach ($items as $item_id => $item_data) {
      if (empty($item_data) || $item_data['matches'] + $item_data['matches_wo'] == 0) {
        continue;
      }
      
      $prate = $item_data['matches'] / ($item_data['matches'] + $item_data['matches_wo']);
      if ($prate == 0) continue;

      $wr_diff = $item_data['wr'] - $item_data['wr_wo'];
      
      $rows .= "<tr ".
        "data-value-matches=\"{$item_data['matches']}\" ".
        "data-value-prate=\"".number_format($prate*100, 2)."\" ".
        "data-value-wr=\"".number_format($item_data['wr']*100, 2)."\" ".
        "data-value-wr_diff=\"".number_format($wr_diff*100, 2)."\" ".
      ">".
        "<td data-col-group=\"_index\">".item_icon($item_id)."</td>".
        "<td data-col-group=\"_index\">".item_link($item_id)."</td>".
        "<td class=\"separator\" data-sorter=\"digit\" data-col-group=\"stats\">".$item_data['matches']."</td>".
        "<td data-sorter=\"digit\" data-col-group=\"stats\">".number_format($prate*100, 2)."%</td>".
        "<td data-sorter=\"digit\" data-col-group=\"stats\">".number_format($item_data['wr']*100, 2)."%</td>".
        "<td data-sorter=\"digit\" data-col-group=\"stats\">".($wr_diff >= 0 ? '+' : '').number_format($wr_diff*100, 2)."%</td>".
      "</tr>";
    }

    if (!empty($rows)) {
      $table_id = "items-enchantments-$selected_tag-cat0";
      $res[$selected_tag] .= search_filter_component($table_id);
      $res[$selected_tag] .= "<table id=\"$table_id\" class=\"list sortable\">";
      $res[$selected_tag] .= "<thead><tr>".
        "<th data-col-group=\"_index\"></th>".
        "<th data-col-group=\"_index\">".locale_string("item")."</th>".
        "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("matches")."</th>".
        "<th data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("purchase_rate")."</th>".
        "<th data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("winrate")."</th>".
        "<th data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("items_winrate_increase")."</th>".
      "</tr></thead><tbody>$rows</tbody></table><br />";
    }
  }

  if (!empty($tier_categories)) {
    $res[$selected_tag] .= "<div class=\"content-header\">".locale_string("items_tiers")."</div>";
  }

  foreach ($category_ids as $tier_number => $category_id) {
    if (!$tier_number || empty($data[$category_id])) continue;

    $items = $data[$category_id];

    uasort($items, function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $rows = "";
    foreach ($items as $item_id => $item_data) {
      if (empty($item_data) || $item_data['matches'] + $item_data['matches_wo'] == 0) {
        continue;
      }
      
      $prate = $item_data['matches'] / ($item_data['matches'] + $item_data['matches_wo']);
      if ($prate == 0) continue;

      $wr_diff = $item_data['wr'] - $item_data['wr_wo'];
      
      $rows .= "<tr ".
        "data-value-matches=\"{$item_data['matches']}\" ".
        "data-value-prate=\"".number_format($prate*100, 2)."\" ".
        "data-value-wr=\"".number_format($item_data['wr']*100, 2)."\" ".
        "data-value-wr_diff=\"".number_format($wr_diff*100, 2)."\" ".
      ">".
        "<td data-col-group=\"_index\">".item_icon($item_id)."</td>".
        "<td data-col-group=\"_index\">".item_link($item_id)."</td>".
        "<td class=\"separator\" data-sorter=\"digit\" data-col-group=\"stats\">".$item_data['matches']."</td>".
        "<td data-sorter=\"digit\" data-col-group=\"stats\">".number_format($prate*100, 2)."%</td>".
        "<td data-sorter=\"digit\" data-col-group=\"stats\">".number_format($item_data['wr']*100, 2)."%</td>".
        "<td data-sorter=\"digit\" data-col-group=\"stats\">".($wr_diff >= 0 ? '+' : '').number_format($wr_diff*100, 2)."%</td>".
      "</tr>";
    }

    if (!empty($rows)) {
      $table_id = "items-enchantments-$selected_tag-cat$category_id";
      $res[$selected_tag] .= "<table id=\"$table_id\" class=\"list sortable\">";
      $res[$selected_tag] .= "<caption>".locale_string("tier")." ".$tier_number."</caption>";
      $res[$selected_tag] .= "<thead><tr>".
        "<th data-col-group=\"_index\"></th>".
        "<th data-col-group=\"_index\">".locale_string("item")."</th>".
        "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("matches")."</th>".
        "<th data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("purchase_rate")."</th>".
        "<th data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("winrate")."</th>".
        "<th data-sorter=\"digit\" data-col-group=\"stats\">".locale_string("items_winrate_increase")."</th>".
      "</tr></thead><tbody>$rows</tbody></table><br />";
    }
  }

  return $res;
}


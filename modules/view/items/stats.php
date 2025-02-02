<?php

$modules['items']['stats'] = [];

function rg_view_generate_items_stats() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars;

  if($mod == $parent."stats") $unset_module = true;
  $parent_module = $parent."stats-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [];

  $res['total'] = '';

  $neutral_items = array_unique(
    array_merge(
      $meta['item_categories']['neutral_tier_1'],
      $meta['item_categories']['neutral_tier_2'],
      $meta['item_categories']['neutral_tier_3'],
      $meta['item_categories']['neutral_tier_4'],
      $meta['item_categories']['neutral_tier_5']
    )
  );
  $enable_neutrals = false;

  if(check_module($parent_module."total")) {
    $hero = "total";
    $tag = "total";
  }

  $hnames = $meta["heroes"];

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

  $meta['item_categories'];
  if (isset($_GET['item_cat'])) {
    $cat = explode(',', $_GET['item_cat']);
    // $linkvars = (empty($linkvars) ? "" : $linkvars."&")."item_cat=".$_GET['item_cat'];
  } else {
    $cat = [];
  }

  foreach ($report['items']['stats'][$hero] as $iid => $v) {
    if (empty($v)) unset($report['items']['stats'][$hero][$iid]);
  }

  items_ranking($report['items']['stats'][$hero]);

  uasort($report['items']['stats'][$hero], function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($report['items']['stats'][$hero])['wrank'];
  $max = reset($report['items']['stats'][$hero])['wrank'];

  foreach ($report['items']['stats'][$hero] as $id => $el) {
    $report['items']['stats'][$hero][$id]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    unset($report['items']['stats'][$hero][$id]['wrank']);

    if (!$enable_neutrals && in_array($id, $neutral_items)) {
      $enable_neutrals = true;
    }
  }

  $res[$tag] .= "<div class=\"selector-modules-level-4\">";

  if ($hero !== 'total') {
    $data = $report['pickban'][$hero];

    $res[$tag] .= "<table id=\"items-$tag-reference\" class=\"list\">";
    $res[$tag] .= "<thead><tr>".
      "<th></th>".
      "<th>".locale_string("hero")."</th>".
      "<th>".locale_string("matches_picked")."</th>".
      "<th>".locale_string("winrate")."</th>".
    "</tr></thead><tbody>";
    $res[$tag] .= "<tr>".
      "<td>".hero_portrait($hero)."</td>".
      "<td>".hero_link($hero)."</td>".
      "<td>".$data['matches_picked']."</td>".
      "<td>".number_format($data['winrate_picked']*100, 2)."%</td>".
    "</tr></tbody></table><br />";
  }

  $item_cats = [
    'major', 'medium', 'early', 
    //'neutral_tier_1', 'neutral_tier_2', 'neutral_tier_3', 'neutral_tier_4', 'neutral_tier_5',
  ];
  if ($enable_neutrals) {
    $item_cats = array_merge($item_cats, [ 'neutral_tier_1', 'neutral_tier_2', 'neutral_tier_3', 'neutral_tier_4', 'neutral_tier_5' ]);
  }

  $item_cats_filtered = [];
  foreach ($cat as $ic) {
    if (empty($meta['item_categories'][$ic]) || !in_array($ic, $item_cats)) continue;

    $item_cats_filtered = array_merge($item_cats_filtered, $meta['item_categories'][$ic]);
  }
  $item_cats_filtered = array_unique($item_cats_filtered);

  $items = array_filter($report['items']['stats'][$hero], function($v, $k) use (&$cat, &$item_cats_filtered) {
    if (!empty($cat)) {
      return in_array($k, $item_cats_filtered) && !empty($v);
    }
    return !empty($v);
  }, ARRAY_FILTER_USE_BOTH);

  $res[$tag] .= 
    "<span class=\"selector\">".locale_string("items_category_selector").":</span> ".
    // onchange=\"select_modules_link(this);\"
    "<div class=\"custom-selector-multiple\">".
    "<select multiple id=\"items-cat-multiselect\" class=\"select-selectors select-selectors-level-4\" 
      data-empty-placeholder=\"".locale_string("items_category_all")."\">";
    // "<option ".($cat === null ? "selected=\"selected\"" : "").
    //   " value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_category_all")."</option>";
  foreach ($item_cats as $ic) {
    $res[$tag] .= "<option ".(in_array($ic, $cat) ? "selected=\"selected\"" : "").
      " value=\"$ic\">".locale_string("items_category_$ic")."</option>";
  }
  $res[$tag] .= "</select></div>";
  $res[$tag] .= "<input type=\"button\" 
    class=\"custom-button\" 
    onclick=\"multiselectSubmit('items-cat-multiselect', '?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."', 'item_cat')\"
    value=\"".locale_string("apply")."\"
  />";
  
  $res[$tag] .= "</div>";

  $res[$tag] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("items_stats_desc")."</div>".
    "</div>".
  "</details>";

  if (empty($items)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $rows = "";
  $matches_med = [];

  foreach ($items as $iid => $line) {
    $matches_med[] = $line['purchases'];
    
    $rows .= "<tr ".
      "data-value-prate=\"{$line['purchases']}\" ".
      "data-value-winrate=\"".number_format($line['winrate']*100, 2)."\" ".
      "data-value-grad_pos=\"".($line['grad'] > 0 ? 1 : 0)."\" ".
      "data-value-grad_neg=\"".($line['grad'] < 0 ? 1 : 0)."\" ".
      "data-value-nograd=\"".($line['grad'] == 0 ? 1 : 0)."\" ".
    ">".
      "<td data-col-group=\"_index\">".item_icon($iid)."</td>".
      "<td data-col-group=\"_index\">".item_link($iid)."</td>".
      "<td class=\"separator\" data-col-group=\"total\">".$line['purchases']."</td>".
      "<td data-col-group=\"total\">".number_format($line['prate']*100, 2)."%</td>".
      "<td data-col-group=\"total\">".number_format($line['rank'], 1)."</td>".
      "<td class=\"separator\" data-col-group=\"items_winrate_shifts\">".number_format($line['winrate']*100, 2)."%</td>".
      "<td data-col-group=\"items_winrate_shifts\">".($line['wo_wr'] < $line['winrate'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 2)."%</td>".
      // "<td>".($line['wo_wr'] > $line['winrate'] ? '+' : '').number_format(($line['wo_wr']-$line['winrate'])*100, 2)."%</td>".
      // "<td>".number_format($line['wo_wr']*100, 2)."%</td>".
      "<td data-col-group=\"items_winrate_shifts\">".($line['early_wr'] > $line['winrate'] ? '+' : '').number_format(($line['early_wr']-$line['winrate'])*100, 2)."%</td>".
      "<td data-col-group=\"items_winrate_shifts\">".($line['late_wr'] > $line['winrate'] ? '+' : '').number_format(($line['late_wr']-$line['winrate'])*100, 2)."%</td>".
      "<td data-col-group=\"items_winrate_shifts\">".number_format($line['grad']*100, 2)."%</td>".
      "<td class=\"separator\" data-col-group=\"items_timings\" value=\"{$line['avg_time']}\">".convert_time_seconds($line['avg_time'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"{$line['min_time']}\">".convert_time_seconds($line['min_time'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"{$line['q1']}\">".convert_time_seconds($line['q1'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"{$line['median']}\">".convert_time_seconds($line['median'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"{$line['q3']}\">".convert_time_seconds($line['q3'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"{$line['max_time']}\">".convert_time_seconds($line['max_time'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"".($line['q3']-$line['q1'])."\">".convert_time_seconds($line['q3']-$line['q1'])."</td>".
      "<td data-col-group=\"items_timings\" value=\"{$line['std_dev']}\">".convert_time_seconds($line['std_dev'])."</td>".
    "</tr>";
  }

  sort($matches_med);

  $res[$tag] .= filter_toggles_component("items-$tag", [
    'prate' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_items_prate'
    ],
    'winrate' => [
      'value' => 50,
      'label' => 'data_filter_items_winrate'
    ],
    'grad_pos' => [
      'value' => 1,
      'label' => 'data_filter_items_grad_pos'
    ],
    'grad_neg' => [
      'value' => 1,
      'label' => 'data_filter_items_grad_neg'
    ],
    'nograd' => [
      'value' => 1,
      'label' => 'data_filter_items_nograd'
    ]
  ], "items-$tag", 'wide');

  $res[$tag] .= table_columns_toggle("items-$tag", [
    'total', 'items_winrate_shifts', 'items_timings',
  ], true);

  $res[$tag] .= search_filter_component("items-$tag", true);

  $res[$tag] .= "<table id=\"items-$tag\" class=\"list wide sortable\">";
  $res[$tag] .= "<thead><tr class=\"overhead\">".
      "<th width=\"15%\" colspan=\"2\" data-col-group=\"_index\"></th>".
      "<th class=\"separator\" width=\"18%\" colspan=\"3\" data-col-group=\"total\">".locale_string("total")."</th>".
      "<th class=\"separator\" width=\"30%\" colspan=\"5\" data-col-group=\"items_winrate_shifts\">".locale_string("items_winrate_shifts")."</th>".
      "<th class=\"separator\" colspan=\"8\" data-col-group=\"items_timings\">".locale_string("items_timings")."</th>".
    "</tr><tr>".
    "<th data-col-group=\"_index\"></th>".
    "<th data-col-group=\"_index\">".locale_string("item")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"total\">".locale_string("purchases")."</th>".
    "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("purchase_rate")."</th>".
    "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("rank")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"items_winrate_shifts\">".locale_string("winrate")."</th>".
    "<th data-sorter=\"digit\" data-col-group=\"items_winrate_shifts\">".locale_string("items_wo_wr_shift")."</th>".
    "<th data-sorter=\"digit\" data-col-group=\"items_winrate_shifts\">".locale_string("items_early_wr_shift")."</th>".
    "<th data-sorter=\"digit\" data-col-group=\"items_winrate_shifts\">".locale_string("items_late_wr_shift")."</th>".
    "<th data-sorter=\"digit\" data-col-group=\"items_winrate_shifts\">".locale_string("items_wr_gradient")."</th>".
    "<th class=\"separator\" data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_mean")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_min")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_q1")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_median")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_q3")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_max")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_window")."</th>".
    "<th data-sorter=\"valuesort\" data-col-group=\"items_timings\">".locale_string("item_time_std_dev")."</th>".
  "</tr></thead><tbody>$rows</tbody></table>";

  return $res;
}


<?php

$modules['items']['icritical'] = [];

function rg_view_generate_items_critical() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars;

  if($mod == $parent."icritical") $unset_module = true;
  $parent_module = $parent."icritical-";
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

  $hero = "total";
  $tag = "total";

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

  foreach ($report['items']['stats'][$hero] as $iid => $v) {
    if (empty($v)) unset($report['items']['stats'][$hero][$iid]);
  }

  $meta['item_categories'];
  if (isset($_GET['item_cat'])) {
    $cat = explode(',', $_GET['item_cat']);
  } else {
    $cat = [];
  }

  $res[$tag] .= "<div class=\"selector-modules-level-4\">";

  if ($hero !== 'total') {
    $data = $report['pickban'][$hero];
    $winrate = $data['winrate_picked'];

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
  } else {
    $winrate = 0.5;
  }

  $item_cats = [
    'major', 'medium', 'early', 
    //'neutral_tier_1', 'neutral_tier_2', 'neutral_tier_3', 'neutral_tier_4', 'neutral_tier_5',
  ];

  $items = array_filter($report['items']['stats'][$hero], function($v, $k) use (&$neutral_items) {
    return ( !in_array($k, $neutral_items) || empty($v) ) && $v['grad'] < -0.01 && $v['grad'] != 0;
  }, ARRAY_FILTER_USE_BOTH);


  $item_cats_filtered = [];
  foreach ($cat as $ic) {
    if (empty($meta['item_categories'][$ic]) || !in_array($ic, $item_cats)) continue;

    $item_cats_filtered = array_merge($item_cats_filtered, $meta['item_categories'][$ic]);
  }
  $item_cats_filtered = array_unique($item_cats_filtered);

  $items = array_filter($items, function($v, $k) use (&$cat, &$item_cats_filtered) {
    if (!empty($cat)) {
      return in_array($k, $item_cats_filtered) && !empty($v);
    }
    return !empty($v);
  }, ARRAY_FILTER_USE_BOTH);

  $items_sz = count($items);
  uasort($items, function($a, $b) {
    return $b['purchases'] <=> $a['purchases'];
  });

  $items = array_psplice($items, 0, round($items_sz*0.75));

  $items_rc = [];
  $matches_med = [];

  foreach ($items as $iid => $line) {
    $items_rc[$iid] = [
      'purchases' => $line['purchases'],
      'prate' => $line['prate'],
      'grad' => $line['grad'],
      'q1' => $line['q1'],
      'median' => $line['median'],
      'winrate' => $line['winrate'],
      'early_wr' => $line['early_wr'],
      'critical_time' => $line['q1'] - 60*($line['early_wr'] - $winrate)/$line['grad'],
    ];
    $matches_med[] = $line['purchases'];
  }

  $res[$tag] .= 
    "<span class=\"selector\">".locale_string("items_category_selector").":</span> ".
    // onchange=\"select_modules_link(this);\"
    "<div class=\"custom-selector-multiple\">".
    "<select multiple id=\"items-cat-multiselect\" class=\"select-selectors select-selectors-level-4\" 
      data-empty-placeholder=\"".locale_string("items_category_all")."\">";
    // "<option ".($cat === null ? "selected=\"selected\"" : "").
    //   " value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_category_all")."</option>";
  foreach ($item_cats as $ic) {
    $res[$tag] .= "<option ".(in_array($ic, $cat ?? []) ? "selected=\"selected\"" : "").
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
      "<div class=\"line\">".locale_string("items_stats_critical_desc")."</div>".
    "</div>".
  "</details>";

  if (empty($items)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
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
    'early_time' => [
      'value' => 1,
      'label' => 'data_filter_items_early_time'
    ],
  ], "items-$tag");

  $res[$tag] .= search_filter_component("items-$tag");

  $res[$tag] .= "<table id=\"items-$tag\" class=\"list sortable\">";
  $res[$tag] .= "<thead><tr class=\"overhead\">".
      "<th width=\"20%\" colspan=\"2\"></th>".
      "<th width=\"18%\" colspan=\"2\"></th>".
      "<th class=\"separator\" width=\"30%\" colspan=\"3\">".locale_string("items_winrate_shifts")."</th>".
      "<th class=\"separator\" colspan=\"4\">".locale_string("items_timings")."</th>".
    "</tr><tr>".
    "<th></th>".
    "<th>".locale_string("item")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\">".locale_string("purchases")."</th>".
    "<th data-sorter=\"digit\">".locale_string("purchase_rate")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\">".locale_string("winrate")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_early_wr_shift")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_wr_gradient")."</th>".
    "<th class=\"separator\" data-sorter=\"valuesort\">".locale_string("item_time_q1")."</th>".
    "<th data-sorter=\"valuesort\">".locale_string("item_time_median")."</th>".
    "<th data-sorter=\"valuesort\">".locale_string("item_time_critical")."</th>".
    "<th data-sorter=\"valuesort\">".locale_string("item_time_window")."</th>".
  "</tr></thead><tbody>";

  foreach ($items_rc as $iid => $line) {
    $res[$tag] .= "<tr ".
      "data-value-prate=\"{$line['purchases']}\" ".
      "data-value-winrate=\"".number_format($line['winrate']*100, 2)."\" ".
      "data-value-early_time=\"".($line['q1'] > 1200 ? 0 : 1)."\" ".
    ">".
      "<td>".item_icon($iid)."</td>".
      "<td>".item_link($iid)."</td>".
      "<td class=\"separator\">".$line['purchases']."</td>".
      "<td>".number_format($line['prate']*100, 2)."%</td>".
      "<td class=\"separator\">".number_format($line['winrate']*100, 2)."%</td>".
      "<td>".($line['early_wr'] > $line['winrate'] ? '+' : '').number_format(($line['early_wr']-$line['winrate'])*100, 2)."%</td>".
      "<td>".number_format($line['grad']*100, 2)."%</td>".
      "<td class=\"separator\" value=\"{$line['q1']}\">".convert_time_seconds($line['q1'])."</td>".
      "<td value=\"{$line['median']}\">".convert_time_seconds($line['median'])."</td>".
      "<td value=\"{$line['critical_time']}\">".convert_time_seconds($line['critical_time'])."</td>".
      "<td value=\"".($line['critical_time']-$line['q1'])."\">".convert_time_seconds($line['critical_time']-$line['q1'])."</td>".
    "</tr>";
  }

  $res[$tag] .= "</tbody></table>";

  return $res;
}


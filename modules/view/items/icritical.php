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
  if (isset($_GET['item_cat']) && isset($meta['item_categories'][ $_GET['item_cat'] ])) {
    $cat = $_GET['item_cat'];
  } else {
    $cat = null;
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
    return ( !in_array($k, $neutral_items) || empty($v) ) && $v['grad'] < -0.01;
  }, ARRAY_FILTER_USE_BOTH);

  if ($cat) {
    $items = array_filter($items, function($v, $k) use ($cat, &$meta) {
      if ($cat !== null) {
        return in_array($k, $meta['item_categories'][$cat]) && !empty($v);
      }
      return !empty($v);
    }, ARRAY_FILTER_USE_BOTH);
  }

  $items_sz = count($items);
  uasort($items, function($a, $b) {
    return $b['purchases'] <=> $a['purchases'];
  });

  $items = array_psplice($items, 0, round($items_sz*0.75));

  $items_rc = [];

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

  }

  $res[$tag] .= 
    "<span class=\"selector\">".locale_string("items_category_selector").":</span> ".
    "<select onchange=\"select_modules_link(this);\" class=\"select-selectors select-selectors-level-4\">".
    "<option ".($cat === null ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_category_all")."</option>";
  foreach ($item_cats as $ic) {
    $res[$tag] .= "<option ".($cat == $ic ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."&item_cat=$ic\">".locale_string("items_category_$ic")."</option>";
  }
  $res[$tag] .= "</select>";
  
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
    "<th class=\"separator\" data-sorter=\"time\">".locale_string("item_time_q1")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_median")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_critical")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_window")."</th>".
  "</tr></thead><tbody>";

  foreach ($items_rc as $iid => $line) {
    $res[$tag] .= "<tr>".
      "<td>".item_icon($iid)."</td>".
      "<td>".item_link($iid)."</td>".
      "<td class=\"separator\">".$line['purchases']."</td>".
      "<td>".number_format($line['prate']*100, 2)."%</td>".
      "<td class=\"separator\">".number_format($line['winrate']*100, 2)."%</td>".
      "<td>".($line['early_wr'] > $line['winrate'] ? '+' : '').number_format(($line['early_wr']-$line['winrate'])*100, 2)."%</td>".
      "<td>".number_format($line['grad']*100, 2)."%</td>".
      "<td class=\"separator\">".convert_time_seconds($line['q1'])."</td>".
      "<td>".convert_time_seconds($line['median'])."</td>".
      "<td>".convert_time_seconds($line['critical_time'])."</td>".
      "<td>".convert_time_seconds($line['critical_time']-$line['q1'])."</td>".
    "</tr>";
  }

  $res[$tag] .= "</tbody></table>";

  return $res;
}


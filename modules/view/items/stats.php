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
  if (isset($_GET['item_cat']) && isset($meta['item_categories'][ $_GET['item_cat'] ])) {
    $cat = $_GET['item_cat'];
  } else {
    $cat = null;
  }

  foreach ($report['items']['stats'][$hero] as $iid => $v) {
    if (empty($v)) unset($report['items']['stats'][$hero][$iid]);
  }

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($report['items']['stats'][$hero], $ranking_sort);

  $increment = 100 / sizeof($report['items']['stats'][$hero]); $i = 0;

  foreach ($report['items']['stats'][$hero] as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

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
      "<td>".hero_name($hero)."</td>".
      "<td>".$data['matches_picked']."</td>".
      "<td>".number_format($data['winrate_picked']*100, 2)."%</td>".
    "</tr></tbody></table><br />";
  }

  $item_cats = [
    'major', 'medium', 'early', 
    // 'neutral_tier_1', 'neutral_tier_2', 'neutral_tier_3', 'neutral_tier_4', 'neutral_tier_5',
  ];

  $items = array_filter($report['items']['stats'][$hero], function($v, $k) use ($cat, &$meta) {
    if ($cat !== null) {
      return in_array($k, $meta['item_categories'][$cat]) && !empty($v);
    }
    return !empty($v);
  }, ARRAY_FILTER_USE_BOTH);

  $res[$tag] .= 
    "<span class=\"selector\">".locale_string("items_category_selector").":</span> ".
    "<select onchange=\"select_modules_link(this);\" class=\"select-selectors select-selectors-level-4\">".
    "<option ".($cat === null ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_category_all")."</option>";
  foreach ($item_cats as $ic) {
    $res[$tag] .= "<option ".($cat == $ic ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."&item_cat=$ic\">".locale_string("items_category_$ic")."</option>";
  }
  $res[$tag] .= "</select>";
  
  $res[$tag] .= "</div>";

  $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_desc")."</div>";

  if (empty($items)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $res[$tag] .= "<table id=\"items-$tag\" class=\"list wide sortable\">";
  $res[$tag] .= "<thead><tr class=\"overhead\">".
      "<th width=\"12%\" colspan=\"2\"></th>".
      "<th width=\"18%\" colspan=\"3\"></th>".
      "<th class=\"separator\" width=\"30%\" colspan=\"5\">".locale_string("items_winrate_shifts")."</th>".
      "<th class=\"separator\" colspan=\"7\">".locale_string("items_timings")."</th>".
    "</tr><tr>".
    "<th></th>".
    "<th>".locale_string("item")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\">".locale_string("purchases")."</th>".
    "<th data-sorter=\"digit\">".locale_string("purchase_rate")."</th>".
    "<th data-sorter=\"digit\">".locale_string("rank")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\">".locale_string("winrate")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_wo_wr_shift")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_early_wr_shift")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_late_wr_shift")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_wr_gradient")."</th>".
    "<th class=\"separator\" data-sorter=\"time\">".locale_string("item_time_mean")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_min")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_q1")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_median")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_q3")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_max")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_std_dev")."</th>".
  "</tr></thead><tbody>";

  foreach ($items as $iid => $line) {
    $res[$tag] .= "<tr>".
      "<td>".item_icon($iid)."</td>".
      "<td>".item_name($iid)."</td>".
      "<td class=\"separator\">".$line['purchases']."</td>".
      "<td>".number_format($line['prate']*100, 2)."%</td>".
      "<td>".number_format($ranks[$iid], 1)."</td>".
      "<td class=\"separator\">".number_format($line['winrate']*100, 2)."%</td>".
      //"<td>".($line['wo_wr'] < $line['winrate'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 2)."%</td>".
      "<td>".number_format($line['wo_wr']*100, 2)."%</td>".
      "<td>".($line['early_wr'] > $line['winrate'] ? '+' : '').number_format(($line['early_wr']-$line['winrate'])*100, 2)."%</td>".
      "<td>".($line['late_wr'] > $line['winrate'] ? '+' : '').number_format(($line['late_wr']-$line['winrate'])*100, 2)."%</td>".
      "<td>".number_format($line['grad']*100, 2)."%</td>".
      "<td class=\"separator\">".convert_time_seconds($line['avg_time'])."</td>".
      "<td>".convert_time_seconds($line['min_time'])."</td>".
      "<td>".convert_time_seconds($line['q1'])."</td>".
      "<td>".convert_time_seconds($line['median'])."</td>".
      "<td>".convert_time_seconds($line['q3'])."</td>".
      "<td>".convert_time_seconds($line['max_time'])."</td>".
      "<td>".convert_time_seconds($line['std_dev'])."</td>".
    "</tr>";
  }

  $res[$tag] .= "</tbody></table>";

  return $res;
}


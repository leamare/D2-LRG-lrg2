<?php

$modules['items']['heroes'] = [];

function rg_view_generate_items_heroes() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;

  if($mod == $parent."heroes") $unset_module = true;
  $parent_module = $parent."heroes-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [];

  $item_ids = array_keys($report['items']['stats']['total']);
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

  // RANKING FOR REFERENCE TABLE

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($report['items']['stats']['total'], $ranking_sort);

  $increment = 100 / sizeof($report['items']['stats']['total']); $i = 0;
  $last_rank = 0;

  foreach ($report['items']['stats']['total'] as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

  // REFERENCE TABLE

  $data = $report['items']['stats']['total'][$item];

  $res['itemid'.$item] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("items_stats_desc")."</div>".
    "</div>".
  "</details>";

  $res['itemid'.$item] .= "<table id=\"items-itemid$item-reference\" class=\"list wide\">";
  $res['itemid'.$item] .= "<thead><tr class=\"overhead\">".
      "<th width=\"12%\" colspan=\"2\" data-col-group=\"_index\"></th>".
      "<th class=\"separator\" width=\"18%\" colspan=\"3\" data-col-group=\"total\">".locale_string("total")."</th>".
      "<th class=\"separator\" width=\"30%\" colspan=\"5\" data-col-group=\"items_winrate_shifts\">".locale_string("items_winrate_shifts")."</th>".
      "<th class=\"separator\" colspan=\"7\" data-col-group=\"items_timings\">".locale_string("items_timings")."</th>".
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
    "<th class=\"separator\" data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_mean")."</th>".
    "<th data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_min")."</th>".
    "<th data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_q1")."</th>".
    "<th data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_median")."</th>".
    "<th data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_q3")."</th>".
    "<th data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_max")."</th>".
    "<th data-sorter=\"time\" data-col-group=\"items_timings\">".locale_string("item_time_std_dev")."</th>".
  "</tr></thead><tbody>";

  $res['itemid'.$item] .= "<tr>".
    "<td data-col-group=\"_index\">".item_icon($item)."</td>".
    "<td data-col-group=\"_index\">".item_link($item)."</td>".
    "<td class=\"separator\" data-col-group=\"total\">".$data['purchases']."</td>".
    "<td data-col-group=\"total\">".number_format($data['prate']*100, 2)."%</td>".
    "<td data-col-group=\"total\">".number_format($ranks[$item], 1)."</td>".
    "<td class=\"separator\" data-col-group=\"items_winrate_shifts\">".number_format($data['winrate']*100, 2)."%</td>".
    "<td data-col-group=\"items_winrate_shifts\">".($data['wo_wr'] < $data['winrate'] ? '+' : '').number_format(($data['winrate']-$data['wo_wr'])*100, 2)."%</td>".
    // "<td>".($data['wo_wr'] > $data['winrate'] ? '+' : '').number_format(($data['wo_wr']-$data['winrate'])*100, 2)."%</td>".
    "<td data-col-group=\"items_winrate_shifts\">".($data['early_wr'] > $data['winrate'] ? '+' : '').number_format(($data['early_wr']-$data['winrate'])*100, 2)."%</td>".
    "<td data-col-group=\"items_winrate_shifts\">".($data['late_wr'] > $data['winrate'] ? '+' : '').number_format(($data['late_wr']-$data['winrate'])*100, 2)."%</td>".
    "<td data-col-group=\"items_winrate_shifts\">".number_format($data['grad']*100, 2)."%</td>".
    "<td class=\"separator\" data-col-group=\"items_timings\">".convert_time_seconds($data['avg_time'])."</td>".
    "<td data-col-group=\"items_timings\">".convert_time_seconds($data['min_time'])."</td>".
    "<td data-col-group=\"items_timings\">".convert_time_seconds($data['q1'])."</td>".
    "<td data-col-group=\"items_timings\">".convert_time_seconds($data['median'])."</td>".
    "<td data-col-group=\"items_timings\">".convert_time_seconds($data['q3'])."</td>".
    "<td data-col-group=\"items_timings\">".convert_time_seconds($data['max_time'])."</td>".
    "<td data-col-group=\"items_timings\">".convert_time_seconds($data['std_dev'])."</td>".
  "</tr>";
  $res['itemid'.$item] .= "</tbody></table>";

  // HEROES TABLE

  unset($report['items']['stats']['total']);
  $heroes = [];
  $matches_med = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    if (!empty($items[$item])) {
      $heroes[$hero] = $items[$item];
      $matches_med[] = $items[$item]['purchases'];
    }
  }

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($heroes, $ranking_sort);

  $increment = 100 / sizeof($heroes); $i = 0;

  foreach ($heroes as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

  sort($matches_med);

  $res['itemid'.$item] .= filter_toggles_component("items-itemid$item", [
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
  ], "items-itemid$item", 'wide');

  $res['itemid'.$item] .= table_columns_toggle("items-itemid$item", [
    'total', 'items_winrate_shifts', 'items_timings',
  ], true);

  $res['itemid'.$item] .= search_filter_component("items-itemid$item", true);

  $res['itemid'.$item] .= "<table id=\"items-itemid$item\" class=\"list wide sortable\">";
  $res['itemid'.$item] .= "<thead><tr class=\"overhead\">".
      "<th width=\"12%\" colspan=\"2\" data-col-group=\"_index\"></th>".
      "<th class=\"separator\" width=\"18%\" colspan=\"3\" data-col-group=\"total\">".locale_string("total")."</th>".
      "<th class=\"separator\" width=\"30%\" colspan=\"5\" data-col-group=\"items_winrate_shifts\">".locale_string("items_winrate_shifts")."</th>".
      "<th class=\"separator\" colspan=\"8\" data-col-group=\"items_timings\">".locale_string("items_timings")."</th>".
    "</tr><tr>".
    "<th data-col-group=\"_index\"></th>".
    "<th data-col-group=\"_index\">".locale_string("hero")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\"  data-col-group=\"total\">".locale_string("purchases")."</th>".
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
  "</tr></thead><tbody>";

  foreach ($heroes as $hero => $line) {
    if (!isset($line['prate'])) $line['prate'] = 0;
    if (!isset($line['wo_wr'])) $line['wo_wr'] = $line['winrate'];
    if (!isset($line['early_wr'])) $line['early_wr'] = $line['winrate'];
    if (!isset($line['late_wr'])) $line['late_wr'] = $line['winrate'];

    $res['itemid'.$item] .= "<tr ".
      "data-value-prate=\"{$line['purchases']}\" ".
      "data-value-winrate=\"".number_format($line['winrate']*100, 2)."\" ".
      "data-value-grad_pos=\"".($line['grad'] > 0 ? 1 : 0)."\" ".
      "data-value-grad_neg=\"".($line['grad'] < 0 ? 1 : 0)."\" ".
      "data-value-nograd=\"".($line['grad'] == 0 ? 1 : 0)."\" ".
    ">".
      "<td data-col-group=\"_index\">".hero_portrait($hero)."</td>".
      "<td data-col-group=\"_index\">".hero_link($hero)."</td>".
      "<td class=\"separator\" data-col-group=\"total\">".$line['purchases']."</td>".
      "<td data-col-group=\"total\">".number_format($line['prate']*100, 2)."%</td>".
      "<td data-col-group=\"total\">".number_format($ranks[$hero], 1)."</td>".
      "<td class=\"separator\" data-col-group=\"items_winrate_shifts\">".number_format($line['winrate']*100, 2)."%</td>".
      "<td data-col-group=\"items_winrate_shifts\">".($line['wo_wr'] < $line['winrate'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 2)."%</td>".
      // "<td>".($line['wo_wr'] > $line['winrate'] ? '+' : '').number_format(($line['wo_wr']-$line['winrate'])*100, 2)."%</td>".
      //"<td>".number_format($line['wo_wr']*100, 2)."%</td>".
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

  $res['itemid'.$item] .= "</tbody></table>";

  return $res;
}


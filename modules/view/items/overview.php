<?php

$modules['items']['overview'] = [];

function rg_view_generate_items_overview() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars;

  $res = "";
  $meta['item_categories'];

  $skip_items = array_unique(
    array_merge(
      $meta['item_categories']['early'], 
      $meta['item_categories']['neutral_tier_1'],
      $meta['item_categories']['neutral_tier_2'],
      $meta['item_categories']['neutral_tier_3'],
      $meta['item_categories']['neutral_tier_4'],
      $meta['item_categories']['neutral_tier_5']
    )
  );

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  if (is_wrapped($report['items']['ph'])) {
    $report['items']['ph'] = unwrap_data($report['items']['ph']);
  }
  if (is_wrapped($report['items']['pi'])) {
    $report['items']['pi'] = unwrap_data($report['items']['pi']);
  }
  if (isset($report['items']['records'])) {
    if (is_wrapped($report['items']['records'])) {
      $report['items']['records'] = unwrap_data($report['items']['records']);
    }
  }

  $res .= "<div class=\"content-header\">".locale_string("items_most_impactful_header")."</div>";
  $res .= "<div class=\"content-text\">".locale_string("items_most_impactful_desc")."</div>";

  // most impactful (5):
  // - best wr difference + more than q3 purchases
  // - worst wr difference + more than q3 purchases

  $res .= "<div class=\"content-text\"><h1>".locale_string("items_most_impactful_total_header")."</h1></div>";
  $res .= "<div class=\"small-list-wrapper\">";

  $limit = $report['settings']['overview_items_limit'] ?? 10; 
  $best = [];
  $worst = [];
  uasort($report['items']['stats']['total'], function($a, $b) {
    return ( $a['winrate']-$a['wo_wr'] ) <=> ( $b['winrate']-$b['wo_wr'] );
  });
  $keys = array_keys($report['items']['stats']['total']);
  $sz = sizeof($keys);

  for ($i = 0, $j = 0; $i < $sz && $j < $limit; $i++) {
    if (empty($keys[$i]) || empty($report['items']['stats']['total'][ $keys[$i] ])) continue;
    if (in_array($keys[$i], $skip_items)) continue;
    if ($report['items']['stats']['total'][ $keys[$i] ]['purchases'] <= $report['items']['ph']['total']['q1']) continue;
    $worst[ $keys[$i] ] = $report['items']['stats']['total'][ $keys[$i] ];
    $j++;
  }

  for ($i = $sz-1, $j = 0; $i > 0 && $j < $limit; $i--) {
    if (empty($keys[$i]) || empty($report['items']['stats']['total'][ $keys[$i] ])) continue;
    if (in_array($keys[$i], $skip_items)) continue;
    if ($report['items']['stats']['total'][ $keys[$i] ]['purchases'] <= $report['items']['ph']['total']['q1']) continue;
    $best[ $keys[$i] ] = $report['items']['stats']['total'][ $keys[$i] ];
    $j++;
  }

  $res .=  "<table id=\"items-over-best\" class=\"list list-small\">
    <thead><tr>
      <th colspan=\"2\">".locale_string("item")."</th>
      <th>".locale_string("purchases")."</th>
      <th>".locale_string("winrate")."</th>
      <th>".locale_string("items_wo_wr_shift")."</th>
    </tr></thead><tbody>";
  foreach ($best as $iid => $line) {
    $res .= "<tr>".
      "<td>".item_icon($iid)."</td>".
      "<td>".item_name($iid)."</td>".
      "<td>".$line['purchases']."</td>".
      "<td>".number_format($line['winrate']*100, 2)."%</td>".
      "<td>".($line['winrate'] > $line['wo_wr'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 2)."%</td>".
    "</tr>";
  }
  $res .= "</tbody></table>";

  $res .=  "<table id=\"items-over-worst\" class=\"list list-small\">
    <thead><tr>
      <th colspan=\"2\">".locale_string("item")."</th>
      <th>".locale_string("purchases")."</th>
      <th>".locale_string("winrate")."</th>
      <th>".locale_string("items_wo_wr_shift")."</th>
    </tr></thead><tbody>";
  foreach ($worst as $iid => $line) {
    $res .= "<tr>".
      "<td>".item_icon($iid)."</td>".
      "<td>".item_name($iid)."</td>".
      "<td>".$line['purchases']."</td>".
      "<td>".number_format($line['winrate']*100, 2)."%</td>".
      "<td>".($line['winrate'] > $line['wo_wr'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 2)."%</td>".
    "</tr>";
  }
  $res .= "</tbody></table>";

  $res .= "</div>";

  unset($report['items']['stats']['total']);

  // most impactful on a hero (5):
  // - best wr difference + more than q3 purchases
  // - worst wr difference + more than q3 purchases

  $gradients = [];
  $best_a = [];
  $worst_a = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    foreach ($items as $iid => $line) {
      if (empty($line)) continue;
      if (in_array($iid, $skip_items)) continue;
      if ($line['purchases'] <= $report['items']['ph'][$hero]['q3'] || $line['purchases'] <= $report['items']['pi'][$iid]['q3']) continue;
      $line['hero'] = $hero;
      $line['item'] = $iid;
      
      if ($line['winrate'] > $line['wo_wr']) $best_a[] = $line;
      else $worst_a[] = $line;

      if ($line['grad'] < 0) $gradients[] = $line;
    }
  }

  uasort($best_a, function($b, $a) {
    return ( $a['winrate']-$a['wo_wr'] ) <=> ( $b['winrate']-$b['wo_wr'] );
  });
  uasort($worst_a, function($a, $b) {
    return ( $a['winrate']-$a['wo_wr'] ) <=> ( $b['winrate']-$b['wo_wr'] );
  });

  $best = array_slice($best_a, 0, $limit);
  $worst = array_slice($worst_a, 0, $limit);

  $res .= "<div class=\"content-text\"><h1>".locale_string("items_most_impactful_heroes_header")."</h1></div>";
  //$res .= "<div class=\"small-list-wrapper\">";
  $res .=  "<table id=\"items-over-best\" class=\"list\">
    <thead><tr>
      <th colspan=\"2\">".locale_string("hero")."</th>
      <th colspan=\"2\">".locale_string("item")."</th>
      <th>".locale_string("purchases")."</th>
      <th>".locale_string("winrate")."</th>
      <th>".locale_string("items_wo_wr_shift")."</th>
    </tr></thead><tbody>";
  foreach ($best as $line) {
    $res .= "<tr>".
      "<td>".hero_portrait($line['hero'])."</td>".
      "<td>".hero_name($line['hero'])."</td>".
      "<td>".item_icon($line['item'])."</td>".
      "<td>".item_name($line['item'])."</td>".
      "<td>".$line['purchases']."</td>".
      "<td>".number_format($line['winrate']*100, 1)."%</td>".
      "<td>".($line['winrate'] > $line['wo_wr'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 1)."%</td>".
    "</tr>";
  }
  $res .= "</tbody></table>";

  $res .=  "<table id=\"items-over-worst\" class=\"list\">
    <thead><tr>
      <th colspan=\"2\">".locale_string("hero")."</th>
      <th colspan=\"2\">".locale_string("item")."</th>
      <th>".locale_string("purchases")."</th>
      <th>".locale_string("winrate")."</th>
      <th>".locale_string("items_wo_wr_shift")."</th>
    </tr></thead><tbody>";
  foreach ($worst as $line) {
    $res .= "<tr>".
      "<td>".hero_portrait($line['hero'])."</td>".
      "<td>".hero_name($line['hero'])."</td>".
      "<td>".item_icon($line['item'])."</td>".
      "<td>".item_name($line['item'])."</td>".
      "<td>".$line['purchases']."</td>".
      "<td>".number_format($line['winrate']*100, 1)."%</td>".
      "<td>".($line['winrate'] > $line['wo_wr'] ? '+' : '').number_format(($line['winrate']-$line['wo_wr'])*100, 1)."%</td>".
    "</tr>";
  }
  $res .= "</tbody></table>";

  //$res .= "</div>";
  
  // critical timings on a hero
  // - highest gradient with more than q3 matches
  // - best timings for these items

  uasort($gradients, function($a, $b) {
    return $a['grad'] <=> $b['grad'];
  });
  $gradients = array_slice($gradients, 0, $limit);

  $res .= "<div class=\"content-text\"><h1>".locale_string("items_overview_timings_header")."</h1></div>";

  $res .=  "<table id=\"items-overview-timings\" class=\"list\"><thead><tr>".
    "<th colspan=\"2\">".locale_string('hero')."</th>".
    "<th colspan=\"2\">".locale_string('item')."</th>".
    "<th>".locale_string("purchases")."</th>".
    "<th>".locale_string("winrate")."</th>".
    "<th>".locale_string("items_wr_gradient")."</th>".
    "<th>".locale_string("item_time_median")."</th>".
    (isset($report['items']['records']) ? "<th>".locale_string("best_timing_record")."</th>" : '').
  "</tr></thead>";
  foreach ($gradients as $line) {
    if (isset($report['items']['records']) && isset($report['items']['records'][ $line['item'] ]))
      $record = $report['items']['records'][ $line['item'] ][ $line['hero'] ] ?? [];
    $res .= "<tr>".
      "<td>".hero_portrait($line['hero'])."</td>".
      "<td>".hero_name($line['hero'])."</td>".
      "<td>".item_icon($line['item'])."</td>".
      "<td>".item_name($line['item'])."</td>".
      "<td>".$line['purchases']."</td>".
      "<td>".number_format($line['winrate']*100, 2)."%</td>".
      "<td>".number_format($line['grad']*100, 2)."%</td>".
      "<td>".convert_time_seconds($line['median'])."</td>".
      (!empty($record) ? "<td>".match_link($record['match'])." (".convert_time_seconds($record['time']).")</td>" : '').
    "</tr>";
  }
  $res .= "</tbody></table>";
  
  return $res;
}


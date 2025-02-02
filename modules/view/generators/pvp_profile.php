<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_pvp_profile($table_id, &$pvp_context, &$context_wrs, $srcid, $heroes_flag = true, $facets = false) {
  $res = "";
  $i = 0;
  $isrank = false;

  if(empty($pvp_context)) return "";

  if (isset( array_values($pvp_context)[0]['diff'] ))
    $nodiff = false;
  else
    $nodiff = true;

  if (isset( array_values($pvp_context)[0]['expectation'] ))
    $exp = true;
  else
    $exp = false;

  if (isset( array_values($pvp_context)[0]['lane_rate'] ))
    $laning = true;
  else
    $laning = false;

  $matches_med = [];

  if(!empty($context_wrs)) {
    $wr_id = $heroes_flag ? "winrate_picked" : "winrate";
    if ($facets) {
      $context_wrs[$srcid]['winrate_picked'] = $context_wrs[$srcid]['w']/$context_wrs[$srcid]['m'];
      $context_wrs[$srcid]['matches_picked'] = $context_wrs[$srcid]['m'];
    }
    $dt = [
      'wr' => $heroes_flag ? $context_wrs[$srcid]['winrate_picked'] : $context_wrs[$srcid]['winrate'],
      'ms' => $heroes_flag ? $context_wrs[$srcid]['matches_picked'] : $context_wrs[$srcid]['matches'],
      'f'  => $context_wrs[$srcid]['f'] ?? null,
    ];

    if ($facets) {
      [ $srcid, $variant ] = explode('-', $srcid);
    }

    $res .= "<table id=\"$table_id-reference\" class=\"list sortable\">".
        "<thead><tr>".
        ($heroes_flag ? "<th width=\"1%\" ".($facets && $variant ? 'colspan="2"' : '')."></th>" : "").
        "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? 'hero' : 'player')."</th>".
        "<th>".locale_string("matches")."</th>".
        "<th>".locale_string("winrate")."</th>".
        ($facets && $variant ? "<th>".locale_string("ratio")."</th>" : '').
        "</tr></thead>".
        "<tbody><tr>".
        ($heroes_flag ? "<td>".hero_portrait($srcid)."</td>" : "").
        ($heroes_flag && $facets && $variant ? "<td>".facet_micro_element($srcid, $variant)."</td>" : "").
        "<td>".($heroes_flag ? hero_link($srcid) : player_link($srcid)).($facets && $variant != 'x' && $variant ? ' '.locale_string("facet_short").$variant : '')."</td>".
        "<td>".$dt['ms']."</td>".
        "<td>".number_format($dt['wr']*100,2)."%</td>".
        ($facets && $variant ? "<td>".number_format($dt['f']*100,2)."%</td>" : '').
        "</tr></tbody></table>";


      $pvp_context_cpy = $pvp_context;

    positions_ranking($pvp_context, $dt['ms']);

    uasort($pvp_context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context)['wrank'];
    $max = reset($pvp_context)['wrank'];
  
    foreach ($pvp_context as $elid => $el) {
      $pvp_context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }

    positions_ranking($pvp_context_cpy, $dt['ms']);

    uasort($pvp_context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context_cpy)['wrank'];
    $max = reset($pvp_context_cpy)['wrank'];
  
    foreach ($pvp_context_cpy as $elid => $el) {
      $pvp_context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($pvp_context[$elid]['wrank']);

      if (isset($el['expectation']) && !isset($el['deviation'])) {
        $pvp_context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $pvp_context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }

    $isrank = true; $i = 0;
  } else {
    foreach ($pvp_context as $elid => $el) {
      $matches_med[] = $el['matches'];
    }
  }

  sort($matches_med);

  $res .= filter_toggles_component($table_id, [
    'match' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_matches'
    ],
    'diff' => [
      'value' => 0,
      'label' => 'data_filter_wr_diff'
    ],
    'dev' => $exp ? [
      'value' => '1',
      'label' => 'data_filter_pos_deviation'
    ] : null,
    'lane' => $laning ? [
      'value' => '25',
      'label' => 'data_filter_lane_rate'
    ] : null
  ], $table_id);

  $res .= search_filter_component($table_id);
  $res .= "<table id=\"$table_id\" class=\"list sortable\">";
  $res .= "<thead><tr>".
          ($heroes_flag && !$i++ ? "<th width=\"1%\" ".($facets ? 'colspan="2"' : '')."></th>" : "").
          "<th data-sortInitialOrder=\"asc\">".locale_string("opponent")."</th>".
          ($isrank ? "<th>".locale_string("rank")."</th><th>".locale_string("antirank")."</th>" : "").
          "<th class=\"separator\">".locale_string("winrate")."</th>".
          (!$nodiff ? "<th>".locale_string("diff")."</th>" : "").
          "<th class=\"separator\">".locale_string("matches")."</th>".
          "<th>".locale_string("won")."</th>".
          "<th>".locale_string("lost")."</th>".
          ($exp ? "<th class=\"separator\">".locale_string("pair_expectation")."</th>".
            "<th>".locale_string("pair_deviation")."</th>".
            "<th>".locale_string("percentage")."</th>" : "").
          ($laning ? "<th class=\"separator\">".locale_string("lane_rate")."</th>".
            "<th>".locale_string("lane_wr")."</th>"
            : "").
          "</tr></thead>";

  if (!$isrank) {
    if ($nodiff) {
      uasort($pvp_context, function($a, $b) {
        if($a['winrate'] == $b['winrate']) return 0;
        else return ($a['winrate'] < $b['winrate']) ? 1 : -1;
      });
    } else {
      uasort($pvp_context, function($a, $b) {
        if($a['diff'] == $b['diff']) return 0;
        else return ($a['diff'] < $b['diff']) ? 1 : -1;
      });
    }
  }

  foreach($pvp_context as $elid_op => $data) {
    if ($facets) {
      [ $elid_op, $variant ] = explode('-', $elid_op);
    }
    $res .= "<tr ".
      "data-value-match=\"".$data['matches']."\" ".
      ($exp ? "data-value-dev=\"".number_format($data['matches']-$data['expectation'], 0)."\" " : "").
      (!$nodiff ? "data-value-diff=\"".number_format($data['diff']*100,2)."\" " : "").
      ($laning ? "data-value-lane=\"".number_format($data['lane_rate']*100, 2)."\" " : "").
      (isset($data['matchids']) ?
                "onclick=\"showModal('".implode(", ", $data['matchids'])."','".locale_string("matches")."')\"" :
                "").">".
      ($heroes_flag ? "<td>".hero_portrait($elid_op)."</td>" : "").
      ($heroes_flag && $facets ? "<td>".facet_micro_element($elid_op, $variant)."</td>" : "").
      "<td>".($heroes_flag ? hero_link($elid_op) : player_link($elid_op)).($facets ? ' '.locale_string("facet_short").$variant : '')."</td>".
      ($isrank ? "<td>".number_format($data['rank'], 2)."</td><td>".number_format($data['arank'], 2)."</td>" : "").
      "<td class=\"separator\">".number_format($data['winrate']*100,2)."%</td>".
      (!$nodiff ? "<td>".number_format($data['diff']*100,2)."%</td>" : "").
      "<td class=\"separator\">".$data['matches']."</td>".
      "<td>".$data['won']."</td>".
      "<td>".$data['lost']."</td>".
      ($exp ? "<td class=\"separator\">".number_format($data['expectation'], 0)."</td>".
      "<td>".number_format($data['matches']-$data['expectation'], 0)."</td>".
      "<td>".number_format(($data['matches']-$data['expectation'])*100/$data['matches'], 2)."%</td>" : "").
      ($laning ? "<td class=\"separator\">".number_format($data['lane_rate']*100, 2)."%</td>".
      "<td>".number_format($data['lane_wr']*100, 2)."%</td>"
      : "").
    "</tr>";
  }

  $res .= "</table>";

  return $res;
}

?>

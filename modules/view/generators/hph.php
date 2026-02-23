<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_hph_profile($table_id, &$context, &$context_wrs, $srcid, $heroes_flag = true, $variant = false) {
  global $report;
  
  $res = "";
  $i = 0;
  $isrank = false;

  $matches_med = [];

  if(!empty($context_wrs)) {
    if ($variant) {
      $context_wrs[$srcid]['matches_picked'] = $context_wrs[$srcid]['m'];
      $context_wrs[$srcid]['winrate_picked'] = $context_wrs[$srcid]['w']/$context_wrs[$srcid]['m'];
    }

    $wr_id = $heroes_flag ? "winrate_picked" : "winrate";
    $dt = [
      'wr' => $heroes_flag ? $context_wrs[$srcid]['winrate_picked'] : $context_wrs[$srcid]['winrate'],
      'ms' => $heroes_flag ? $context_wrs[$srcid]['matches_picked'] : $context_wrs[$srcid]['matches'],
    ];

    if ($variant) {
      [$hid, $v_num] = explode('-', $srcid);
    } else {
      $hid = $srcid;
    }

    $res .= "<table id=\"$table_id-reference\" class=\"list\">".
        "<thead><tr>".
        ($heroes_flag ? "<th width=\"1%\"".($variant ? " colspan=\"2\"" : "")."></th>" : "").
        "<th>".locale_string($heroes_flag ? 'hero' : 'player')."</th>".
        "<th>".locale_string("matches")."</th>".
        "<th>".locale_string("winrate")."</th></tr></thead>".
        "<tbody><tr>".
        ($heroes_flag ? "<td>".hero_portrait($hid)."</td>" : "").
        ($heroes_flag && $variant ? "<td>".facet_micro_element($hid, $v_num)."</td>" : "").
        "<td>".($heroes_flag ? hero_link($hid) : player_link($hid)).(is_numeric($variant) && $variant ? ' '.locale_string("facet_short").$v_num : "")."</td>".
        "<td>".$dt['ms']."</td>".
        "<td>".number_format($dt['wr']*100,2)."%</td>".
        "</tr></tbody></table>";


    foreach ($context as $id => $el) {
      if ($el == null) unset($context[$id]);
      if ($el === true) $context[$id] = $report['hph'.($variant ? '_v' : '')][$id][$srcid];
    }

    positions_ranking($context, $dt['ms']);
    $context_cpy = $context;

    if (empty($context)) return $res;
    uasort($context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context)['wrank'];
    $max = reset($context)['wrank'];
  
    foreach ($context as $elid => $el) {
      $context[$elid]['rank'] = ($max != $min) ? 100 * ($el['wrank']-$min) / ($max-$min) : 0;
      $context_cpy[$elid]['winrate'] = 1-$context_cpy[$elid]['winrate'];
    }

    positions_ranking($context_cpy, $dt['ms']);

    uasort($context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context_cpy)['wrank'];
    $max = reset($context_cpy)['wrank'];
  
    foreach ($context_cpy as $elid => $el) {
      $context[$elid]['arank'] = ($max != $min) ? 100 * ($el['wrank']-$min) / ($max-$min) : 0;
      unset($context[$elid]['wrank']);

      if (isset($el['exp']) && !isset($el['deviation'])) {
        $context[$elid]['deviation'] = $el['matches']-$el['exp'];
        $context[$elid]['deviation_pct'] = round(($el['matches']-$el['exp'])*100/$el['matches'], 2);
      }
    }

    $isrank = true;
  } else {
    foreach ($context as $elid => $el) {
      $matches_med[] = $el['matches'];
    }
  }

  sort($matches_med);

  $keys = array_keys(reset($context));
  $is_lanewr = in_array("lane_wr", $keys);

  $res .= filter_toggles_component($table_id, [
    'match' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_matches'
    ],
    'diff' => [
      'value' => 0,
      'label' => 'data_filter_wr_diff'
    ],
    'dev' => [
      'value' => '1',
      'label' => 'data_filter_pos_deviation'
    ],
    'lane' => [
      'value' => '25',
      'label' => 'data_filter_lane_rate'
    ]
  ], $table_id);

  $res .= search_filter_component($table_id);
  $res .= "<table id=\"$table_id\" class=\"list sortable\">";
  $res .= "<thead><tr>".
          ($heroes_flag ? "<th width=\"1%\"".($variant ? " colspan=\"2\"" : "")."></th>" : "").
          "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? "hero" : "player")."</th>".
          ($isrank ? "<th>".locale_string("rank")."</th><th>".locale_string("antirank")."</th>" : "").
          "<th class=\"separator\">".locale_string("matches")."</th>".
          "<th>".locale_string("winrate")."</th>".
          "<th>".locale_string("winrate_diff")."</th>".
          "<th class=\"separator\">".locale_string("expectation")."</th>".
          "<th>".locale_string("deviation")."</th>".
          "<th>".locale_string("percentage")."</th>".
          "<th class=\"separator\">".locale_string("lane_rate")."</th>".
          ($is_lanewr ? "<th>".locale_string("lane_wr")."</th>" : "").
          "</tr></thead>";

  $pvp_context = $pvp_context ?? null;
  if (!$isrank && is_array($pvp_context)) {
    uasort($pvp_context, function($a, $b) {
      if($a['wr_diff'] == $b['wr_diff']) return 0;
      else return ($a['wr_diff'] < $b['wr_diff']) ? 1 : -1;
    });
  }

  foreach($context as $elid_op => $data) {
    if ($variant) {
      [$elid_op, $v_num] = explode('-', $elid_op);
    }
    if ($data['matches'] == 0) continue;
    $res .= "<tr ".
        "data-value-match=\"".$data['matches']."\" ".
        "data-value-diff=\"".number_format(($data['winrate'] - $dt['wr'])*100,2)."\" ".
        "data-value-dev=\"".number_format($data['matches']-$data['exp'], 0)."\" ".
        "data-value-lane=\"".number_format($data['lane_rate']*100, 2)."\" ".
      ">".
      ($heroes_flag ? "<td>".hero_portrait($elid_op)."</td>" : "").
      ($heroes_flag && $variant ? "<td>".facet_micro_element($elid_op, $v_num)."</td>" : "").
      "<td>".($heroes_flag ? hero_link($elid_op) : player_link($elid_op)).($variant ? ' '.locale_string("facet_short").$v_num : "")."</td>".
      ($isrank ? "<td>".number_format($data['rank'], 2)."</td><td>".number_format($data['arank'], 2)."</td>" : "").
      "<td class=\"separator\">".number_format($data['matches'])."</td>".
      "<td>".number_format($data['winrate']*100,2)."%</td>".
      "<td>".number_format(($data['winrate'] - $dt['wr'])*100,2)."%</td>".
      "<td class=\"separator\">".number_format($data['exp'])."</td>".
      "<td>".number_format($data['matches'] - $data['exp'])."</td>".
      "<td>".number_format(100*($data['matches'] - $data['exp'])/$data['matches'], 2)."%</td>".
      "<td class=\"separator\">".number_format($data['lane_rate']*100, 2)."%</td>".
      ($is_lanewr ? "<td>".number_format($data['lane_wr']*100, 2)."%</td>" : "").
      "</tr>";
  }

  $res .= "</table>";

  return $res;
}

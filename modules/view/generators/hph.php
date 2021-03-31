<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_hph_profile($table_id, &$context, &$context_wrs, $srcid, $heroes_flag = true) {
  global $report;
  
  $res = "";
  $i = 0;
  $isrank = false;

  if(!empty($context_wrs)) {
    $wr_id = $heroes_flag ? "winrate_picked" : "winrate";
    $dt = [
      'wr' => $heroes_flag ? $context_wrs[$srcid]['winrate_picked'] : $context_wrs[$srcid]['winrate'],
      'ms' => $heroes_flag ? $context_wrs[$srcid]['matches_picked'] : $context_wrs[$srcid]['matches'],
    ];

    $res .= "<table id=\"$table_id\" class=\"list\">".
        "<thead><tr>".
        ($heroes_flag ? "<th width=\"1%\"></th>" : "").
        "<th>".locale_string($heroes_flag ? 'hero' : 'player')."</th>".
        "<th>".locale_string("matches")."</th>".
        "<th>".locale_string("winrate")."</th></tr></thead>".
        "<tbody><tr>".
        ($heroes_flag ? "<td>".hero_portrait($srcid)."</td>" : "").
        "<td>".($heroes_flag ? hero_name($srcid) : player_name($srcid))."</td>".
        "<td>".$dt['ms']."</td>".
        "<td>".number_format($dt['wr']*100,2)."%</td>".
        "</tr></tbody></table>";


    foreach ($context as $id => $el) {
      if ($el == null) unset($context[$id]);
      if ($el === true) $context[$id] = $report['hph'][$id][$srcid];
    }

    $compound_ranking_sort = function($a, $b) use ($dt) {
      return positions_ranking_sort($a, $b, $dt['ms']);
    };
    uasort($context, $compound_ranking_sort);
    $context_cpy = $context;
  
    $increment = 100 / sizeof($context); $i = 0;
  
    foreach ($context as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $context[$elid]['rank'] = $last_rank;
      } else
        $context[$elid]['rank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $context[$elid]['rank'];

      $context_cpy[$elid]['winrate'] = 1-$context_cpy[$elid]['winrate'];
    }
  
    unset($last);

    uasort($context_cpy, $compound_ranking_sort);
    $i = 0;
  
    foreach ($context_cpy as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $context[$elid]['arank'] = $last_rank;
      } else
        $context[$elid]['arank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $context[$elid]['arank'];
    }
  
    unset($last);

    $isrank = true; $i = 0;
  }

  $res .= "<table id=\"$table_id\" class=\"list sortable\">";
  $res .= "<thead><tr>".
          ($heroes_flag && !$i++ ? "<th width=\"1%\"></th>" : "").
          "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? "hero" : "player")."</th>".
          ($isrank ? "<th>".locale_string("rank")."</th><th>".locale_string("antirank")."</th>" : "").
          "<th class=\"separator\">".locale_string("matches")."</th>".
          "<th>".locale_string("winrate")."</th>".
          "<th>".locale_string("winrate_diff")."</th>".
          "<th class=\"separator\">".locale_string("expectation")."</th>".
          "<th>".locale_string("deviation")."</th>".
          "<th>".locale_string("percentage")."</th>".
          "<th class=\"separator\">".locale_string("lane_rate")."</th>".
          "</tr></thead>";

  if (!$isrank) {
    uasort($pvp_context, function($a, $b) {
      if($a['wr_diff'] == $b['wr_diff']) return 0;
      else return ($a['wr_diff'] < $b['wr_diff']) ? 1 : -1;
    });
  }

  foreach($context as $elid_op => $data) {
    if ($data['matches'] == 0) continue;
    $res .= "<tr>".
            ($heroes_flag ? "<td>".hero_portrait($elid_op)."</td>" : "").
            "<td>".($heroes_flag ? hero_name($elid_op) : player_name($elid_op))."</td>".
            ($isrank ? "<td>".number_format($data['rank'], 2)."</td><td>".number_format($data['arank'], 2)."</td>" : "").
            "<td class=\"separator\">".number_format($data['matches'])."</td>".
            "<td>".number_format($data['winrate']*100,2)."%</td>".
            "<td>".number_format(($data['winrate'] - $dt['wr'])*100,2)."%</td>".
            "<td class=\"separator\">".number_format($data['exp'])."</td>".
            "<td>".number_format($data['matches'] - $data['exp'])."</td>".
            "<td>".number_format(100*($data['matches'] - $data['exp'])/$data['matches'], 2)."%</td>".
            "<td class=\"separator\">".number_format($data['lane_rate']*100, 2)."%</td>".
            "</tr>";
  }

  $res .= "</table>";

  return $res;
}

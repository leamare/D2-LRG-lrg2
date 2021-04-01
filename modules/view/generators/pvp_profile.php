<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_pvp_profile($table_id, &$pvp_context, &$context_wrs, $srcid, $heroes_flag = true) {
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

  if(!empty($context_wrs)) {
    $wr_id = $heroes_flag ? "winrate_picked" : "winrate";
    $dt = [
      'wr' => $heroes_flag ? $context_wrs[$srcid]['winrate_picked'] : $context_wrs[$srcid]['winrate'],
      'ms' => $heroes_flag ? $context_wrs[$srcid]['matches_picked'] : $context_wrs[$srcid]['matches'],
    ];

    $res .= "<table id=\"$table_id\" class=\"list sortable\">".
        "<thead><tr>".
        ($heroes_flag ? "<th width=\"1%\"></th>" : "").
        "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? 'hero' : 'player')."</th>".
        "<th>".locale_string("matches")."</th>".
        "<th>".locale_string("winrate")."</th></tr></thead>".
        "<tbody><tr>".
        ($heroes_flag ? "<td>".hero_portrait($srcid)."</td>" : "").
        "<td>".($heroes_flag ? hero_name($srcid) : player_name($srcid))."</td>".
        "<td>".$dt['ms']."</td>".
        "<td>".number_format($dt['wr']*100,2)."%</td>".
        "</tr></tbody></table>";


    $compound_ranking_sort = function($a, $b) use ($dt) {
      return positions_ranking_sort($a, $b, $dt['ms']);
    };
    uasort($pvp_context, $compound_ranking_sort);
    $pvp_context_cpy = $pvp_context;
  
    $increment = 100 / sizeof($pvp_context); $i = 0;
  
    foreach ($pvp_context as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $pvp_context[$elid]['rank'] = $last_rank;
      } else
        $pvp_context[$elid]['rank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $pvp_context[$elid]['rank'];

      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }
  
    unset($last);

    uasort($pvp_context_cpy, $compound_ranking_sort);
    $i = 0;
  
    foreach ($pvp_context_cpy as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $pvp_context[$elid]['arank'] = $last_rank;
      } else
        $pvp_context[$elid]['arank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $pvp_context[$elid]['arank'];
    }
  
    unset($last);

    $isrank = true; $i = 0;
  }

  $res .= "<table id=\"$table_id\" class=\"list sortable\">";
  $res .= "<thead><tr>".
          ($heroes_flag && !$i++ ? "<th width=\"1%\"></th>" : "").
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
    $res .= "<tr ".(isset($data['matchids']) ?
                      "onclick=\"showModal('".implode($data['matchids'], ", ")."','".locale_string("matches")."')\"" :
                      "").">".
            ($heroes_flag ? "<td>".hero_portrait($elid_op)."</td>" : "").
            "<td>".($heroes_flag ? hero_name($elid_op) : player_name($elid_op))."</td>".
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

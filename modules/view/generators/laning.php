<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

const MIN10_GOLD = 4973;

function rg_generator_laning_profile($table_id, &$context, $id_o, $heroes_flag = true, $variants = false) {
  $res = "";

  if(!sizeof($context)) return "";

  $ids = [ 0 ];
  $matches_med = [];
  if (!empty($id_o)) $ids[] = $id_o;

  if ($id_o) unset($context[$id_o][$id_o]);

  foreach ($ids as $id) {
    // uasort($context[$id], function($a, $b) {
    //   $aa = (float)($a['lane_wr']);
    //   $bb = (float)($b['lane_wr']);
    //   return $bb <=> $aa;
    // });

    $mm = 0;
    foreach ($context[$id] as $k => $h) {
      if ($h === null) {
        unset($context[$id][$k]);
        continue;
      }
      if ($h['matches'] > $mm) $mm = $h['matches'];
      if (!isset($h['matches']) || $h['matches'] == 0) unset($context[$id][$k]);
      if (empty($id_o) || $id == $id_o) $matches_med[] = $h['matches'];
    }

    uasort($context[$id], function($a, $b) {
      return $a['avg_advantage'] <=> $b['avg_advantage'];
    });
    $mk = array_keys($context[$id]);
    $median_adv = $context[$id][ $mk[ floor( count($mk)/2 ) ] ]['avg_advantage'];

    uasort($context[$id], function($a, $b) {
      return $a['avg_disadvantage'] <=> $b['avg_disadvantage'];
    });
    $mk = array_keys($context[$id]);
    $median_disadv = $context[$id][ $mk[ floor( count($mk)/2 ) ] ]['avg_disadvantage'];

    compound_ranking_laning($context[$id], $mm, $median_adv, $median_disadv);
  
    uasort($context[$id], function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context[$id])['wrank'];
    $max = reset($context[$id])['wrank'];
  
    foreach ($context[$id] as $k => $el) {
      $context[$id][$k]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($context[$id][$k]['wrank']);
    }
  }

  $colspan = $heroes_flag ? ($variants ? 3 : 2) : 1;

  if ($id_o) {
    if ($variants) {
      [$id, $var] = explode('-', $id_o);
    }

    $res = "<table id=\"$table_id-reference\" class=\"list wide\"><caption>".locale_string("laning_reference")."</caption>";
    $res .= "<thead><tr class=\"overhead\">".
            "<th width=\"12%\" colspan=\"".$colspan."\"></th>".
            "<th width=\"18%\" colspan=\"3\"></th>".
            "<th class=\"separator\" colspan=\"4\">".locale_string("lane_advantage")."</th>".
            "<th class=\"separator\" colspan=\"2\">".locale_string("lane_won")."</th>".
            "<th class=\"separator\" colspan=\"2\">".locale_string("lane_tie")."</th>".
            "<th class=\"separator\" colspan=\"2\">".locale_string("lane_loss")."</th>".
            "<th class=\"separator\" colspan=\"1\">".locale_string("winrate_diff")."</th>".
          "</tr><tr>".
          ($heroes_flag ? "<th width=\"1%\"></th>" : "").
          ($heroes_flag && $variants ? "<th width=\"1%\">".locale_string("facet")."</th>" : "").
          "<th>".locale_string("hero")."</th>".
          "<th>".locale_string("matches")."</th>".
          "<th>".locale_string("lane_wr")."</th>".
          "<th>".locale_string("rank")."</th>".
          "<th class=\"separator\">".locale_string("lane_win")."</th>".
          "<th>".locale_string("lane_loss")."</th>".
          "<th>".locale_string("lane_avg_gold_diff")."</th>".
          "<th>".locale_string("trends_diff")."</th>".

          "<th class=\"separator\">".locale_string("ratio_freq")."</th>".
          "<th>".locale_string("lane_game_won")."</th>".
          "<th class=\"separator\">".locale_string("ratio_freq")."</th>".
          "<th>".locale_string("lane_game_won")."</th>".
          "<th class=\"separator\">".locale_string("ratio_freq")."</th>".
          "<th>".locale_string("lane_game_won")."</th>".

          "<th class=\"separator\">".locale_string("laning_loss_to_win")."</th>".
        "</tr></thead>";

    $data = $context[0][$id_o];
    $data['matches'] = $data['matches'] ?? 0;
    $wr_diff = $data['matches'] ? (
      ( $data['lanes_won'] ? $data['won_from_won']/$data['lanes_won'] : ( $data['lanes_tied'] ? $data['won_from_tie']/$data['lanes_tied'] : 0 ) ) - 
      ( $data['lanes_lost'] ? $data['won_from_behind']/$data['lanes_lost'] : ( $data['lanes_tied'] ? $data['won_from_tie']/$data['lanes_tied'] : 0 ) )
    ) : 0;
    if (!isset($data['avg_gold_diff'])) {
      $data['avg_gold_diff'] = $data['matches'] ? (
        $data['avg_advantage']*$data['lanes_won'] + 
        $data['avg_disadvantage']*$data['lanes_lost'] +
        ($data['avg_advantage']+$data['avg_disadvantage'])*0.5*$data['lanes_tied']
      ) / $data['matches'] : 0;
    }

    $res .= "<tr>".
      ($heroes_flag ? "<td>".hero_portrait($id_o)."</td>" : '').
      ($heroes_flag && $variants ? "<td width=\"1%\">".facet_micro_element($id, $var)."</td>" : "").
      "<td>".($heroes_flag ? hero_link($id_o) : player_link($id_o)).($variants ? ' '.locale_string("facet_short").$var : '')."</td>".
      "<td>".($data['matches'] ? $data['matches'] : '-')."</td>".
      "<td>".($data['matches'] ? number_format($data['lane_wr']*100, 2).'%' : '-')."</td>".
      "<td>".($data['matches'] ? number_format($data['rank'], 1) : '0')."</td>".
      "<td class=\"separator\">".($data['matches'] ? number_format($data['avg_advantage']*MIN10_GOLD, 2) : '-')."</td>".
      "<td>".($data['matches'] ? number_format($data['avg_disadvantage']*MIN10_GOLD, 2) : '-')."</td>".
      "<td>".($data['matches'] ? number_format($data['avg_gold_diff']*MIN10_GOLD, 2) : '-')."</td>".
      "<td>".($data['matches'] ? number_format(($data['avg_advantage']-$data['avg_disadvantage'])*MIN10_GOLD, 2) : '-')."</td>".

      "<td class=\"separator\">".($data['matches'] ? number_format($data['lanes_won']*100/$data['matches'], 2).'%' : '-')."</td>".
      "<td>".($data['matches'] ? ($data['lanes_won'] ? number_format($data['won_from_won']*100/$data['lanes_won'], 2) : '0').'%' : '-')."</td>".

      "<td class=\"separator\">".($data['matches'] ? number_format($data['lanes_tied']*100/$data['matches'], 2).'%' : '-')."</td>".
      "<td>".($data['matches'] ? ($data['lanes_tied'] ? number_format($data['won_from_tie']*100/$data['lanes_tied'], 2) : '0').'%' : '-')."</td>".

      "<td class=\"separator\">".($data['matches'] ? number_format($data['lanes_lost']*100/$data['matches'], 2).'%' : '-')."</td>".
      "<td>".($data['matches'] ? ($data['lanes_lost'] ? number_format($data['won_from_behind']*100/$data['lanes_lost'], 2) : '0').'%' : '-')."</td>".

      "<td class=\"separator\">".($data['matches'] ? number_format($wr_diff*100, 2).'%' : '-')."</td>".
    "</tr>";
    

    $res .= "</table>";
  }

  sort($matches_med);

  $res .= "<div class=\"content-text\"><h1>".locale_string($id ? "laning_opponents" : "laning_total")."</h1></div>";

  $res .= filter_toggles_component($table_id, [
    'match' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_matches'
    ],
    'lane_wr' => [
      'value' => 49,
      'label' => 'data_filter_lane_wr'
    ],
  ], $table_id, 'wide');

  $res .= table_columns_toggle($table_id, [
    'total', 'lane_advantage', 'lane_won', 'lane_tie', 'lane_loss', 'winrate_diff',
  ], true);

  $res .= search_filter_component($table_id, true);
  $res .= "<table id=\"$table_id\" class=\"list wide sortable\">";
  $res .= "<thead><tr class=\"overhead\">".
            "<th width=\"12%\" colspan=\"".$colspan."\" data-col-group=\"_index\"></th>".
            "<th class=\"separator\" width=\"18%\" colspan=\"3\" data-col-group=\"total\">".locale_string("total")."</th>".
            "<th class=\"separator\" colspan=\"4\" data-sorter=\"digit\" data-col-group=\"lane_advantage\">".locale_string("lane_advantage")."</th>".
            "<th class=\"separator\" colspan=\"2\" data-sorter=\"digit\" data-col-group=\"lane_won\">".locale_string("lane_won")."</th>".
            "<th class=\"separator\" colspan=\"2\" data-sorter=\"digit\" data-col-group=\"lane_tie\">".locale_string("lane_tie")."</th>".
            "<th class=\"separator\" colspan=\"2\" data-sorter=\"digit\" data-col-group=\"lane_loss\">".locale_string("lane_loss")."</th>".
            "<th class=\"separator\" colspan=\"1\" data-sorter=\"digit\" data-col-group=\"winrate_diff\">".locale_string("winrate_diff")."</th>".
          "</tr><tr>".
          ($heroes_flag ? "<th width=\"1%\" data-col-group=\"_index\"></th>" : "").
          ($heroes_flag && $variants ? "<th width=\"1%\" data-col-group=\"_index\">".locale_string("facet")."</th>" : "").
          "<th data-sortInitialOrder=\"asc\" data-sorter=\"text\" data-col-group=\"_index\">".locale_string($id ? "opponent" : "hero")."</th>".
          "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"total\">".locale_string("matches")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("lane_wr")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("rank")."</th>".
          "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"lane_advantage\">".locale_string("lane_win")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"lane_advantage\">".locale_string("lane_loss")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"lane_advantage\">".locale_string("lane_avg_gold_diff")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"lane_advantage\">".locale_string("trends_diff")."</th>".

          "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"lane_won\">".locale_string("ratio_freq")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"lane_won\">".locale_string("lane_game_won")."</th>".
          "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"lane_tie\">".locale_string("ratio_freq")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"lane_tie\">".locale_string("lane_game_won")."</th>".
          "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"lane_loss\">".locale_string("ratio_freq")."</th>".
          "<th data-sorter=\"digit\" data-col-group=\"lane_loss\">".locale_string("lane_game_won")."</th>".
          "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"winrate_diff\">".locale_string("laning_loss_to_win")."</th>".
          // "<th data-sorter=\"digit\">".locale_string("gradient")."</th>".
        "</tr></thead>";

  foreach($context[$id_o] as $elid => $data) {
    if ($variants) {
      [$elid, $var] = explode('-', $elid);
    }

    $data['matches'] = $data['matches'] ?? 0;
    $wr_diff = $data['matches'] ? (
        ( $data['lanes_won'] ? $data['won_from_won']/$data['lanes_won'] : ( $data['lanes_tied'] ? $data['won_from_tie']/$data['lanes_tied'] : 0 ) ) - 
        ( $data['lanes_lost'] ? $data['won_from_behind']/$data['lanes_lost'] : ( $data['lanes_tied'] ? $data['won_from_tie']/$data['lanes_tied'] : 0 ) )
      ) : 0;
    if (!isset($data['avg_gold_diff'])) {
      $data['avg_gold_diff'] = $data['matches'] ? (
        $data['avg_advantage']*$data['lanes_won'] + 
        $data['avg_disadvantage']*$data['lanes_lost'] +
        ($data['avg_advantage']+$data['avg_disadvantage'])*0.5*$data['lanes_tied']
      ) / $data['matches'] : 0;
    }

    $res .= "<tr ".
      "data-value-match=\"".$data['matches']."\" ".
      "data-value-lane_wr=\"".($data['matches'] ? number_format($data['lane_wr']*100, 2) : 0)."\" ".
      " class=\"row\">".
      ($heroes_flag ? "<td data-col-group=\"_index\">".hero_portrait($elid)."</td>" : '').
      ($heroes_flag && $variants ? "<td width=\"1%\" data-col-group=\"_index\">".facet_micro_element($elid, $var)."</td>" : "").
      "<td data-col-group=\"_index\">".($heroes_flag ? hero_link($elid) : player_link($elid)).($variants ? ' '.locale_string("facet_short").$var : '')."</td>".
      "<td class=\"separator\" data-col-group=\"total\">".($data['matches'] ? $data['matches'] : '-')."</td>".
      "<td data-col-group=\"total\">".($data['matches'] ? number_format($data['lane_wr']*100, 2).'%' : '-')."</td>".
      "<td data-col-group=\"total\">".($data['matches'] ? number_format($data['rank'], 1) : '0')."</td>".
      "<td class=\"separator\" data-col-group=\"lane_advantage\">".($data['matches'] ? number_format($data['avg_advantage']*MIN10_GOLD, 2) : '-')."</td>".
      "<td data-col-group=\"lane_advantage\">".($data['matches'] ? number_format($data['avg_disadvantage']*MIN10_GOLD, 2) : '-')."</td>".
      "<td data-col-group=\"lane_advantage\">".($data['matches'] ? number_format($data['avg_gold_diff']*MIN10_GOLD, 2) : '-')."</td>".
      "<td data-col-group=\"lane_advantage\">".($data['matches'] ? number_format(($data['avg_advantage']-$data['avg_disadvantage'])*MIN10_GOLD, 2) : '-')."</td>".

      "<td class=\"separator\" data-col-group=\"lane_won\">".($data['matches'] ? number_format($data['lanes_won']*100/$data['matches'], 2).'%' : '-')."</td>".
      "<td data-col-group=\"lane_won\">".
        ($data['matches'] ? ($data['lanes_won'] ? number_format($data['won_from_won']*100/$data['lanes_won'], 2) : '0').'%' : '-')."</td>".

      "<td class=\"separator\" data-col-group=\"lane_tie\">".($data['matches'] ? number_format($data['lanes_tied']*100/$data['matches'], 2).'%' : '-')."</td>".
      "<td data-col-group=\"lane_tie\">".
        ($data['matches'] ? ($data['lanes_tied'] ? number_format($data['won_from_tie']*100/$data['lanes_tied'], 2) : '0').'%' : '-')."</td>".

      "<td class=\"separator\" data-col-group=\"lane_loss\">".($data['matches'] ? number_format($data['lanes_lost']*100/$data['matches'], 2).'%' : '-')."</td>".
      "<td data-col-group=\"lane_loss\">".
        ($data['matches'] ? ($data['lanes_lost'] ? number_format($data['won_from_behind']*100/$data['lanes_lost'], 2) : '0').'%' : '-')."</td>".

      "<td class=\"separator\" data-col-group=\"winrate_diff\">".($data['matches'] ? number_format($wr_diff*100, 2).'%' : '-')."</td>".
      // "<td>".($data['matches'] ? '%' : '-')."</td>".
    "</tr>";
  }

  $res .= "</table>";

  return $res;
}

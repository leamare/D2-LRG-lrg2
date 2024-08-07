<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_positions_overview($table_id, &$context, $hero_flag = true) {
  if(!sizeof($context)) return "";

  if (is_wrapped($context)) $context = unwrap_data($context);

  $position_overview_template = array("total" => 0);
  for ($i=1; $i>=0 && !isset($keys); $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      //if (!$i) { $j = 0; }
      if(isset($context[$i][$j][0])) {
        $keys = array_keys($context[$i][$j][0]);
        break;
      }
      //if (!$i) { break; }
    }
  }

  for ($i=1; $i>=0; $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      //if (!$i) { $j = 0; }
      if(isset($context[$i][$j]) && sizeof($context[$i][$j]))
        $position_overview_template["$i.$j"] = array("matches" => 0, "wr" => 0);
      //if (!$i) { break; }
    }
  }

  $overview = [];
  $ranks = [];

  $filters = [
    'overview_total' => [
      'value' => null,
      'label' => 'data_filter_positions_overview_total_mp'
    ]
  ];

  for ($i=1; $i>=0; $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      //if (!$i) { $j = 0; }

      if (!isset($context[$i][$j]) || !sizeof($context[$i][$j])) {
        //if (!$i) break;
        continue;
      }

      $matches = [];

      $ranks[$i][$j] = [];
      $context_copy = $context[$i][$j];
      $total_matches = 0;
      foreach ($context_copy as $c) {
        if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
        $matches[] = $c['matches_s'];
      }

      sort($matches);
      $filters["position_$i.$j"] = [
        'value' => $matches[ round(count($matches)/2) ],
        'label' => "data_filter_positions_{$i}.{$j}_mp"
      ];

      positions_ranking($context_copy, $total_matches);
    
      uasort($context_copy, function($a, $b) {
        return $b['wrank'] <=> $a['wrank'];
      });
    
      $min = end($context_copy)['wrank'];
      $max = reset($context_copy)['wrank'];
    
      foreach ($context_copy as $id => $el) {
        $ranks[$i][$j][$id] = 100 * ($el['wrank']-$min) / ($max-$min);
      }

      unset($context_copy);

      // antiranks here

      foreach($context[$i][$j] as $id => $el) {
        if (!isset($overview[ $id ])) $overview[ $id ] = $position_overview_template;

        $overview[ $id ]["$i.$j"]['matches'] = $el['matches_s'];
        $overview[ $id ]["$i.$j"]['wr'] = $el['winrate_s'];
        $overview[ $id ]["total"] += $el['matches_s'];
        $overview[ $id ]["$i.$j"]['rank'] = $ranks[$i][$j][$id]; 
        // $overview[ $id ]["$i.$j"]['antirank'] = $antiranks[$i][$j][$id]; 
      }

      //if (!$i) { break; }
    }
  }
  uasort($overview, function($a, $b) {
    if($a['total'] == $b['total']) return 0;
    else return ($a['total'] < $b['total']) ? 1 : -1;
  });

  $filters['overview_total']['value'] = array_values($overview)[ floor(count($overview)*0.45) ]['total'];

  $colgroups = [];
  foreach ($position_overview_template as $k => $v) {
    if ($k == "total") continue;
    $colgroups[] = "position_".$k;
  }

  $res = filter_toggles_component($table_id, $filters, $table_id, 'wide');

  $res .= table_columns_toggle($table_id, $colgroups, true);

  $res .= search_filter_component($table_id, true);

  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead><tr class=\"overhead\"><th width=\"20%\" colspan=\"".(2+$hero_flag)."\"></th>";

  $heroline = "<tr>".
                ($hero_flag ?
                  "<th class=\"sorter-no-parser\" width=\"1%\" data-col-group=\"_index\"></th>".
                  "<th data-sortInitialOrder=\"asc\" data-sorter=\"text\" data-col-group=\"_index\">".locale_string("hero")."</th>" :
                  "<th data-sortInitialOrder=\"asc\" data-sorter=\"text\" data-col-group=\"_index\">".locale_string("player")."</th>"
                ).
                "<th data-col-group=\"_index\">".locale_string("matches_s")."</th>";
  $i = 2;
  foreach($position_overview_template as $k => $v) {
    if ($k == "total") continue;

    $colgr = "position_".str_replace('.', '-', $k);

    $res .= "<th colspan=\"4\" class=\"separator\" data-sorter=\"digit\" data-col-group=\"$colgr\">".locale_string("position_$k")."</th>";
    $heroline .= "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"$colgr\">".locale_string("matches_s")."</th>".
                  "<th data-sorter=\"digit\" data-col-group=\"$colgr\">".locale_string("rank")."</th>".
                  // "<th data-sorter=\"digit\">".locale_string("antirank")."</th>".
                  "<th data-sorter=\"digit\" data-col-group=\"$colgr\">".locale_string("ratio_pos")."</th>".
                  "<th data-sorter=\"digit\" data-col-group=\"$colgr\">".locale_string("winrate_s")."</th>";
  }
  $res .= "</tr>".$heroline."</tr></thead>";

  foreach ($overview as $elid => $el) {
    $params = [
      "data-value-overview_total=\"".$el['total']."\""
    ];

    $elres = "";

    foreach($el as $k => $v) {
      if (!is_array($v)) continue;

      $params[] = "data-value-position_$k=\"".$v['matches']."\"";

      $colgr = "position_".str_replace('.', '-', $k);

      if(!$v['matches']) {
        $elres .= "<td class=\"separator\" data-col-group=\"$colgr\">-</td>".
                      "<td data-col-group=\"$colgr\">-</td>".
                      "<td data-col-group=\"$colgr\">-</td>".
                      "<td data-col-group=\"$colgr\">-</th>";
      } else {
        $elres .= "<td class=\"separator\" data-col-group=\"$colgr\">".$v['matches']."</td>".
                    "<td data-col-group=\"$colgr\">".number_format($v['rank'],1)."</td>".
                    // "<td>".number_format($v['antirank'],1)."</td>".
                    "<td data-col-group=\"$colgr\">".number_format($v['matches']*100/$el['total'],1)."%</td>".
                    "<td data-col-group=\"$colgr\">".number_format($v['wr']*100,1)."%</td>";
      }
    }

    $res .= "<tr ".implode(" ", $params)."><td data-col-group=\"_index\">".
        ($hero_flag ? hero_portrait($elid)."</td><td data-col-group=\"_index\">".hero_link($elid) : player_link($elid)).
        "</td><td data-col-group=\"total\">".$el['total']."</td>".$elres."</tr>";
  }
  $res .= "</table>";

  return $res;
}


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

  for ($i=1; $i>=0; $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      //if (!$i) { $j = 0; }

      if (!isset($context[$i][$j]) || !sizeof($context[$i][$j])) {
        //if (!$i) break;
        continue;
      }

      $ranks[$i][$j] = [];
      $context_copy = $context[$i][$j];
      $total_matches = 0;
      foreach ($context_copy as $c) {
        if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
      }
  
      uasort($context_copy, function($a, $b) use ($total_matches) {
        return positions_ranking_sort($a, $b, $total_matches);
      });
  
      $increment = 100 / sizeof($context_copy); $k = 0;
  
      foreach ($context_copy as $id => $el) {
        if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
          $k++;
          $ranks[$i][$j][$id] = $last_rank;
        } else
          $ranks[$i][$j][$id] = 100 - $increment*$k++;
        $last = $el;
        $last_rank = $ranks[$i][$j][$id];
      }
      unset($last);
      unset($context_copy);

      // $antiranks = [];
      // $context_copy = $context[$i][$j];
      // foreach($context_copy as &$el)  {
      //   $el['winrate_s'] = 1-$el['winrate_s'];
      // }    
  
      // uasort($context_copy, function($a, $b) use ($total_matches) {
      //   return positions_ranking_sort($a, $b, $total_matches);
      // });
  
      // $increment = 100 / sizeof($context_copy); $k = 0;
  
      // foreach ($context_copy as $id => $el) {
      //   if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
      //     $k++;
      //     $antiranks[$i][$j][$id] = $last_rank;
      //   } else
      //     $antiranks[$i][$j][$id] = 100 - $increment*$k++;
      //   $last = $el;
      //   $last_rank = $antiranks[$i][$j][$id];
      // }
      // unset($last);
      // unset($context_copy);

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

  $res = "<input name=\"filter\" class=\"search-filter wide\" data-table-filter-id=\"$table_id\" placeholder=\"".locale_string('filter_placeholder')."\" />";

  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead><tr class=\"overhead\"><th width=\"20%\" colspan=\"".(2+$hero_flag)."\"></th>";

  $heroline = "<tr>".
                ($hero_flag ?
                  "<th class=\"sorter-no-parser\" width=\"1%\"></th>".
                  "<th data-sortInitialOrder=\"asc\" data-sorter=\"text\">".locale_string("hero")."</th>" :
                  "<th data-sortInitialOrder=\"asc\" data-sorter=\"text\">".locale_string("player")."</th>"
                ).
                "<th>".locale_string("matches_s")."</th>";
  $i = 2;
  foreach($position_overview_template as $k => $v) {
    if ($k == "total") continue;

    $res .= "<th colspan=\"4\" class=\"separator\" data-sorter=\"digit\">".locale_string("position_$k")."</th>";
    $heroline .= "<th class=\"separator\" data-sorter=\"digit\">".locale_string("matches_s")."</th>".
                  "<th data-sorter=\"digit\">".locale_string("rank")."</th>".
                  // "<th data-sorter=\"digit\">".locale_string("antirank")."</th>".
                  "<th data-sorter=\"digit\">".locale_string("ratio_pos")."</th>".
                  "<th data-sorter=\"digit\">".locale_string("winrate_s")."</th>";
  }
  $res .= "</tr>".$heroline."</tr></thead>";

  foreach ($overview as $elid => $el) {
    $res .= "<tr><td>".
        ($hero_flag ? hero_portrait($elid)."</td><td>".hero_link($elid) : player_link($elid)).
        "</td><td>".$el['total']."</td>";
    foreach($el as $v) {
      if (!is_array($v)) continue;

      if(!$v['matches']) {
        $res .= "<td class=\"separator\">-</td>".
                      "<td>-</td>".
                      "<td>-</td>".
                      "<td>-</th>";
      } else {
        $res .= "<td class=\"separator\">".$v['matches']."</td>".
                    "<td>".number_format($v['rank'],1)."</td>".
                    // "<td>".number_format($v['antirank'],1)."</td>".
                    "<td>".number_format($v['matches']*100/$el['total'],1)."%</td>".
                    "<td>".number_format($v['wr']*100,1)."%</td>";
      }
    }
    $res .= "</tr>";
  }
  $res .= "</table>";

  return $res;
}


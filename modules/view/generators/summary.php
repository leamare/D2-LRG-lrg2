<?php
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/convert_time.php");

function rg_generator_summary($table_id, &$context, $hero_flag = true, $rank = false) {
  if(!sizeof($context)) return "";

  if (is_wrapped($context)) $context = unwrap_data($context);

  global $report;

  $keys = array_keys( array_values($context)[0] );

  if ($rank) {
    $ranks = [];
    $context_copy = $context;
    $total_matches = 0;
    foreach ($context as $c) {
      if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
    }

    uasort($context_copy, function($a, $b) use ($total_matches) {
      return positions_ranking_sort($a, $b, $total_matches);
    });

    $increment = 100 / sizeof($context_copy); $i = 0; $last_rank = 0;

    foreach ($context_copy as $id => $el) {
      if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
        $i++;
        $ranks[$id] = $last_rank;
      } else
        $ranks[$id] = 100 - $increment*$i++;
      $last = $el;
      $last_rank = $ranks[$id];
    }
    unset($last);

    $aranks = [];
    $context_copy = $context;
    foreach ($context_copy as &$data) {
      $data['winrate_s'] = 1-$data['winrate_s'];
    }

    uasort($context_copy, function($a, $b) use ($total_matches) {
      return positions_ranking_sort($a, $b, $total_matches);
    });

    $i = 0;

    foreach ($context_copy as $id => $el) {
      if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
        $i++;
        $aranks[$id] = $last_rank;
      } else
        $aranks[$id] = 100 - $increment*$i++;
      $last = $el;
      $last_rank = $aranks[$id];
    }
    unset($last);

    unset($context_copy);
  }

  if (in_array("hero_damage_per_min_s", $keys) && in_array("gpm", $keys) && !in_array("damage_to_gold_per_min_s", $keys)) {
    foreach ($context as $id => $el) {
      $context[$id] = array_insert_before($context[$id], "gpm", [
        "damage_to_gold_per_min_s" => ($context[$id]['hero_damage_per_min_s'] ?? 0)/($context[$id]['gpm'] ?? 1),
      ]);
    }

    $keys = array_insert_before($keys, array_search("gpm", $keys), [ 'damage_to_gold_per_min_s' ]);
  }

  $res = search_filter_component($table_id, true);

  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead><tr>".
          ($hero_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
          "<th data-sortInitialOrder=\"asc\">".locale_string($hero_flag ? "hero" : "player")."</th>";

  for($k=0, $end=sizeof($keys); $k < $end; $k++) {
    if ($k == 2) {
      if ($rank) {
        $res .= "<th>".locale_string("rank")."</th>";
        $res .= "<th>".locale_string("antirank")."</th>";
      }
      $res .= "<th class=\"separator\">".locale_string($keys[$k])."</th>";
      continue;
    }
    $res .= "<th>".locale_string($keys[$k])."</th>";
  }
  $res .= "</tr></thead><tbody>";

  foreach($context as $id => $el) {
    $res .= "<tr><td>".
              ($hero_flag ? hero_portrait($id)."</td><td>".hero_link($id) : player_link($id, true, true)).
            "</td>".
            "<td>".$el['matches_s']."</td>".
            "<td>".number_format($el['winrate_s']*100,1)."%</td>".
            ($rank ? "<td>".number_format($ranks[$id],2)."</td>"."<td>".number_format($aranks[$id],2)."</td>" : "");

    for($k=2, $end=sizeof($keys); $k < $end; $k++) {
      if ($k == 2) $res .= "<td class=\"separator\">";
      else $res .= "<td>";

      if (strpos($keys[$k], "duration") !== FALSE || strpos($keys[$k], "_len") !== FALSE) {
        $res .= convert_time($el[$keys[$k]]);
      } else if(is_numeric($el[$keys[$k]])) {
        if ($el[$keys[$k]] > 10)
          $res .= number_format($el[$keys[$k]],1);
        else if ($el[$keys[$k]] > 1)
          $res .= number_format($el[$keys[$k]],2);
        else
          $res .= number_format($el[$keys[$k]],3);
      } else {
        $res .= $el[$keys[$k]];
      }
      $res .= "</td>";
    }
    $res .= "</tr>";
  }
  $res .= "</tbody></table>";
  unset($keys);

  return $res;
}

?>

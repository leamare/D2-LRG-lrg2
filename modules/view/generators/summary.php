<?php
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/convert_time.php");
include_once($root."/modules/view/functions/summary_utils.php");

function rg_generator_summary($table_id, &$context, $hero_flag = true, $rank = false) {
  if(!sizeof($context)) return "";

  if (is_wrapped($context)) $context = unwrap_data($context);

  $keys = array_keys( array_values($context)[0] );

  $matches = [];

  $total_matches = 0;
  foreach ($context as $c) {
    if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
    $matches[] = $c['matches_s'];
  } 

  if ($rank) {
    $ranks = [];
    $context_copy = $context;

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

  // COLUMNS GROUPING

  $groups = [];
  foreach ($keys as $key) {
    $group = get_summary_key_primary_group($key);
    if (!isset($groups[ $group ])) $groups[ $group ] = [];
    $groups[ $group ][] = $key;
  }

  if ($rank) {
    $groups[ SUMMARY_GROUPS['rank'] ][] = 'rank';
    $groups[ SUMMARY_GROUPS['antirank'] ][] = 'antirank';
  }

  $index_group = $groups['_index'];
  unset($groups['_index']);

  $priorities = [];
  foreach ($groups as $gr => $cols) {
    $priorities[] = SUMMARY_GROUPS_PRIORITIES[$gr] ?? count($groups);
  }

  // TABLE RENDERING

  sort($matches);
  $res = filter_toggles_component($table_id, [
    'summary_matches' => [
      'value' => $matches[ round(count($matches)/2) ] ?? $matches[0],
      'label' => 'data_filter_summary_matches'
    ]
  ], $table_id, 'wide');

  $res .= table_columns_toggle($table_id, array_keys($groups), true, $priorities);

  $res .= search_filter_component($table_id, true);

  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead><tr class=\"overhead\">".
    // ($hero_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
    "<th colspan=\"".(($hero_flag ? 2 : 1) + count($index_group))."\" data-col-group=\"_index\"></th>".
    implode('', array_map(
      function($a) use (&$groups) {
        return "<th class=\"separator\" colspan=\"".count($groups[$a])."\" data-col-group=\"$a\">".locale_string($a)."</th>";
      }, array_keys($groups)
    ))."</tr>".
    "<tr>".
    ($hero_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
    "<th data-col-group=\"_index\">".locale_string($hero_flag ? "hero" : "player")."</th>".
    "<th data-col-group=\"_index\">".implode(
      "</th><th data-col-group=\"_index\">", array_map(function($el) {
        return locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el);
      }, $index_group)
    )."</th>".
    implode('', array_map(
      function($a, $k) {
        return "<th class=\"separator\" data-col-group=\"$k\">".implode(
          "</th><th data-col-group=\"$k\">", array_map(function($el) {
            return locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el);
          }, $a)
        )."</th>";
      }, $groups, array_keys($groups)
    )).
    "</tr>".
  "</thead><tbody>";

  foreach($context as $id => $el) {
    if ($rank) {
      $el['rank'] = number_format($ranks[$id],2);
      $el['antirank'] = number_format($aranks[$id],2);
    }

    $res .= "<tr data-value-summary_matches=\"".$el['matches_s']."\"><td data-col-group=\"_index\">".
      ($hero_flag ? hero_portrait($id)."</td><td>".hero_link($id) : player_link($id, true, true)).
    "</td>";

    foreach ($index_group as $key) {
      $res .= "<td data-col-group=\"_index\">".summary_prepare_value($key, $el[$key] ?? '-')."</td>";
    } 

    $res .= implode('', array_map(
      function($a) use (&$groups, &$el) {
        return "<td class=\"separator\" data-col-group=\"$a\">".implode(
          "</td><td data-col-group=\"$a\">", array_map(function($key) use (&$el) {
            return summary_prepare_value($key, $el[$key]);
          }, $groups[$a])
        )."</td>";
      }, array_keys($groups)
    ));

    $res .= "</tr>";
  }
  $res .= "</tbody></table>";
  unset($keys);

  return $res;
}


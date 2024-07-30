<?php
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/convert_time.php");
include_once($root."/modules/view/functions/summary_utils.php");

function rg_generator_summary($table_id, &$context, $hero_flag = true, $rank = false, $variants = false) {
  if(!sizeof($context)) return "";

  if (is_wrapped($context)) $context = unwrap_data($context);

  $keys = array_keys( array_values($context)[0] );

  $matches = [];

  $total_matches = 0;
  foreach ($context as $id => $c) {
    if (empty($c) || !$id) continue;
    if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
    $matches[] = $c['matches_s'];
  } 

  if ($rank) {
    $ranks = [];
    $context_copy = $context;

    positions_ranking($context, $total_matches);

    uasort($context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context)['wrank'];
    $max = reset($context)['wrank'];
  
    foreach ($context as $id => $el) {
      $ranks[$id] = 100 * ($el['wrank']-$min) / ($max-$min);
      $context_copy[$id]['winrate_s'] = 1-($el['winrate'] ?? $el['winrate_s']);
    }

    $aranks = [];
  
    positions_ranking($context_copy, $total_matches);
  
    uasort($context_copy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context_copy)['wrank'];
    $max = reset($context_copy)['wrank'];
  
    foreach ($context_copy as $id => $el) {
      $aranks[$id] = 100 * ($el['wrank']-$min) / ($max-$min);
    }

    unset($context_copy);
  }

  if (in_array("hero_damage_per_min_s", $keys) && in_array("gpm", $keys) && !in_array("damage_to_gold_per_min_s", $keys)) {
    foreach ($context as $id => $el) {
      if (empty($el) || !$id) continue;
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

  $colspan = ($hero_flag ? ($variants ? 3 : 2) : 1) + count($index_group);

  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead><tr class=\"overhead\">".
    // ($hero_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
    "<th colspan=\"".$colspan."\" data-col-group=\"_index\"></th>".
    implode('', array_map(
      function($a) use (&$groups) {
        return "<th class=\"separator\" colspan=\"".count($groups[$a])."\" data-col-group=\"$a\">".locale_string($a)."</th>";
      }, array_keys($groups)
    ))."</tr>".
    "<tr>".
    ($hero_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
    ($hero_flag && $variants ? "<th class=\"sorter-no-parser\" width=\"1%\">".locale_string("facet")."</th>" : "").
    "<th data-col-group=\"_index\">".locale_string($hero_flag ? "hero" : "player")."</th>".
    "<th data-col-group=\"_index\">".implode(
      "</th><th data-col-group=\"_index\">", array_map(function($el) {
        return locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el);
      }, $index_group)
    )."</th>".
    implode('', array_map(
      function($a, $k) {
        return implode(
          "", array_map(function($el, $i) use (&$k) {
            return "<th class=\"".
              (!$i ? "separator " : "").
              (in_array($el, VALUESORT_COLS_KEYS) ? "sorter-valuesort" : "").
              "\" data-col-group=\"$k\">".
              locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el).
            "</th>";
          }, $a, array_keys($a))
        );
      }, $groups, array_keys($groups)
    )).
    "</tr>".
  "</thead><tbody>";

  foreach($context as $id => $el) {
    if (empty($el) || !$id) continue;

    if ($rank) {
      $el['rank'] = number_format($ranks[$id],2);
      $el['antirank'] = number_format($aranks[$id],2);
    }

    if ($variants) {
      [$id, $var] = explode('-', $id);
    }

    $res .= "<tr data-value-summary_matches=\"".$el['matches_s']."\"><td data-col-group=\"_index\">".
      ($hero_flag ?
        hero_portrait($id)."</td>".
        ($variants ? "<td>".facet_micro_element($id, $var)."</td>" : "").
        "<td>".hero_link($id).($variants ? ' '.locale_string("facet_short").$var : "") : 
        player_link($id, true, true)
      ).
    "</td>";

    foreach ($index_group as $key) {
      $res .= "<td data-col-group=\"_index\">".summary_prepare_value($key, $el[$key] ?? '-')."</td>";
    } 

    $res .= implode('', array_map(
      function($a) use (&$groups, &$el) {
        return implode(
          "", array_map(function($key, $i) use (&$el, &$a) {
            return "<td ".
              (!$i ? " class=\"separator\"" : "").
              (in_array($key, VALUESORT_COLS_KEYS) ? " value=\"{$el[$key]}\"" : "").
              " data-col-group=\"$a\">".
              summary_prepare_value($key, $el[$key]).
            "</td>";
          }, $groups[$a], array_keys($groups[$a]))
        );
      }, array_keys($groups)
    ));

    $res .= "</tr>";
  }
  $res .= "</tbody></table>";
  unset($keys);

  return $res;
}


<?php
/** @var string $root */
include_once("$root/modules/view/functions/links.php");
include_once("$root/modules/view/functions/convert_time.php");
include_once("$root/modules/view/functions/hero_name.php");

function teams_summary_top_heroes($tid, $count = 4) {
  global $report;

  if (empty($report['teams'][$tid]['pickban'])) return "";

  $heroes = $report['teams'][$tid]['pickban'];
  uasort($heroes, function($a, $b) {
    return ($b['matches_picked'] ?? 0) <=> ($a['matches_picked'] ?? 0);
  });

  $out = "";
  $i = 0;
  foreach ($heroes as $hid => $stats) {
    if ($i++ >= $count) break;
    if (empty($stats['matches_picked'])) break;

    $wr = isset($stats['wins_picked'])
      ? $stats['wins_picked'] / $stats['matches_picked']
      : ($stats['winrate_picked'] ?? 0);

    $out .= "<a title=\"".addcslashes(
        hero_name($hid)." - ".$stats['matches_picked']." ".locale_string('picks').
        " - ".number_format($wr*100, 2)."% ".locale_string('winrate'), "'\"").
      "\">".hero_icon($hid)."</a>";
  }

  return $out === "" ? "" : "<div class=\"team-top-heroes\">".$out."</div>";
}

const TEAM_SUMMARY_SHORT_LIST = [
  "kills",
  "deaths",
  "assists",
  "gpm",
  "xpm",
  "hero_pool",
  "avg_match_len"
];

function rg_view_generator_teams_summary($context = null, $short_flag = false) {
  global $report;

  if($context == null) $context = array_keys($report['teams']);
  else $context = array_keys($context);

  if(!sizeof($context)) return "";

  if ($short_flag)
    $res = "";
  else
    $res  = "<div class=\"content-text\">".locale_string("desc_teams_summary")."</div>";

  uasort($context, function($a, $b) use ($report) {
    $ta = $report['teams'][$a]['matches_total'] ?? 0;
    $tb = $report['teams'][$b]['matches_total'] ?? 0;
    if($ta == $tb) return 0;
    else return ($ta < $tb) ? 1 : -1;
  });

  foreach ($report['teams'] as $vals) {
    if (!isset($vals['averages'])) continue;

    $keys = array_keys($vals['averages']);
    break;
  }
  if ($short_flag) {
    $keys = array_intersect($keys ?? [], TEAM_SUMMARY_SHORT_LIST);
  }

  $keys = $keys ?? [];
  array_unshift($keys, 'matches');
  array_unshift($keys, 'winrate');

  // COLUMNS GROUPING

  $groups = [ '_index' => [], ];
  foreach ($keys as $key) {
    $group = get_summary_key_primary_group($key);
    if (!isset($groups[ $group ])) $groups[ $group ] = [];
    $groups[ $group ][] = $key;
  }

  $index_group = $groups['_index'];
  unset($groups['_index']);

  $priorities = [];
  foreach ($groups as $gr => $cols) {
    $priorities[] = SUMMARY_GROUPS_PRIORITIES[$gr] ?? count($groups);
  }

  // TABLE RENDERING

  $has_top_heroes = false;
  foreach ($context as $team_id) {
    if (!empty($report['teams'][$team_id]['pickban'])) { $has_top_heroes = true; break; }
  }

  if (!$short_flag) {
    $toggle_groups = array_keys($groups);
    $toggle_priorities = $priorities;
    if ($has_top_heroes) {
      $toggle_groups[] = 'heroes';
      $toggle_priorities[] = SUMMARY_GROUPS_PRIORITIES['heroes'] ?? count($toggle_groups);
    }
    $res .= table_columns_toggle('teams-summary', $toggle_groups, true, $toggle_priorities);

    $res .= search_filter_component("teams-summary", true);
  }

  $res .= "<table id=\"teams-summary\" class=\"list ".($short_flag ? "" : "wide")." sortable\">";

  $table_id = "teams-summary";
  $i = 0;

  $res .= "<thead><tr class=\"overhead\">".
    "<th colspan=\"".(2 + count($index_group))."\" data-col-group=\"_index\"></th>".
    implode('', array_map(
      function($a) use (&$groups) {
        return "<th class=\"separator\" colspan=\"".count($groups[$a])."\" data-col-group=\"$a\">".locale_string($a)."</th>";
      }, array_keys($groups)
    )).
    ($has_top_heroes ? "<th class=\"separator\" data-col-group=\"heroes\">".locale_string("heroes")."</th>" : "").
    "</tr>".
    "<tr>".
      "<th data-col-group=\"_index\"></th>".
      "<th data-sortInitialOrder=\"asc\" data-col-group=\"_index\">".locale_string("team_name")."</th>".
      "<th data-col-group=\"_index\">".implode(
        "</th><th data-col-group=\"_index\">", array_map(function($el) {
          return locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el);
        }, $index_group)
      )."</th>".
      implode('', array_map(
        function($a, $k) {
          return implode(
            "", array_map(function($el, $i) use ($k) {
              return "<th class=\"".
                (!$i ? "separator " : "").
                (in_array($el, VALUESORT_COLS_KEYS) ? "sorter-valuesort " : "").
                "\" data-col-group=\"$k\">".
                locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el).
              "</th>";
            }, $a, array_keys($a))
          );
        }, $groups, array_keys($groups)
      )).
      ($has_top_heroes ? "<th class=\"separator sorter-no-parser\" data-col-group=\"heroes\">".locale_string("most_played")."</th>" : "").
    "</tr>".
  "</thead><tbody>";

  foreach($context as $team_id) {
    if (isset($report['teams_interest']) && !in_array($team_id, $report['teams_interest'])) continue;

    $el = $report['teams'][$team_id]['averages'] ?? [];
    $el['matches'] = $report['teams'][$team_id]['matches_total'] ?? 0;
    $el['winrate'] = ($report['teams'][$team_id]['matches_total'] ?? 0) ? 
      ($report['teams'][$team_id]['wins'] ?? 0)/($report['teams'][$team_id]['matches_total']) : 0;
    // Fallback only when the analyzer never produced a median (older reports).
    if (!isset($el['matches_median_duration'])) $el['matches_median_duration'] = $el['avg_match_len'] ?? 0;

    $res .= "<tr>".
      "<td data-col-group=\"_index\">".team_logo($team_id)."</td>".
      "<td data-col-group=\"_index\">".team_link($team_id)."</td>";

    foreach ($index_group as $key) {
      $res .= "<td data-col-group=\"_index\">".summary_prepare_value($key, $el[$key] ?? '-')."</td>";
    }

    $res .= implode('', array_map(
      function($a) use (&$groups, &$el) {
        return implode(
          "", array_map(function($key, $i) use (&$el, &$a) {
            return "<td ".
              (!$i ? "class=\"separator\"" : "").
              (in_array($key, VALUESORT_COLS_KEYS) ? " value=\"{$el[$key]}\"" : "").
              " data-col-group=\"$a\">".
              summary_prepare_value($key, $el[$key]).
            "</td>";
          }, $groups[$a], array_keys($groups[$a]))
        );
      }, array_keys($groups)
    ));

    if ($has_top_heroes) {
      $res .= "<td class=\"separator\" data-col-group=\"heroes\">".teams_summary_top_heroes($team_id)."</td>";
    }

    $res .= "</tr>";
  }
  $res .= "</tbody></table>";

  return $res;
}


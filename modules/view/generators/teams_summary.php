<?php
include_once("$root/modules/view/functions/links.php");
include_once("$root/modules/view/functions/convert_time.php");

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
    if($report['teams'][$a]['matches_total'] == $report['teams'][$b]['matches_total']) return 0;
    else return ($report['teams'][$a]['matches_total'] < $report['teams'][$b]['matches_total']) ? 1 : -1;
  });

  foreach ($report['teams'] as $vals) {
    if (!isset($vals['averages'])) continue;

    $keys = array_keys($vals['averages']);
    break;
  }
  if ($short_flag) {
    $keys = array_intersect($keys, TEAM_SUMMARY_SHORT_LIST);
  }

  $keys[] = 'matches';
  $keys[] = 'winrate';

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

  if (!$short_flag) {
    $res .= table_columns_toggle('teams-summary', array_keys($groups), true, $priorities);

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
    ))."</tr>".
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
          return "<th class=\"separator\" data-col-group=\"$k\">".implode(
            "</th><th data-col-group=\"$k\">", array_map(function($el) {
              return locale_string(SUMMARY_KEYS_REPLACEMENTS[$el] ?? $el);
            }, $a)
          )."</th>";
        }, $groups, array_keys($groups)
      )).
    "</tr>".
  "</thead><tbody>";

  foreach($context as $team_id) {
    if (isset($report['teams_interest']) && !in_array($team_id, $report['teams_interest'])) continue;

    $el = $report['teams'][$team_id]['averages'];
    $el['matches'] = $report['teams'][$team_id]['matches_total'];
    $el['winrate'] = $report['teams'][$team_id]['matches_total'] ? 
      $report['teams'][$team_id]['wins']*100/$report['teams'][$team_id]['matches_total'] : 0;

    $res .= "<tr>".
      "<td data-col-group=\"_index\">".team_logo($team_id)."</td>".
      "<td data-col-group=\"_index\">".team_link($team_id)."</td>";

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

  return $res;
}

?>

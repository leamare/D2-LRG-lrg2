<?php 

$modules['heroes']['wrplayers'] = '';

function rg_view_generate_heroes_wrplayers() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  $res = "";
  

  if (is_wrapped($report['hero_winrate_spammers'])) {
    $report['hero_winrate_spammers'] = unwrap_data($report['hero_winrate_spammers']);
  }

  $rows = "";
  $matches_med = [];
  $players_med = [];

  foreach ($report['hero_winrate_spammers'] as $hid => $data) {
    $matches = $report['pickban'][$hid]['matches_picked'];
    $players = ($data['players_1only']+$data['players_1plus']);

    $matches_med[] = $matches;
    $players_med[] = $players;

    $rows .= "<tr ".
        "data-value-match=\"".$matches."\" ".
        "data-value-players=\"".$players."\" ".
      "><td data-col-group=\"_index\">".hero_portrait($hid)."</td><td data-col-group=\"_index\">".hero_link($hid)."</td>".
      "<td class=\"separator\" data-col-group=\"players\">".$data['players_1only']."</td>".
      "<td data-col-group=\"players\">".$data['players_1plus']."</td>".
      "<td data-col-group=\"players\">".$players."</td>".
      "<td class=\"separator\" data-col-group=\"matches\">".$data['q1matches']."</td>".
      // "<td>".$data['q2matches']."</td>".
      "<td data-col-group=\"matches\">".$data['q3matches']."</td>".
      "<td data-col-group=\"matches\">".$data['max_matches']."</td>".
      "<td data-col-group=\"matches\">".$matches."</td>".
      "<td class=\"separator\" data-col-group=\"wrp_q1_players\">".number_format($data['q1_wr_avg']*100, 2)."%</td>".
      "<td data-col-group=\"wrp_q1_players\">".number_format($data['q1_matches_avg'], 2)."</td>".
      "<td data-col-group=\"wrp_q1_players\">".$data['q1_players']."</td>".
      "<td class=\"separator\" data-col-group=\"wrp_q3_players\">".number_format($data['q3_wr_avg']*100, 2)."%</td>".
      "<td data-col-group=\"wrp_q3_players\">".number_format($data['q3_matches_avg'], 2)."</td>".
      "<td data-col-group=\"wrp_q3_players\">".$data['q3_players']."</td>".
      "<td class=\"separator\" data-col-group=\"wrp_diffs\">".number_format($data['grad']*100, 2)."%</td>".
      "<td data-col-group=\"wrp_diffs\">".number_format(($data['q3_wr_avg']-$data['q1_wr_avg'])*100, 2)."%</td>".
    "</tr>";
  }

  sort($matches_med);
  sort($players_med);

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_wrplayers")."</div>".
    "</div>".
  "</details>";

  $res .= filter_toggles_component("heroes-wrspammers", [
    'match' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_matches'
    ],
    'players' => [
      'value' => $players_med[ round(count($players_med)/2) ] ?? 0,
      'label' => 'data_filter_players_cnt'
    ],
  ], "heroes-wrspammers", 'wide');

  $res .= table_columns_toggle("heroes-wrspammers", [
    'players', 'matches', 'wrp_q1_players', 'wrp_q3_players', 'wrp_diffs',
  ], true);

  $res .= search_filter_component("heroes-wrspammers", true);

  $res .= "<table id=\"heroes-wrspammers\" class=\"list wide sortable\"><thead><tr class=\"overhead\">".
      "<th colspan=\"2\" data-col-group=\"_index\"></th>".
      "<th colspan=\"3\" class=\"separator\" data-col-group=\"players\">".locale_string("players")."</th>".
      "<th colspan=\"4\" class=\"separator\" data-col-group=\"matches\">".locale_string("matches")."</th>".
      "<th colspan=\"3\" class=\"separator\" data-col-group=\"wrp_q1_players\">".locale_string("wrp_q1_players")."</th>".
      "<th colspan=\"3\" class=\"separator\" data-col-group=\"wrp_q3_players\">".locale_string("wrp_q3_players")."</th>".
      "<th colspan=\"2\" class=\"separator\" data-col-group=\"wrp_diffs\">".locale_string("wrp_diffs")."</th>".
    "</tr><tr>".
      "<th data-col-group=\"_index\"></th><th data-col-group=\"_index\">".locale_string("hero")."</th>".
      "<th class=\"separator\" data-col-group=\"players\">".locale_string("wrp_1match_players")."</th>".
      "<th data-col-group=\"players\">".locale_string("wrp_1plus_players")."</th>".
      "<th data-col-group=\"players\">".locale_string("total")."</th>".
      "<th class=\"separator\" data-col-group=\"matches\">".locale_string("wrp_q1matches")."</th>".
      "<th data-col-group=\"matches\">".locale_string("wrp_q3matches")."</th>".
      "<th data-col-group=\"matches\">".locale_string("wrp_max_matches")."</th>".
      "<th data-col-group=\"matches\">".locale_string("total")."</th>".
      "<th class=\"separator\" data-col-group=\"wrp_q1_players\">".locale_string("wrp_q1_wr_avg")."</th>".
      "<th data-col-group=\"wrp_q1_players\">".locale_string("wrp_q1_matches_avg")."</th>".
      "<th data-col-group=\"wrp_q1_players\">".locale_string("wrp_q1_players_cnt")."</th>".
      "<th class=\"separator\" data-col-group=\"wrp_q3_players\">".locale_string("wrp_q3_wr_avg")."</th>".
      "<th data-col-group=\"wrp_q3_players\">".locale_string("wrp_q3_matches_avg")."</th>".
      "<th data-col-group=\"wrp_q3_players\">".locale_string("wrp_q3_players_cnt")."</th>".
      "<th class=\"separator\" data-col-group=\"wrp_diffs\">".locale_string("wr_gradient")."</th>".
      "<th data-col-group=\"wrp_diffs\">".locale_string("wrp_diff")."</th>".
  "</tr></thead><tbody>$rows</tbody></table>";
  
  return $res;
}



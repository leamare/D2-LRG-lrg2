<?php 

$modules['heroes']['wrplayers'] = '';

function rg_view_generate_heroes_wrplayers() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  $res = "";
  

  if (is_wrapped($report['hero_winrate_spammers'])) {
    $report['hero_winrate_spammers'] = unwrap_data($report['hero_winrate_spammers']);
  }

  $res .= "<div class=\"content-text\">".locale_string("desc_heroes_wrplayers")."</div>";

  $res .= "<table class=\"list wide sortable\"><thead><tr class=\"overhead\">".
      "<th colspan=\"2\"></th>".
      "<th colspan=\"2\" class=\"separator\">".locale_string("players")."</th>".
      "<th colspan=\"3\" class=\"separator\">".locale_string("matches")."</th>".
      "<th colspan=\"3\" class=\"separator\">".locale_string("wrp_q1_players")."</th>".
      "<th colspan=\"3\" class=\"separator\">".locale_string("wrp_q3_players")."</th>".
      "<th colspan=\"2\" class=\"separator\">".locale_string("wrp_diffs")."</th>".
    "</tr><tr>".
      "<th></th><th>".locale_string("hero")."</th>".
      "<th class=\"separator\">".locale_string("wrp_1match_players")."</th>".
      "<th>".locale_string("wrp_1plus_players")."</th>".
      "<th class=\"separator\">".locale_string("wrp_q1matches")."</th>".
      "<th>".locale_string("wrp_q3matches")."</th>".
      "<th>".locale_string("wrp_max_matches")."</th>".
      "<th class=\"separator\">".locale_string("wrp_q1_wr_avg")."</th>".
      "<th>".locale_string("wrp_q1_matches_avg")."</th>".
      "<th>".locale_string("wrp_q1_players_cnt")."</th>".
      "<th class=\"separator\">".locale_string("wrp_q3_wr_avg")."</th>".
      "<th>".locale_string("wrp_q3_matches_avg")."</th>".
      "<th>".locale_string("wrp_q3_players_cnt")."</th>".
      "<th class=\"separator\">".locale_string("wr_gradient")."</th>".
      "<th>".locale_string("wrp_diff")."</th>".
  "</tr></thead><tbody>";

  foreach ($report['hero_winrate_spammers'] as $hid => $data) {
    $res .= "<tr><td>".hero_portrait($hid)."</td><td>".hero_name($hid)."</td>".
      "<td class=\"separator\">".$data['players_1only']."</td>".
      "<td>".$data['players_1plus']."</td>".
      "<td class=\"separator\">".$data['q1matches']."</td>".
      // "<td>".$data['q2matches']."</td>".
      "<td>".$data['q3matches']."</td>".
      "<td>".$data['max_matches']."</td>".
      "<td class=\"separator\">".number_format($data['q1_wr_avg']*100, 2)."%</td>".
      "<td>".number_format($data['q1_matches_avg'], 2)."</td>".
      "<td>".$data['q1_players']."</td>".
      "<td class=\"separator\">".number_format($data['q3_wr_avg']*100, 2)."%</td>".
      "<td>".number_format($data['q3_matches_avg'], 2)."</td>".
      "<td>".$data['q3_players']."</td>".
      "<td class=\"separator\">".number_format($data['grad']*100, 2)."%</td>".
      "<td>".number_format(($data['q3_wr_avg']-$data['q1_wr_avg'])*100, 2)."%</td>".
    "</tr>";
  }

  $res .= "</tbody></table>";
  
  return $res;
}



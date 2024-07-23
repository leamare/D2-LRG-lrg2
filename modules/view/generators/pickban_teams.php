<?php 

function rg_create_team_pickban_data($context_pb, $context_vs_pb, $context_total_matches, $context_wins) {
  $r = [];
  $dummy = [
    "matches" => 0,

    "matches_total" => 0,
    "matches_picked" => 0,
    "matches_banned" => 0,
    "winrate_picked" => 0,
    "winrate_wo" => 0,
    "winrate_banned" => 0,
    "winrate_wo_ban" => 0,

    "matches_total_vs" => 0,
    "matches_picked_vs" => 0,
    "matches_banned_vs" => 0,
    "winrate_picked_vs" => 0,
    "winrate_wo_vs" => 0,
    "winrate_banned_vs" => 0,
    "winrate_wo_ban_vs" => 0,

    "rank" => 0,
    "arank" => 0,
    "rank_vs" => 0,
    "arank_vs" => 0,
  ];

  foreach($context_pb as $hid => $line) {
    if (!isset($r[$hid])) {
      $r[$hid] = $dummy;
    }

    $r[$hid]['matches'] += $line['matches_total'];
    $r[$hid]['matches_total'] = $line['matches_total'];
    $r[$hid]['matches_picked'] = $line['matches_picked'];
    $r[$hid]['matches_banned'] = $line['matches_banned'];
    $r[$hid]['winrate_picked'] = $line['winrate_picked'];
    $r[$hid]['winrate_wo'] = ($context_total_matches-$line['matches_picked']) ? 
      ($context_wins-round($line['matches_picked']*$line['winrate_picked'])) / ($context_total_matches-$line['matches_picked']) : 
      0;
    $r[$hid]['winrate_banned'] = $line['winrate_banned'];
    $r[$hid]['winrate_wo_ban'] = ($context_total_matches-$line['matches_banned']) ? 
      ($context_wins-round($line['matches_banned']*$line['winrate_banned'])) / ($context_total_matches-$line['matches_banned']) :
      0;
  }

  foreach($context_vs_pb as $hid => $line) {
    if (!isset($r[$hid])) {
      $r[$hid] = $dummy;
    }

    $r[$hid]['matches'] += $line['matches_total'];
    $r[$hid]['matches_total_vs'] = $line['matches_total'];
    $r[$hid]['matches_picked_vs'] = $line['matches_picked'];
    $r[$hid]['matches_banned_vs'] = $line['matches_banned'];
    $r[$hid]['winrate_picked_vs'] = $line['matches_picked'] ? round($line['wins_picked']/$line['matches_picked'], 5) : 0;
    $r[$hid]['winrate_wo_vs'] = ($context_total_matches-$line['matches_picked']) ? 
      ($context_total_matches-$context_wins-round($line['matches_picked']*($r[$hid]['winrate_picked'] ?? 0))) / 
        ($context_total_matches-$line['matches_picked']) : 
      0;
    $r[$hid]['winrate_banned_vs'] = $line['matches_banned'] ? round($line['wins_banned']/$line['matches_banned'], 5) : 0;
    $r[$hid]['winrate_wo_ban_vs'] = ($context_total_matches-$line['matches_banned']) ? 
      ($context_total_matches-$context_wins-round($line['matches_banned']*$r[$hid]['winrate_banned_vs'])) / 
        ($context_total_matches-$line['matches_banned']) : 
      0;
  }

  $rank = [
    "team" => [],
    "team_rev" => [],
    "enemy" => [],
    "enemy_rev" => [],
  ];

  foreach($r as $hid => $line) {
    $rank['team'][$hid] = [
      'matches_total' => $line['matches_picked']+$line['matches_banned_vs'],
      'matches_picked' => $line['matches_picked'],
      'matches_banned' => $line['matches_banned_vs'],
      'winrate_picked' => $line['winrate_picked'],
      'winrate_banned' => $line['winrate_banned_vs'],
    ];

    $rank['team_rev'][$hid] = [
      'matches_total' => $line['matches_picked']+$line['matches_banned_vs'],
      'matches_picked' => $line['matches_picked'],
      'matches_banned' => $line['matches_banned_vs'],
      'winrate_picked' => 1-$line['winrate_picked'],
      'winrate_banned' => 1-$line['winrate_banned_vs'],
    ];

    $rank['enemy'][$hid] = [
      'matches_total' => $line['matches_picked_vs']+$line['matches_banned'],
      'matches_picked' => $line['matches_picked_vs'],
      'matches_banned' => $line['matches_banned'],
      'winrate_picked' => $line['winrate_picked_vs'],
      'winrate_banned' => $line['winrate_banned'],
    ];

    $rank['enemy_rev'][$hid] = [
      'matches_total' => $line['matches_picked_vs']+$line['matches_banned'],
      'matches_picked' => $line['matches_picked_vs'],
      'matches_banned' => $line['matches_banned'],
      'winrate_picked' => 1-$line['winrate_picked_vs'],
      'winrate_banned' => 1-$line['winrate_banned'],
    ];
  }

  foreach ($rank as $type => $pb) {
    $key = (strpos($type, "_rev") > 0 ? "a" : "") . "rank" . (strpos($type, "enemy") === 0 ? "_vs" : "");

    compound_ranking($pb, $context_total_matches);

    uasort($pb, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pb)['wrank'];
    $max = reset($pb)['wrank'];
  
    foreach ($pb as $id => $el) {
      unset($pb[$id]['wrank']);
      $r[$id][$key] = 100 * ($el['wrank']-$min) / ($max-$min);
    }
  }

  return $r;
}

function rg_generator_team_pickban($table_id, $context) {
  $pb = rg_create_team_pickban_data($context['pickban'], $context['pickban_vs'] ?? [], $context['matches_total'], $context['wins'] ?? 0);

  if (empty($pb)) return "";

  $res = "";

  uasort($pb, function($a, $b) {
    if($a['matches'] == $b['matches']) return 0;
    else return ($a['matches'] < $b['matches']) ? 1 : -1;
  });

  // $res = "<div class=\"content-text\">".locale_string("heroes_median_picks").
  //   " (mp): $mp - ".locale_string("heroes_median_bans").
  //   " (mb): $mb - ".locale_string("matches_total").": $context_total_matches</div>";

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_pickban_teams")."</div>".
    "</div>".
  "</details>";

  $res .= table_columns_toggle($table_id, [
    'total',
    'team',
    'enemy'
  ], true, [
    0, 1, 2
  ]);
  $res .= search_filter_component($table_id, true);
  $res .=  "<table id=\"$table_id\" class=\"wide list sortable\"><thead><tr class=\"overhead\">".
      "<th colspan=\"2\" data-col-group=\"_index\"></th>".
      "<th colspan=\"2\" class=\"separator\" data-col-group=\"total\">".locale_string("total")."</th>".
      "<th colspan=\"10\" class=\"separator\" data-col-group=\"team\">".locale_string("team")."</th>".
      "<th colspan=\"10\" class=\"separator\" data-col-group=\"enemy\">".locale_string("enemy")."</th>".
    "</tr><tr>".
    "<th class=\"sorter-no-parser\" width=\"1%\" data-col-group=\"_index\"></th>".
    "<th data-sortInitialOrder=\"asc\" data-col-group=\"_index\">".locale_string("hero")."</th>".
    "<th class=\"separator\" data-col-group=\"total\">".locale_string("matches")."</th>".
    "<th data-col-group=\"total\">".locale_string("contest_rate")."</th>".

    "<th class=\"separator\" data-col-group=\"team\">".locale_string("rank")."</th>".
    "<th data-col-group=\"team\">".locale_string("antirank")."</th>".
    "<th class=\"separator\" data-col-group=\"team\">".locale_string("matches_picked")."</th>".
    "<th data-col-group=\"team\">".locale_string("pickrate")."</th>".
    "<th data-col-group=\"team\">".locale_string("winrate")."</th>".
    "<th data-col-group=\"team\">".locale_string("wr_wo")."</th>".
    // "<th>".locale_string("mp")."</th>".
    "<th class=\"separator\" data-col-group=\"team\">".locale_string("matches_banned")."</th>".
    "<th data-col-group=\"team\">".locale_string("banrate")."</th>".
    "<th data-col-group=\"team\">".locale_string("winrate")."</th>".
    "<th data-col-group=\"team\">".locale_string("wr_wo")."</th>".
    // "<th>".locale_string("mb")."</th>".

    "<th class=\"separator\" data-col-group=\"enemy\">".locale_string("rank")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("antirank")."</th>".
    "<th class=\"separator\" data-col-group=\"enemy\">".locale_string("matches_picked")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("pickrate")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("winrate")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("wr_wo")."</th>".
    // "<th>".locale_string("mp")."</th>".
    "<th class=\"separator\" data-col-group=\"enemy\">".locale_string("matches_banned")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("banrate")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("winrate")."</th>".
    "<th data-col-group=\"enemy\">".locale_string("wr_wo")."</th>".
    // "<th>".locale_string("mb")."</th>".

    "</tr></thead>";

  $res .= "<tbody>";
  
  foreach($pb as $id => $el) {
    $res .=  "<tr>".
      "<td data-col-group=\"_index\">".hero_portrait($id)."</td><td data-col-group=\"_index\">".hero_link($id)."</td>".
      "<td class=\"separator\" data-col-group=\"total\">".$el['matches']."</td>".
      "<td data-col-group=\"total\">".number_format($el['matches']/$context['matches_total']*100,2)."%</td>".

      "<td class=\"separator\" data-col-group=\"team\">".number_format($el['rank'],2)."</td>".
      "<td data-col-group=\"team\">".number_format($el['arank'],2)."</td>".
      "<td class=\"separator\" data-col-group=\"team\">".$el['matches_picked']."</td>".
      "<td data-col-group=\"team\">".number_format($el['matches_picked']/$context['matches_total']*100,2)."%</td>".
      "<td data-col-group=\"team\">".number_format($el['winrate_picked']*100,2)."%</td>".
      "<td data-col-group=\"team\">".number_format($el['winrate_wo']*100,2)."%</td>".
      // "<td>".number_format($el['matches_picked']/$mp, 1)."</td>".
      "<td class=\"separator\" data-col-group=\"team\">".$el['matches_banned']."</td>".
      "<td data-col-group=\"team\">".number_format($el['matches_banned']/$context['matches_total']*100,2)."%</td>".
      "<td data-col-group=\"team\">".number_format($el['winrate_banned']*100,2)."%</td>".
      "<td data-col-group=\"team\">".number_format($el['winrate_wo_ban']*100,2)."%</td>".
      // "<td>".number_format($el['matches_banned']/$mb, 1)."</td>".

      "<td class=\"separator\" data-col-group=\"enemy\">".number_format($el['rank_vs'],2)."</td>".
      "<td data-col-group=\"enemy\">".number_format($el['arank_vs'],2)."</td>".
      "<td class=\"separator\" data-col-group=\"enemy\">".$el['matches_picked_vs']."</td>".
      "<td data-col-group=\"enemy\">".number_format($el['matches_picked_vs']/$context['matches_total']*100,2)."%</td>".
      "<td data-col-group=\"enemy\">".number_format($el['winrate_picked_vs']*100,2)."%</td>".
      "<td data-col-group=\"enemy\">".number_format($el['winrate_wo_vs']*100,2)."%</td>".
      // "<td>".number_format($el['matches_picked']/$mp, 1)."</td>".
      "<td class=\"separator\" data-col-group=\"enemy\">".$el['matches_banned_vs']."</td>".
      "<td data-col-group=\"enemy\">".number_format($el['matches_banned_vs']/$context['matches_total']*100,2)."%</td>".
      "<td data-col-group=\"enemy\">".number_format($el['winrate_banned_vs']*100,2)."%</td>".
      "<td data-col-group=\"enemy\">".number_format($el['winrate_wo_ban_vs']*100,2)."%</td>".
      // "<td>".number_format($el['matches_banned']/$mb, 1)."</td>".
    "</tr>";
  }

  $res .= "</tbody></table>";

  return $res;
}

function rg_generator_team_pickban_profile($context) {
  $pb = rg_create_team_pickban_data($context['pickban'], $context['pickban_vs'] ?? [], $context['matches_total'], $context['wins']);

  if (empty($pb)) return "";

  $res = "<div class=\"small-list-wrapper\">";

  // best most picked
  uasort($pb, function($a, $b) { return $b['rank'] <=> $a['rank']; });
  $res .=  "<table id=\"over-heroes-pick\" class=\"list\"><caption>".locale_string("team_best_ranked_picks")."</caption>
  <thead><tr>
    <th width=\"1%\"></th>
    <th>".locale_string("hero")."</th>
    <th>".locale_string("rank")."</th>
    <th>".locale_string("matches_picked")."</th>
    <th>".locale_string("winrate_s")."</th>
    <th>".locale_string("matches_banned_vs")."</th>
    <th>".locale_string("winrate_s")."</th>
  </tr></thead>";

  $counter = 7;
  foreach($pb as $hid => $hero) {
    if($counter == 0) break;
    $res .=  "<tr><td>".hero_portrait($hid)."</td><td>".hero_link($hid)."</td>".
      "<td>".number_format($hero['rank'], 1)."</td>".
      "<td>".$hero['matches_picked']."</td>".
      "<td>".number_format($hero['winrate_picked']*100,1)."%</td>".
      "<td>".$hero['matches_banned_vs']."</td>".
      "<td>".number_format($hero['winrate_banned_vs']*100,1)."%</td>".
    "</tr>";
    $counter--;
  }
  $res .= "</table>";

  // worst most picked
  uasort($pb, function($a, $b) { return $b['arank'] <=> $a['arank']; });
  $res .=  "<table id=\"over-heroes-pick\" class=\"list\"><caption>".locale_string("team_aranked_picks")."</caption>
  <thead><tr>
    <th width=\"1%\"></th>
    <th>".locale_string("hero")."</th>
    <th>".locale_string("arank")."</th>
    <th>".locale_string("matches_picked")."</th>
    <th>".locale_string("winrate_s")."</th>
    <th>".locale_string("matches_banned_vs")."</th>
    <th>".locale_string("winrate_s")."</th>
  </tr></thead>";

  $counter = 7;
  foreach($pb as $hid => $hero) {
    if($counter == 0) break;
    $res .=  "<tr><td>".hero_portrait($hid)."</td><td>".hero_link($hid)."</td>".
      "<td>".number_format($hero['arank'], 1)."</td>".
      "<td>".$hero['matches_picked']."</td>".
      "<td>".number_format($hero['winrate_picked']*100,1)."%</td>".
      "<td>".$hero['matches_banned_vs']."</td>".
      "<td>".number_format($hero['winrate_banned_vs']*100,1)."%</td>".
    "</tr>";
    $counter--;
  }
  $res .= "</table>";
  
  // most effective vs
  uasort($pb, function($a, $b) { return $b['rank_vs'] <=> $a['rank_vs']; });
  $res .=  "<table id=\"over-heroes-pick\" class=\"list\"><caption>".locale_string("team_best_ranked_bans")."</caption>
  <thead><tr>
    <th width=\"1%\"></th>
    <th>".locale_string("hero")."</th>
    <th>".locale_string("rank")."</th>
    <th>".locale_string("matches_picked_vs")."</th>
    <th>".locale_string("winrate_s")."</th>
    <th>".locale_string("matches_banned")."</th>
    <th>".locale_string("winrate_s")."</th>
  </tr></thead>";

  $counter = 7;
  foreach($pb as $hid => $hero) {
    if($counter == 0) break;
    $res .=  "<tr><td>".hero_portrait($hid)."</td><td>".hero_link($hid)."</td>".
      "<td>".number_format($hero['rank_vs'], 1)."</td>".
      "<td>".$hero['matches_picked_vs']."</td>".
      "<td>".number_format($hero['winrate_picked_vs']*100,1)."%</td>".
      "<td>".$hero['matches_banned']."</td>".
      "<td>".number_format($hero['winrate_banned']*100,1)."%</td>".
    "</tr>";
    $counter--;
  }
  $res .= "</table>";

  // most picked less effective
  uasort($pb, function($a, $b) { return $b['arank_vs'] <=> $a['arank_vs']; });
  $res .=  "<table id=\"over-heroes-pick\" class=\"list\"><caption>".locale_string("team_aranked_bans")."</caption>
  <thead><tr>
    <th width=\"1%\"></th>
    <th>".locale_string("hero")."</th>
    <th>".locale_string("arank")."</th>
    <th>".locale_string("matches_picked_vs")."</th>
    <th>".locale_string("winrate_s")."</th>
    <th>".locale_string("matches_banned")."</th>
    <th>".locale_string("winrate_s")."</th>
  </tr></thead>";

  $counter = 7;
  foreach($pb as $hid => $hero) {
    if($counter == 0) break;
    $res .=  "<tr><td>".hero_portrait($hid)."</td><td>".hero_link($hid)."</td>".
      "<td>".number_format($hero['arank_vs'], 1)."</td>".
      "<td>".$hero['matches_picked_vs']."</td>".
      "<td>".number_format($hero['winrate_picked_vs']*100,1)."%</td>".
      "<td>".$hero['matches_banned']."</td>".
      "<td>".number_format($hero['winrate_banned']*100,1)."%</td>".
    "</tr>";
    $counter--;
  }
  $res .= "</table>";

  $res .= "</div>";

  return $res;
}
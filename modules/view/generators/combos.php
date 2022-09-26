<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_combos($table_id, &$context, $context_matches, $heroes_flag = true) {
  $id = $heroes_flag ? "heroid" : "playerid";

  if(!sizeof($context))
    return "";

  # Figuring out what kind of context we have here

  $combo = array_values($context)[0];

  if(isset($combo['lane_rate']))
    $lane_rate = true;
  else
    $lane_rate = false;

  if(isset($combo['lane']))
    $lane = true;
  else
    $lane = false;

  if(isset($combo['expectation']))
    $expectation = true;
  else
    $expectation = false;

  if(isset($combo['wr_diff']))
    $wr_diff = true;
  else
    $wr_diff = false;

  if(isset($combo[$id.'3']))
    $trios = true;
  else
    $trios = false;

  unset($combo);

  $res = filter_toggles_component($table_id, [
    'diff' => $wr_diff ? [
      'value' => 0,
      'label' => 'data_filter_wr_diff'
    ] : null,
    'dev' => $expectation ? [
      'value' => '1',
      'label' => 'data_filter_pos_deviation'
    ] : null,
    'lane' => $lane_rate ? [
      'value' => '25',
      'label' => 'data_filter_lane_rate'
    ] : null
  ], $table_id);

  $res .= search_filter_component($table_id);

  $res .= "<table id=\"$table_id\" class=\"list sortable\"><thead><tr class=\"thead\">".
         ($heroes_flag ? "<th colspan=\"".($trios ? 6 : 4)."\">".locale_string("heroes")."</th>" : "<th colspan=\"".($trios ? 3 : 2)."\">".locale_string("players")."</th>").
         "<th>".locale_string("matches")."</th>".
         "<th>".locale_string("winrate")."</th>".
         ($wr_diff ? "<th>".locale_string("winrate_diff")."</th>" : "").
         ($expectation ? "<th>".locale_string("pair_expectation")."</th>".
                         "<th>".locale_string("pair_deviation")."</th>".
                         "<th>".locale_string("percentage")."</th>" : "").
         ($lane_rate ? "<th>".locale_string("lane_rate")."</th>" : "").
         ($lane ? "<th>".locale_string("lane")."</th>" : "").
         ((is_array($context_matches) && !empty($context_matches)) ? "<th>".locale_string("matchlinks")."</th>" : "").
         "</tr></thead><tbody>";


  foreach($context as $combo) {
    if (!empty($context_matches))
      $matches_head = locale_string("matches")." : ".
        ($heroes_flag ? hero_name($combo[$id.'1']) : player_name($combo[$id.'1']))." + ".
        ($heroes_flag ? hero_name($combo[$id.'2']) : player_name($combo[$id.'2'])).
        ($trios ? " + ".($heroes_flag ? hero_name($combo[$id.'3']) : player_name($combo[$id.'3'])) : "");
    $res .= "<tr ".
        ($expectation ? "data-value-dev=\"".number_format($combo['matches']-$combo['expectation'], 0)."\" " : "").
        ($wr_diff ? "data-value-diff=\"".number_format($combo['wr_diff']*100,2)."\" " : "").
        (isset($combo['lane_rate']) ? "data-value-lane=\"".number_format($combo['lane_rate']*100, 2)."\" " : "").
      ">".
      ($heroes_flag ? "<td>".hero_portrait($combo[$id.'1'])."</td>" : "").
      "<td>".($heroes_flag ? hero_link($combo[$id.'1']) : player_link($combo[$id.'1']))."</td>".
      ($heroes_flag ? "<td>".hero_portrait($combo[$id.'2'])."</td>" : "").
      "<td>".($heroes_flag ? hero_link($combo[$id.'2']) : player_link($combo[$id.'2']))."</td>".
      (
        $trios ?
        ($heroes_flag ? "<td>".hero_portrait($combo[$id.'3'])."</td>" : "").
        "<td>".($heroes_flag ? hero_link($combo[$id.'3']) : player_link($combo[$id.'3']))."</td>" :
        ""
        ).
      "<td>".$combo['matches']."</td>".
      "<td>".number_format($combo['winrate']*100,2)."%</td>".
      ($wr_diff ? "<td>".number_format($combo['wr_diff']*100,2)."%</td>" : "").
      ($expectation ? "<td>".number_format($combo['expectation'], 0)."</td>".
                      "<td>".number_format($combo['matches']-$combo['expectation'], 0)."</td>".
                      "<td>".number_format(($combo['matches']-$combo['expectation'])*100/$combo['matches'], 2)."%</td>" : "").
      ($lane_rate ? "<td>".number_format($combo['lane_rate']*100, 2)."%</td>" : "").
      ($lane ? "<td>".locale_string("lane_".$combo['lane'])."</td>" : "").
      ((is_array($context_matches) && !empty($context_matches)) ?
        "<td><a onclick=\"showModal('".htmlspecialchars(
          join_matches($context_matches[ $combo[$id.'1'].'-'.$combo[$id.'2'].($trios ? '-'.$combo[$id.'3'] : "") ])).
          "', '".addcslashes(htmlspecialchars($matches_head), "'")."');\">".locale_string("matches")."</a></td>" :
      "").
    "</tr>";
  }
  $res .= "</tbody></table>";

  return $res;
}

?>

<?php 

$endpoints['draft'] = function($mods, $vars, &$report) {
  if (in_array("players", $mods))
    $type = "players";
  else 
    $type = "heroes";

  if (isset($vars['team'])) {
    $context =& $type == "heroes" ? $report['teams'][ $vars['team'] ]['pickban'] : $report['teams'][ $vars['team'] ]['players_draft_pb'];
    $context_draft =& $type == "heroes" ? $report['teams'][ $vars['team'] ]['draft'] : $report['teams'][ $vars['team'] ]['players_draft'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
  } else if (isset($vars['region'])) {
    $context_draft =& $type == "heroes" 
      ? $report['regions_data'][ $vars['region'] ]['draft'] 
      : $report['regions_data'][ $vars['region'] ]['players_draft'];
    if ($type == "heroes")
      $context =& $report['regions_data'][ $vars['region'] ]['pickban'];
    else 
      $rep =& $report['regions_data'][ $vars['region'] ];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']["matches_total"];
  } else {
    $context_draft =& $type == "heroes" ? $report['draft'] : $report['players_draft'];
    if ($type == "heroes")
      $context =& $report['pickban'];
    else 
      $rep =& $report;
    $context_total_matches = $report["random"]["matches_total"];
  }

  if (empty($context) && $type == "players") {
    $context = [];
    foreach($rep['players_summary'] as $id => $el) {
      $context[ $id ] = [
        "matches_banned" => 0,
        "winrate_banned" => 0,
        "matches_picked" => $el['matches_s'],
        "matches_total" => $el['matches_s'],
        "winrate_picked" => $el['winrate_s']
      ];
    }
  }

  if(!sizeof($context)) return [];

  uasort($context, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $ranks = [];
  $context_copy = $context;

  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }

  foreach($context as $id => &$el) {
    $el['rank'] = round($ranks[$id], 2);
    $el['contest_rate'] = $el['matches_total']/$context_total_matches;
  }

  $draft = [];
  $id_name = $type."_id";
  $draft_template = [
    "matches_total" => 0,
    "matches_picked" => 0,
    "winrate_picked" => 0,
    "matches_banned" => 0,
    "winrate_banned" => 0
  ];

  for ($i=0; $i<2; $i++) {
    $stage_type = $i ? "picked" : "banned";
    $max_stage = 1;
    if(!isset($context_draft[$i])) continue;
    foreach($context_draft[$i] as $stage_num => $stage) {
      if ($stage_num > $max_stage) $max_stage = $stage_num;
      foreach($stage as $el) {
        if(!isset($draft[ $el[$id_name] ])) {
          if($stage_num > 1) {
            for($j=1; $j<$stage_num; $j++) {
              $draft[ $el[$id_name] ][$j] = $draft_template;
            }
          }
        }

        if(!isset($draft[ $el[$id_name] ][$stage_num]))
          $draft[ $el[$id_name] ][$stage_num] = $draft_template;
        $draft[ $el[$id_name] ][$stage_num]["matches_total"] += $el['matches'];
        $draft[ $el[$id_name] ][$stage_num]["matches_".$stage_type] = $el['matches'];
        $draft[ $el[$id_name] ][$stage_num]["winrate_".$stage_type] = $el['winrate'];
      }
    }
  }

  $ranks_stages = [];
  for ($i = 1; $i <= $max_stage; $i++) {
    $ranks_stages[$i] = [];
    $scores = [];
    foreach ($draft as $id => $stages) {
      if(isset($stages[$i]) && ($stages[$i]['matches_picked']+$stages[$i]['matches_banned']))
        $scores[$id] = $stages[$i];
    }
    uasort($scores, $compound_ranking_sort);

    $increment = 100 / sizeof($scores); $j = 0;

    foreach ($scores as $id => $el) {
      if(isset($last) && $el == $last) {
        $j++;
        $draft[$id][$i]['rank'] = $last_rank;
        $ranks_stages[$i][$id] = $last_rank;
      } else
        $draft[$id][$i]['rank'] = 100 - $increment*$j++;
      $last = $el;
      $last_rank = $draft[$id][$i]['rank'];
    }
  }

  return [
    'type' => $type,
    'total' => $context,
    'stages' => $draft
  ];
};

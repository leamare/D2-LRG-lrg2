<?php 

$repeatVars['draft'] = ['team', 'region'];

#[Endpoint(name: 'draft')]
#[Description('Draft totals and stages for heroes or players')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'DraftResult')]
class Draft extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (in_array("players", $mods))
    $type = "player";
  else 
    $type = "hero";

  if (isset($vars['team'])) {
    if ($type == "hero") {
      $context =& $report['teams'][ $vars['team'] ]['pickban'];
      $context_draft =& $report['teams'][ $vars['team'] ]['draft'];
    } else {
      $context =& $report['teams'][ $vars['team'] ]['players_draft_pb'];
      $context_draft =& $report['teams'][ $vars['team'] ]['players_draft'];
    }
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
  } else if (isset($vars['region'])) {
    if ($type == "hero")
      $context_draft =& $report['regions_data'][ $vars['region'] ]['draft'];
    else 
      $context_draft =& $report['regions_data'][ $vars['region'] ]['players_draft'];
    if ($type == "hero")
      $context =& $report['regions_data'][ $vars['region'] ]['pickban'];
    else 
      $rep =& $report['regions_data'][ $vars['region'] ];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']["matches_total"];
  } else {
    if ($type == "hero")
      $context_draft =& $report['draft'];
    else 
      $context_draft =& $report['players_draft'];
    if ($type == "hero")
      $context =& $report['pickban'];
    else 
      $rep =& $report;
    $context_total_matches = $report["random"]["matches_total"];
  }

  if (isset($context) && is_wrapped($context)) {
    $context = unwrap_data($context);
  }
  if (is_wrapped($context_draft)) {
    $context_draft = unwrap_data($context_draft);
  }

  if (empty($context) && $type == "player") {
    $context = [];
    foreach($rep['players_summary'] as $id => $el) {
      $context[ $id ] = [
        "matches_banned" => 0,
        "winrate_banned" => 0,
        "matches_picked" => +$el['matches_s'],
        "matches_total" => +$el['matches_s'],
        "winrate_picked" => +$el['winrate_s']
      ];
    }
  }

  if(!count($context)) return [];

  if ($type == "player") {
    if (!empty($report['draft']) && !empty($report['matches_additional'])) {
      include_once __DIR__."/../../../view/functions/players_bans_estimate.php";
      
      if (!empty($report['teams']) && !empty($report['match_participants_teams'])) {
        if (isset($vars['team'])) {
          estimate_players_draft_processor_tvt_single_team($report['teams'], $vars['team']);
        } else {
          estimate_players_draft_processor_tvt_report($context);
        }
      } else {
        estimate_players_draft_processor_pvp_report($context);
      }
    }
  }

  foreach($context as $k => $v) {
    if(isset($v['winrate_picked'])) break;

    if($context[$k]['matches_picked'])
      $context[$k]['winrate_picked'] = $context[$k]['wins_picked'] / $context[$k]['matches_picked'];
    else
      $context[$k]['winrate_picked'] = 0;

    if($context[$k]['matches_banned'])
      $context[$k]['winrate_banned'] = $context[$k]['wins_banned'] / $context[$k]['matches_banned'];
    else
      $context[$k]['winrate_banned'] = 0;
  }

  compound_ranking($context, $context_total_matches);

  uasort($context, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context)['wrank'];
  $max = reset($context)['wrank'];

  foreach ($context as $id => $el) {
    $context[$id]['rank'] = round(100 * ($el['wrank']-$min) / ($max-$min+0.01), 2);
    $context[$id]['contest_rate'] = round($el['matches_total']/$context_total_matches, 4);
    unset($context[$id]['wrank']);
  }

  $draft = [];
  $id_name = $type."id";
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
    if(empty($context_draft[$i])) continue;
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
        $draft[ $el[$id_name] ][$stage_num]["matches_total"] += +$el['matches'];
        $draft[ $el[$id_name] ][$stage_num]["matches_".$stage_type] = +$el['matches'];
        $draft[ $el[$id_name] ][$stage_num]["winrate_".$stage_type] = +$el['winrate'];

        if ($i) {
          $draft[ $el[$id_name] ][$stage_num]["ratio"] = round($context[ $el[$id_name] ]['matches_picked'] ? $el['matches']/$context[ $el[$id_name] ]['matches_picked'] : 0, 4);
        }
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
    compound_ranking($scores, $context_total_matches);

    uasort($scores, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($scores)['wrank'];
    $max = reset($scores)['wrank'];
  
    foreach ($scores as $id => $el) {
      $draft[$id][$i]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $ranks_stages[$i][$id] = $draft[$id][$i]['rank'];
    }
  }

  return [
    'type' => $type,
    'total' => $context,
    'stages' => $draft
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('DraftStage', TypeDefs::obj([
    'matches_total' => TypeDefs::int(),
    'matches_picked' => TypeDefs::int(),
    'winrate_picked' => TypeDefs::num(),
    'matches_banned' => TypeDefs::int(),
    'winrate_banned' => TypeDefs::num(),
    'rank' => TypeDefs::num(),
    'ratio' => TypeDefs::num(),
  ]));
  SchemaRegistry::register('DraftResult', TypeDefs::obj([
    'type' => TypeDefs::str(),
    'total' => TypeDefs::mapOf(TypeDefs::obj([])),
    'stages' => TypeDefs::mapOf(TypeDefs::mapOf('DraftStage'))
  ]));
}

<?php 

$repeatVars['vsdraft'] = ['team'];

#[Endpoint(name: 'vsdraft')]
#[Description('Opponent draft stages statistics for a team')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ReturnSchema(schema: 'VsDraftResult')]
class VsDraft extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $type = "hero";
  $fallback_type = "heroes";

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ]['pickban_vs'];
    $context_draft =& $report['teams'][ $vars['team'] ]['draft_vs'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
  } else throw new \Exception("VSdraft works only for teams");

  if(!sizeof($context)) return [];

  uasort($context, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  foreach ($context as $hid => $data) {
    if (!isset($data['winrate_picked'])) {
      if (!isset($data['matches_picked'])) $data['matches_picked'] = 0;
      $context[$hid]['winrate_picked'] = round( $data['matches_picked'] ? ($data['wins_picked'] ?? 0)/$data['matches_picked'] : 0, 4);
      if (isset($data['wins_picked'])) unset($context[$hid]['wins_picked']);
    }
    if (!isset($data['winrate_banned'])) {
      if (!isset($data['matches_banned'])) $data['matches_banned'] = 0;
      $context[$hid]['winrate_banned'] = round($data['matches_banned'] ? ($data['wins_banned'] ?? 0)/$data['matches_banned'] : 0, 4);
      if (isset($data['wins_banned'])) unset($context[$hid]['wins_banned']);
    }
  }

  compound_ranking($context, $context_total_matches);

  uasort($context, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context)['wrank'];
  $max = reset($context)['wrank'];

  foreach ($context as $id => $el) {
    $context[$id]['rank'] = round(100 * ($el['wrank']-$min) / ($max-$min), 2);
    $context[$id]['contest_rate'] = round($el['matches_total']/$context_total_matches, 4);
    unset($context[$id]['wrank']);
  }

  $draft = [];
  $id_name = $type."id";
  // $id_name_fb = $fallback_type."_id";
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
    'total' => $context,
    'stages' => $draft
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('VsDraftResult', TypeDefs::obj([
    'total' => TypeDefs::mapOfIdKeys(TypeDefs::obj([])),
    'stages' => TypeDefs::mapOf(TypeDefs::mapOfIdKeys(TypeDefs::obj([])))
  ]));
}

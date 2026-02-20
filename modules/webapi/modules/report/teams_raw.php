<?php 

include_once(__DIR__ . "/../../../view/functions/teams_diversity_recalc.php");

#[Endpoint(name: 'teams_raw')]
#[Description('Raw teams object or a specific team by id, with diversity computed')]
#[ModlineVar(name: 'teamid', schema: ['type' => 'integer'], description: 'Team id')]
#[ReturnSchema(schema: 'TeamsRawResult')]
class TeamsRaw extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (isset($vars['teamid']) && isset($report['teams'][ $vars['teamid'] ])) {
    if (isset($report['teams'][ $vars['team'] ]['averages']) || isset($report['teams'][ $vars['team'] ]['averages']['hero_pool'])) 
      $report['teams'][ $vars['team'] ]['averages']['diversity'] = teams_diversity_recalc($report['teams'][ $vars['team'] ]);

    return $report['teams'][ $vars['teamid'] ];
  }

  foreach ($report['teams'] as $team => $data) {
    if (!isset($data['averages']) || !isset($data['averages']['hero_pool'])) continue;

      $report['teams'][$team]['averages']['diversity'] = teams_diversity_recalc($data);
  }

  return $report['teams'];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('TeamsRawResult', TypeDefs::oneOf([
    TypeDefs::mapOfIdKeys(TypeDefs::obj([])),
    TypeDefs::obj([])
  ]));
}

<?php 

#[Endpoint(name: 'participants')]
#[Description('List players or teams participating in the report/region')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'ParticipantsResult')]
class Participants extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $res = [];

  if (isset($report['teams']) && in_array("teams", $mods)) {
    $teams = true;
  } else {
    $teams = false;
  }

  if (isset($vars['region'])) {
    if ($teams) {
      $context =& $report['regions_data'][ $vars['region'] ]['teams'];
    } else {
      $context =& $report['regions_data'][ $vars['region'] ]['players_summary'];
    }
  } else {
    if ($teams) {
      $context =& $report['teams'];
    } else {
      $context =& $report['players'];
    }
  }

  if (empty($context)) return $res;

  $res[$teams ? 'teams' : 'players'] = [];
  foreach ($context as $id => $data) {
    if ($teams) {
      $res['teams'][] = team_card($id);
    } else {
      $res['players'][] = player_card($id);
    }
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('ParticipantsResult', TypeDefs::oneOf([
    TypeDefs::obj(['teams' => TypeDefs::arrayOf(TypeDefs::obj([]))]),
    TypeDefs::obj(['players' => TypeDefs::arrayOf(TypeDefs::obj([]))]),
  ]));
}

<?php

#[Endpoint(name: 'heroes-abilitydraft')]
#[Description('Ability Draft (game mode 18) pick/win stats per ability')]
#[ModlineVar(name: 'abilityid', schema: ['type' => 'integer'], description: 'Ability id, omit for the full list')]
#[ReturnSchema(schema: 'AbilityDraftResult')]
class HeroesAbilitydraft extends EndpointTemplate {
public function process() {
  $vars = $this->vars; $report = $this->report;

  if (empty($report['ability_draft']['abilities']))
    throw new UserInputException("No Ability Draft data");

  $abilities = $report['ability_draft']['abilities'];
  if (is_wrapped($abilities)) $abilities = unwrap_data($abilities);

  if (isset($vars['abilityid'])) {
    $aid = (int)$vars['abilityid'];
    return $abilities[$aid] ?? [];
  }

  $res = [];
  foreach ($abilities as $aid => $a) {
    $res[] = [ 'ability' => (int)$aid ] + $a;
  }
  usort($res, function($a, $b) { return $b['matches'] <=> $a['matches']; });

  return [
    'matches' => $report['ability_draft']['matches'],
    'players' => $report['ability_draft']['players'],
    'abilities' => $res,
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('AbilityDraftAbilityRow', TypeDefs::obj([
    'ability' => TypeDefs::int(),
    'matches' => TypeDefs::int(),
    'wins' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
    'pick_rate' => TypeDefs::num(),
    'as_ultimate' => TypeDefs::int(),
    'ultimate_rate' => TypeDefs::num(),
  ]));
  SchemaRegistry::register('AbilityDraftResult', TypeDefs::oneOf([
    TypeDefs::obj([
      'matches' => TypeDefs::int(),
      'players' => TypeDefs::int(),
      'abilities' => TypeDefs::arrayOf('AbilityDraftAbilityRow'),
    ]),
    TypeDefs::ref('AbilityDraftAbilityRow'),
  ]));
}

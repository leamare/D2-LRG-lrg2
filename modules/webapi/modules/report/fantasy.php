<?php 

#[Endpoint(name: 'fantasy')]
#[Description('Fantasy MVP breakdown for players or heroes')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'FantasyResult')]
class Fantasy extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("players", $mods)) {
    if (is_wrapped($context['fantasy']['players_mvp'])) $context['fantasy']['players_mvp'] = unwrap_data($context['fantasy']['players_mvp']);
    $res = $context['fantasy']['players_mvp'];
    $res['__endp'] = "players-fantasy";
  } else if (in_array("heroes", $mods)) {
    if (is_wrapped($context['fantasy']['heroes_mvp'])) $context['fantasy']['heroes_mvp'] = unwrap_data($context['fantasy']['heroes_mvp']);
    $res = $context['fantasy']['heroes_mvp'];
    $res['__endp'] = "heroes-fantasy";
  } else {
    throw new \Exception("What kind of fantasy data do you need?");
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('FantasyResult', TypeDefs::mapOf(TypeDefs::mapOf(TypeDefs::obj([]))));
}

<?php 

#[Endpoint(name: 'haverages')]
#[Description('Hero or player averages (haverages) for report/region')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'HaveragesResult')]
class HAverages extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("heroes", $mods)) {
    $type = "heroes";
  } else if (in_array("players", $mods)) {
    $type = "players";
  } else {
    throw new \Exception("No module specified");
  }

  $res = $context['averages_'.$type] ?? $context['haverages_'.$type];

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('HaveragesResult', TypeDefs::mapOf(TypeDefs::obj([])));
}

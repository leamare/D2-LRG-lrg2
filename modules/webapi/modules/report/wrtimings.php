<?php 

#[Endpoint(name: 'wrtimings')]
#[Description('Hero winrate over game duration buckets')]
#[ReturnSchema(schema: 'WrTimingsResult')]
class WrTimings extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (!in_array("heroes", $mods)) {
    throw new UserInputException("Endpoint `wrtimings` only works for heroes");
  }

  if (is_wrapped($report['hero_winrate_timings'])) {
    $report['hero_winrate_timings'] = unwrap_data($report['hero_winrate_timings']);
  }

  return $report['hero_winrate_timings'];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('WrTimingsResult', TypeDefs::mapOfIdKeys(TypeDefs::obj([])));
}

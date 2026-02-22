<?php 

#[Endpoint(name: 'wrplayers')]
#[Description('Hero winrate vs player experience (spammers quartiles)')]
#[ReturnSchema(schema: 'WrPlayersResult')]
class WrPlayers extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (!in_array("heroes", $mods)) {
    throw new UserInputException("Endpoint `wrplayers` only works for heroes");
  }

  if (is_wrapped($report['hero_winrate_spammers'])) {
    $report['hero_winrate_spammers'] = unwrap_data($report['hero_winrate_spammers']);
  }

  foreach ($report['hero_winrate_spammers'] as $hid => $data) {
    $report['hero_winrate_spammers'][$hid]['diff'] = round($data['q3_wr_avg'] - $data['q1_wr_avg'], 5);
    $report['hero_winrate_spammers'][$hid]['matches_total'] = $report['pickban'][$hid]['matches_picked'];
  }

  return $report['hero_winrate_spammers'];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('WrPlayersResult', TypeDefs::mapOfIdKeys(TypeDefs::obj([])));
}

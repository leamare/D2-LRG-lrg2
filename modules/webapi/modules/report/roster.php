<?php 

$repeatVars['roster'] = ['team'];

#[Endpoint(name: 'roster')]
#[Description('Active roster for a specific team')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ReturnSchema(schema: 'RosterResult')]
class Roster extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (isset($vars['team']) && isset($report['teams'][ $vars['team'] ])) {
    $res = [];
    foreach($report['teams'][ $vars['team'] ]['active_roster'] as $player) {
      $res[] = player_card($player);
    }
    return $res;
  }
  throw new UserInputException("You need teamid for roster");
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('RosterResult', TypeDefs::arrayOf(TypeDefs::obj([])));
}

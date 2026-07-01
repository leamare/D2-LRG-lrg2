<?php

include_once(__DIR__ . "/../../../view/generators/bracket.php");

#[Endpoint(name: 'bracket')]
#[Description('Reconstructed tournament structure: sub-events, group stages, brackets and final placements, inferred from series progression')]
#[ReturnSchema(schema: 'BracketResult')]
class Bracket extends EndpointTemplate {
public function process() {
  global $report, $meta;
  if (!bracket_available())
    throw new UserInputException("No team match data available for this report");

  return bracket_json(bracket_generate());
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('BracketTeam', TypeDefs::obj([
    'id'   => TypeDefs::int(),
    'tag'  => TypeDefs::str(),
    'name' => TypeDefs::str(),
  ]));

  SchemaRegistry::register('BracketSeries', TypeDefs::obj([
    'teams'   => TypeDefs::arrayOf('BracketTeam'),
    'scores'  => TypeDefs::arrayOf(TypeDefs::int()),
    'winner'  => TypeDefs::int(),
    'bo'      => TypeDefs::int(),
    'date'    => TypeDefs::int(),
    'matches' => TypeDefs::arrayOf(TypeDefs::int()),
    'flags'   => TypeDefs::arrayOf(TypeDefs::str()),
  ]));

  SchemaRegistry::register('BracketStanding', TypeDefs::obj([
    'rank'       => TypeDefs::int(),
    'team'       => 'BracketTeam',
    'wins'       => TypeDefs::int(),
    'draws'      => TypeDefs::int(),
    'losses'     => TypeDefs::int(),
    'map_wins'   => TypeDefs::int(),
    'map_losses' => TypeDefs::int(),
  ]));

  SchemaRegistry::register('BracketGroup', TypeDefs::obj([
    'name'      => TypeDefs::str(),
    'format'    => TypeDefs::str(),
    'standings' => TypeDefs::arrayOf('BracketStanding'),
  ]));

  SchemaRegistry::register('BracketRound', TypeDefs::obj([
    'name'   => TypeDefs::str(),
    'series' => TypeDefs::arrayOf('BracketSeries'),
  ]));

  SchemaRegistry::register('BracketStage', TypeDefs::obj([
    'type'        => TypeDefs::str(),
    'name'        => TypeDefs::str(),
    'phase_type'  => TypeDefs::str(),
    'format'      => TypeDefs::str(),
    'groups'      => TypeDefs::arrayOf('BracketGroup'),
    'tiebreakers' => TypeDefs::arrayOf('BracketSeries'),
    'decider'     => TypeDefs::arrayOf('BracketSeries'),
    'upper'       => TypeDefs::arrayOf('BracketRound'),
    'lower'       => TypeDefs::arrayOf('BracketRound'),
    'grand_final' => 'BracketRound',
    'unplaced'    => TypeDefs::arrayOf('BracketSeries'),
    'series'      => TypeDefs::arrayOf('BracketSeries'),
  ]));

  SchemaRegistry::register('BracketPlacement', TypeDefs::obj([
    'place_from' => TypeDefs::int(),
    'place_to'   => TypeDefs::int(),
    'teams'      => TypeDefs::arrayOf('BracketTeam'),
  ]));

  SchemaRegistry::register('BracketEvent', TypeDefs::obj([
    'name'       => TypeDefs::str(),
    'stages'     => TypeDefs::arrayOf('BracketStage'),
    'placements' => TypeDefs::arrayOf('BracketPlacement'),
  ]));

  SchemaRegistry::register('BracketResult', TypeDefs::obj([
    'generated_at' => TypeDefs::int(),
    'events'       => TypeDefs::arrayOf('BracketEvent'),
  ]));
}

<?php

include_once(__DIR__ . "/../../../view/functions/meta_levels.php");

#[Endpoint(name: 'meta_levels')]
#[Description('Meta game layers (meta-0, meta-1, ...): core heroes, combo heroes, reasoning, and up to a couple of projected layers past the last real one')]
#[ReturnSchema(schema: 'MetaLevelsResult')]
class MetaLevels extends EndpointTemplate {
public function process() {
  $data = meta_levels_from_report($this->report);

  if (empty($data)) {
    throw new UserInputException("This report doesn't have the daily winrate, pickban, counters and combos sections meta levels needs.");
  }

  return [
    'layers' => array_values($data['layers']),
    'projections' => array_values($data['projections']),
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('MetaLevelsLayer', TypeDefs::obj([
    'core' => TypeDefs::arrayOf(TypeDefs::int()),
    'combo' => TypeDefs::arrayOf(TypeDefs::int()),
    'reasoning' => TypeDefs::obj([]),
    'loops_to' => TypeDefs::int(),
  ]));

  SchemaRegistry::register('MetaLevelsProjection', TypeDefs::obj([
    'method' => TypeDefs::literal(['loop', 'trend']),
    'core' => TypeDefs::arrayOf(TypeDefs::int()),
    'combo' => TypeDefs::arrayOf(TypeDefs::int()),
    'loop_period' => TypeDefs::int(),
    'loop_start' => TypeDefs::int(),
  ]));

  SchemaRegistry::register('MetaLevelsResult', TypeDefs::obj([
    'layers' => TypeDefs::arrayOf('MetaLevelsLayer'),
    'projections' => TypeDefs::arrayOf('MetaLevelsProjection'),
  ], [ 'layers' ]));
}

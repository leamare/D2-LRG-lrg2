<?php 

#[Endpoint(name: 'draft_tree')]
#[Description('Draft edges and draft context for heroes')]
#[GetParam(name: 'league', required: true, schema: ['type' => 'string'], description: 'Report tag')]
#[GetParam(name: 'cat', required: false, schema: ['type' => 'number'], description: 'Quantile cutoff 0..1 to filter edges by count')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'DraftTreeResult')]
class DraftTree extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (!isset($report['draft_tree']))
    throw new \Exception("This module doesn't exist");
  if (!in_array("heroes", $mods))
    throw new \Exception("This module is only available for heroes");

  // positions context
  if (isset($vars['team'])) {
    $parent =& $report['teams'][ $vars['team'] ]; 
    $context =& $report['teams'][ $vars['team'] ]['draft_tree'];
    $draft_context =& $report['teams'][ $vars['team'] ]['draft'];
    $limiter = $report['settings']['limiter_triplets'];
  } else if (isset($vars['region'])) {
    $parent =& $report['regions_data'][ $vars['region'] ]; 
    $context =& $report['regions_data'][ $vars['region'] ]['draft_tree'];
    $draft_context =& $report['regions_data'][ $vars['region'] ]['draft'];
    $limiter = $report['regions_data'][ $vars['region'] ]['settings']['limiter_graph']*6;
  } else {
    $parent =& $report;
    $context =& $report['draft_tree'];
    $draft_context =& $report['draft'];
    $limiter = $report['settings']['limiter_combograph']*6;
  }

  if (is_wrapped($context)) $context = unwrap_data($context);

  usort($context, function($a, $b) {
    return $b['count'] <=> $a['count'];
  });

  if (!empty($vars['cat'])) {
    $i = floor(count($context) * (float)($vars['cat']));
    $med = $context[ $i > 0 ? $i-1 : 0 ]['count'];
  } else {
    $med = 0;
  }

  $stages_pop = [];
  $items = [];

  $context = array_filter($context, function($a) use ($med) {
    return $a['count'] > $med;
  });

  return [
    'edges' => $context,
    'draft' => $draft_context
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('DraftEdge', TypeDefs::obj([
    'pick' => TypeDefs::int(),
    'ban' => TypeDefs::int(),
    'count' => TypeDefs::int(),
    'from' => TypeDefs::int(),
    'to' => TypeDefs::int(),
  ]));

  SchemaRegistry::register('DraftTreeResult', TypeDefs::obj([
    'edges' => TypeDefs::arrayOf('DraftEdge'),
    'draft' => TypeDefs::arrayOf(TypeDefs::arrayOf(TypeDefs::arrayOf(TypeDefs::int())))
  ]));
}
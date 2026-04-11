<?php 

$repeatVars['vsummary'] = ['position'];

#[Endpoint(name: 'vsummary')]
#[Description('Hero variants summary by selected position')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Position code like 1.2 or label')]
#[ReturnSchema(schema: 'VSummaryResult')]
class VSummary extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (!in_array("heroes", $mods)) throw new UserInputException("This module is only available for heroes");

  if (!isset($report['hero_summary_variants'])) {
    return [];
  }
  if (is_wrapped($report['hero_summary_variants'])) {
    $report['hero_summary_variants'] = unwrap_data($report['hero_summary_variants']);
  }
  if (!is_array($report['hero_summary_variants'])) {
    return [];
  }

  $i = $vars['position'] ? array_search($vars['position'], ROLES_IDS_SIMPLE) : 0;
  if ($i === false) {
    $i = 0;
  }

  $variant_row = $report['hero_summary_variants'][$i] ?? [];
  $context = array_filter(is_array($variant_row) ? $variant_row : [], function($e) {
    return !empty($e) && !empty($e['matches_s']);
  });
  uasort($context, function($a, $b) {
    return $b['matches_s'] <=> $a['matches_s'];
  });

  if (empty($context)) {
    return [];
  }

  $total_matches = 0;
  $matches = [];
  foreach ($context as $id => $c) {
    if (empty($c) || !$id) continue;
    if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
    $matches[] = $c['matches_s'];
  } 

  $ranks = [];
  $context_copy = $context;

  positions_ranking($context, $total_matches);

  uasort($context, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context)['wrank'];
  $max = reset($context)['wrank'];

  foreach ($context as $id => $el) {
    $context[$id]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    unset($context[$id]['wrank']);
    $context_copy[$id]['winrate_s'] = 1-($el['winrate'] ?? $el['winrate_s']);
  }

  $aranks = [];

  positions_ranking($context_copy, $total_matches);

  uasort($context_copy, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context_copy)['wrank'];
  $max = reset($context_copy)['wrank'];

  foreach ($context_copy as $id => $el) {
    $context[$id]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
  }

  unset($context_copy);

  return $context;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('VSummaryResult', TypeDefs::mapOfIdKeys(TypeDefs::obj([])));
}
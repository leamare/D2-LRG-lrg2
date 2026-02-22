<?php 

#[Endpoint(name: 'pvp')]
#[Description('Player vs player matchup ranks and deviations')]
#[GetParam(name: 'league', required: true, schema: ['type' => 'string'], description: 'Report tag')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Filter by player id')]
#[ReturnSchema(schema: 'PvpResult')]
class Pvp extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (isset($vars['team'])) {
    throw new UserInputException("No team allowed");
  } else if (isset($vars['region'])) {
    throw new UserInputException("No region allowed");
  }

  $winrates = [];
  if (isset($report['players_additional'])) {
    foreach($report['players_additional'] as $id => $player) {
      $winrates[$id]['matches'] = $player['matches'];
      $winrates[$id]['winrate'] = $player['won']/$player['matches'];
    }
  }

  $pvp = rg_generator_pvp_unwrap_data($report['pvp'], $winrates, false);

  foreach ($pvp as $srcid => &$pvp_context) {
    if (isset($vars['playerid']) && $vars['playerid'] != $srcid) continue;

    $dt = [
      'ms' => $winrates[ $srcid ]['matches']
    ];

    $pvp_context_cpy = $pvp_context;

    positions_ranking($pvp_context, $dt['ms']);

    uasort($pvp_context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context)['wrank'];
    $max = reset($pvp_context)['wrank'];
  
    foreach ($pvp_context as $elid => $el) {
      $pvp_context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }

    positions_ranking($pvp_context_cpy, $dt['ms']);

    uasort($pvp_context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context_cpy)['wrank'];
    $max = reset($pvp_context_cpy)['wrank'];
  
    foreach ($pvp_context_cpy as $elid => $el) {
      $pvp_context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($pvp_context[$elid]['wrank']);

      if (isset($el['expectation']) && !isset($el['deviation'])) {
        $pvp_context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $pvp_context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }
  }

  if (isset($vars['playerid'])) {
    return [
      'reference' => [
        'id' => $vars['playerid'],
        'matches' => $winrates[ $vars['playerid'] ]['matches'],
        'wins' => $report['players_additional'][ $vars['playerid'] ]['won'],
        'winrate' => round($winrates[ $vars['playerid'] ]['winrate'], 4),
      ],
      'opponents' => $pvp[ $vars['playerid'] ]
    ];
  }
  return $pvp;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('PvpOpponent', TypeDefs::obj([
    'matches' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
    'rank' => TypeDefs::num(),
    'arank' => TypeDefs::num(),
    'deviation' => TypeDefs::num(),
    'deviation_pct' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('PvpReference', TypeDefs::obj([
    'id' => TypeDefs::int(),
    'matches' => TypeDefs::int(),
    'wins' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('PvpResult', TypeDefs::oneOf([
    TypeDefs::mapOfIdKeys('PvpOpponent'),
    TypeDefs::obj([
      'reference' => 'PvpReference',
      'opponents' => TypeDefs::mapOfIdKeys('PvpOpponent')
    ])
  ]));
}

<?php 

#[Endpoint(name: 'party_graph')]
#[Description('Players party/combo graph nodes and pairs, optionally per region')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'PartyGraphResult')]
class PartyGraph extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  $pairs = $context['players_parties_graph'] 
      ?? $context['players_combo_graph'];
  
  $context_pickban = $report['players_additional'];

  $limiter = $context['settings']['limiter_graph']
      ?? $context['settings']['limiter_combograph'];

  $max_wr = 0; $max_games = 0;
  foreach($pairs as $combo) {
    $diff = abs(($combo['winrate'] ?? $combo['wins']/$combo['matches'])-0.5);
    $max_wr = $diff > $max_wr ? $diff : $max_wr;
    $max_games = $combo['matches'] > $max_games ? $combo['matches'] : $max_games;
  }
  $max_wr *= 2;

  $nodes = [];
  $counter = 0; $endp = sizeof($context_pickban)*0.35;
  foreach($context_pickban as $elid => $el) {
    if($counter++ >= $endp && !has_pair($elid, $pairs)) {
        continue;
    }
    $nodes[] = [
      "player_id" => $elid,
      "matches" => $el['matches'],
      "winrate" => $el['won'] / $el['matches'],
    ];
  }

  $res = [
    "limiter" => $limiter,
    "max_wr" => $max_wr,
    "max_games" => $max_games,
    "nodes" => $nodes,
    "pairs" => $pairs
  ];

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('PartyGraphResult', TypeDefs::obj([
    'limiter' => TypeDefs::int(),
    'max_wr' => TypeDefs::num(),
    'max_games' => TypeDefs::int(),
    'nodes' => TypeDefs::arrayOf(TypeDefs::obj([])),
    'pairs' => TypeDefs::arrayOf(TypeDefs::obj([])),
  ]));
}

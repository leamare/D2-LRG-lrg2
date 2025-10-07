<?php 

$repeatVars['meta_graph'] = ['region'];

#[Endpoint(name: 'meta_graph')]
#[Description('Hero synergy graph nodes and pairs (derived from pairs), team/region/global')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'MetaGraphResult')]
class MetaGraph extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;

    if (empty($report['hero_combos_graph']) && ( isset($report['hero_pairs']) || isset($report['hph']) ) ) {
      if (!empty($report['hph']) && is_wrapped($report['hph'])) {
        $report['hph'] = unwrap_data($report['hph']);
      }
  
      if (empty($report['hero_pairs'])) {
        $context['hero_pairs'] = rg_generate_hero_pairs($report['hph'], $report['settings']['limiter_combograph']);
      }
  
      $context['hero_combos_graph'] = [];
      $dev = [];
  
      foreach ($context['hero_pairs'] as &$pair) {
        $pair['dev_pct'] = ($pair['matches'] - $pair['expectation'])/$pair['matches'];
        $dev[] = $pair['dev_pct'];
      }
      sort($dev);
      $lim = $dev[ round( count($dev)*0.6 ) ];
      
      foreach ($context['hero_pairs'] as $pair) {
        if ($pair['dev_pct'] < $lim) continue;
  
        $context['hero_combos_graph'][] = [
          'heroid1' => $pair['heroid1'],
          'heroid2' => $pair['heroid2'],
          'matches' => $pair['matches'],
          'wins' => $pair['wins'] ?? round($pair['matches']*$pair['winrate']),
          'winrate' => $pair['winrate'],
          'dev_pct' => $pair['dev_pct'],
        ];
      }
    }
  }

  $pairs = $context['hero_combos_graph'] 
      ?? $context['hero_combos_graph'] 
      ?? $context['hero_graph'] 
      ?? $context['hero_pairs'];
  
  $context_pickban = $context['pickban'];

  $limiter = $context['settings']['limiter_graph']
      ?? $context['settings']['limiter_combograph']
      ?? ceil($report['settings']['limiter_triplets'] * $context['matches_total'] / $report['random']['matches_total']);

  $max_wr = 0; $max_games = 0;
  foreach($pairs as &$combo) {
    $diff = abs(($combo['winrate'] ?? $combo['wins']/$combo['matches'])-0.5);
    $max_wr = $diff > $max_wr ? $diff : $max_wr;
    $max_games = $combo['matches'] > $max_games ? $combo['matches'] : $max_games;
  }
  $max_wr *= 2;

  foreach($context_pickban as $k => $v) {
    if(isset($v['winrate_picked'])) break;

    if($context_pickban[$k]['matches_picked'])
      $context_pickban[$k]['winrate_picked'] = $context_pickban[$k]['wins_picked'] / $context_pickban[$k]['matches_picked'];
    else
      $context_pickban[$k]['winrate_picked'] = 0;

    if($context_pickban[$k]['matches_banned'])
      $context_pickban[$k]['winrate_banned'] = $context_pickban[$k]['wins_banned'] / $context_pickban[$k]['matches_banned'];
    else
      $context_pickban[$k]['winrate_banned'] = 0;
  }

  $counter = 0; $endp = sizeof($context_pickban)*0.35;

  uasort($context_pickban, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $nodes = [];
  foreach($context_pickban as $elid => $el) {
    if($counter++ >= $endp && !has_pair($elid, $pairs)) {
        continue;
    }
    $nodes[] = [
      "hero_id" => $elid,
      "matches" => $el['matches_total'],
      "matches_picked" => $el['matches_picked'],
      "winrate_picked" => (float)$el['winrate_picked'],
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
  SchemaRegistry::register('MetaGraphResult', TypeDefs::obj([
    'limiter' => TypeDefs::int(),
    'max_wr' => TypeDefs::num(),
    'max_games' => TypeDefs::int(),
    'nodes' => TypeDefs::arrayOf(TypeDefs::obj([])),
    'pairs' => TypeDefs::arrayOf(TypeDefs::obj([])),
  ]));
}

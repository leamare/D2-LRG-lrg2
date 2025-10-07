<?php 

#[Endpoint(name: 'items-progression')]
#[Description('Item progression pairs and edges for hero or total')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id or total')]
#[ReturnSchema(schema: 'ItemsProgressionResult')]
class ItemsProgression extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['progr']))
    throw new \Exception("No items data");

  $res = [];

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }
  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  
  if (isset($vars['heroid'])) {
    $hero = $vars['heroid'];
  } else {
    $hero = 'total';
  }

  if (!isset($report['items']['progr'][$hero]))
    $report['items']['progr'][$hero] = [];

  $pairs = [];
  $items = [];
  $max_wr = 0;
  $max_games = 0;

  foreach ($report['items']['progr'][$hero] as $i => $v) {
    if (empty($v) || !isset($v['total'])) unset($report['items']['progr'][$hero][$i]);
  }

  usort($report['items']['progr'][$hero], function($a, $b) {
    return $b['total'] <=> $a['total'];
  });

  if (!empty($_GET['cat'])) {
    $i = floor(count($report['items']['progr'][$hero]) * (float)($_GET['cat']));
    $med = $report['items']['progr'][$hero][ $i > 0 ? $i-1 : 0 ]['total'];
  } else {
    $med = 0;
  }

  foreach ($report['items']['progr'][$hero] as $v) {
    if ($v['total'] < $med) continue;

    $pairs[] = $v;
    
    if (!in_array($v['item1'], $items)) $items[] = $v['item1'];
    if (!in_array($v['item2'], $items)) $items[] = $v['item2'];

    if ($v['total'] > $max_games) $max_games = $v['total'];
    $base_wr = isset($report['pickban'][$hero]['winrate_picked']) ? $report['pickban'][$hero]['winrate_picked'] : 0.5;
    $diff = abs($v['winrate'] - $base_wr);
    if ($diff > $max_wr) {
      $max_wr = $diff;
    }
  }
  $max_wr *= 2;

  $res['hero'] = $vars['heroid'] ?? null;
  $res['wr_amplitude'] = $max_wr;
  $res['matches_amplitude'] = $max_games;
  $res['items'] = [];
  foreach ($items as $iid) {
    $res['items'][$iid] = [
      'purchases' => $report['items']['stats'][$hero][$iid]['purchases'],
      'median_time' => $report['items']['stats'][$hero][$iid]['median'],
      'winrate' => $report['items']['stats'][$hero][$iid]['winrate'],
    ];
  }
  $res['edges'] = $pairs;

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('ItemsProgressionEdge', TypeDefs::obj([
    'item1' => TypeDefs::int(),
    'item2' => TypeDefs::int(),
    'total' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('ItemsProgressionResult', TypeDefs::obj([
    'hero' => TypeDefs::int(),
    'wr_amplitude' => TypeDefs::num(),
    'matches_amplitude' => TypeDefs::num(),
    'items' => TypeDefs::mapOf(TypeDefs::obj([
      'purchases' => TypeDefs::int(), 'median_time' => TypeDefs::num(), 'winrate' => TypeDefs::num()
    ])),
    'edges' => TypeDefs::arrayOf('ItemsProgressionEdge'),
  ]));
}
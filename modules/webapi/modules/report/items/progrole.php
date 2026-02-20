<?php 

// $repeatVars['items-progrole'] = ['heroid', 'position'];

#[Endpoint(name: 'items-progrole')]
#[Description('Item progression per role for a hero, with builds and tree')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id or null')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Role code (core.lane)')]
#[ReturnSchema(schema: 'ItemsProgRoleResult')]
class ItemsProgRole extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints, $meta, $root;
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['progr']))
    throw new \Exception("No items data");

  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  
  if (isset($vars['heroid'])) {
    $hero = $vars['heroid'];
  } else {
    $hero = null;
  }

  if (!isset($report['items']['progrole']['data'][$hero]))
    $report['items']['progrole']['data'][$hero] = [];

  if (isset($vars['position'])) {
    if (!isset($report['items']['progrole']['data'][$hero][ $vars['position'] ]))
      return [];
    $crole = $vars['position'];
  } else {
    $crole = array_keys($report['items']['progrole']['data'][$hero])[0] ?? null;
  }



  if ($hero === null) {
    foreach ($report['items']['progrole']['data'] as $hid => $positions) {
      if (empty($hid)) continue;
      $res[] = [
        'hero' => $hid,
        'positions' => array_keys($positions),
      ];
    }
  } else {
    if ($crole === null) return [];

    $data = [];

    foreach ($report['items']['progrole']['data'][$hero][$crole] as $elem) {
      $data[] = array_combine($report['items']['progrole']['keys'], $elem);
    }

    $pbdata = $report['pickban'][$hero] ?? null;

    $pairs = [];
    $items = [];
    $items_matches = []; $items_matches_1 = [];
    $max_wr = 0;
    $max_games = 0;

    usort($data, function($a, $b) {
      return $b['total'] <=> $a['total'];
    });
  
    if (!empty($_GET['cat'])) {
      $i = floor(count($data) * (float)($_GET['cat']));
      $med = $report['items']['progr'][$hero][ $i > 0 ? $i-1 : 0 ]['total'];
    } else {
      $med = 0;
    }
  
    foreach ($data as $v) {
      if ($v['total'] < $med) continue;
  
      $pairs[] = $v;
      
      if (!in_array($v['item1'], $items)) {
        $items[] = $v['item1'];
      }
      
      if (!isset($items_matches_1[ $v['item1'] ])) {
        $items_matches_1[ $v['item1'] ] = 0;
      }
      if (!isset($items_matches[ $v['item1'] ])) {
        $items_matches[ $v['item1'] ] = 0;
      }
      $items_matches_1[ $v['item1'] ] += $v['total'];
      
      if (!isset($items_matches[ $v['item2'] ])) {
        $items_matches[ $v['item2'] ] = 0;
      }
      $items_matches[ $v['item2'] ] += $v['total'];
  
      if (!in_array($v['item2'], $items)) {
        $items[] = $v['item2'];
      }
  
      if ($v['total'] > $max_games) $max_games = $v['total'];
      $diff = abs($v['winrate']-($pbdata ? $pbdata['winrate_picked'] : 0.5));
      if ($diff > $max_wr) {
        $max_wr = $diff;
      }
    }
    $max_wr *= 2;

    foreach ($items_matches as $iid => $v) {
      $items_matches[$iid] = max($items_matches[$iid] ?? 0, $items_matches_1[$iid] ?? 0);
    }
    unset($items_matches_1);
  
    $res['hero'] = $hero ?? null;
    $res['position'] = $crole;
    $res['wr_amplitude'] = $max_wr;
    $res['matches_amplitude'] = $max_games;
    $res['items'] = [];
    foreach ($items as $iid) {
      $res['items'][$iid] = [
        'purchases' => $report['items']['stats'][$hero][$iid]['purchases'],
        'role' => $items_matches[$iid],
        'median_time' => $report['items']['stats'][$hero][$iid]['median'],
        'winrate' => $report['items']['stats'][$hero][$iid]['winrate'],
      ];
    }
    $res['edges'] = $pairs;
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('ItemsProgRoleBuild', TypeDefs::obj([
    'build' => TypeDefs::arrayOf(TypeDefs::int()),
    'matches' => TypeDefs::num(),
    'winrate' => TypeDefs::num(),
    'lane_wr' => TypeDefs::num(),
    'ratio' => TypeDefs::num(),
    'factor' => TypeDefs::str(),
  ]));

  SchemaRegistry::register('ItemsProgRoleResult', TypeDefs::oneOf([
    TypeDefs::arrayOf(TypeDefs::obj(['hero' => TypeDefs::int(), 'positions' => TypeDefs::arrayOf(TypeDefs::str())])),
    TypeDefs::obj([
      'hero' => TypeDefs::int(),
      'role' => TypeDefs::str(),
      'stats' => TypeDefs::obj([]),
      'build' => TypeDefs::arrayOf('ItemsProgRoleBuild'),
      'facets' => TypeDefs::arrayOf(TypeDefs::obj([])),
      'tree' => TypeDefs::obj([]),
      'starting_items' => TypeDefs::obj([
        'stats' => TypeDefs::mapOf(TypeDefs::obj(['wins' => TypeDefs::num(), 'matches' => TypeDefs::num(), 'lane_wins' => TypeDefs::num()])),
        'builds' => TypeDefs::arrayOf(TypeDefs::obj([]))
      ])
    ])
  ]));
}
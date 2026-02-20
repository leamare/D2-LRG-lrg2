<?php 
#[Endpoint(name: 'items-heroes')]
#[Description('For a selected item, show total and per-hero rankings')]
#[ModlineVar(name: 'item', schema: ['type' => 'integer'], description: 'Item id')]
#[ReturnSchema(schema: 'ItemsHeroesResult')]
class ItemsHeroes extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  global $endpoints, $meta;
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['stats']))
    throw new \Exception("No items stats data");

  $res = [];

  if (!isset($vars['item']))
    throw new \Exception("Need to select an item for items-heroes.");

  $item = $vars['item'];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  if (!isset($report['items']['stats']['total'][$item]))
    return [
      'item' => $item,
      'total' => null,
      'heroes' => null
    ];

  items_ranking($report['items']['stats']['total']);

  uasort($report['items']['stats']['total'], function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($report['items']['stats']['total'])['wrank'];
  $max = reset($report['items']['stats']['total'])['wrank'];

  foreach ($report['items']['stats']['total'] as $id => $el) {
    $report['items']['stats']['total'][$id]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    unset($report['items']['stats']['total'][$id]['wrank']);
  }

  $total = $report['items']['stats']['total'][$item];
  
  if ($meta['items_full'][$item]['cost'] > 0) {
    $total['grad_per_gold'] = $total['grad'] * 100000 / $meta['items_full'][$item]['cost'];
  } else {
    $total['grad_per_gold'] = 0;
  }
  
  unset($report['items']['stats']['total']);

  $heroes = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    if (!empty($items[$item]))
      $heroes[$hero] = $items[$item];
  }

  items_ranking($heroes);

  uasort($heroes, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($heroes)['wrank'];
  $max = reset($heroes)['wrank'];

  foreach ($heroes as $id => $el) {
    $heroes[$id]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    unset($heroes[$id]['wrank']);
    unset($heroes[$id]['category']);
    
    if ($meta['items_full'][$item]['cost'] > 0) {
      $heroes[$id]['grad_per_gold'] = $heroes[$id]['grad'] * 100000 / $meta['items_full'][$item]['cost'];
    } else {
      $heroes[$id]['grad_per_gold'] = 0;
    }
  }

  // $increment = 100 / sizeof($heroes); $i = 0;

  // foreach ($heroes as $id => $el) {
  //   if(isset($last) && $el == $last) {
  //     $i++;
  //     $ranks[$id] = $last_rank;
  //   } else
  //     $ranks[$id] = 100 - $increment*$i++;
  //   $heroes[$id]['rank'] = round($ranks[$id], 2);
  //   unset($heroes[$id]['category']);
  //   $last = $el;
  //   $last_rank = $ranks[$id];
  // }
  // unset($last);

  $res['item'] = $item;
  $res['total'] = $total;
  $res['heroes'] = $heroes;

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('ItemsHeroesResult', TypeDefs::obj([
    'item' => TypeDefs::int(),
    'total' => TypeDefs::obj([]),
    'heroes' => TypeDefs::mapOf('ItemStatsLine'),
  ]));
}
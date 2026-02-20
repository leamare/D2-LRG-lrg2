<?php 

#[Endpoint(name: 'items-stats')]
#[Description('Per-hero or total item stats with ranking and filters')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id or total')]
#[GetParam(name: 'item_cat', required: false, schema: ['type' => 'array','items' => ['type' => 'string']], description: 'Item category filters')]
#[ReturnSchema(schema: 'ItemsStatsResult')]
class ItemsStats extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints, $meta;
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['stats']))
    throw new \Exception("No items stats data");

  $res = [];

  $hero = $vars['heroid'] ?? 'total';

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  if (!empty($vars['item_cat']) && !in_array('all', $vars['item_cat'])) {
    $items_allowed = [];
    $items_categories = [];
    $meta['item_categories'];
    foreach ($vars['item_cat'] as $ic) {
      if (!isset($meta['item_categories'][$ic])) continue;
      $items_categories[] = $ic;
      $items_allowed = array_merge($items_allowed, $meta['item_categories'][$ic]);
    }
    $items_allowed = array_unique($items_allowed);
  } else {
    $items_allowed = null;
    $items_categories = ['all'];
  }

  if (!isset($report['items']['stats'][$hero]))
    $report['items']['stats'][$hero] = [];

  foreach ($report['items']['stats'][$hero] as $iid => $v) {
    if (empty($v)) unset($report['items']['stats'][$hero][$iid]);
  }

  if (!empty($report['items']['stats'][$hero])) {
    items_ranking($report['items']['stats'][$hero]);

    uasort($report['items']['stats'][$hero], function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });

    $min = end($report['items']['stats'][$hero])['wrank'];
    $max = reset($report['items']['stats'][$hero])['wrank'];

    foreach ($report['items']['stats'][$hero] as $id => $el) {
      $report['items']['stats'][$hero][$id]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($report['items']['stats'][$hero][$id]['wrank']);
    }
  }

  $res['categories'] = $items_categories;
  $res['allowed_items'] = $items_allowed;
  $res['hero'] = $vars['heroid'] ?? null;
  if (isset($vars['heroid'])) {
    $res['hero_pickban'] = $report['pickban'][$hero] ?? [];
  } else {
    $res['hero_pickban'] = null;
  }
  if (empty($items_allowed)) {
    $res['items'] = $report['items']['stats'][$hero];
  } else {
    $res['items'] = [];
    foreach($report['items']['stats'][$hero] as $iid => $line) {
      if (in_array($iid, $items_allowed)) $res['items'][$iid] = $line;
    }
  }

  foreach ($res['items'] as $iid => &$item) {
    if ($meta['items_full'][$iid]['cost'] > 0) {
      $item['grad_per_gold'] = $item['grad'] * 100000 / $meta['items_full'][$iid]['cost'];
    } else {
      $item['grad_per_gold'] = 0;
    }
  }
  unset($item);

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('ItemStatsLine', TypeDefs::obj([
    'purchases' => TypeDefs::int(),
    'wo_wr' => TypeDefs::num(),
    'early_wr' => TypeDefs::num(),
    'late_wr' => TypeDefs::num(),
    'rank' => TypeDefs::num(),
    'grad_per_gold' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('ItemsStatsResult', TypeDefs::obj([
    'categories' => TypeDefs::arrayOf(TypeDefs::str()),
    'allowed_items' => TypeDefs::arrayOf(TypeDefs::int()),
    'hero' => TypeDefs::int(),
    'hero_pickban' => TypeDefs::obj([]),
    'items' => TypeDefs::mapOf('ItemStatsLine'),
  ]));
}
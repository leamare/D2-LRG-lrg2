<?php 

#[Endpoint(name: 'items-critical')]
#[Description('Critical item timings per hero or total')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id or total')]
#[GetParam(name: 'item_cat', required: false, schema: ['type' => 'array','items' => ['type' => 'string']], description: 'Item category filters')]
#[ReturnSchema(schema: 'ItemsCriticalResult')]
class ItemsCritical extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints, $meta;
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['stats']))
    throw new UserInputException("No items stats data");

  $res = [];

  $hero = $vars['heroid'] ?? 'total';

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $neutral_items = array_unique(
    array_merge(
      $meta['item_categories']['neutral_tier_1'],
      $meta['item_categories']['neutral_tier_2'],
      $meta['item_categories']['neutral_tier_3'],
      $meta['item_categories']['neutral_tier_4'],
      $meta['item_categories']['neutral_tier_5']
    )
  );

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

  if (isset($vars['heroid'])) {
    $res['hero_pickban'] = $report['pickban'][$hero] ?? [];
    $winrate = $report['pickban'][$hero]['winrate_picked'];
  } else {
    $res['hero_pickban'] = null;
    $winrate = 0.5;
  }

  $items = array_filter($report['items']['stats'][$hero], function($v, $k) use (&$neutral_items) {
    return ( !in_array($k, $neutral_items) || empty($v) ) && $v['grad'] < -0.01;
  }, ARRAY_FILTER_USE_BOTH);

  if (!empty($items_allowed)) {
    $items = array_filter($items, function($v, $k) use (&$items_allowed) {
      return in_array($k, $items_allowed) && !empty($v);
    }, ARRAY_FILTER_USE_BOTH);
  }

  $items_sz = count($items);
  uasort($items, function($a, $b) {
    return $b['purchases'] <=> $a['purchases'];
  });

  $items = array_psplice($items, 0, round($items_sz*0.75));

  $items_rc = [];

  foreach ($items as $iid => $line) {
    $items_rc[$iid] = [
      'purchases' => $line['purchases'],
      'prate' => $line['prate'],
      'grad' => $line['grad'],
      'q1' => $line['q1'],
      'median' => $line['median'],
      'winrate' => $line['winrate'],
      'early_wr' => $line['early_wr'],
      'critical_time' => round( $line['q1'] - 60*($line['early_wr'] - $winrate)/$line['grad'] ),
    ];
  }


  $res['categories'] = $items_categories;
  $res['allowed_items'] = $items_allowed;
  $res['hero'] = $vars['heroid'] ?? null;

  $res['items'] = $items_rc;

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('CriticalLine', TypeDefs::obj([
    'purchases' => TypeDefs::int(),
    'prate' => TypeDefs::num(),
    'grad' => TypeDefs::num(),
    'q1' => TypeDefs::num(),
    'median' => TypeDefs::num(),
    'winrate' => TypeDefs::num(),
    'early_wr' => TypeDefs::num(),
    'critical_time' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('ItemsCriticalResult', TypeDefs::obj([
    'categories' => TypeDefs::arrayOf(TypeDefs::str()),
    'allowed_items' => TypeDefs::arrayOf(TypeDefs::int()),
    'hero' => TypeDefs::int(),
    'hero_pickban' => TypeDefs::obj([]),
    'items' => TypeDefs::mapOf('CriticalLine'),
  ]));
}
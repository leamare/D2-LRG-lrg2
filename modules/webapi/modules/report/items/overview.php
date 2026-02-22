<?php 

#[Endpoint(name: 'items-overview')]
#[Description('Overview of best/worst items and gradients')]
#[ReturnSchema(schema: 'ItemsOverviewResult')]
class ItemsOverview extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints, $meta;
  if (!isset($report['items']) || empty($report['items']['pi']))
    throw new UserInputException("No items data");

  $meta['item_categories'];

  $skip_items = array_unique(
    array_merge(
      $meta['item_categories']['early'], 
      $meta['item_categories']['neutral_tier_1'],
      $meta['item_categories']['neutral_tier_2'],
      $meta['item_categories']['neutral_tier_3'],
      $meta['item_categories']['neutral_tier_4'],
      $meta['item_categories']['neutral_tier_5']
    )
  );

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  if (is_wrapped($report['items']['ph'])) {
    $report['items']['ph'] = unwrap_data($report['items']['ph']);
  }
  if (is_wrapped($report['items']['pi'])) {
    $report['items']['pi'] = unwrap_data($report['items']['pi']);
  }
  if (isset($report['items']['records'])) {
    if (is_wrapped($report['items']['records'])) {
      $report['items']['records'] = unwrap_data($report['items']['records']);
    }
  }

  $res = [];

  $limit = $report['settings']['overview_items_limit'] ?? 10; 

  $best = [];
  $worst = [];
  uasort($report['items']['stats']['total'], function($a, $b) {
    return ( max($a['early_wr'], $a['late_wr'])-$a['wo_wr'] ) <=> ( max($b['early_wr'], $b['late_wr'])-$b['wo_wr'] );
  });
  $keys = array_keys($report['items']['stats']['total']);
  $sz = sizeof($keys);

  for ($i = 0, $j = 0; $i < $sz && $j < $limit; $i++) {
    if (empty($keys[$i]) || empty($report['items']['stats']['total'][ $keys[$i] ])) continue;
    if (in_array($keys[$i], $skip_items)) continue;
    if ($report['items']['stats']['total'][ $keys[$i] ]['purchases'] <= $report['items']['ph']['total']['q1']) continue;
    $worst[ $keys[$i] ] = $report['items']['stats']['total'][ $keys[$i] ];
    $j++;
  }

  for ($i = $sz-1, $j = 0; $i > 0 && $j < $limit; $i--) {
    if (empty($keys[$i]) || empty($report['items']['stats']['total'][ $keys[$i] ])) continue;
    if (in_array($keys[$i], $skip_items)) continue;
    if ($report['items']['stats']['total'][ $keys[$i] ]['purchases'] <= $report['items']['ph']['total']['q1']) continue;
    $best[ $keys[$i] ] = $report['items']['stats']['total'][ $keys[$i] ];
    $j++;
  }

  $res['total_best'] = $best;
  $res['total_worst'] = $worst;

  unset($report['items']['stats']['total']);

  $gradients = [];
  $best_a = [];
  $worst_a = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    foreach ($items as $iid => $line) {
      if (empty($line)) continue;
      if (in_array($iid, $skip_items)) continue;
      if ($line['purchases'] <= $report['items']['ph'][$hero]['q3'] || $line['purchases'] <= $report['items']['pi'][$iid]['q3']) continue;
      $line['hero'] = $hero;
      $line['item'] = $iid;
      $wr = max($line['early_wr'], $line['late_wr']);
      
      if ($wr > $line['wo_wr']) $best_a[] = $line;
      else $worst_a[] = $line;

      if ($line['grad'] < 0) $gradients[] = $line;
    }
  }

  uasort($best_a, function($b, $a) {
    return ( max($a['early_wr'], $a['late_wr'])-$a['wo_wr'] ) <=> ( max($b['early_wr'], $b['late_wr'])-$b['wo_wr'] );
  });
  uasort($worst_a, function($a, $b) {
    return ( max($a['early_wr'], $a['late_wr'])-$a['wo_wr'] ) <=> ( max($b['early_wr'], $b['late_wr'])-$b['wo_wr'] );
  });

  $best = array_slice($best_a, 0, $limit);
  $worst = array_slice($worst_a, 0, $limit);

  $res['heroes_best'] = $best;
  $res['heroes_worst'] = $worst;

  uasort($gradients, function($a, $b) {
    return $a['grad'] <=> $b['grad'];
  });
  $gradients = array_slice($gradients, 0, $limit);

  if (isset($report['items']['records'])) {
    foreach ($gradients as $i => $v) {
      $gradients[$i]['record'] = $report['items']['records'][ $v['item'] ][ $v['hero'] ] ?? [];
      if (!empty($report['match_participants_teams']) && !empty($gradients[$i]['record']))
        $gradients[$i]['record']['match_card_min'] = match_card_min($gradients[$i]['record']['match']);
      else 
        $gradients[$i]['record']['match_card_min'] = null;
    }
  }
  $res['gradients'] = $gradients;

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('ItemImpact', TypeDefs::obj([
    'early_wr' => TypeDefs::num(),
    'late_wr' => TypeDefs::num(),
    'wo_wr' => TypeDefs::num(),
    'purchases' => TypeDefs::int(),
    'hero' => TypeDefs::int(),
    'item' => TypeDefs::int(),
    'grad' => TypeDefs::num(),
    'record' => TypeDefs::obj([]),
  ]));

  SchemaRegistry::register('ItemsOverviewResult', TypeDefs::obj([
    'total_best' => TypeDefs::mapOf('ItemImpact'),
    'total_worst' => TypeDefs::mapOf('ItemImpact'),
    'heroes_best' => TypeDefs::arrayOf('ItemImpact'),
    'heroes_worst' => TypeDefs::arrayOf('ItemImpact'),
    'gradients' => TypeDefs::arrayOf('ItemImpact'),
  ]));
}
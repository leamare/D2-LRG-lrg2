<?php 

#[Endpoint(name: 'items-enchantments')]
#[Description('Enchantment item stats per hero and tier category; heroid=total returns aggregate')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id (defaults to total)')]
#[ReturnSchema(schema: 'ItemsEnchantmentsResult')]
class ItemsEnchantments extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints, $meta;
  if (!isset($report['items']['enchantments']))
    throw new UserInputException("No enchantments data");
  
  if (is_wrapped($report['items']['enchantments'])) {
    $report['items']['enchantments'] = unwrap_data($report['items']['enchantments']);
  }

  $selected_hid = $vars['heroid'] ?? 'total';
  
  if ($selected_hid !== 'total' && !isset($report['items']['enchantments'][$selected_hid])) {
    return [];
  }

  $data = $report['items']['enchantments'][$selected_hid] ?? [];


  $category_ids = array_keys($data);
  sort($category_ids);
  
  if (in_array(0, $category_ids)) {
    $category_ids = array_diff($category_ids, [0]);
    array_unshift($category_ids, 0);
  }

  $res = [];
  $res['hero'] = $selected_hid;
  $res['categories'] = [];
  
  $category_names = [];
  foreach (array_keys($meta['item_categories']) as $i => $category_name) {
    if (strpos($category_name, 'enhancement_tier_') === 0) {
      $category_names[$i] = $category_name;
    }
  }
  $category_names[0] = 'enhancement_tier_total';

  foreach ($category_ids as $category_id) {
    if (empty($data[$category_id])) continue;

    $items = $data[$category_id];
    $cat_name = 'enhancement_tier_total';
    if ($category_id != 0 && isset($category_names[$category_id])) {
      $cat_name = $category_names[$category_id];
    }

    $category_data = [
      'id' => $category_id,
      'name' => $cat_name,
      'items' => []
    ];

    foreach ($items as $item_id => $item_data) {
      if (empty($item_data) || $item_data['matches'] + $item_data['matches_wo'] == 0) {
        continue;
      }
      
      $prate = $item_data['matches'] / ($item_data['matches'] + $item_data['matches_wo']);
      $wr_diff = $item_data['wr'] - $item_data['wr_wo'];
      
      $category_data['items'][] = [
        'item_id' => $item_id,
        'matches' => $item_data['matches'],
        'prate' => round($prate, 4),
        'wr' => round($item_data['wr'], 4),
        'wr_diff' => round($wr_diff, 4),
      ];
    }

    $res['categories'][] = $category_data;
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('EnchantmentItem', TypeDefs::obj([
    'item_id' => TypeDefs::int(),
    'matches' => TypeDefs::int(),
    'prate' => TypeDefs::num(),
    'wr' => TypeDefs::num(),
    'wr_diff' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('EnchantmentCategory', TypeDefs::obj([
    'id' => TypeDefs::int(),
    'name' => TypeDefs::str(),
    'items' => TypeDefs::arrayOf('EnchantmentItem'),
  ]));

  SchemaRegistry::register('ItemsEnchantmentsResult', TypeDefs::obj([
    'hero' => TypeDefs::any(), // integer hero id or "total"
    'categories' => TypeDefs::arrayOf('EnchantmentCategory'),
  ]));
}

<?php

function enchantments_finalize_category(array &$items): void {
  if (empty($items)) return;

  $wins_total = 0;
  $matches_total = 0;

  foreach ($items as $data) {
    if (!is_array($data)) continue;
    $wins_total += (int)($data['wins'] ?? 0);
    $matches_total += (int)($data['matches'] ?? 0);
  }

  if ($matches_total <= 0) return;

  foreach ($items as &$data) {
    if (!is_array($data)) continue;

    $m = (int)($data['matches'] ?? 0);
    $w = (int)($data['wins'] ?? 0);
    $wo_m = $matches_total - $m;

    $data['wr'] = $m > 0 ? round($w / $m, 4) : 0.0;
    $data['matches_wo'] = $wo_m;
    $data['wr_wo'] = $wo_m > 0 ? round(($wins_total - $w) / $wo_m, 4) : 0.0;
  }
  unset($data);
}

function enchantment_item_passes_filter(array $item_data, int $min_matches = 5): bool {
  return ((int)($item_data['matches'] ?? 0)) >= $min_matches;
}

function enchantments_finalize_hero_categories(array &$hero_data, int $min_matches = 5): void {
  foreach ($hero_data as $cat_id => &$items) {
    if (!is_array($items)) continue;
    enchantments_finalize_category($items);
    if ($cat_id == 0) continue;
    $items = array_filter($items, function($item_data) use ($min_matches) {
      return is_array($item_data) && enchantment_item_passes_filter($item_data, $min_matches);
    });
  }
  unset($items);
}

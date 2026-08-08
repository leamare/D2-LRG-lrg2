<?php

function sb_unwrap_role(&$section, $rid) {
  if (!isset($section[$rid])) return [];
  if (is_wrapped($section[$rid])) $section[$rid] = unwrap_data($section[$rid]);
  return $section[$rid];
}

function sb_unwrap_rows(&$section, $rid) {
  $data = sb_unwrap_role($section, $rid);
  foreach ($data as $hid => $rows) {
    if (!is_array($rows)) continue;
    $data[$hid] = array_values(array_filter($rows, function($r) { return !empty($r); }));
  }
  return $data;
}

function sb_priority_ranking(&$rows) {
  $total = array_sum(array_column($rows, 'matches'));
  if (!$total) return;

  foreach ($rows as $k => $r) {
    $ratio = $r['matches'] / $total;
    $rows[$k]['wrank'] = wilson_rating($r['wins'], $r['matches'], 1 - $ratio);
  }

  $min = min(array_column($rows, 'wrank'));
  $max = max(array_column($rows, 'wrank'));

  foreach ($rows as $k => $r) {
    $rows[$k]['rank'] = $max > $min ? round(100 * ($r['wrank'] - $min) / ($max - $min), 2) : 100;
    unset($rows[$k]['wrank']);
  }
}

function sb_decode_priority($rows) {
  foreach ($rows as &$row) {
    if (($row['priority'] ?? null) !== null) $row['priority'] = json_decode($row['priority'], true);
  }
  unset($row);
  return $rows;
}

function sb_decode_featured_build($rows) {
  foreach ($rows as &$row) {
    if (($row['featured_build'] ?? null) !== null) $row['featured_build'] = json_decode($row['featured_build'], true);
  }
  unset($row);
  return $rows;
}

function sb_decode_skill_stats($rows) {
  foreach ($rows as &$row) {
    if (($row['skill_stats'] ?? null) !== null) $row['skill_stats'] = json_decode($row['skill_stats'], true);
  }
  unset($row);
  return $rows;
}

function sb_talent_ranking(&$options) {
  $tier_total = array_sum(array_column($options, 'matches'));
  if (!$tier_total) return;

  foreach ($options as $k => $o) {
    if (!$o['matches']) { $options[$k]['rank'] = null; continue; }
    $ratio = $o['matches'] / $tier_total;
    $options[$k]['wrank'] = wilson_rating($o['wins'], $o['matches'], 1 - $ratio);
  }

  $ranked = array_filter($options, function($o) { return isset($o['wrank']); });
  if (empty($ranked)) return;

  $min = min(array_column($ranked, 'wrank'));
  $max = max(array_column($ranked, 'wrank'));

  foreach ($options as $k => $o) {
    if (!isset($o['wrank'])) continue;
    $options[$k]['rank'] = $max > $min ? round(100 * ($o['wrank'] - $min) / ($max - $min), 2) : 100;
    unset($options[$k]['wrank']);
  }
}

function sb_talent_lr($hero, $tier, $options) {
  global $meta;

  $canonical = $meta['heroes_spells'][$hero]['talents'][$tier] ?? null;
  $left = null; $right = null; $rest = [];

  if ($canonical) {
    $left_tag = $canonical[0] ?? null;
    $right_tag = $canonical[1] ?? null;
    foreach ($options as $o) {
      $tag = spell_tag($o['talent']);
      if ($tag === $left_tag && $left === null) { $left = $o; continue; }
      if ($tag === $right_tag && $right === null) { $right = $o; continue; }
      $rest[] = $o;
    }
  } else {
    $rest = $options;
  }

  if ($left === null && !empty($rest)) $left = array_shift($rest);
  if ($right === null && !empty($rest)) $right = array_shift($rest);

  return [ $left, $right, $rest ];
}

function sb_talent_top2($hero, $tier, $options) {
  global $meta;

  $top = array_slice($options, 0, 2);
  if (count($top) < 2) return [ $top[0] ?? null, null ];

  $canonical = $meta['heroes_spells'][$hero]['talents'][$tier] ?? null;
  $left_tag = $canonical[0] ?? null;

  if ($left_tag !== null && spell_tag($top[1]['talent']) === $left_tag && spell_tag($top[0]['talent']) !== $left_tag) {
    return [ $top[1], $top[0] ];
  }

  return $top;
}

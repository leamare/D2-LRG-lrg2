<?php

function rg_nw_fields(array $vals, bool $chart = false, ?float $min = null, ?float $max = null): array {
  $out = labeled_values($vals, [5, 10, 15, 20], 'nw_');
  if ($chart) $out['nw_chart'] = rg_nw_sparkline($vals, $min, $max);
  return $out;
}

function rg_nw_rows_extent(array $rows): array {
  $min = null;
  $max = null;
  foreach ($rows as $vals) {
    if (!is_array($vals)) continue;
    foreach ($vals as $v) {
      if (!is_numeric($v)) continue;
      $v = (float)$v;
      if ($min === null || $v < $min) $min = $v;
      if ($max === null || $v > $max) $max = $v;
    }
  }
  if ($min === null) return [0.0, 1.0];
  if ($max <= $min) {
    $min -= 1;
    $max += 1;
  }
  return [$min, $max];
}

function rg_nw_sparkline(array $vals, ?float $min = null, ?float $max = null): string {
  $n = count($vals);
  $nums = [];
  foreach ($vals as $v) {
    if (is_numeric($v)) $nums[] = (float)$v;
  }
  if (count($nums) < 2) return '';
  $w = 72; $h = 18; $pad = 1;
  if ($min === null) $min = min($nums);
  if ($max === null) $max = max($nums);
  if ($max <= $min) {
    $min -= 1;
    $max += 1;
  }
  $span = $max - $min;
  $pts = [];
  foreach ($vals as $i => $v) {
    if (!is_numeric($v)) continue;
    $x = $pad + ($n <= 1 ? 0 : $i * ($w - 2 * $pad) / max(1, $n - 1));
    $y = $h - $pad - (($v - $min) / $span) * ($h - 2 * $pad);
    $pts[] = round($x, 1).','.round($y, 1);
  }
  return '<svg class="nw-spark" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'" aria-hidden="true">'.
    '<polyline fill="none" stroke="currentColor" stroke-width="1.5" points="'.implode(' ', $pts).'"/></svg>';
}

function rg_nw_merge_inject(array $inject, array $rows): array {
  if (!$rows) return $inject;
  [$min, $max] = rg_nw_rows_extent($rows);
  $blank = rg_nw_fields(array_fill(0, 4, null), true, $min, $max);
  if (!$inject) {
    $out = [];
    foreach ($rows as $id => $vals) $out[$id] = rg_nw_fields($vals, true, $min, $max);
    return $out;
  }
  foreach ($inject as $id => $row) {
    $inject[$id] = array_merge($blank, $row, isset($rows[$id]) ? rg_nw_fields($rows[$id], true, $min, $max) : []);
  }
  foreach ($rows as $id => $vals) {
    if (!isset($inject[$id])) $inject[$id] = array_merge($blank, rg_nw_fields($vals, true, $min, $max));
  }
  return $inject;
}

function rg_nw_profile_table(string $id, array $rows): string {
  if (!$rows) return '';
  $html = "<table id=\"$id\" class=\"list\"><caption>".locale_string('networth')."</caption><thead><tr><th></th>";
  foreach ([5, 10, 15, 20] as $m) {
    $html .= "<th>".locale_string("nw_$m")."</th>";
  }
  $html .= "<th>".locale_string('nw_chart')."</th></tr></thead><tbody>";
  foreach ($rows as $label => $vals) {
    if (!is_array($vals)) continue;
    $html .= "<tr><td>".htmlspecialchars((string)$label)."</td>";
    for ($i = 0; $i < 4; $i++) {
      $v = $vals[$i] ?? null;
      $html .= "<td>".(is_numeric($v) ? number_format((float)$v, 0) : "-")."</td>";
    }
    $html .= "<td>".rg_nw_sparkline($vals)."</td></tr>";
  }
  return $html."</tbody></table>";
}

function rg_nw_bucket(string $key) {
  global $report;
  $b = $report['nw_at'][$key] ?? [];
  return is_array($b) ? $b : [];
}

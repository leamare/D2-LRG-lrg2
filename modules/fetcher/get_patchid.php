<?php

function get_patchid($date, &$meta, $override = null) {
  if ($override !== null && $override !== '' && is_numeric($override)) {
    return (int)$override;
  }

  $meta['patchdates'];

  $major = 0;

  foreach ($meta['patchdates'] as $patch => $data) {
    if ($date < $data['main']) break;

    $major = $patch;
  }

  sort($meta['patchdates'][$major]['dates']);
  for ($i = 0, $sz = sizeof($meta['patchdates'][$major]['dates']); $i < $sz; $i++) {
    if ($date < $meta['patchdates'][$major]['dates'][$i]) break;
  }
  return $major*100 + $i;
}

function lrg_resolve_patchid($matchdata, &$meta) {
  $override = $matchdata['lrg_patch_id'] ?? null;
  $date = $matchdata['start_time'] ?? $matchdata['start_date'] ?? 0;
  return get_patchid($date, $meta, $override);
}

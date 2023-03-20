<?php

function get_patchid($date, &$meta) {
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

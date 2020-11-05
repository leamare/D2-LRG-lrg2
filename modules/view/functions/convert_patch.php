<?php

function convert_patch($pid) {
  global $meta;

  $meta['versions'];

  $patch_hi = (int) ($pid/100);
  $patch_lo = $pid % 100;

  if(!isset($meta->versions[$patch_hi])) {
    for($i = $patch_hi; $i > 0; $i--) {
      if(isset($meta['versions'][$i])) {
        break;
      }
    }
    $diff = $patch_hi - $i;
    $parent_patch = explode(".", $meta->versions[$i]);
    
    $parent_patch[1] = (int)$parent_patch[1] + $diff;
    if ($parent_patch[1] < 10)
        $parent_patch[1] = "0".$parent_patch[1];
    $meta->versions[$patch_hi] = implode(".", $parent_patch);
  }

  $major_ver = $meta['versions'][ $patch_hi ];

  return $major_ver.(
    $patch_lo ? chr( ord('a') + $patch_lo ) : ""
  );
}
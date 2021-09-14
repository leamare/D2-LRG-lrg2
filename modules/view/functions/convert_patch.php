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

function unconvert_patch($patchstr) {
  global $meta;

  $meta['versions'];
  $verchars = ['.', '/'];
  for ($i = 0; $i < 10; $i++) {
    $verchars[] = "$i";
  }

  $len = strlen($patchstr);
  $letter = $patchstr[$len-1];
  if (in_array($letter, $verchars)) {
    $letter = null;
  } else {
    $patchstr = substr($patchstr, 0, $len-1);
  }

  $lastpid = 0; $diff = 0;
  $patchblocks = explode('.', $patchstr, 2);
  if (strpos($patchblocks[1], '/') !== false) {
    $patchblocks[1] = explode('/', $patchblocks[1])[0];
  }

  foreach ($meta['versions'] as $pid => $str) {
    $ps = explode('.', $str, 2);
    if (strpos($ps[1], '/') !== false) {
      $ps[1] = explode('/', $ps[1])[0];
    }
    if ($ps[0] != $patchblocks[0]) continue;
    if ($ps[1] > $patchblocks[1]) break;

    $lastpid = $pid;
    $diff = $patchblocks[1] - $ps[1];
  }

  return ($lastpid + $diff) * 100 + ($letter ? ord($letter)-ord('a') : 0);
}
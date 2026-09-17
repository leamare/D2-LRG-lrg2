<?php

function flat_rscandir($dirname) {
  if ($dirname === null || $dirname === '') return [];
  $dir = @scandir($dirname);
  if ($dir === false) return [];
  $subfolders = [];

  foreach ($dir as $k => &$v) {
    if($v[0] == ".") {
      unset($dir[$k]);
      continue;
    }
    if(is_dir($dirname."/".$v)) {
      $folder = flat_rscandir($dirname."/".$v);
      foreach($folder as $file) {
        $subfolders[] = $v."/".$file;
      }
      unset($dir[$k]);
    }
  }

  $dir = array_merge($dir, $subfolders);

  sort($dir);

  return $dir;
}

function rscandir($dirname) {
  if ($dirname === null || $dirname === '') return [];
  $dir = @scandir($dirname);
  if ($dir === false) return [];
  foreach ($dir as $k => &$v) {
    if($v[0] == ".") {
      unset($dir[$k]);
      continue;
    }
    if(is_dir($dirname."/".$v)) {
      $v = [
        "name" => $v,
        "files" => rscandir($dirname."/".$v)
      ];
    }
  }
  sort($dir);

  return $dir;
}

?>

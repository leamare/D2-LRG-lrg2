<?php
include_once("modules/commons/recursive_scandir.php");
include_once("modules/view/functions/report_descriptor.php");

# open cachefile

$cache_update = false;

if (file_exists($cache_file)) {
  try {
    $cache = file_get_contents($cache_file);
    $cache = json_decode($cache, true);
    //var_dump(!isset($cache['files']) || !isset($cache['reps']) || !isset($cache['ver']));
    if(!isset($cache['files']) || !isset($cache['reps']) || !isset($cache['ver']))
      throw(new Exception("Not a cache"));
    if(compare_release_ver($cache['ver'], $lg_version) < 0) {
      throw(new Exception("Old cache version"));
    }

    foreach($cache['reps'] as &$r) {
      $r['last_update'] = $cache['files'][ $r['file'] ][2];
    }
  } catch (Exception $e) {
    $cache_update = true;
    $cache = [
      "files" => [],
      "reps" => [],
      "ver" => $lg_version
    ];
  }
} else {
  $cache_update = true;
  $cache = [
    "files" => [],
    "reps" => [],
    "ver" => $lg_version
  ];
}

?>

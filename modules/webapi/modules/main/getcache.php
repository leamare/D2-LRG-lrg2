<?php 

$endpoints['getcache'] = function($mods, $vars, &$report) use ($cache_file, $lg_version, $reports_dir, $report_mask) {
  $lightcache = true;
  include_once(__DIR__ . "/../../../view/__open_cache.php");
  include_once(__DIR__ . "/../../../view/__update_cache.php");
  return $cache;
};

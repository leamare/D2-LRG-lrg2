<?php 

$endpoints['getcache'] = function($mods, $vars, &$report) use ($cache_file) {
  $lightcache = true;
  include_once(__DIR__ . "../../view/__open_cache.php");
  return $cache;
};

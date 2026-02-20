<?php 

#[Endpoint(name: 'getcache')]
#[Description('Return cache of reports (internal helper)')]
#[ReturnSchema(schema: 'GetCacheResult')]
class GetCache extends EndpointTemplate {
public function process() {
  global $cache_file, $lg_version, $reports_dir, $report_mask, $cache;
  $lightcache = true;
  include_once(__DIR__ . "/../../../view/__open_cache.php");
  include_once(__DIR__ . "/../../../view/__update_cache.php");
  return $cache;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('GetCacheResult', TypeDefs::obj([]));
}

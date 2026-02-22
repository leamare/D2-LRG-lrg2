<?php 

#[Endpoint(name: 'raw')]
#[Description('Return full report JSON by tag')]
#[GetParam(name: 'rep', required: true, schema: ['type' => 'string'], description: 'Report tag')]
#[ReturnSchema(schema: 'RawReportResult')]
class RawReport extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  global $report_mask_search, $cache_file, $reports_dir, $lg_version;
  $leaguetag = $vars['rep'];
  if (!empty($leaguetag)) {
    $fname = $reports_dir."/".$report_mask_search[0].$leaguetag.$report_mask_search[1];
    if(!file_exists($fname)) {
      $lightcache = true;
      include(__DIR__ . "/../../../view/__open_cache.php");
      if(isset($cache['reps'][$leaguetag]['file'])) {
        $fname = $reports_dir."/".$cache['reps'][$leaguetag]['file'];
      }
    }
    $report = file_get_contents($fname);
    if (!$report) throw new UserInputException("Can't open $leaguetag, probably no such report\n");
    $report = json_decode($report, true);
  }
  return [
    "leaguetag" => $leaguetag,
    "report" => $report
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('RawReportResult', TypeDefs::obj([
    'leaguetag' => TypeDefs::str(),
    'report' => TypeDefs::obj([]),
  ]));
}

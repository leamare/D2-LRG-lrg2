<?php 

$endpoints['raw'] = function($mods, $vars, &$report) use ($report_mask_search, $cache_file) {
  $leaguetag = $vars['rep'];
  if (!empty($leaguetag)) {
    $fname = $reports_dir."/".$report_mask_search[0].$leaguetag.$report_mask_search[1];
    if(!file_exists($fname)) {
      $lightcache = true;
      include(__DIR__ . "../../view/__open_cache.php");
      if(isset($cache['reps'][$leaguetag]['file'])) {
        $fname = $cache['reps'][$leaguetag]['file'];
      }
    }
    $report = file_get_contents($fname);
    if (!$report) throw new \Exception("Can't open $leaguetag, probably no such report\n");
    $report = json_decode($report, true);
  }
  return [
    "leaguetag" => $leaguetag,
    "report" => $report
  ];
};

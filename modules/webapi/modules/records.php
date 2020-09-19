<?php 

$endpoints['records'] = function($mods, $vars, &$report) {
  // parse mods for region ID
  // check if region persists
  if (isset($vars['region']) && isset($report['regions_data'][ $vars['region'] ])) {
    return $report['regions_data'][ $vars['region'] ]['records'] ?? null;
  }
  return $report['records'] ?? null;
};
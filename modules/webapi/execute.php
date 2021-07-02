<?php 

function execute(&$modline, &$endp, &$vars, &$report) {
  global $resp;

  try {
    $result = $endp($modline, $vars, $report);
    return $result;
  } catch (\Exception $e) {
    if (!isset($resp['errors'])) $resp['errors'] = [];
      $resp['errors'][] = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
  }
}


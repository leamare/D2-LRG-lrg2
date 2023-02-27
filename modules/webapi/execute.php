<?php 

function execute(&$modline, &$endp, &$vars, &$report) {
  global $resp, $__lrg_onerror;

  try {
    $result = $endp($modline, $vars, $report);
    return $result;
  } catch (\Exception $e) {
    if (!empty($__lrg_onerror)) {
      $__lrg_onerror([
        'type' => 'error',
        'project' => $projectName ?? "LRG2",
        'path' => $_SERVER['REQUEST_URI'] ?? null,
        'message' => $e->getMessage()."::".json_encode($e->getTrace()),
        'file' => str_replace(__DIR__, "", $e->getFile()),
        'line' => $e->getLine(),
        'severity' => E_ERROR | $e->getCode(),
      ]);
    }

    if (!isset($resp['errors'])) $resp['errors'] = [];
      $resp['errors'][] = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
  }
}


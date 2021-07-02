<?php 

function repeater(array $repeaters, &$modline, $endp, &$vars, &$report) {
  if (empty($repeaters)) {
    return execute($modline, $endp, $vars, $report);
  }

  do {
    $repeatParam = array_shift($repeaters);
    if (!isset($vars[ $repeatParam ]) || !is_array($vars[ $repeatParam ])) {
      $repeatParam = null;
      continue;
    }
    break;
  } while (!empty($repeaters));


  if (!$repeatParam) {
    return execute($modline, $endp, $vars, $report);
  }

  $result = [
    'repeater' => $repeatParam,
    'values' => $vars[ $repeatParam ],
    'results' => [],
  ];


  $varsClone = $vars;
  foreach ($vars[ $repeatParam ] as $val) {

    $varsClone[ $repeatParam ] = $val;
    $result['results'][$val] = repeater($repeaters, $modline, $endp, $varsClone, $report);

    if (isset($result['results'][$val]['__endp'])) {
      if (!isset($result['__endp'])) $result['__endp'] = $result['results'][$val]['__endp'];
      unset($result['results'][$val]['__endp']);
    }

    if (isset($result['results'][$val]['__stopRepeater'])) {
      if ($result['results'][$val]['__stopRepeater'] === TRUE || 
          $result['results'][$val]['__stopRepeater'] == $repeatParam || 
          (
            is_array($result['results'][$val]['__stopRepeater']) && in_array($repeatParam, $result['results'][$val]['__stopRepeater'])
          )
        ) {
        unset($result['results'][$val]['__stopRepeater']);
        return $result['results'][$val];
      } else {
        unset($result['results'][$val]['__stopRepeater']);
      }
    }
  }

  return $result;
}
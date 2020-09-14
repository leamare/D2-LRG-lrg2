<?php 

$endpoints['laning'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  $ranks = [];
  $ids = [ 0 ];
  if (!empty($vars['heroid'])) $ids[] = $vars['heroid'];

  if (is_wrapped($report['hero_laning'])) {
    $report['hero_laning'] = unwrap_data($report['hero_laning']);
  }

  $context =& $report['hero_laning'];

  foreach ($ids as $id) {
    uasort($context[$id], function($a, $b) {
      $aa = (float)($a['lane_wr']);
      $bb = (float)($b['lane_wr']);
      return $bb <=> $aa;
    });

    $mm = 0;
    foreach ($context[$id] as $h) {
      if ($h['matches'] > $mm) $mm = $h['matches'];
    }

    $compound_ranking_sort = function($a, $b) use ($mm) {
      return compound_ranking_laning_sort($a, $b, $mm);
    };
    uasort($context[$id], $compound_ranking_sort);

    $increment = 100 / sizeof($context[$id]); $i = 0;

    foreach ($context[$id] as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $context[$id][$elid]['rank'] = $last_rank;
      } else
        $context[$id][$elid]['rank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $context[$id][$elid]['rank'];
    }

    unset($last);
  }

  if (!empty($vars['heroid'])) {
    return [
      'total' => $context[0][ $vars['heroid'] ],
      'opponents' => $context[ $vars['heroid'] ]
    ];
  }
  return [
    'total' => $context[0]
  ];
};

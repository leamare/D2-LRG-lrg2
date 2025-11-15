<?php 

$endpoints['tickets'] = function($mods, $vars, &$report) use (&$meta) {
  if (empty($report['tickets'])) 
    return [];

  $res = [];
  
  foreach ($report['tickets'] as $lid => $data) {
    $res[] = [
      'lid' => $lid,
      'matches' => $data['matches'],
      // 'league_name' => "League #" . $lid,
    ];
  }

  usort($res, function($a, $b) {
    return $b['matches'] <=> $a['matches'];
  });

  return $res;
};


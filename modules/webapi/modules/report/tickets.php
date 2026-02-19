<?php 

$endpoints['tickets'] = function($mods, $vars, &$report) use (&$meta) {
  if (empty($report['tickets'])) 
    return [];

  $res = [];
  
  foreach ($report['tickets'] as $lid => $data) {
    $league_name = "none";
    if ($lid && $lid > 0) {
      if (!empty($data['name'])) {
        $league_name = $data['name'];
      } else {
        $league_name = "League #" . $lid;
      }
    }
    
    $item = [
      'lid' => $lid,
      'matches' => $data['matches'],
      'league_name' => $league_name,
    ];
    
    if (!empty($data['url'])) {
      $item['url'] = $data['url'];
    }
    if (!empty($data['description'])) {
      $item['description'] = $data['description'];
    }
    
    $res[] = $item;
  }

  usort($res, function($a, $b) {
    return $b['matches'] <=> $a['matches'];
  });

  return $res;
};



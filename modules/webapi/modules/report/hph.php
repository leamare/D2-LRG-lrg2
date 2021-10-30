<?php 

$endpoints['hph'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }
  if (!isset($vars['heroid'])) {
    throw new \Exception("Can't give you data without hero ID");
  }

  if (is_wrapped($report['hph'])) {
    $report['hph'] = unwrap_data($report['hph']);
  }

  $context_wrs =& $report['pickban'];

  $i = 0;
  $isrank = false;
  $srcid = $vars['heroid'];

  if(!empty($context_wrs) && !empty($context_wrs[$srcid])) {
    $dt = [
      'wr' => $context_wrs[$srcid]['winrate_picked'],
      'ms' => $context_wrs[$srcid]['matches_picked'],
    ];

    $hero_reference = [
      "id" => $srcid,
      "matches" => $dt['ms'],
      "wins" => round($dt['ms'] * $dt['wr']),
      "winrate" => $dt['wr']
    ];

    if ($srcid) {
      $context =& $report['hph'][$srcid];

      foreach ($context as $id => $el) {
        if ($el == null) unset($context[$id]);
        if ($el === true) $context[$id] = $report['hph'][$id][$srcid];
      }

      foreach ($report['hph'][$srcid] as $id => $line) {
        if ($id == '_h') {
          unset($report['hph'][$srcid][$id]);
          continue;
        }
        if ($line == null) {
          unset($context[$id]);
          continue;
        }
        if ($line === true || is_array($line) && $line['matches'] === -1)
          $report['hph'][$srcid][$id] = $report['hph'][$id][$srcid];

        $context[$id]['wr_diff'] = round($context[$id]['winrate'] - $dt['wr'], 5);
      }

      $compound_ranking_sort = function($a, $b) use ($dt) {
        return positions_ranking_sort($a, $b, $dt['ms']);
      };
      uasort($context, $compound_ranking_sort);
      $context_cpy = $context;
    
      $increment = 100 / sizeof($context); $i = 0;
    
      foreach ($context as $elid => $el) {
        if(isset($last) && $el == $last) {
          $i++;
          $context[$elid]['rank'] = $last_rank;
        } else
          $context[$elid]['rank'] = round(100 - $increment*$i++, 2);
        $last = $el;
        $last_rank = $context[$elid]['rank'];

        $context_cpy[$elid]['winrate'] = 1-$context_cpy[$elid]['winrate'];
      }
    
      unset($last);
  
      uasort($context_cpy, $compound_ranking_sort);
      $i = 0;
    
      foreach ($context_cpy as $elid => $el) {
        if(isset($last) && $el == $last) {
          $i++;
          $context[$elid]['arank'] = $last_rank;
        } else
          $context[$elid]['arank'] = round(100 - $increment*$i++, 2);
        $last = $el;
        $last_rank = $context[$elid]['arank'];
      }
    
      unset($last);

      $isrank = true; $i = 0;
    }
  }

  if (!$isrank && !empty($pvp_context)) {
    uasort($pvp_context, function($a, $b) {
      if($a['wr_diff'] == $b['wr_diff']) return 0;
      else return ($a['wr_diff'] < $b['wr_diff']) ? 1 : -1;
    });
  }

  if (isset($vars['heroid'])) {
    return [
      'reference' => $hero_reference ?? null,
      'pairs' => $context ?? null
    ];
  }
  return $report['hph'];
};

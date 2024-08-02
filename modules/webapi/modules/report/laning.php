<?php 

const MIN10_GOLD = 4973;

$repeatVars['laning'] = ['heroid'];

$endpoints['laning'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  if (in_array("players", $mods))
    $type = "player";
  else 
    $type = "hero";

  $ids = [ 0 ];
  if (!empty($vars[$type.'id'])) $ids[] = $vars[$type.'id'];

  if ($type == "hero") {
    if (is_wrapped($report['hero_laning'])) {
      $report['hero_laning'] = unwrap_data($report['hero_laning']);
    }
  
    $context =& $report['hero_laning'];
  } else {
    if (is_wrapped($report['player_laning'])) {
      $report['player_laning'] = unwrap_data($report['player_laning']);
    }
  
    $context =& $report['player_laning'];
  }

  foreach ($ids as $id) {
    $mm = 0;
    foreach ($context[$id] as $k => $h) {
      if (empty($h)) {
        unset($context[$id][$k]);
        continue;
      }
      if ($h['matches'] > $mm) $mm = $h['matches'];
      if (!isset($h['matches']) || $h['matches'] == 0) unset($context[$id][$k]);
    }

    uasort($context[$id], function($a, $b) {
      return $a['avg_advantage'] <=> $b['avg_advantage'];
    });
    $mk = array_keys($context[$id]);
    $median_adv = $context[$id][ $mk[ floor( count($mk)/2 ) ] ]['avg_advantage'];

    uasort($context[$id], function($a, $b) {
      return $a['avg_disadvantage'] <=> $b['avg_disadvantage'];
    });
    $mk = array_keys($context[$id]);
    $median_disadv = $context[$id][ $mk[ floor( count($mk)/2 ) ] ]['avg_disadvantage'];

    compound_ranking_laning($context[$id], $mm, $median_adv, $median_disadv);
  
    uasort($context[$id], function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context[$id])['wrank'];
    $max = reset($context[$id])['wrank'];
  
    foreach ($context[$id] as $k => $el) {
      $context[$id][$k]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($context[$id][$k]['wrank']);
    }
  }

  if (!empty($vars[$type.'id'])) {
    $context[0][ $vars[$type.'id'] ]['avg_advantage_gold'] = MIN10_GOLD*$context[0][ $vars[$type.'id'] ]['avg_advantage'];
    $context[0][ $vars[$type.'id'] ]['avg_disadvantage_gold'] = MIN10_GOLD*$context[0][ $vars[$type.'id'] ]['avg_disadvantage'];
    $el =& $context[0][ $vars[$type.'id'] ];
    if (!isset($el['avg_gold_diff'])) {
      $el['avg_gold_diff'] = $el['matches'] ? round( (
        $el['avg_advantage']*$el['lanes_won'] + 
        $el['avg_disadvantage']*$el['lanes_lost'] +
        ($el['avg_advantage']+$el['avg_disadvantage'])*0.5*$el['lanes_tied']
      ) / $el['matches'], 4) : 0;
    }
    $el['avg_gold_diff_gold'] = MIN10_GOLD*$el['avg_gold_diff'];

    foreach($context[ $vars[$type.'id'] ] as &$el) {
      $el['avg_advantage_gold'] = MIN10_GOLD*$el['avg_advantage'];
      $el['avg_disadvantage_gold'] = MIN10_GOLD*$el['avg_disadvantage'];
      if (!isset($el['avg_gold_diff'])) {
        $el['avg_gold_diff'] = $el['matches'] ? round( (
          $el['avg_advantage']*$el['lanes_won'] + 
          $el['avg_disadvantage']*$el['lanes_lost'] +
          ($el['avg_advantage']+$el['avg_disadvantage'])*0.5*$el['lanes_tied']
        ) / $el['matches'], 4) : 0;
      }
      $el['avg_gold_diff_gold'] = MIN10_GOLD*$el['avg_gold_diff'];
    }

    return [
      'total' => $context[0][ $vars[$type.'id'] ],
      'opponents' => $context[ $vars[$type.'id'] ]
    ];
  }

  foreach($context[0] as &$el) {
    $el['avg_advantage_gold'] = MIN10_GOLD*$el['avg_advantage'];
    $el['avg_disadvantage_gold'] = MIN10_GOLD*$el['avg_disadvantage'];
    if (!isset($el['avg_gold_diff'])) {
      $el['avg_gold_diff'] = $el['matches'] ? round( (
        $el['avg_advantage']*$el['lanes_won'] + 
        $el['avg_disadvantage']*$el['lanes_lost'] +
        ($el['avg_advantage']+$el['avg_disadvantage'])*0.5*$el['lanes_tied']
      ) / $el['matches'], 4) : 0;
    }
    $el['avg_gold_diff_gold'] = MIN10_GOLD*$el['avg_gold_diff'];
  }

  return [
    'total' => $context[0]
  ];
};

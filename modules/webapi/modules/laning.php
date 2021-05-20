<?php 

const MIN10_GOLD = 4973;

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

    $compound_ranking_sort = function($a, $b) use ($mm, $median_adv, $median_disadv) {
      if ($a['matches'] == 0) return 1;
      if ($b['matches'] == 0) return -1;
      return compound_ranking_laning_sort($a, $b, $mm, $median_adv, $median_disadv);
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
    $context[0][ $vars['heroid'] ]['avg_advantage_gold'] = MIN10_GOLD*$context[0][ $vars['heroid'] ]['avg_advantage'];
    $context[0][ $vars['heroid'] ]['avg_disadvantage_gold'] = MIN10_GOLD*$context[0][ $vars['heroid'] ]['avg_disadvantage'];
    $el =& $context[0][ $vars['heroid'] ];
    if (!isset($el['avg_gold_diff'])) {
      $el['avg_gold_diff'] = $el['matches'] ? round( (
        $el['avg_advantage']*$el['lanes_won'] + 
        $el['avg_disadvantage']*$el['lanes_lost'] +
        ($el['avg_advantage']+$el['avg_disadvantage'])*0.5*$el['lanes_tied']
      ) / $el['matches'], 4) : 0;
    }
    $el['avg_gold_diff_gold'] = MIN10_GOLD*$el['avg_gold_diff'];

    foreach($context[ $vars['heroid'] ] as &$el) {
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
      'total' => $context[0][ $vars['heroid'] ],
      'opponents' => $context[ $vars['heroid'] ]
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

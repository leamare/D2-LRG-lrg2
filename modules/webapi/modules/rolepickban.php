<?php 

$endpoints['rolepickban'] = function($mods, $vars, &$report) {
  if (!in_array("heroes", $mods)) throw new \Exception("This module is only available for heroes");

  if (isset($vars['team']) && isset($report['teams'])) {
    $parent =& $report['teams'][ $vars['team'] ]; 
    $context_pb =& $report['teams'][ $vars['team'] ]['pickban'];
    $context =& $report['teams'][ $vars['team'] ]['hero_positions'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
    $context_main =& $report['teams'][ $vars['team'] ];
  } else if (isset($vars['region']) && isset($report['regions_data'])) {
    $parent =& $report['regions_data'][ $vars['region'] ]; 
    $context_pb =& $report['regions_data'][ $vars['region'] ]['pickban'];
    $context =& $report['regions_data'][ $vars['region'] ]['hero_positions'];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']['matches_total'];
    $context_main =& $report['regions_data'][ $vars['region'] ]['main'];
  } else {
    $parent =& $report;
    $context_pb =& $report['pickban'];
    $context =& $report['hero_positions'];
    $context_total_matches = $report['random']['matches_total'];
    $context_main =& $report['random'];
  }

  if (is_wrapped($context)) {
    $context = unwrap_data($context);
  }

  if (is_wrapped($context_pb)) {
    $context_pb = unwrap_data($context_pb);
  }

  if(!sizeof($context)) return [];

  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;

  $pb = [];

  for ($i=1; $i>=0; $i--) {
    for ($j=0; $j<6 && $j>=0; $j++) {
      if(!empty($context[$i][$j])) {
        $role = "$i.$j";
        foreach ($context[$i][$j] as $hid => $data) {
          $pb[$hid.'|'.$role] = [
            'hero_id' => $hid,
            'matches_picked' => $data['matches_s'],
            'winrate_picked' => $data['winrate_s'],
            'matches_banned' => round( ($data['matches_s']/$context_pb[$hid]['matches_picked'])*$context_pb[$hid]['matches_banned'] ),
            'winrate_banned' => $context_pb[$hid]['winrate_banned'],
          ];
          $pb[$hid.'|'.$role]['matches_total'] = $pb[$hid.'|'.$role]['matches_picked'] + $pb[$hid.'|'.$role]['matches_banned'];
          $pb[$hid.'|'.$role]['role'] = $role;
        }
      }
    }
  }

  if (!$mp) {
    uasort($pb, function($a, $b) {
      return $a['matches_picked'] <=> $b['matches_picked'];
    });
    $mp = $pb[ round(sizeof($pb)*0.5) ]['matches_picked'];
  }
  if (!$mp) $mp = 1;

  if (!$mb) {
    uasort($pb, function($a, $b) {
      return $a['matches_banned'] <=> $b['matches_banned'];
    });
    $mb = $pb[ round(sizeof($context)*0.5) ]['matches_banned'];
  }
  if (!$mb) $mb = 1;

  uasort($pb, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $ranks = [];

  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($pb, $compound_ranking_sort);

  $increment = 100 / sizeof($pb); $i = 0;

  foreach ($pb as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  $last = null;

  $context_cpy = [];
  foreach($pb as $hid => $data) {
    $context_cpy[$hid] = $data;
    $context_cpy[$hid]['winrate_picked'] = 1-$context_cpy[$hid]['winrate_picked'];
    $context_cpy[$hid]['winrate_banned'] = 1-$context_cpy[$hid]['winrate_banned'];
  }

  $aranks = [];

  uasort($context_cpy, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context_cpy as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $aranks[$id] = $last_rank;
    } else
      $aranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $aranks[$id];
  }
  
  foreach($pb as $id => &$el) {
    $el['rank'] = round($ranks[$id], 2);
    $el['arank'] = round($aranks[$id], 2);
    $el['contest_rate'] = round($el['matches_total']/$context_total_matches, 5);
    $el['pickrate'] = round($el['matches_picked']/$context_total_matches, 5);
    $el['banrate'] = round($el['matches_banned']/$context_total_matches, 5);
    $el['picks_to_median'] = isset($mp) ? round($el['matches_picked']/$mp, 1) : null;
    $el['bans_to_median'] = isset($mb) ? round($el['matches_banned']/$mb, 1) : null;
  }

  return [
    'pickban' => array_values($pb)
  ];
};

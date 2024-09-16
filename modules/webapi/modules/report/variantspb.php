<?php 

$repeatVars['variantspb'] = ['team', 'region'];
$repeatVars['hvariants'] = ['team', 'region'];

$endpoints['variantspb'] = function($mods, $vars, &$report) {
  if (!in_array("heroes", $mods)) throw new \Exception("This module is only available for heroes");

  if (isset($vars['team']) && isset($report['teams'])) {
    $parent =& $report['teams'][ $vars['team'] ];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];

    $pb = array_map(function($el) {
      return [
        'matches_total' => $el['m'],
        'matches_picked' => $el['m'],
        'winrate_picked' => $el['w']/$el['m'],
        'ratio' => $el['f'],
        'matches_banned' => null,
        'winrate_banned' => null,
      ];
    }, $parent['hvariants']);
  } else if (isset($vars['region']) && isset($report['regions_data'])) {
    $reg_report =& $report['regions_data'][ $vars['region'] ];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']['matches_total'];

    foreach ($reg_report['hvariants'] as $hvid => $stats) {
      [ $hid, $v ] = explode('-', $hvid);
  
      $pb[$hvid] = [
        'matches_picked' => $stats['m'],
        'winrate_picked' => $stats['w']/$stats['m'],
        'matches_banned' => round( $stats['f']*$reg_report['pickban'][$hid]['matches_banned'] ),
        'winrate_banned' => $reg_report['pickban'][$hid]['winrate_banned'],
        'ratio' => $stats['m'] ? $stats['m']/$reg_report['pickban'][$hid]['matches_picked'] : 0,
      ];
      $pb[$hvid]['matches_total'] = $pb[$hvid]['matches_picked'] + $pb[$hvid]['matches_banned'];
      $pb[$hvid]['variant'] = +$v;
      $pb[$hvid]['hero_id'] = +$hid;
    }
  } else {
    $context_total_matches = $report['random']['matches_total'];
    $pb = [];

    foreach ($report['hero_variants'] as $hvid => $stats) {
      [ $hid, $v ] = explode('-', $hvid);
  
      $pb[$hvid] = [
        'matches_picked' => $stats['m'],
        'winrate_picked' => $stats['w']/$stats['m'],
        'matches_banned' => round( $stats['f']*$report['pickban'][$hid]['matches_banned'] ),
        'winrate_banned' => $report['pickban'][$hid]['winrate_banned'],
        'ratio' => $stats['m'] ? $stats['m']/$report['pickban'][$hid]['matches_picked'] : 0,
      ];
      $pb[$hvid]['matches_total'] = $pb[$hvid]['matches_picked'] + $pb[$hvid]['matches_banned'];
      $pb[$hvid]['variant'] = +$v;
      $pb[$hvid]['hero_id'] = +$hid;
    }
  }

  $mp = null;
  $mb = null;

  if (!$mp) {
    uasort($pb, function($a, $b) {
      return $a['matches_picked'] <=> $b['matches_picked'];
    });
    $keys = array_keys($pb);
    $mp = $pb[ $keys[round(sizeof($pb)*0.5)] ]['matches_picked'];
  }
  if (!$mp) $mp = 1;

  if (!$mb) {
    uasort($pb, function($a, $b) {
      return $a['matches_banned'] <=> $b['matches_banned'];
    });
    $keys = array_keys($pb);
    $mb = $pb[ $keys[round(count($pb)*0.5)] ]['matches_banned'];
  }
  if (!$mb) $mb = 1;

  uasort($pb, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $ranks = [];

  compound_ranking($pb, $context_total_matches);

  $context_copy = $pb;

  uasort($pb, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($pb)['wrank'];
  $max = reset($pb)['wrank'];

  foreach ($pb as $id => $el) {
    $ranks[$id] = 100 * ($el['wrank']-$min) / ($max-$min);

    $context_copy[$id]['winrate_picked'] = 1-$el['winrate_picked'];
    $context_copy[$id]['winrate_banned'] = 1-$el['winrate_banned'];
  }

  $aranks = [];

  compound_ranking($context_copy, $context_total_matches);

  uasort($context_copy, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context_copy)['wrank'];
  $max = reset($context_copy)['wrank'];

  foreach ($context_copy as $id => $el) {
    $aranks[$id] = 100 * ($el['wrank']-$min) / ($max-$min);
  }

  unset($context_copy);
  
  foreach($pb as $id => &$el) {
    unset($el['wrank']);
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

$endpoints['hvariants'] = $endpoints['variantspb'];
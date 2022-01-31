<?php 

include_once(__DIR__ . "/../../functions/pickban_overview.php");
include_once(__DIR__ . "/../../functions/overview_uncontested.php");
include_once(__DIR__ . "/../../../view/functions/teams_diversity_recalc.php");

$repeatVars['pickban'] = ['team', 'region'];

$endpoints['pickban'] = function($mods, $vars, &$report) use (&$meta, &$endpoints) {
  if (!in_array("heroes", $mods)) throw new \Exception("This module is only available for heroes");

  if (isset($vars['team']) && isset($report['teams'])) {
    $parent =& $report['teams'][ $vars['team'] ]; 
    $context =& $report['teams'][ $vars['team'] ]['pickban'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
    $context_main =& $report['teams'][ $vars['team'] ];

    $pb = rg_create_team_pickban_data($context_main['pickban'], $context_main['pickban_vs'] ?? [], $context_main['matches_total']);

    foreach($pb as $hid => &$line) {
      $line['rank'] = round($line['rank'], 2);
      $line['rank_vs'] = round($line['rank_vs'], 2);
      $line['arank'] = round($line['arank'], 2);
      $line['arank_vs'] = round($line['arank_vs'], 2);
      $line['pickrate'] = round($line['matches_picked']/$context_total_matches, 5);
      $line['pickrate_vs'] = round($line['matches_picked_vs']/$context_total_matches, 5);
      $line['banrate'] = round($line['matches_banned']/$context_total_matches, 5);
      $line['banrate_vs'] = round($line['matches_banned_vs']/$context_total_matches, 5);
    }

    return [
      'pickban' => $pb
    ];
  } else if (isset($vars['region']) && isset($report['regions_data'])) {
    $parent =& $report['regions_data'][ $vars['region'] ]; 
    $context =& $report['regions_data'][ $vars['region'] ]['pickban'];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']['matches_total'] ?? $report['regions_data'][ $vars['region'] ]['main']['matches'];
    $context_main =& $report['regions_data'][ $vars['region'] ]['main'];
  } else {
    $parent =& $report;
    $context =& $report['pickban'];
    $context_total_matches = $report['random']['matches_total'];
    $context_main =& $report['random'];
  }

  if (is_wrapped($context)) {
    $context = unwrap_data($context);
  }

  if(!sizeof($context)) return [];

  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;

  if (!$mp) {
    uasort($context, function($a, $b) {
      return $a['matches_picked'] <=> $b['matches_picked'];
    });
    $mp = isset($context[ round(sizeof($context)*0.5) ]) ? $context[ round(sizeof($context)*0.5) ]['matches_picked'] : 1;
  }
  if (!$mp) $mp = 1;

  if (!$mb) {
    if ($mp > 1) {
      $mb = 1;
    } else {
      uasort($context, function($a, $b) {
        return $a['matches_banned'] <=> $b['matches_banned'];
      });
      $mb = isset($context[ round(sizeof($context)*0.5) ]) ? $context[ round(sizeof($context)*0.5) ]['matches_banned'] : 1;
    }
  }
  if (!$mb) $mb = 1;

  uasort($context, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $ranks = [];

  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context as $id => $el) {
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
  foreach($context as $hid => $data) {
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
  
  foreach($context as $id => &$el) {
    $el['rank'] = round($ranks[$id], 2);
    $el['arank'] = round($aranks[$id], 2);
    $el['contest_rate'] = round($el['matches_total']/$context_total_matches, 5);
    $el['pickrate'] = round($el['matches_picked']/$context_total_matches, 5);
    $el['banrate'] = round($el['matches_banned']/$context_total_matches, 5);
    $el['picks_to_median'] = isset($mp) ? round($el['matches_picked']/$mp, 1) : null;
    $el['bans_to_median'] = isset($mb) ? round($el['matches_banned']/$mb, 1) : null;
  }

  try {
    $uncontested = rgapi_generator_uncontested($meta['heroes'], $context, true);
    $res[ $uncontested['type'] ] = $uncontested['data'];
  } catch (Exception $e) {
    $uncontested = [
      'type' => 'heroes_uncontested',
      'data' => null
    ];
  }

  [ $balance, $b_wr, $b_pr, $b_cr ] = balance_rank($context);

  return [
    'median_picks' => $mp ?? null,
    'median_bans' => $mb ?? null,
    'total' => $context_total_matches ?? null,
    'pickban' => $context,
    $uncontested['type'] => $uncontested['data'],
    'balance' => [
      'total' => round($balance, 3),
      'winrate' => round($b_wr, 3),
      'pickrate' => round($b_pr, 3),
      'contest' => round($b_cr, 3),
    ]
  ];
};

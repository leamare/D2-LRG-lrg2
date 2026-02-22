<?php 

include_once(__DIR__ . "/../../functions/pickban_overview.php");
include_once(__DIR__ . "/../../functions/overview_uncontested.php");
include_once(__DIR__ . "/../../../view/functions/teams_diversity_recalc.php");

$repeatVars['rolepickban'] = ['team', 'region'];

#[Endpoint(name: 'rolepickban')]
#[Description('Pick/Ban stats by hero role (position), team/region/global')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'RolePickbanResult')]
class RolePickban extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (!in_array("heroes", $mods)) throw new UserInputException("This module is only available for heroes");

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

  [ $balance, $b_wr, $b_pr, $b_cr ] = balance_rank($pb);

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

  unset($context_copy);

  return [
    'pickban' => array_values($pb),
    'balance' => [
      'total' => round($balance, 3),
      'winrate' => round($b_wr, 3),
      'pickrate' => round($b_pr, 3),
      'contest' => round($b_cr, 3),
    ]
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('RolePickbanBalance', TypeDefs::obj([
    'total' => TypeDefs::num(),
    'winrate' => TypeDefs::num(),
    'pickrate' => TypeDefs::num(),
    'contest' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('RolePickbanResult', TypeDefs::obj([
    'pickban' => TypeDefs::arrayOf(TypeDefs::obj([])),
    'balance' => 'RolePickbanBalance',
  ]));
}

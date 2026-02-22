<?php 

$repeatVars['hvh'] = ['heroid'];

#[Endpoint(name: 'hvh')]
#[Description('Hero vs Hero (HvH) pairs, with lane rates if available')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'variant', schema: ['type' => 'integer'], description: 'Hero variant id')]
#[ReturnSchema(schema: 'HvhResult')]
class Hvh extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (isset($vars['team'])) {
    throw new UserInputException("No team allowed");
  } else if (isset($vars['region'])) {
    throw new UserInputException("No region allowed");
  }

  if (isset($vars['variant']) || in_array("variants", $mods)) {
    return $endpoints['variants-hvh']($mods, $vars, $report);
  }

  $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);

  foreach ($hvh as $srcid => &$pvp_context) {
    if (isset($vars['heroid']) && $vars['heroid'] != $srcid) continue;

    $dt = [
      'ms' => $report['pickban'][ $srcid ]['matches_picked']
    ];

    $pvp_context_cpy = $pvp_context;

    positions_ranking($pvp_context, $dt['ms']);

    uasort($pvp_context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context)['wrank'];
    $max = reset($pvp_context)['wrank'] + 0.01;
  
    foreach ($pvp_context as $elid => $el) {
      $pvp_context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }

    positions_ranking($pvp_context_cpy, $dt['ms']);

    uasort($pvp_context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context_cpy)['wrank'];
    $max = reset($pvp_context_cpy)['wrank'] + 0.01;
  
    foreach ($pvp_context_cpy as $elid => $el) {
      $pvp_context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($pvp_context[$elid]['wrank']);

      if (isset($el['expectation']) && !isset($el['deviation'])) {
        $pvp_context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $pvp_context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }
  }

  if (isset($vars['heroid'])) {
    if (isset($report['hero_laning'])) {
      if (is_wrapped($report['hero_laning'])) {
        $report['hero_laning'] = unwrap_data($report['hero_laning']);
      }

      foreach($report['hero_laning'][$vars['heroid']] as $opid => $hero) {
        if (empty($hvh[$vars['heroid']][$opid])) continue;
        $hvh[$vars['heroid']][$opid]['lane_rate'] = round( ($hero['matches'] ?? 0)/$hvh[$vars['heroid']][$opid]['matches'], 4 );
        $hvh[$vars['heroid']][$opid]['lane_wr'] = $hero['lane_wr'] ?? 0;
      }
    }

    return [
      'reference' => [
        'id' => $vars['heroid'],
        'matches' => $report['pickban'][ $vars['heroid'] ]['matches_picked'],
        'wins' => round($report['pickban'][ $vars['heroid'] ]['matches_picked'] * $report['pickban'][ $vars['heroid'] ]['winrate_picked']),
        'winrate' => $report['pickban'][ $vars['heroid'] ]['winrate_picked'],
      ],
      'opponents' => $hvh[ $vars['heroid'] ]
    ];
  }
  return $hvh;
}
}

#[Endpoint(name: 'variants-hvh')]
#[Description('HvH for hero variants')]
#[ReturnSchema(schema: 'HvhResult')]
class HvhVariants extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (isset($vars['team'])) {
    throw new UserInputException("No team allowed");
  } else if (isset($vars['region'])) {
    throw new UserInputException("No region allowed");
  }

  if (!isset($vars['heroid'])) {
    throw new UserInputException("Can't get variants HvH data without a hero");
  }

  $hvh = rg_generator_pvp_unwrap_data($report['hvh_v'], $report['hero_variants'], true, true);

  if (!isset($vars['variant'])) {
    $hid = $vars['heroid'];
    
    $variants = get_hero_variants_list($vars['heroid']);
    if (isset($hvh[$hid.'-0'])) {
      array_unshift($variants, "_no_variant_");
    }

    $hid = $vars['heroid'];
    
    $report['hero_variants'][$hid."-x"] = [
      'm' => 0,
      'w' => 0,
      'f' => 1,
    ];
    $hvh[$hid."-x"] = [];
    foreach ($variants as $i => $tag) {
      $i++;
      if (empty($report['hero_variants'][$hid."-".$i])) continue;
      $report['hero_variants'][$hid."-x"]['m'] += $report['hero_variants'][$hid."-".$i]['m'];
      $report['hero_variants'][$hid."-x"]['w'] += $report['hero_variants'][$hid."-".$i]['w'];

      foreach ($hvh[$hid."-".$i] as $opid => $data) {
        if (!isset($hvh[$hid."-x"][$opid])) {
          $hvh[$hid."-x"][$opid] = [
            "matches" => 0,
            "expectation" => 0,
            "won" => 0,
            "lost" => 0,
            "winrate" => null,
            "diff" => null,
            "lane_rate" => 0,
            "lane_wr" => 0,
          ];
        }

        $hvh[$hid."-x"][$opid]['matches'] += $data['matches'];
        $hvh[$hid."-x"][$opid]['expectation'] += $data['expectation'];
        $hvh[$hid."-x"][$opid]['won'] += $data['won'];
        $hvh[$hid."-x"][$opid]['lost'] += $data['lost'];
        $hvh[$hid."-x"][$opid]['lane_rate'] += round($data['lane_rate'] * $data['matches']);
        $hvh[$hid."-x"][$opid]['lane_wr'] += $data['lane_rate'] * $data['matches'] * $data['lane_wr'];
      }
    }
    $wr = $report['hero_variants'][$hid."-x"]['w']/$report['hero_variants'][$hid."-x"]['m'];
    foreach ($hvh[$hid."-x"] as $opid => $data) {
      $hvh[$hid."-x"][$opid]['winrate'] = $data['won']/$data['matches'];
      $hvh[$hid."-x"][$opid]['diff'] = $hvh[$hid."-x"][$opid]['winrate'] - $wr;
      $hvh[$hid."-x"][$opid]['lane_wr'] = $data['lane_wr']/($data['lane_rate'] ?: 1);
      $hvh[$hid."-x"][$opid]['lane_rate'] = $data['lane_rate']/$data['matches'];
    }
  }

  $srcid = $vars['heroid'].'-'.($vars['variant'] ?? 'x');

  foreach ($hvh as $srcid => &$pvp_context) {
    if (isset($vars['heroid']) && $vars['heroid'].'.'.$vars['variant'] != $srcid) continue;

    $dt = [
      'ms' => $report['hero_variants'][ $srcid ]['m']
    ];

    $pvp_context_cpy = $pvp_context;

    positions_ranking($pvp_context, $dt['ms']);

    uasort($pvp_context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context)['wrank'];
    $max = reset($pvp_context)['wrank'] + 0.01;
  
    foreach ($pvp_context as $elid => $el) {
      $pvp_context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }

    positions_ranking($pvp_context_cpy, $dt['ms']);

    uasort($pvp_context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context_cpy)['wrank'];
    $max = reset($pvp_context_cpy)['wrank'] + 0.01;
  
    foreach ($pvp_context_cpy as $elid => $el) {
      $pvp_context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($pvp_context[$elid]['wrank']);

      if (isset($el['expectation']) && !isset($el['deviation'])) {
        $pvp_context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $pvp_context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }
  }

  return [
    'reference' => [
      'id' => $srcid,
      'matches' => $report['hero_variants'][ $srcid ]['m'],
      'wins' => $report['hero_variants'][ $srcid ]['w']/$report['hero_variants'][ $srcid ]['m'],
      'winrate' => $report['hero_variants'][ $srcid ]['w'],
    ],
    'opponents' => $hvh[ $srcid ]
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('HvhPair', TypeDefs::obj([
    'matches' => TypeDefs::int(), 'winrate' => TypeDefs::num(), 'rank' => TypeDefs::num(), 'arank' => TypeDefs::num(),
    'deviation' => TypeDefs::num(), 'deviation_pct' => TypeDefs::num(), 'lane_rate' => TypeDefs::num(), 'lane_wr' => TypeDefs::num()
  ]));
  SchemaRegistry::register('HvhResult', TypeDefs::oneOf([
    TypeDefs::mapOf('HvhPair'),
    TypeDefs::obj(['reference' => TypeDefs::obj([]), 'opponents' => TypeDefs::mapOf('HvhPair')])
  ]));
}
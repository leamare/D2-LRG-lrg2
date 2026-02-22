<?php 

$repeatVars['hph'] = ['heroid'];

#[Endpoint(name: 'hph')]
#[Description('Hero vs Player (H vs P) pairs for a given hero')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'variant', schema: ['type' => 'integer'], description: 'Hero variant id')]
#[ReturnSchema(schema: 'HphResult')]
class Hph extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (isset($vars['team'])) {
    throw new UserInputException("No team allowed");
  } else if (isset($vars['region'])) {
    throw new UserInputException("No region allowed");
  }
  if (!isset($vars['heroid'])) {
    throw new UserInputException("Can't give you data without hero ID");
  }

  if (isset($vars['variant']) || in_array("variants", $mods)) {
    return $endpoints['variants-hph']($mods, $vars, $report);
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

      if (empty($context)) return [];

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

      positions_ranking($context, $dt['ms']);
      $context_cpy = $context;

      uasort($context, function($a, $b) {
        return $b['wrank'] <=> $a['wrank'];
      });
    
      $min = end($context)['wrank'];
      $max = reset($context)['wrank'] + 0.01;
    
      foreach ($context as $elid => $el) {
        $context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
        $context_cpy[$elid]['winrate'] = 1-$context_cpy[$elid]['winrate'];
      }

      positions_ranking($context_cpy, $dt['ms']);

      uasort($context_cpy, function($a, $b) {
        return $b['wrank'] <=> $a['wrank'];
      });
    
      $min = end($context_cpy)['wrank'];
      $max = reset($context_cpy)['wrank'] + 0.01;
    
      foreach ($context_cpy as $elid => $el) {
        $context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
        unset($context[$elid]['wrank']);

        if (isset($el['expectation']) && !isset($el['deviation'])) {
          $context[$elid]['deviation'] = $el['matches']-$el['expectation'];
          $context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
        }
      }

      $isrank = true;
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
}
}

#[Endpoint(name: 'variants-hph')]
#[Description('Hero vs Player for hero variants')]
#[ReturnSchema(schema: 'HphResult')]
class HphVariants extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (isset($vars['team'])) {
    throw new UserInputException("No team allowed");
  } else if (isset($vars['region'])) {
    throw new UserInputException("No region allowed");
  }
  if (!isset($vars['heroid'])) {
    throw new UserInputException("Can't give you data without hero ID");
  }

  if (is_wrapped($report['hph_v'])) {
    $report['hph_v'] = unwrap_data($report['hph_v']);
  }

  if (!isset($vars['variant'])) {
    $hid = $vars['heroid'];

    $variants = get_hero_variants_list($vars['heroid']);
    if (isset($report['hph'][$hid.'-0'])) {
      array_unshift($variants, "_no_variant_");
    }
    $report['hero_variants'][$hid."-x"] = [
      'm' => 0,
      'w' => 0,
      'f' => 1,
    ];
    $report['hph_v'][$hid."-x"] = [];
    foreach ($variants as $i => $tag) {
      $i++;
      if (empty($report['hero_variants'][$hid."-".$i])) continue;
      $report['hero_variants'][$hid."-x"]['m'] += $report['hero_variants'][$hid."-".$i]['m'];
      $report['hero_variants'][$hid."-x"]['w'] += $report['hero_variants'][$hid."-".$i]['w'];

      foreach ($report['hph_v'][$hid."-".$i] as $opid => $data) {
        if (empty($data) || $data['matches'] == -1) continue;
        
        if (!isset($report['hph_v'][$hid."-x"][$opid])) {
          $report['hph_v'][$hid."-x"][$opid] = [
            "matches" => 0,
            "exp" => 0,
            "won" => 0,
            "lost" => 0,
            "winrate" => null,
            "diff" => null,
            "lane_rate" => 0,
            "lane_wr" => 0,
          ];
        }

        $report['hph_v'][$hid."-x"][$opid]['matches'] += $data['matches'];
        $report['hph_v'][$hid."-x"][$opid]['exp'] += $data['exp'];
        $report['hph_v'][$hid."-x"][$opid]['won'] += $data['won'];
        $report['hph_v'][$hid."-x"][$opid]['lane_rate'] += round($data['lane_rate'] * $data['matches']);
        $report['hph_v'][$hid."-x"][$opid]['lane_wr'] += $data['lane_rate'] * $data['matches'] * $data['lane_wr'];
      }
    }
    $wr = $report['hero_variants'][$hid."-x"]['w']/$report['hero_variants'][$hid."-x"]['m'];
    foreach ($report['hph_v'][$hid."-x"] as $opid => $data) {
      if (!$data['matches']) continue;
      $report['hph_v'][$hid."-x"][$opid]['winrate'] = $data['won']/$data['matches'];
      $report['hph_v'][$hid."-x"][$opid]['diff'] = $report['hph_v'][$hid."-x"][$opid]['winrate'] - $wr;
      $report['hph_v'][$hid."-x"][$opid]['lane_wr'] = $data['lane_wr']/($data['lane_rate'] ?: 1);
      $report['hph_v'][$hid."-x"][$opid]['lane_rate'] = $data['lane_rate']/$data['matches'];
    }
  }

  $srcid = $vars['heroid'].'-'.($vars['variant'] ?? 'x');

  $dt = [
    'wr' => $report['hero_variants'][$srcid]['w']/$report['hero_variants'][$srcid]['m'],
    'ms' => $report['hero_variants'][$srcid]['m'],
  ];

  $hero_reference = [
    "id" => isset($vars['variant']) ? $srcid : +$vars['heroid'],
    "matches" => $dt['ms'],
    "wins" => $report['hero_variants'][$srcid]['w'],
    "winrate" => $dt['wr']
  ];

  if ($srcid) {
    $context =& $report['hph_v'][$srcid];

    if (empty($context)) return [];

    foreach ($context as $id => $el) {
      if ($el == null) unset($context[$id]);
      if ($el === true) $context[$id] = $report['hph'][$id][$srcid];
    }

    foreach ($report['hph_v'][$srcid] as $id => $line) {
      if ($id == '_h') {
        unset($report['hph_v'][$srcid][$id]);
        continue;
      }
      if ($line == null) {
        unset($context[$id]);
        continue;
      }
      if ($line === true || is_array($line) && $line['matches'] === -1)
        $report['hph_v'][$srcid][$id] = $report['hph_v'][$id][$srcid];

      $context[$id]['wr_diff'] = round($context[$id]['winrate'] - $dt['wr'], 5);
    }

    positions_ranking($context, $dt['ms']);
    $context_cpy = $context;

    uasort($context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context)['wrank'];
    $max = reset($context)['wrank'] + 0.01;
  
    foreach ($context as $elid => $el) {
      $context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $context_cpy[$elid]['winrate'] = 1-$context_cpy[$elid]['winrate'];
    }

    positions_ranking($context_cpy, $dt['ms']);

    uasort($context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($context_cpy)['wrank'];
    $max = reset($context_cpy)['wrank'] + 0.01;
  
    foreach ($context_cpy as $elid => $el) {
      $context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($context[$elid]['wrank']);

      if (isset($el['expectation']) && !isset($el['deviation'])) {
        $context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }
  }

  return [
    'reference' => $hero_reference ?? null,
    'pairs' => $context ?? null
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('HphPair', TypeDefs::obj([
    'matches' => TypeDefs::int(), 'winrate' => TypeDefs::num(), 'wr_diff' => TypeDefs::num(),
    'rank' => TypeDefs::num(), 'arank' => TypeDefs::num(),
    'deviation' => TypeDefs::num(), 'deviation_pct' => TypeDefs::num()
  ]));
  SchemaRegistry::register('HphResult', TypeDefs::oneOf([
    TypeDefs::mapOf('HphPair'),
    TypeDefs::obj(['reference' => TypeDefs::obj([]), 'pairs' => TypeDefs::mapOf('HphPair')])
  ]));
}

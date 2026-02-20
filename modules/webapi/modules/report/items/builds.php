<?php 

include_once($root."/modules/view/functions/itembuilds.php");

#[Endpoint(name: 'items-builds')]
#[Description('Starting from role progression, generate item builds/tree for a hero role')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Role code (core.lane)')]
#[ReturnSchema(schema: 'ItemsProgRoleResult')]
class ItemsBuilds extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints, $meta, $root;
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['progr']))
    throw new \Exception("No items data");

  if (!isset($report['items']['progrole']))
    throw new \Exception("No items progression for roles");

  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  
  if (isset($vars['heroid'])) {
    $hero = $vars['heroid'];
  } else {
    $hero = null;
  }

  if (!isset($report['items']['progrole']['data'][$hero]))
    $report['items']['progrole']['data'][$hero] = [];

  if (isset($vars['position'])) {
    if (!isset($report['items']['progrole']['data'][$hero][ $vars['position'] ]))
      return [];
    $crole = $vars['position'];
  } else {
    $crole = array_keys($report['items']['progrole']['data'][$hero])[0] ?? null;
  }



  if ($hero === null) {
    foreach ($report['items']['progrole']['data'] as $hid => $positions) {
      if (empty($hid)) continue;
      $res[] = [
        'hero' => $hid,
        'positions' => array_keys($positions),
      ];
    }

    return $res;
  } else {
    if ($crole === null) return [];

    $data = [];

    foreach ($report['items']['progrole']['data'][$hero][$crole] as $elem) {
      $data[] = array_combine($report['items']['progrole']['keys'], $elem);
    }

    $pbdata = $report['pickban'][$hero] ?? [];

    $pairs = [];
    $items_matches = []; $items_matches_1 = [];

    if (!isset($report['items']['progr'][$hero])) $report['items']['progr'][$hero] = [];
    foreach ($data as $v) {
      if (empty($v)) continue;
      if ($v['item1'] == $v['item2']) continue;
      $pairs[] = $v;

      if (!isset($items_matches_1[ $v['item1'] ])) {
        $items_matches_1[ $v['item1'] ] = 0;
      }
      if (!isset($items_matches[ $v['item1'] ])) {
        $items_matches[ $v['item1'] ] = 0;
      }
      $items_matches_1[ $v['item1'] ] += $v['total'];
      
      if (!isset($items_matches[ $v['item2'] ])) {
        $items_matches[ $v['item2'] ] = 0;
      }
      $items_matches[ $v['item2'] ] += $v['total'];
    }

    foreach ($items_matches as $iid => $v) {
      $report['items']['stats'][$hero][$iid]['purchases'] = max($items_matches[$iid] ?? 0, $items_matches_1[$iid] ?? 0);
    }
    unset($items_matches_1);
    
    usort($pairs, function($a, $b) {
      return $b['total'] <=> $a['total'];
    });
  
    if (isset($report['hero_positions'])) {
      if (is_wrapped($report['hero_positions'])) $report['hero_positions'] = unwrap_data($report['hero_positions']);
      [$core, $lane] = explode('.', $crole);
      $pbdata['role_matches'] = isset($report['hero_positions'][$core][$lane][$hero])
        ? $report['hero_positions'][$core][$lane][$hero]['matches_s']
        : 0;
      $pbdata['role_winrate'] = isset($report['hero_positions'][$core][$lane][$hero])
        ? $report['hero_positions'][$core][$lane][$hero]['winrate_s']
        : 0;
    } else {
      $pbdata['role_matches'] = empty($pairs) ? 0 : $pairs[ 0 ]['total']*1.25;
    }

    $report['items']['stats'][$hero] = array_filter($report['items']['stats'][$hero], function($a) {
      return !empty($a);
    });

    $facets = null;

    // variants
    if (!empty($report['hero_summary_variants'])) {
      if (is_wrapped($report['hero_summary_variants'])) {
        $report['hero_summary_variants'] = unwrap_data($report['hero_summary_variants']);
      }

      $facets = [];
      $facets_list = isset($report['meta']['variants']) ? array_keys($report['meta']['variants'][$hero]) : $meta['facets']['heroes'][$hero];
      $rid = array_search($core.'.'.$lane, ROLES_IDS_SIMPLE);
      $role_matches = 0;
      foreach ($facets_list as $i => $facet) {
        $i++;
        $hvid = $hero.'-'.$i;
      
        $hero_stats = $report['hero_summary_variants'][$rid][$hvid] ?? [];

        // TODO: add deprecated support
        if (empty($hero_stats)) continue;

        $role_matches += $hero_stats['matches_s'];

        $facets[] = [
          'variant' => $i,
          'is_role' => true,
          // 'ratio' => ($hero_stats['matches_s'] ?? 0)/$pbdata['role_matches'],
          'matches' => $hero_stats['matches_s'] ?? 0,
          'winrate' => $hero_stats['winrate_s'] ?? '-',
        ];
      }
      foreach ($facets as $i => $facet) {
        $facets[$i]['ratio'] = $facet['matches']/($role_matches ? $role_matches : 1);
      }
    } else if (isset($report['hero_variants'])) {
      $facets_list = isset($report['meta']['variants']) ? array_keys($report['meta']['variants'][$hero]) : $meta['facets']['heroes'][$hero];
      $facets = [];
      foreach ($facets_list as $i => $facet) {
        $i++;
        $hvid = $hero.'-'.$i;
        $stats = [
          'm' => 0,
          'w' => 0,
          'f' => 0,
        ];
        if (isset($report['hero_variants'][$hvid])) {
          $stats = $report['hero_variants'][$hvid];
        }
        $facets[] = [
          'variant' => $i,
          'is_role' => false,
          'ratio' => $stats['f'],
          'matches' => $stats['m'],
          'winrate' => $stats['m'] ? $stats['w']/$stats['m'] : 0,
        ];
      }
    }
    
    [ $build, $tree ] = generate_item_builds($pairs, $report['items']['stats'][$hero], $pbdata);

    $res = [
      'hero' => $hero,
      'role' => $crole,
      'stats' => $pbdata,
      'build' => $build,
    ];

    if (!empty($facets)) {
      $res['facets'] = $facets;
    }

    if (isset($vars['gets']) && in_array("tree", $vars['gets'])) {
      $res['tree'] = $tree;
    }

    
    if (isset($report['starting_items'])) {
      $sti_builds = [];
      $sti_stats = [];
      $sti_matches_context = [];

      

      $srid = array_search($crole, ROLES_IDS_SIMPLE);

      if (isset($report['starting_items']['items'])) {
        if (isset($report['starting_items']['items'][$srid][$hero])) {
          $sti_context =& $report['starting_items']['items'][$srid][$hero];
        } else if (isset($report['starting_items']['items'][0][$hero])) {
          $sti_context =& $report['starting_items']['items'][0][$hero];
        } else if (isset($report['starting_items']['items'][$srid][0])) {
          $sti_context =& $report['starting_items']['items'][$srid][0];
        } else {
          $sti_context =& $report['starting_items']['items'][0][0];
        }

        $sti_context['head'] = $report['starting_items']['items_head'];
        $sti_stats = unwrap_data($sti_context);
      }

      if (isset($report['starting_items']['matches'][$srid]['data'])) {
        $report['starting_items']['matches'][$srid] = unwrap_data($report['starting_items']['matches'][$srid]);
      }
      if (isset($report['starting_items']['matches'][0]['data'])) {
        $report['starting_items']['matches'][0] = unwrap_data($report['starting_items']['matches'][0]);
      }

      if (isset($report['starting_items']['matches'][$srid][$hero])) {
        $sti_matches_context =& $report['starting_items']['matches'][$srid][$hero];
      } else if (isset($report['starting_items']['items'][0][$hero])) {
        $sti_matches_context =& $report['starting_items']['matches'][0][$hero];
      } else if (isset($report['starting_items']['items'][$srid][0])) {
        $sti_matches_context =& $report['starting_items']['matches'][$srid][0];
      } else {
        $sti_matches_context =& $report['starting_items']['matches'][0][0];
      }

      $builds_fallback = false;

      if (isset($report['starting_items']['builds'])) {
        if (isset($report['starting_items']['builds'][$srid]['data'])) {
          $report['starting_items']['builds'][$srid] = unwrap_data($report['starting_items']['builds'][$srid]);
        }
        if (isset($report['starting_items']['builds'][0]['data'])) {
          $report['starting_items']['builds'][0] = unwrap_data($report['starting_items']['builds'][0]);
        }

        if (isset($report['starting_items']['builds'][$srid][$hero])) {
          $stib_context =& $report['starting_items']['builds'][$srid][$hero];
        } else if (isset($report['starting_items']['builds'][0][$hero])) {
          $stib_context =& $report['starting_items']['builds'][0][$hero];
        } else if (isset($report['starting_items']['builds'][$srid][0])) {
          $stib_context =& $report['starting_items']['builds'][$srid][0];
        } else {
          $stib_context =& $report['starting_items']['builds'][0][0];
        }

        $stib_context = array_filter($stib_context, function($el) { return !empty($el); });

        usort($stib_context, function($a, $b) {
          return $b['wins'] <=> $a['wins'];
        });

        $i = 0;
        foreach ($stib_context as $stibuild) {
          if (empty($stibuild)) continue;
          $sti_builds[] = $stibuild;
          if (++$i == 3) break;
        }
      } else if (!empty($sti_stats)) {
        $builds_fallback = true;
        $sort_factors = ['matches', 'wins', 'lane_wins'];
        foreach ($sort_factors as $factor) {
          $gold = STARTING_GOLD;
          $lane_wins = [];
          $matches = [];
          $wins = [];
          $items = [];
          uasort($sti_stats, function($a, $b) use (&$factor) {
            return $b[$factor] <=> $a[$factor];
          });

          foreach ($sti_stats as $i => $stats) {
            $iid = floor($i/100);
            $gold -= $meta['items_full'][$iid]['cost'];
            if ($gold < 0) break;

            $lane_wins[] = $stats['lane_wins'];
            $matches[] = $stats['matches'];
            $wins[] = $stats['wins'];
            $items[] = $iid;
          }

          $sti_builds[] = [
            'build' => $items,
            'matches' => array_sum($matches)/count($matches),
            'winrate' => array_sum($wins)/array_sum($matches),
            'lane_wr' => array_sum($lane_wins)/array_sum($matches),
            'ratio' => min($matches)/($pbdata['role_matches'] ?? $pbdata['matches_picked']),
            'factor' => $factor,
          ];
        }
      }

      if (empty($sti_stats)) {
        $sti_stats = [];
    
        foreach ($sti_builds as $i => $stibuild) {
          $cnts = [];
          foreach($stibuild['build'] as $item) {
            if (!isset($cnts[$item])) $cnts[$item] = 0;
            $cnts[$item]++;
            $stiid = $cnts[$item] + $item*100;
    
            if (!isset($sti_stats[$stiid])) {
              $sti_stats[$stiid] = [ 'wins' => 0, 'matches' => 0, 'lane_wins' => 0 ];
            }
    
            $sti_stats[$stiid]['wins'] += $stibuild['wins'];
            $sti_stats[$stiid]['matches'] += $stibuild['matches'];
            $sti_stats[$stiid]['lane_wins'] += $stibuild['lane_wins'];
          }
        }
      }

      $res['starting_items'] = [
        'stats' => $sti_stats,
        'builds' => $sti_builds,
      ];
    }

    if (isset($report['items']['enchantments'])) {
      if (is_wrapped($report['items']['enchantments'])) {
        $report['items']['enchantments'] = unwrap_data($report['items']['enchantments']);
      }
      $res['enchantments'] = [];
      if (!empty($report['items']['enchantments'][$hero])) {
        $tier = 1;
        foreach ($report['items']['enchantments'][$hero] as $i => $items) {
          if ($i == 0) continue;
          $items = array_filter($items, function($a) {
            return !empty($a) && $a['matches'] > 0;
          });
          uasort($items, function($a, $b) {
            return $b['matches'] <=> $a['matches'];
          });
          $items = array_map(function($a) {
            return [
              'matches' => $a['matches'],
              'prate' => round($a['matches'] / ($a['matches'] + $a['matches_wo']), 4),
              'winrate' => round($a['wr'], 4),
              'wr_incr' => round($a['wr'] - $a['wr_wo'], 4),
              'wr_wo' => round($a['wr_wo'], 4),
            ];
          }, $items);
          $res['enchantments'][] = [
            'tier' => $tier,
            'category' => array_keys($meta['item_categories'])[$i] ?? $i,
            'items' => $items,
          ];
          $tier++;
        }
      }
    }

    return $res;
  }

  return [];
}
}
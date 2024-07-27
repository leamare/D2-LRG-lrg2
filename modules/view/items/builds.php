<?php

include_once($root."/modules/view/functions/itembuilds.php");
include_once($root."/modules/view/generators/item_component.php");

if (!empty($report['items']) && !empty($report['items']['pi'])) {
  $modules['items']['builds'] = [];
}

function rg_view_generate_items_builds() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $roleicon_logo_provider, $leaguetag, $linkvars;

  if($mod == $parent."builds") $unset_module = true;
  $parent_module = $parent."builds-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  generate_positions_strings();

  $res = [ 'overview' => "" ];

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  if(check_module($parent_module."overview")) {
    $hero = null;
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    if (empty($report['items']['progrole']['data'][$hid])) {
      $res["heroid".$hid] = "";
    } else {
      $res["heroid".$hid] = [];
    }

    if(check_module($parent_module."heroid".$hid)) {
      $hero = $hid;
      $tag = "heroid$hid";

      if (empty($report['items']['progrole']['data'][$hid])) continue;

      if($mod == $parent_module."heroid".$hid) $unset_module = true;
      $parent_module = $parent_module."heroid".$hid."-";

      foreach($report['items']['progrole']['data'][$hid] as $role => $pairs) {
        $res["heroid".$hid]["position_".$role] = "";

        if(check_module($parent_module."position_".$role)) {
          $roletag = "position_".$role;
          $crole = $role;
        }
      }
    }
  }

  if ($hero === null) {

    if (isset($roleicon_logo_provider)) {
      $roleicons = [
        "0.1" => "hardsupporticon",
        "0.3" => "softsupporticon",
        "1.1" => "safelaneicon",
        "1.2" => "midlaneicon",
        "1.3" => "offlaneicon",
      ];
    }

    $res['overview'] .= "<div class=\"content-header\">".locale_string("progrole_available_heroes")."</div>";

    $_presetroles = [
      '1.1', '1.2', '1.3', '0.3', '0.1'
    ];

    $res['overview'] .= search_filter_component("filterable-heroes-builds");

    $res['overview'] .= "<table id=\"filterable-heroes-builds\" class=\"list sortable\"><thead>".
      "<tr><th width=\"1%\"></th><th width=\"15%\" class=\"sortInitialOrder-asc\">".
      locale_string("hero")."</th><th width=\"15%\" class=\"separator\">". 
      locale_string("positions_count")."</th>";
    foreach ($_presetroles as $i => $role) {
      $res['overview'] .= "<th class=\"".($i ? "" : "separator ")."centered sorter-image sortInitialOrder-asc\">".locale_string("position_".$role)."</th>";
    }
    $res['overview'] .= "<th class=\"separator\">".locale_string('common_position')."</th>";
    $res['overview'] .= "</tr></thead><tbody>";

    foreach ($hnames as $hid => $hname) {
      $roles = $report['items']['progrole']['data'][$hid] ?? []; 
      $res['overview'] .= "<tr><td>".hero_icon($hid)."</td><td>".hero_link($hid)."</td><td class=\"separator\">";

      $res['overview'] .= count($roles)."</td>";

      $max_role = [ null, 0 ];

      foreach ($_presetroles as $i => $role) {
        $res['overview'] .= "<td class=\"".($i ? "" : "separator ")."centered\">".
          (isset($roles[$role]) ?
            "<a href=\"?league=$leaguetag&mod=items-builds-heroid$hid-position_$role".
            (empty($linkvars) ? "" : "&".$linkvars)."\">".
            (isset($roleicon_logo_provider) && isset($roleicons[$role]) ?
              "<img src=\"".str_replace("%ROLE%", $roleicons[$role], $roleicon_logo_provider)."\" alt=\"".$roleicons[$role]."\" />" :
              locale_string("position_$role")
            )."</a>" :
            ""
          ).
        "</td>";

        [ $core, $lane ] = explode('.', $role);

        if (isset($roles[$role])) {
          $m = $report['hero_positions'][$core][$lane][$hid]['matches_s'];
          if ($max_role[1] < $m) {
            $max_role = [ $role, $m ];
          }
        }
      }

      $res['overview'] .= "</td><td class=\"separator\">".
        ($max_role[0] ? locale_string("position_".$max_role[0]) : '-').
      "</td></tr>";
    }
    $res['overview'] .= "</tbody></table>";

    return $res;
  }

  // BUILD PROCESSING

  if (empty($report['items']['progrole']['data'][$hero]) || !isset($crole)) {
    $res[$tag] = "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

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

  // if (empty($pairs)) {
  //   $res[$tag][$roletag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
  //   return $res;
  // }

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

  // $m_lim = $pairs[ ceil(count($pairs)*0.5)-1 ]['total'];
  $report['items']['stats'][$hero] = array_filter($report['items']['stats'][$hero], function($a) {
    return !empty($a);
  });

  [ $build, $tree ] = generate_item_builds($pairs, $report['items']['stats'][$hero], $pbdata);  

  $reslocal = "";

  if ($build['partial']) $reslocal .= "<div class=\"content-text alert\">".locale_string("builds_partial")."</div>";

  if ($hero && !empty($pbdata)) {
    $reslocal .= "<div class=\"content-text\"><table id=\"items-build-$tag-reference\" class=\"list\">";
    $reslocal .= "<thead><tr>".
      "<th></th>".
      "<th>".locale_string("hero")."</th>".
      "<th>".locale_string("matches_picked")."</th>".
      ($pbdata['role_matches'] && isset($report['hero_positions']) ? "<th>".locale_string("position")." (".locale_string("ratio").")"."</th>" : "").
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("items_stats")."</th>".
      "<th>".locale_string("progression")."</th>".
    "</tr></thead><tbody>";
    $reslocal .= "<tr>".
      "<td>".hero_portrait($hero)."</td>".
      "<td>".hero_link($hero)."</td>".
      "<td>".($pbdata['role_matches'] ?? $pbdata['matches_picked'])."</td>".
      ($pbdata['role_matches'] && isset($report['hero_positions']) ? "<td>".number_format(100*$pbdata['role_matches']/$pbdata['matches_picked'], 2)."%</td>" : "").
      "<td>".number_format(($pbdata['role_winrate'] ?? $pbdata['winrate_picked'])*100, 2)."%</td>".
      "<td><a href=\"?league=$leaguetag&mod=items-stats-$tag".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("link")."</a></td>".
      "<td><a href=\"?league=$leaguetag&mod=items-progression-$tag-".(($crole ?? false) ? "position_".$crole : "all").(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("link")."</a></td>".
    "</tr></tbody></table></div>";
  }

  // variants
  if (!empty($report['hero_summary_variants'])) {
    if (is_wrapped($report['hero_summary_variants'])) {
      $report['hero_summary_variants'] = unwrap_data($report['hero_summary_variants']);
    }
    $keys = [
      'matches_s', 'winrate_s', 'kills', 'deaths', 'assists', 'gpm', 'xpm', 
    ];

    $stats = [];
    $facets_list = isset($report['meta']['variants']) ? array_keys($report['meta']['variants'][$hero]) : $meta['facets']['heroes'][$hero];
    $rid = array_search($core.'.'.$lane, ROLES_IDS_SIMPLE);
    foreach ($facets_list as $i => $facet) {
      $i++;
      $hvid = $hero.'-'.$i;
    
      $hero_stats = $report['hero_summary_variants'][$rid][$hvid] ?? [];

      $stats[] = [
        'variant' => $i,
        'ratio' => $hero_stats['matches_s']/$pbdata['role_matches'],
        'matches' => $hero_stats['matches_s'] ?? 0,
        'winrate' => $hero_stats['winrate_s'] ?? '-',
      ];
    }

    $reslocal .=  "<div class=\"content-text\">".
      "<table id=\"build-$hero-variants-summary\" class=\"list sortable\"><thead><tr>".
      "<th>".locale_string("facet")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "</tr>".
    "</thead><tbody>";

    foreach ($stats as $line) {
      $reslocal .= "<tr>".
        "<td>".facet_full_element($hero, $line['variant'])."</td>".
        "<td>".number_format($line['ratio']*100, 2)."%</td>".
        "<td>".$line['matches']."</td>".
        "<td>".($line['matches'] ? number_format($line['winrate']*100, 2).'%' : $line['winrate'])."</td>".
      "</tr>";
    }

    $reslocal .= "</tbody></table></div>";
  } else if (isset($report['hero_variants'])) {
    $reslocal .= "<div class=\"content-text\"><table id=\"profile-$hero-variants\" class=\"list\"><thead><tr>".
      "<th>".locale_string("facet")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
    "</tr></thead><tbody>";

    $facets_list = isset($report['meta']['variants']) ? array_keys($report['meta']['variants'][$hero]) : $meta['facets']['heroes'][$hero];
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
      $reslocal .= "<tbody><tr>".
        "<td>".facet_full_element($hero, $i)."</td>".
        "<td>".number_format(100*$stats['f'], 2)."%</td>".
        "<td>".$stats['m']."</td>".
        "<td>".number_format($stats['m'] ? 100*$stats['w']/$stats['m'] : 0, 2)."%</td>".
      "</tr>";
    }

    $reslocal .= "</tbody></table></div>";
  }

  // DEMO BLOCKS FOR EXPLAINER

  $demoblocks = "";

  $demoblocks .= "<div class=\"explainer-demo-block\"><div class=\"header\">".locale_string('build_item_type_regular')."</div>".
    "<div class=\"build-item-component\">".
    "<a class=\"item-image\">".item_icon(69)."<span class=\"item-prate\">".locale_string('purchase_rate')."</span></a>".
    "<div class=\"labels\"><span class=\"item-time item-stat-tooltip-line\">".
    "<a class=\"item-stat-tooltip item-time-median\">".locale_string('item_time_median_long')."</a></span>".
    "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\">".locale_string('items_winrate_increase')."</a>".
    "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\">".locale_string('winrate')."</a></div></div>".
    "<div class=\"description\">".locale_string('build_item_type_regular_explain')."</div>".
  "</div>";

  $demoblocks .= "<div class=\"explainer-demo-block\"><div class=\"header\">".locale_string('build_item_type_common')."</div>".
    "<div class=\"build-item-component common\">".
    "<a class=\"item-image\">".item_icon(29)."<span class=\"item-prate\">".locale_string('purchase_rate')."</span></a>".
    "<div class=\"labels\"><span class=\"item-time item-stat-tooltip-line\">".
    "<a class=\"item-stat-tooltip item-time-median\">".locale_string('item_time_median_long')."</a></span>".
    "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\">".locale_string('items_winrate_increase')."</a>".
    "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\">".locale_string('winrate')."</a></div></div>".
    "<div class=\"description\">".locale_string('build_item_type_common_explain')."</div>".
  "</div>";

  $demoblocks .= "<div class=\"explainer-demo-block\"><div class=\"header\">".locale_string('build_item_type_strong')."</div>".
    "<div class=\"build-item-component strong\">".
    "<a class=\"item-image\">".item_icon(110)."<span class=\"item-prate\">".locale_string('purchase_rate')."</span></a>".
    "<div class=\"labels\"><span class=\"item-time item-stat-tooltip-line\">".
    "<a class=\"item-stat-tooltip item-time-median\">".locale_string('item_time_median_long')."</a></span>".
    "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\">".locale_string('items_winrate_increase')."</a>".
    "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\">".locale_string('winrate')."</a></div></div>".
    "<div class=\"description\">".locale_string('build_item_type_strong_explain')."</div>".
  "</div>";

  $demoblocks .= "<div class=\"explainer-demo-block\"><div class=\"header\">".locale_string('build_item_type_strong_grad')."</div>".
    "<div class=\"build-item-component strong\">".
    "<a class=\"item-image\">".item_icon(204)."<span class=\"item-prate\">".locale_string('purchase_rate')."</span></a>".
    "<div class=\"labels\"><span class=\"item-time item-stat-tooltip-line\">".
    "<a class=\"item-stat-tooltip item-time-median\">".locale_string('item_time_median_long')."</a></span>".
    "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\">".locale_string('items_winrate_increase')."</a>".
    "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\">".locale_string('items_wr_gradient')."</a></div></div>".
    "<div class=\"description\">".locale_string('build_item_type_strong_grad_explain')."</div>".
  "</div>";

  $demoblocks .= "<div class=\"explainer-demo-block\"><div class=\"header\">".locale_string('build_item_type_critical')."</div>".
    "<div class=\"build-item-component critical\">".
    "<a class=\"item-image\">".item_icon(65)."<span class=\"item-prate\">".locale_string('purchase_rate')."</span></a>".
    "<div class=\"labels\"><span class=\"item-time item-stat-tooltip-line\">".
    "<a class=\"item-stat-tooltip item-time-median\">".locale_string('item_time_q1_long')." - ".locale_string('item_time_critical')."</a></span>".
    "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\">".locale_string('items_early_wr_long')."</a>".
    "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\">".locale_string('items_wr_gradient')."</a></div></div>".
    "<div class=\"description\">".locale_string('build_item_type_critical_explain')."</div>".
  "</div>";

  // ACTUAL EXPLAINER

  $reslocal .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line header\">".locale_string("builds_desc_tldr_0").":</div>".
      "<div class=\"line\">".locale_string("builds_desc_tldr_1")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_tldr_2")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_tldr_3")."</div>".
      "<div class=\"line demo-blocks\">".$demoblocks."</div>".
      "<div class=\"line\">".locale_string("builds_desc_tldr_4")."</div>".
      "<hr />".
      "<div class=\"line\">".locale_string("builds_desc_1")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_2")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_3")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_4")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_5")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_6")."</div>".
      "<div class=\"line\">".locale_string("builds_desc_7")."</div>".
    "</div>".
  "</details>";

  // BUILD OVERVIEW

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

    $report['starting_items']['matches'][$srid] = unwrap_data($report['starting_items']['matches'][$srid]);
    $report['starting_items']['matches'][0] = unwrap_data($report['starting_items']['matches'][0]);

    if (isset($report['starting_items']['matches'][$srid][$hero])) {
      $sti_matches_context =& $report['starting_items']['matches'][$srid][$hero];
    } else if (isset($report['starting_items']['items'][0][$hero])) {
      $sti_matches_context =& $report['starting_items']['matches'][0][$hero];
    } else if (isset($report['starting_items']['items'][$srid][0])) {
      $sti_matches_context =& $report['starting_items']['matches'][$srid][0];
    } else {
      $sti_matches_context =& $report['starting_items']['matches'][0][0];
    }

    if (isset($report['starting_items']['builds'])) {
      $report['starting_items']['builds'][$srid] = unwrap_data($report['starting_items']['builds'][$srid]);
      $report['starting_items']['builds'][0] = unwrap_data($report['starting_items']['builds'][0]);

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

  if (!empty($sti_builds)) {
    $sti_matches_context['wins'] = round($sti_matches_context['wr'] * $sti_matches_context['m']);

    $reslocal .= "<div class=\"content-header\">".locale_string("builds_starting")."</div>".
      "<div class=\"hero-build-overview-container hero-build starting-items-builds main\">";
    foreach ($sti_builds as $i => $stibuild) {
      $cnts = [];
      $reslocal .= "<div class=\"build-overview-container ".($i ? "small" : "primary")."\"><div class=\"items-list\">".
        "<div class=\"build-item-component text common\">".
          "<a class=\"item-text smaller\" title=\"".locale_string("ratio")."\">".number_format($stibuild['ratio'] * 100, 0)."%</a>".
          "<div class=\"labels\">".
            // "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate\" title=\"".locale_string("ratio")."\">R ".number_format($stibuild['ratio'] * 100, 1)."%</a>".
            "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate\" title=\"".locale_string("winrate")."\">WR ".number_format($stibuild['winrate'] * 100, 1)."%</a>".
            "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate\" title=\"".locale_string("lane_wr")."\">LWR ".number_format($stibuild['lane_wr'] * 100, 1)."%</a>".
          "</div>".
        "</div>".
        "<div class=\"build-item-arrow build-item-arrow-right\"></div>".
      "<div class=\"items-list items-list-inner\">";
      foreach($stibuild['build'] as $item) {
        if (!isset($cnts[$item])) $cnts[$item] = 0;
        $cnts[$item]++;
        $stiid = $cnts[$item] + $item*100;
        
        // $m_wo = $sti_matches_context['m']-$sti_matches_context['matches'];
        // if ($m_wo) {
        //   $wr_wo = ($sti_matches_context['wins']-$sti_stats[$stiid]['wins'])/$m_wo;
        // } else {
        //   $wr_wo = 0;
        // }

        $reslocal .= itembuild_item_component_simple($item, [
          'pcnt' => $cnts[$item],
          'winrate' => $sti_stats[$stiid]['wins']/$sti_stats[$stiid]['matches'],
          'lane_wr' => $sti_stats[$stiid]['lane_wins']/$sti_stats[$stiid]['matches'],
          'prate' => $sti_stats[$stiid]['matches']/$sti_matches_context['m'],
          // 'wr_incr' => $sti_stats[$stiid]['winrate'] - $wr_wo,
        ]);
      }
      $reslocal .= "</div></div></div>";
    }
    $reslocal .= "</div>";
  }

  $reslocal .= "<div class=\"content-header\">".locale_string("builds_main_build")."</div>";

  $reslocal .= "<div class=\"hero-build-overview-container hero-build\">";
  
  $overview_categories = [
    "early" => [],
    "core" => [],
    "lategame" => [],
    "situational_early" => [],
    "situational" => [],
  ];

  if (!empty($build['early'][0])) {
    foreach ($build['early'][0] as $item => $stats) {
      if ($stats['prate'] > 0.8) {
        $overview_categories['early'][] = $item;
      } else if ($stats['prate'] > 0.45) {
        $overview_categories['situational_early'][] = $item;
      }
    }
  }
  $overview_categories['early'][] = $build['path'][0];

  if (isset($build['early'][1])) {
    foreach ($build['early'][1] as $item => $stats) {
      if ($stats['prate'] > 0.85) {
        $overview_categories['core'][] = $item;
      } else if ($stats['prate'] > 0.45) {
        $overview_categories['situational_early'][] = $item;
      }
    }
  }
  $overview_categories['core'] = array_merge($overview_categories['core'], array_slice($build['path'], 1, $build['lategamePoint']-2));

  foreach ($build['sit'] as $item => $order)  {
    if ($order < $build['lategamePoint']-1) {
      $overview_categories['situational_early'][] = $item;
    } else {
      $overview_categories['situational'][] = $item;
    }
  }

  if (count($build['path']) > $build['lategamePoint']+9) {
    $overview_categories['lategame'] = array_slice($build['path'], $build['lategamePoint']-1, 10);
    $overview_categories['situational'] = array_merge(array_slice($build['path'], $build['lategamePoint']+9), $overview_categories['situational']);
  } else {
    $overview_categories['lategame'] = array_slice($build['path'], $build['lategamePoint']-1);
  }

  foreach ($build['lategame'] as $item)  {
    if (!in_array($item, $overview_categories['lategame']) && !in_array($item, $overview_categories['situational']) && $build['stats'][$item]['prate'] > 0.075) {
      $overview_categories['situational'][] = $item;
    }
  }
  foreach ($overview_categories['lategame'] as $item)  {
    if (isset($build['alt'][$item])) {
      foreach ($build['alt'][$item] as $altitem) {
        if (!in_array($altitem, $overview_categories['situational'])) {
          $overview_categories['situational'][] = $altitem;
        }
      }
    }
  }

  usort($overview_categories['lategame'], function($a, $b) use (&$build) {
    return $build['stats'][$a]['med_time'] <=> $build['stats'][$b]['med_time'];
  });


  foreach ($overview_categories as $codename => $items) {
    $reslocal .= "<div class=\"build-overview-container main-build-$codename\">";
    $reslocal .= "<div class=\"header\">".locale_string("builds_".$codename)."</div>";
    if (empty($items)) {
      $reslocal .= "<div class=\"content-text\">".locale_string("stats_no_elements")."</div>";
    } else {
      foreach ($items as $item) {
        $reslocal .= itembuild_item_component($build, $item);
      }
    }
    $reslocal .= "</div>";

    if ($codename == "lategame") $reslocal .= "<div class=\"flexbreak\"></div>";
  }

  $reslocal .= "<div class=\"build-overview-container main-build-alts\">";
  $reslocal .= "<div class=\"header\">".locale_string("builds_alts")."</div>";
  if (empty($build['alt'])) {
    $reslocal .= "<div class=\"content-text\">".locale_string("stats_no_elements")."</div>";
  } else {
    $reslocal .= "<div class=\"container-content\">";
    foreach ($build['alt'] as $item => $alts) {
      $reslocal .= "<div class=\"items-list items-alts\">";
      $reslocal .= itembuild_item_component($build, $item);
      $reslocal .= "<div class=\"build-item-arrow build-item-arrow-right\"></div><div class=\"items-list items-list-inner\">";
      foreach ($alts as $alt) {
        $reslocal .= itembuild_item_component($build, $alt);
      }
      $reslocal .= "</div></div>";
    }
    $reslocal .= "</div>";
  }
  $reslocal .= "</div>";

  $reslocal .= "<div class=\"build-overview-container main-build-swaps\">";
  $reslocal .= "<div class=\"header\">".locale_string("builds_swaps")."</div>";
  if (empty($build['swap'])) {
    $reslocal .= "<div class=\"content-text\">".locale_string("stats_no_elements")."</div>";
  } else {
    foreach ($build['swap'] as $i1 => $i2) {
      $reslocal .= "<div class=\"items-list items-swaps\">".
        itembuild_item_component($build, $i1).
        "<div class=\"build-item-arrow build-item-arrow-swap\"></div>".
        itembuild_item_component($build, $i2).
      "</div>";
    }
  }
  $reslocal .= "</div>";

  // closing block
  $reslocal .= "</div>";

  // timeline

  $reslocal .= "<div class=\"content-header\">".locale_string("builds_timeline")."</div>";

  $reslocal .= "<div class=\"hero-build-timeline-container hero-build\">";

  $time = 0;
  for ($i=0; $i < $build['lategamePoint']; $i++) {
    if (!isset($build['path'][$i])) continue;
    if (!empty($build['early'][$i])) {
      uasort($build['early'][$i], function($b, $a) {
        return $a['prate'] <=> $b['prate'];
      });

      $reslocal .= "<div class=\"build-timeline-order early".(reset($build['early'][$i])['prate'] >= 0.85 ? ' important' : '')."\">";
      $reslocal .= "<div class=\"build-timeline-header\">".locale_string("builds_early")."</div>";

      foreach ($build['early'][$i] as $item => $stats) {
        $reslocal .= itembuild_item_component($build, $item, [ 'big' => $stats['prate'] >= 0.85, 'smallest' => $stats['prate'] < 0.5 ]);
      }
      $reslocal .= "</div>";
    }

    $situationals = array_keys(
      array_filter($build['sit'], function($a) use ($i) {
        return $a == $i;
      })
    );
    if (!empty($situationals)) {
      $reslocal .= "<div class=\"build-timeline-order situationals".(count($situationals) > 3 ? " wide" : "")."\">";
      $reslocal .= "<div class=\"build-timeline-header\">".locale_string("builds_situational_short")."</div>";
      foreach ($situationals as $item) $reslocal .= itembuild_item_component($build, $item);
      $reslocal .= "</div>";
    }

    $time += $build['times'][$i];
    $reslocal .= "<div class=\"build-timeline-order main\">";
    $reslocal .= "<div class=\"build-timeline-header\">"."~".round($time)." ".locale_string('minute_short')."</div>";
    $reslocal .= itembuild_item_component($build, $build['path'][$i], [ 'big' => true ]);

    if (isset($build['swap'][ $build['path'][$i] ]) || in_array($build['path'][$i], $build['swap'])) {
      $reslocal .= "<div class=\"build-timeline-alts\">";
      $reslocal .= "<div class=\"build-timeline-alts-header build-timeline-header\">".locale_string("builds_swaps_subheader")."</div>";
      $swaps = [];
      foreach ($build['swap'] as $i1 => $i2) {
        if ($i1 != $build['path'][$i] && $i2 != $build['path'][$i]) continue;
        $iid = $i1 == $build['path'][$i] ? $i2 : $i1;
        if (in_array($iid, $swaps)) continue;

        $swaps[] = $iid;
        $reslocal .= itembuild_item_component($build, $iid, [ 'small' => true, 'at_time' => $time ]);
      }
      $reslocal .= "</div>";  
    }

    if (isset($build['alt'][ $build['path'][$i] ])) {
      $reslocal .= "<div class=\"build-timeline-alts\">";
      $reslocal .= "<div class=\"build-timeline-alts-header build-timeline-header\">".locale_string("builds_alt_subheader")."</div>";
      foreach ($build['alt'][ $build['path'][$i] ] as $item) $reslocal .= itembuild_item_component($build, $item);
      $reslocal .= "</div>";
    }
    $reslocal .= "</div>";
  }

  $reslocal .= "</div>";

  // lategame

  $reslocal .= "<div class=\"content-header\">".locale_string("builds_lategame")."</div>";

    if (empty($build['lategame'])) {
      $reslocal .= "<div class=\"content-text\">".locale_string("stats_no_elements")."</div>";
    } else {
      $lategame = array_slice($build['path'], $build['lategamePoint']-1);

      $reslocal .= "<div class=\"hero-build-overview-container hero-build\">";
      
      if (!empty($lategame)) {
        $reslocal .= "<div class=\"build-blocks-container\">";
        $reslocal .= "<div class=\"header\">".locale_string("builds_lategame_main_route")."</div>";
    
        foreach ($lategame as $item) {
          $reslocal .= itembuild_item_component($build, $item);
        }
        $reslocal .= "</div>";
      }

      $reslocal .= "<div class=\"build-blocks-container\">";
      $reslocal .= "<div class=\"header\">".locale_string("builds_lategame_all")."</div>";
      usort($build['lategame'], function($a, $b) use (&$build) {
        return $build['stats'][$a]['med_time'] <=> $build['stats'][$b]['med_time'];
      });
      foreach($build['lategame'] as $item) {
        $reslocal .= itembuild_item_component($build, $item);
      }
      $reslocal .= "</div>";
    }

  $reslocal .= "</div>";
  // full neutral items

  if (!empty($build['neutrals'])) {
    $reslocal .= "<div class=\"content-header\">".locale_string("builds_neutrals")."</div>";
    $reslocal .= "<div class=\"hero-build-overview-container hero-build\">";
    $reslocal .= "<div class=\"build-blocks-container\">";
    foreach ($build['neutrals'] as $i => $items) {
      $reslocal .= "<div class=\"items-list\">".
        "<div class=\"build-item-component text common\"><a class=\"item-text\">T$i</a></div>".
        "<div class=\"build-item-arrow build-item-arrow-right\"></div><div class=\"items-list items-list-inner\">";
      foreach ($items as $j => $item) {
        $reslocal .= itembuild_item_component($build, $item);
      } 
      $reslocal .= "</div></div>";
    }
    $reslocal .= "</div>";
    $reslocal .= "</div>";
  }

  // other value items

  if (!empty($build['significant'])) {
    $reslocal .= "<div class=\"content-header\">".locale_string("builds_other_value")."</div>";
    $reslocal .= "<div class=\"hero-build-overview-container hero-build\">";
    foreach($build['significant'] as $item) {
      $reslocal .= itembuild_item_component($build, $item);
    }
    $reslocal .= "</div>";
  }

  $res[$tag][$roletag] .= $reslocal;

  return $res;
}


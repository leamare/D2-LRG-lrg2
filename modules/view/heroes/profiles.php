<?php

// include_once($root."/modules/view/functions/itembuilds.php");
// include_once($root."/modules/view/items/builds.php");
include_once($root."/modules/view/generators/laning.php");
include_once($root."/modules/view/generators/matches_list.php");
include_once($root."/modules/view/heroes/daily_wr.php");

$modules['heroes']['profiles'] = [];

function pickban_partial($context, $context_main, $hero) {
  $context_total_matches = $context_main['matches'] ?? $context_main["matches_total"] ?? 0;
  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;

  if (!isset($context[$hero])) {
    return null;
  }

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

  $ranks = [];
  $antiranks = [];
  $context_copy = $context;

  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;
  $last_rank = 0;

  foreach ($context as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);
  unset($context_copy);

  $context_copy = $context;
  foreach($context_copy as &$el)  {
    $el['winrate_picked'] = 1-$el['winrate_picked'];
    $el['winrate_banned'] = 1-$el['winrate_banned'];
  }

  uasort($context_copy, $compound_ranking_sort);

  $increment = 100 / sizeof($context_copy); $i = 0;

  foreach ($context_copy as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $antiranks[$id] = $last_rank;
    } else
      $antiranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $antiranks[$id];
  }
  unset($last);
  unset($context_copy);

  $context[$hero]['contest_rate'] = $el['matches_total']/$context_total_matches;
  $context[$hero]['rank'] = $ranks[$hero];
  $context[$hero]['antirank'] = $antiranks[$hero];
  $context[$hero]['mp'] = $context[$hero]['matches_picked']/$mp;
  $context[$hero]['mb'] = $context[$hero]['matches_banned']/$mb;

  return $context[$hero];
}

function rg_view_generate_heroes_profiles() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $hero_renderer_provider, $links_providers, $leaguetag, $use_graphjs, $linkvars;

  if($mod == $parent."profiles") $unset_module = true;
  $parent_module = $parent."profiles-";
  $res = [];

  $hnames = [];
  foreach ($meta['heroes'] as $id => $v) {
    $hnames[$id] = $v['name'];
    $strings['en']["heroid".$id] = $v['name'];
  }

  uasort($hnames, function($a, $b) {
    if($a == $b) return 0;
    else return ($a > $b) ? 1 : -1;
  });

  foreach($hnames as $hid => $name) {
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      $hero = $hid;
    }
  }

  if (isset($report['hero_summary'])) {
    if (is_wrapped($report['hero_summary'])) $report['hero_summary'] = unwrap_data($report['hero_summary']);
  }

  if (isset($report['hero_summary']) && isset($report['hero_summary'][$hero])) {
    $data = $report['hero_summary'][$hero];
  } else {
    $data = [
      'matches_s' => 0,
    ];
  }

  $total_matches = $data['matches_s'];
  $scripts = [];

  if (isset($report['hero_positions'])) {
    if (is_wrapped($report['hero_positions'])) $report['hero_positions'] = unwrap_data($report['hero_positions']);

    $roles = [];

    generate_positions_strings();

    for ($i=1; $i>=0; $i--) {
      for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
        if (empty($report['hero_positions'][$i][$j]) || empty($report['hero_positions'][$i][$j][$hero])) {
          continue;
        }
        
        $roles["$i.$j"] = [ $report['hero_positions'][$i][$j][$hero]['matches_s'], $report['hero_positions'][$i][$j][$hero]['winrate_s'] ];

        if (isset($report['hero_positions_matches'])) {
          foreach($report['hero_positions_matches'][$i][$j] as $hid => $matches) {
            $roles["$i.$j"][] = "<a onclick=\"showModal('".
              htmlspecialchars(join_matches($matches)).
              "', '".locale_string("matches")." - ".addcslashes(hero_name($hid)." - ".locale_string("position_$i.$j"), "'")."');\">".
              locale_string("matches")."</a>";
          }
        }
      }
    }

    if (!empty($roles)) {
      uasort($roles, function($a, $b) {
        return $b[0] <=> $a[0];
      });

      $main_role = array_keys($roles)[0];
    }
  }

  $data = array_slice($data, 0, 2, true) + 
    [ "common_position" => isset($main_role) ? locale_string("position_".$main_role) : locale_string("none") ] + 
    array_slice($data, 2, count($data)-1);

  $rare = rand(0, 100);

  $res['heroid'.$hero] .= "<div class=\"profile-header bigger-image\">".
    "<div class=\"profile-image\"><img src=\"".
      str_replace("%HERO%", $meta['heroes'][$hero]['tag'].($rare <= 10 ? ($rare < 5 ? "_ultrarare" : "_rare") : ""), $hero_renderer_provider ?? "").
      "\" /></div>".
    "<div class=\"profile-name\">".hero_link($hero)."</div>".
    (!empty($meta['heroes'][$hero]['alt']) ? "<div class=\"profile-name subheader\">".$meta['heroes'][$hero]['alt']."</div>" : "").
    "<div class=\"profile-content\">";
  
  $count = count($data);
  $gr_size = round($count / 3);
  $desc_blocks = [];

  $i = 0; $block = [];
  foreach ($data as $k => $v) {
    $k = str_replace("_s", "", $k);

    if (strpos($k, "duration") !== FALSE || strpos($k, "_len") !== FALSE) {
      $v = convert_time($v);
    } else if (strpos($k, "matches") !== FALSE) {
      
    } else if (strpos($k, "winrate") !== FALSE) {
      $v = number_format($v*100, 2)."%";
    } else if(is_numeric($v)) {
      if ($v > 10)
        $v = number_format($v,1);
      else if ($v > 1)
        $v = number_format($v,2);
      else
        $v = number_format($v,3);
    }

    $block[] = "<label>".locale_string($k)."</label>: ".$v;
    $i++;
    if ($i == $gr_size || $i == $count) {
      $desc_blocks[] = "<div class=\"profile-statline\">".implode("</div><div class=\"profile-statline\">", $block)."</div>";
      $block = [];
      $i = 0;
    }
  }
  
  $res['heroid'.$hero] .= "<div class=\"profile-stats\">".implode("</div><div class=\"profile-stats\">", $desc_blocks)."</div>";

  $res['heroid'.$hero] .= "</div>";

  $combos = [];
  $combos_limit = 5;

  if (isset($report['hph'])) {
    if (is_wrapped($report['hph'])) {
      $report['hph'] = unwrap_data($report['hph']);
    }

    if (isset($report['hph'][$hero])) {
      foreach ($report['hph'][$hero] as $h => $data) {
        if (empty($data) || !$data['matches'] || $h == "_h") unset($report['hph'][$hero][$h]);
      }

      uasort($report['hph'][$hero], function($a, $b) {
        return $b['wr_diff'] <=> $b['wr_diff'];
      });

      $keys = array_keys($report['hph'][$hero]);

      $combos['best_friends'] = array_slice($keys, 0, $combos_limit);
      $combos['worst_friends'] = array_slice($keys, count($keys)-$combos_limit);
    }
  }

  if (isset($report['hvh'])) {
    $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);

    if (isset($hvh[$hero])) {
      foreach ($hvh[$hero] as $h => $data) {
        if (empty($data) || !$data['matches'] || $h == "_h") unset($hvh[$h]);
      }

      uasort($hvh[$hero], function($a, $b) {
        return $b['winrate'] <=> $b['winrate'];
      });

      $keys = array_keys($hvh[$hero]);

      $combos['worst_opponents'] = array_slice($keys, 0, $combos_limit);
      $combos['best_opponents'] = array_slice($keys, count($keys)-$combos_limit);
    }
  }
  

  if (!empty($combos)) {
    $res['heroid'.$hero] .= "<div class=\"profile-content\">";

    foreach($combos as $k => $heroes) {
      $res['heroid'.$hero] .= "<div class=\"profile-stats\"><div class=\"profile-stats-header\">".locale_string($k)."</div><div class=\"profile-stats-icons\">";
      foreach ($heroes as $h) {
        $res['heroid'.$hero] .= hero_icon($h);
      }
      $res['heroid'.$hero] .= "</div></div>";
    }

    $res['heroid'.$hero] .= "</div>";
  }

  $res['heroid'.$hero] .= "</div>";

  if (!empty($combos)) {
    $links = [];
    if (!empty($report['hph']))
      $links[] = "<a href=\"?league=$leaguetag&mod=heroes-hph-heroid$hero".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string('hph')."</a>";
    if (!empty($report['hvh']))
      $links[] = "<a href=\"?league=$leaguetag&mod=heroes-hvh-heroid$hero".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string('hvh')."</a>";
    if (!empty($report['matches']) && $report['matches_additional'])
      $links[] = "<a href=\"?league=$leaguetag&mod=matches-heroes-heroid$hero".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string('recent_matches')."</a>";

    $res['heroid'.$hero] .= "<div class=\"content-text\">".implode(" / ", $links)."</div>";
  }

  // PICKBAN
  $el = pickban_partial($report['pickban'], $report['random'], $hero);

  if (!$el) return $res;

  // trends
  if (isset($report['hero_daily_wr'])) {
    $use_graphjs = true;

    if (is_wrapped($report['hero_daily_wr'])) {
      $days = $report['hero_daily_wr']['head'][0];
      sort($days);
      $report['hero_daily_wr_days'] = $days;
      $report['hero_daily_wr'] = unwrap_data($report['hero_daily_wr']);
    } else {
      $days = array_keys($report['hero_daily_wr'][ array_keys($report['hero_daily_wr'])[0] ]);
      sort($days);
    }
  
    $global_days = [];
    if (count($days) == count($report['days'])) {
      foreach($report['days'] as $d) {
        $global_days[ $d['timestamp'] ] = $d['matches_num'];
      }
    } else {
      $sz = count($days)-1; $i = -1;
      foreach($report['days'] as $d) {
        if ($i < $sz && $d['timestamp'] >= $days[ $i+1 ]) {
          $i++;
          $global_days[ $days[$i] ] = 0;
        }
        $global_days[ $days[$i] ] += $d['matches_num'];
      }
    }
  
    $days_data = $report['hero_daily_wr'][$hero];

    $dwr = []; $dm = []; $dmb = []; $prev = null; $prev_b = null;
    $prev_dt = null;
    $first_wr = 0; $first_ms = 0; $first_msb = 0;
    foreach($days as $dt) {
      $dd = $days_data[$dt] ?? [ 'ms' => 0, 'wr' => 0 ];

      if ($prev_dt == null || $global_days[$dt]/$prev_dt > 0.05) {
        $prev_dt = $global_days[$dt] ?? null;
          $dmb[] = round(100*($dd['bn'] ?? 0)/$global_days[$dt], 2);
        if (!$first_msb && ($dd['bn'] ?? 0)) {
          $first_msb = round(100*$dd['bn']/$global_days[$dt], 2);
        }

        if (!$first_ms && $dd['ms']) {
          $first_ms = round(100*$dd['ms']/$global_days[$dt], 2);
          $first_wr = $dd['wr']*100;
        }

        $dwr[] = $dd['wr']*100;
        $dm[] = round(100*$dd['ms']/$global_days[$dt], 2);
      } else {
        $dmb[] = $dmb[ sizeof($dmb)-1 ];
        $dm[] = $dm[ sizeof($dm)-1 ];
        $dwr[] = $dwr[ sizeof($dwr)-1 ];
      }
    }

    $labels = [];
    foreach ($days as $timestamp) {
      $labels[] = date(locale_string("date_format"), $timestamp);
    }

    $res['heroid'.$hero] .=  "<table id=\"profile-$hero-dailywr\" class=\"list\"><caption>".locale_string("daily_wr")."</caption><thead><tr>".
      "<th>".locale_string("value")."</th>".
      "<th>".locale_string("trends_first")."</th>".
      "<th width=\"60%\"></th>".
      "<th>".locale_string("trends_last")."</th>".
      "<th>".locale_string("trends_diff")."</th>".
      "</tr></thead><tbody>". 
      "<tr>".
        "<td>".locale_string("trends_winrate")."</td>".
        "<td class=\"separator\">".number_format($first_wr, 2)."%</td>".
        "<td><div style=\"position: relative; width: 100%; height: 140px\"><canvas id=\"hero-daily-wr-$hid\"></canvas></div></td>".
        "<td>".number_format($dwr[ count($dwr)-1 ], 2)."%</td>".
        "<td>".number_format($dwr[ count($dwr)-1 ]-$first_wr, 2)."%</td>".
      "</tr><tr>".
        "<td>".locale_string("pickrate")."</td>".
        "<td class=\"separator\">".number_format($first_ms, 2)."%</td>".
        "<td><div style=\"position: relative; width: 100%; height: 140px\"><canvas id=\"hero-daily-matches-$hid\"></canvas></div></td>".
        "<td>".number_format($dm[ count($dm)-1 ], 2)."%</td>".
        "<td>".number_format($dm[ count($dm)-1 ]-$first_ms, 2)."%</td>".
      "</tr><tr>".
        "<td>".locale_string("banrate")."</td>".
        "<td class=\"separator\">".number_format($first_msb, 2)."%</td>".
        "<td><div style=\"position: relative; width: 100%; height: 140px\"><canvas id=\"hero-daily-bans-$hid\"></canvas></div></td>".
        "<td>".number_format($dmb[ count($dmb)-1 ], 2)."%</td>".
        "<td>".number_format($dmb[ count($dmb)-1 ]-$first_msb, 2)."%</td>".
      "</tr>".
    "</tbody></table>";

    // winrate
    $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(92, 176, 255)'", $labels, $dwr, "hero-daily-wr-$hid", true);

    // bans
    $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(255, 164, 96)'", $labels, $dmb, "hero-daily-bans-$hid", true);

    // pickrate
    $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(128, 224, 96)'", $labels, $dm, "hero-daily-matches-$hid", true);
  }

  // Basic item build + best items + link

  if (isset($report['items']['progr'])) {
    if (is_wrapped($report['items']['progr'])) {
      $report['items']['progr'] = unwrap_data($report['items']['progr']);
    }
    if (is_wrapped($report['items']['stats'])) {
      $report['items']['stats'] = unwrap_data($report['items']['stats']);
    }

    if (isset($report['items']['progr'][$hero])) {
      $res['heroid'.$hero] .= "<div class=\"content-text\"><h1>".locale_string("build_basic")."</h1></div>";

      $pairs = [];

      foreach ($report['items']['progr'][$hero] as $v) {
        if (empty($v)) continue;
        if ($v['item1'] == $v['item2']) continue;
        $pairs[] = $v;
      }

      $report['items']['stats'][$hero] = array_filter($report['items']['stats'][$hero], function($a) {
        return !empty($a);
      });

      $el['role_matches'] = $el['matches_picked'];
      [ $build, $tree ] = generate_item_builds($pairs, $report['items']['stats'][$hero], $el);

      if (!empty($build['path'])) {
        $res['heroid'.$hero] .=  "<div class=\"hero-build-overview-container hero-build\">";
        $res['heroid'.$hero] .= "<div class=\"build-overview-container main-build-alts\">";
        $sz = count($build['path']);
        for ($i = 0; $i < $build['lategamePoint'] && isset($build['path'][$i]); $i++) {
          if (isset($build['early'][$i])) {
            foreach ($build['early'][$i] as $iid => $stats) {
              if ($stats['prate'] < 0.85) continue;
              $res['heroid'.$hero] .= itembuild_item_component($build, $iid);
            }
          }
          $res['heroid'.$hero] .= itembuild_item_component($build, $build['path'][$i]);
        }
        $res['heroid'.$hero] .= "</div></div>";

        $res['heroid'.$hero] .= "<div class=\"content-text\">".
          "<a href=\"?league=$leaguetag&mod=items-builds-heroid$hero".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string('builds_more')."</a>".
        "</div>";
      } else {
        $res['heroid'.$hero] .= "<div class=\"content-text\">".
          locale_string('items_stats_empty').
        "</div>";
      }
    }
  }

  $res['heroid'.$hero] .=  "<table id=\"profile-$hero-pickban\" class=\"list\"><caption>".locale_string("pickban")."</caption><thead><tr>".
    "<th>".locale_string("matches_total")."</th>".
    "<th class=\"separator\">".locale_string("contest_rate")."</th>".
    "<th>".locale_string("rank")."</th>".
    "<th>".locale_string("antirank")."</th>".
    "<th class=\"separator\">".locale_string("matches_picked")."</th>".
    // "<th>".locale_string("pickrate")."</th>".
    "<th>".locale_string("winrate")."</th>".
    "<th>".locale_string("mp")."</th>".
    "<th class=\"separator\">".locale_string("matches_banned")."</th>".
    // "<th>".locale_string("banrate")."</th>".
    "<th>".locale_string("winrate")."</th>".
    "<th>".locale_string("mb")."</th>".
    "</tr></thead><tbody>". 
    "<tr>".
    "<td>".$el['matches_total']."</td>".
    "<td class=\"separator\">".number_format($el['contest_rate']*100,2)."%</td>".
    "<td>".number_format($el['rank'],2)."</td>".
    "<td>".number_format($el['antirank'],2)."</td>".
    "<td class=\"separator\">".$el['matches_picked']."</td>".
    // "<td>".number_format($el['matches_picked']/$context_total_matches*100,2)."%</td>".
    "<td>".number_format($el['winrate_picked']*100,2)."%</td>".
    "<td>".number_format($el['mp'], 1)."</td>".
    "<td class=\"separator\">".$el['matches_banned']."</td>".
    // "<td>".number_format($el['matches_banned']/$context_total_matches*100,2)."%</td>".
    "<td>".number_format($el['winrate_banned']*100,2)."%</td>".
    "<td>".number_format($el['mb'], 1)."</td>".
    "</tr>".
  "</tbody></table>";

  if (isset($report['matches']) && isset($report['matches_additional'])) {
    $res['heroid'.$hero] .= "<div class=\"content-text\"><h1>".locale_string("recent_matches")."</h1></div>";
    $res['heroid'.$hero] .= rg_generator_hero_matches_list("profile-$hero-recent", $hero, 5);
    $res['heroid'.$hero] .= "<div class=\"content-text\">".
      "<a href=\"?league=$leaguetag&mod=matches-heroes-heroid$hero".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string('full_matches')."</a>".
    "</div>";
  }

  // factions
  if (isset($report['hero_sides'])) {
    $res['heroid'.$hero] .= "<table id=\"profile-$hero-pickban\" class=\"list\"><caption>".locale_string("sides")."</caption><thead><tr>".
      "<th>".locale_string("side")."</th>".
      "<th class=\"separator\">".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("gpm")."</th>".
      "<th>".locale_string("xpm")."</th>".
    "</tr></thead><tbody>";

    foreach ($report['hero_sides'] as $side => $heroes) {
      foreach ($heroes as $data) {
        if ($data['heroid'] != $hero) continue;
        
        $res['heroid'.$hero] .= "<tr>".
          "<td>".locale_string($side ? 'dire' : 'radiant')."</td>".
          "<td class=\"separator\">".$data['matches']."</td>".
          "<td>".number_format(100*$data['winrate'], 2)."%</td>".
          "<td>".number_format($data['gpm'],0)."</td>".
          "<td>".number_format($data['xpm'],0)."</td>".
        "</tr>";

        break;
      }
    }
    
    $res['heroid'.$hero] .= "</tbody></table>";
  }

  // Draft stages

  if (isset($report['draft'])) {
    $stages = [];

    for ($pick = 0; $pick <= 1; $pick++) {
      foreach ($report['draft'][$pick] as $stage => $heroes) {
        if (!isset($stages[$stage])) {
          $stages[$stage] = [
            'total' => 0,
            'pick_wr' => 0,
            'picks' => 0,
            'ban_wr' => 0,
            'bans' => 0,
          ];
        }
        
        foreach ($heroes as $stats) {
          if ($stats['heroid'] != $hero) continue;

          $stages[$stage]['total'] += $stats['matches'];
          $stages[$stage][$pick ? 'picks' : 'bans'] = $stats['matches'];
          $stages[$stage][$pick ? 'pick_wr' : 'ban_wr'] = $stats['winrate'];

          break;
        }
      }
    }

    $res['heroid'.$hero] .= "<table id=\"profile-$hero-pickban\" class=\"list sortable\"><caption>".locale_string("draft")."</caption><thead><tr>".
      "<th>".locale_string("stage")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th class=\"separator\">".locale_string("picks")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "<th class=\"separator\">".locale_string("bans")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "</tr></thead><tbody>";

    foreach ($stages as $stage => $data) {
      $res['heroid'.$hero] .= "<tr>".
        "<td>".$stage."</td>".
        "<td>".$data['total']."</td>".
        "<td class=\"separator\">".$data['picks']."</td>".
        "<td>".number_format($data['pick_wr']*100, 2)."%</td>".
        "<td>".number_format($el['matches_picked'] ? 100*$data['picks']/$el['matches_picked'] : 0, 2)."%</td>".
        "<td class=\"separator\">".$data['bans']."</td>".
        "<td>".number_format($data['ban_wr']*100, 2)."%</td>".
        "<td>".number_format($el['matches_banned'] ? 100*$data['bans']/$el['matches_banned'] : 0, 2)."%</td>".
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";
  }
  
  // ROLES
  
  if (!empty($roles)) {
    $res['heroid'.$hero] .=  "<table id=\"profile-$hero-roles\" class=\"list sortable\"><caption>".locale_string("positions")."</caption><thead><tr>".
      "<th>".locale_string("position")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "<th>".locale_string("winrate")."</th>".
      (isset($report['hero_positions_matches']) ? "<th>".locale_string("matches")."</th>" : "").
      "</tr>".
    "</thead><tbody>";
  
    foreach ($roles as $role => $data) {
      $res['heroid'.$hero] .= "<tr>".
        "<td><a href=\"?league=$leaguetag&mod=heroes-positions-position_$role".(empty($linkvars) ? "" : "&".$linkvars)."\">".
          locale_string("position_$role").
        "</a></td>".
        "<td>".$data[0]."</td>".
        "<td>".number_format(100*$data[0]/$total_matches, 2)."%</td>".
        "<td>".number_format($data[1]*100, 2)."%</td>".
        (isset($data[2]) ? "<td>".$data[2]."</td>" : "").
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";
  }

  // REGIONS - pickban stats

  if (isset($report['regions_data'])) {
    $regions = [];
    $positions = false;

    foreach ($report['regions_data'] as $region => $data) {
      $reg = [];

      if (!isset($data['pickban']) || !isset($data['pickban'][$hero])) continue;

      $context_main = $data['random'] ?? $data['main'];

      $context_total_matches = $context_main['matches'] ?? $context_main["matches_total"] ?? 0;

      $reg[] = [
        'role' => null,
        'contest' => $data['pickban'][$hero]['matches_total']/$context_total_matches,
        'pickrate' => $data['pickban'][$hero]['matches_picked']/$context_total_matches,
        'matches' => $data['pickban'][$hero]['matches_picked'],
        'winrate' => $data['pickban'][$hero]['winrate_picked'],
        'ratio' => 1,
        'link' => "?league=$leaguetag&mod=regions-region$region-heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)
      ];

      if (isset($data['hero_positions'])) {
        $positions = true;

        if (is_wrapped($data['hero_positions'])) {
          $data['hero_positions'] = unwrap_data($data['hero_positions']);
        }

        for ($i=1; $i>=0; $i--) {
          for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
            if (empty($data['hero_positions'][$i][$j]) || empty($data['hero_positions'][$i][$j][$hero])) {
              continue;
            }
            
            $reg[] = [
              'role' => "$i.$j",
              'contest' => $data['hero_positions'][$i][$j][$hero]['matches_s']/$context_total_matches,
              'pickrate' => $data['hero_positions'][$i][$j][$hero]['matches_s']/$context_total_matches,
              'matches' => $data['hero_positions'][$i][$j][$hero]['matches_s'],
              'winrate' => $data['hero_positions'][$i][$j][$hero]['winrate_s'],
              'ratio' => $data['hero_positions'][$i][$j][$hero]['matches_s']/$data['pickban'][$hero]['matches_picked'],
              'link' => "?league=$leaguetag&mod=regions-region$region-heroes-positions-position_$i.$j".(empty($linkvars) ? "" : "&".$linkvars)
            ];
          }
        }
      }

      $regions[$region] = $reg;
    }

    if (!empty($regions)) {
      $res['heroid'.$hero] .= "<div class=\"content-text\"><h1>".locale_string("regions")."</h1></div>";

      $res['heroid'.$hero] .= search_filter_component("profile-$hero-regions");
      $res['heroid'.$hero] .=  "<table id=\"profile-$hero-regions\" class=\"list sortable\"><thead><tr>".
        "<th>".locale_string("region")."</th>".
        "<th>".locale_string("position")."</th>".
        "<th>".locale_string("contest_rate")."</th>".
        "<th>".locale_string("pickrate")."</th>".
        "<th>".locale_string("matches")."</th>".
        "<th>".locale_string("ratio")."</th>".
        "<th>".locale_string("winrate")."</th>".
        "<th>".locale_string("link")."</th>".
        "</tr>".
      "</thead><tbody>";

      foreach ($regions as $region => $lines) {
        foreach ($lines as $line) {
          $res['heroid'.$hero] .= "<tr>".
            "<td>".locale_string("region$region")."</td>".
            "<td>".locale_string($line['role'] ? "position_".$line['role'] : "total")."</td>".
            "<td>".number_format($line['contest']*100, 2)."%</td>".
            "<td>".number_format($line['pickrate']*100, 2)."%</td>".
            "<td>".$line['matches']."</td>".
            "<td>".number_format($line['ratio']*100, 2)."%</td>".
            "<td>".number_format($line['winrate']*100, 2)."%</td>".
            "<td><a href=\"".$line['link']."\">".locale_string("link")."</a></td>".
          "</tr>";
        }
      }

      $res['heroid'.$hero] .= "</tbody></table>";
    }
  }

  // Laning stats -> link

  if (isset($report['hero_laning'])) {
    if (is_wrapped($report['hero_laning'])) {
      $report['hero_laning'] = unwrap_data($report['hero_laning']);
    }

    $res['heroid'.$hero] .= "<table id=\"profile-$hero-laning\" class=\"list\"><caption>".locale_string("laning")."</caption>".
      "<thead><tr class=\"overhead\">".
        "<th width=\"18%\" colspan=\"3\"></th>".
        "<th class=\"separator\" colspan=\"4\">".locale_string("lane_advantage")."</th>".
      "</tr><tr>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("lane_wr")."</th>".
      "<th>".locale_string("rank")."</th>".
      "<th class=\"separator\">".locale_string("lane_win")."</th>".
      "<th>".locale_string("lane_loss")."</th>".
      "<th>".locale_string("lane_avg_gold_diff")."</th>".
      "<th>".locale_string("trends_diff")."</th>".
    "</tr></thead><tbody>";

    if ($report['hero_laning'][$hero]) {
      unset($report['hero_laning'][$hero][$hero]);
      $context =& $report['hero_laning'];

      $mm = 0;
      foreach ($context[0] as $k => $h) {
        if ($h === null) {
          unset($context[0][$k]);
          continue;
        }
        if ($h['matches'] > $mm) $mm = $h['matches'];
        if (!isset($h['matches']) || $h['matches'] == 0) unset($context[0][$k]);
      }

      uasort($context[0], function($a, $b) {
        return $a['avg_advantage'] <=> $b['avg_advantage'];
      });
      $mk = array_keys($context[0]);
      $median_adv = $context[0][ $mk[ floor( count($mk)/2 ) ] ]['avg_advantage'];

      uasort($context[0], function($a, $b) {
        return $a['avg_disadvantage'] <=> $b['avg_disadvantage'];
      });
      $mk = array_keys($context[0]);
      $median_disadv = $context[0][ $mk[ floor( count($mk)/2 ) ] ]['avg_disadvantage'];

      $compound_ranking_sort = function($a, $b) use ($mm, $median_adv, $median_disadv) {
        if ($a['matches'] == 0) return 1;
        if ($b['matches'] == 0) return -1;
        return compound_ranking_laning_sort($a, $b, $mm, $median_adv, $median_disadv);
      };
      uasort($context[0], $compound_ranking_sort);

      $increment = 100 / sizeof($context[0]); $i = 0;
      $last_rank = 0;

      foreach ($context[0] as $elid => $eldata) {
        if(isset($last) && $eldata == $last) {
          $i++;
          $context[0][$elid]['rank'] = $last_rank;
        } else
          $context[0][$elid]['rank'] = round(100 - $increment*$i++, 2);
        $last = $eldata;
        $last_rank = $context[0][$elid]['rank'];
      }

      unset($last);
    }

    $data = $context[0][$hero];
    $data['matches'] = $data['matches'] ?? 0;
    $wr_diff = $data['matches'] ? (
      ( $data['lanes_won'] ? $data['won_from_won']/$data['lanes_won'] : ( $data['lanes_tied'] ? $data['won_from_tie']/$data['lanes_tied'] : 0 ) ) - 
      ( $data['lanes_lost'] ? $data['won_from_behind']/$data['lanes_lost'] : ( $data['lanes_tied'] ? $data['won_from_tie']/$data['lanes_tied'] : 0 ) )
    ) : 0;
    if (!isset($data['avg_gold_diff'])) {
      $data['avg_gold_diff'] = $data['matches'] ? (
        $data['avg_advantage']*$data['lanes_won'] + 
        $data['avg_disadvantage']*$data['lanes_lost'] +
        ($data['avg_advantage']+$data['avg_disadvantage'])*0.5*$data['lanes_tied']
      ) / $data['matches'] : 0;
    }

    if (isset($context[0][$hero])) {
      $res['heroid'.$hero] .= "<tr>".
        "<td>".($data['matches'] ? $data['matches'] : '-')."</td>".
        "<td>".($data['matches'] ? number_format($data['lane_wr']*100, 2).'%' : '-')."</td>".
        "<td>".($data['matches'] ? number_format($data['rank'], 1) : '0')."</td>".
        "<td class=\"separator\">".($data['matches'] ? number_format($data['avg_advantage']*MIN10_GOLD, 2) : '-')."</td>".
        "<td>".($data['matches'] ? number_format($data['avg_disadvantage']*MIN10_GOLD, 2) : '-')."</td>".
        "<td>".($data['matches'] ? number_format($data['avg_gold_diff']*MIN10_GOLD, 2) : '-')."</td>".
        "<td>".($data['matches'] ? number_format(($data['avg_advantage']-$data['avg_disadvantage'])*MIN10_GOLD, 2) : '-')."</td>".
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";

    if (isset($context[0][$hero])) {
      $res['heroid'.$hero] .= "<table id=\"profile-$hero-laning-2\" class=\"list\">".
        "<thead><tr class=\"overhead\">".
          "<th colspan=\"2\">".locale_string("lane_won")."</th>".
          "<th class=\"separator\" colspan=\"2\">".locale_string("lane_tie")."</th>".
          "<th class=\"separator\" colspan=\"2\">".locale_string("lane_loss")."</th>".
          "<th class=\"separator\" colspan=\"1\">".locale_string("winrate_diff")."</th>".
        "</tr><tr>".
        "<th>".locale_string("ratio_freq")."</th>".
        "<th>".locale_string("lane_game_won")."</th>".
        "<th class=\"separator\">".locale_string("ratio_freq")."</th>".
        "<th>".locale_string("lane_game_won")."</th>".
        "<th class=\"separator\">".locale_string("ratio_freq")."</th>".
        "<th>".locale_string("lane_game_won")."</th>".

        "<th class=\"separator\">".locale_string("laning_loss_to_win")."</th>".
      "</tr></thead><tbody>";

      $res['heroid'.$hero] .= "<tr>".
        "<td>".($data['matches'] ? number_format($data['lanes_won']*100/$data['matches'], 2).'%' : '-')."</td>".
        "<td>".($data['matches'] ? ($data['lanes_won'] ? number_format($data['won_from_won']*100/$data['lanes_won'], 2) : '0').'%' : '-')."</td>".

        "<td class=\"separator\">".($data['matches'] ? number_format($data['lanes_tied']*100/$data['matches'], 2).'%' : '-')."</td>".
        "<td>".($data['matches'] ? ($data['lanes_tied'] ? number_format($data['won_from_tie']*100/$data['lanes_tied'], 2) : '0').'%' : '-')."</td>".

        "<td class=\"separator\">".($data['matches'] ? number_format($data['lanes_lost']*100/$data['matches'], 2).'%' : '-')."</td>".
        "<td>".($data['matches'] ? ($data['lanes_lost'] ? number_format($data['won_from_behind']*100/$data['lanes_lost'], 2) : '0').'%' : '-')."</td>".

        "<td class=\"separator\">".($data['matches'] ? number_format($wr_diff*100, 2).'%' : '-')."</td>".
      "</tr>";

      $res['heroid'.$hero] .= "</tbody></table>";
    }
  }

  // WR Timings (%ile)
  if (isset($report['hero_winrate_timings'])) {
    if (is_wrapped($report['hero_winrate_timings'])) {
      $report['hero_winrate_timings'] = unwrap_data($report['hero_winrate_timings']);
    }

    $res['heroid'.$hero] .= "<table id=\"heroes-wrtimings\" class=\"list\"><caption>".locale_string("wrtimings")."</caption>".
      "<thead><tr class=\"overhead\">".
        "<th></th>".
        "<th colspan=\"5\" class=\"separator\">".locale_string("duration")."</th>".
        "<th colspan=\"4\" class=\"separator\">".locale_string("trends_winrate")."</th>".
        // "<th width=\"1%\"></th>".
      "</tr><tr>".
        "<th>".locale_string("matches")."</th>".
        "<th class=\"separator\">".locale_string("q1duration")."</th>".
        "<th>".locale_string("median")."</th>".
        "<th>".locale_string("q3duration")."</th>".
        "<th>".locale_string("avg_duration")."</th>".
        "<th>".locale_string("std_dev")."</th>".
        "<th class=\"separator\">".locale_string("early_wr")."</th>".
        "<th>".locale_string("avg_winrate")."</th>".
        "<th>".locale_string("late_wr")."</th>".
        "<th>".locale_string("wr_gradient")."</th>".
    "</tr></thead><tbody>";

    if (!empty($report['hero_winrate_timings'][$hero])) {
      $data = $report['hero_winrate_timings'][$hero]; 
      $res['heroid'.$hero] .= "<tr><td>".($data['matches'])."</td>".
        "<td class=\"separator\">".convert_time_seconds($data['q1duration'])."</td>".
        "<td>".convert_time_seconds($data['q2duration'])."</td>".
        "<td>".convert_time_seconds($data['q3duration'])."</td>".
        "<td>".convert_time_seconds($data['avg_duration'])."</td>".
        "<td>".convert_time_seconds($data['std_dev'])."</td>".
        "<td class=\"separator\">".number_format($data['early_wr']*100, 2)."%</td>".
        "<td>".number_format($data['winrate_avg']*100, 2)."%</td>".
        "<td>".number_format($data['late_wr']*100, 2)."%</td>".
        "<td>".number_format($data['grad']*100, 2)."%</td>".
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";

    if (!empty($report['hero_winrate_timings'][$hero])) {
      global $use_graphjs_boxplots;
      $use_graphjs_boxplots = true;

      $res['heroid'.$hero] .= "<div class=\"content-text\">".
        "<div style=\"position: relative; width: 100%; height: 100px\"><canvas id=\"hero-timings-$hid\"></canvas></div>".
      "</div>";

      $lineFactor = 0.7;
      $d = 0.25*0.7;

      $scripts[] = "new Chart(document.getElementById(\"hero-timings-$hid\").getContext(\"2d\"), {
        type: 'horizontalBoxplot',
        data: {
          datasets: [{
            label: 'Timings',
            backgroundColor: 'rgba(20,144,255,0.7)',
            borderColor: 'rgb(20,144,255)',
            borderWidth: 1,
            outlierColor: '#999999',
            padding: 10,
            itemRadius: 0,
            order: 2,
            data: [{ min: ".$data['min_duration'].", q1: ".$data['q1duration'].", median: ".$data['q2duration'].", q3: ".$data['q3duration'].", max: ".$data['max_duration'].", mean: ".$data['avg_duration']." }]
          },
          {
            label: '".hero_tag($hid)."',
            type: 'line',
            borderColor: 'rgba(255,255,255,1)',
            fill: false,
            order: 1,
            pointRadius: 1,
            data: [
            {x: ".$data['q1duration']."+1, y: ".($lineFactor*(0.5-$data['early_wr']))."}, {x: ".($data['avg_duration']).", y: ".($lineFactor*(0.5-$data['winrate_avg']))."}, {x: ".$data['q3duration']."-1, y: ".($lineFactor*(0.5-$data['late_wr']))."}
          ] }
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: {
            display: false,
          },
          tooltips: {
            enabled: false,
          },
          scales: {
            xAxes: [{
              ticks: {
                display: false,
                max: 4600
              },
              gridLines: {
                display: false
              },
            }], 
            yAxes: [{
              gridLines: {
                display: false
              },
            }]
          }
        }
      })";
    }
  }

  // WR Spammers
  if (isset($report['hero_winrate_spammers'])) {
    if (is_wrapped($report['hero_winrate_spammers'])) {
      $report['hero_winrate_spammers'] = unwrap_data($report['hero_winrate_spammers']);
    }

    $res['heroid'.$hero] .= "<table id=\"heroes-wrspammers\" class=\"list\"><caption>".locale_string("wrplayers")."</caption>".
      "<thead><tr class=\"overhead\">".
        "<th colspan=\"2\">".locale_string("players")."</th>".
        "<th colspan=\"3\" class=\"separator\">".locale_string("matches")."</th>".
        "<th colspan=\"3\" class=\"separator\">".locale_string("wrp_q1_players")."</th>".
        "<th colspan=\"3\" class=\"separator\">".locale_string("wrp_q3_players")."</th>".
        "<th colspan=\"2\" class=\"separator\">".locale_string("wrp_diffs")."</th>".
      "</tr><tr>".
        "<th>".locale_string("wrp_1match_players")."</th>".
        "<th>".locale_string("wrp_1plus_players")."</th>".
        "<th class=\"separator\">".locale_string("wrp_q1matches")."</th>".
        "<th>".locale_string("wrp_q3matches")."</th>".
        "<th>".locale_string("wrp_max_matches")."</th>".
        "<th class=\"separator\">".locale_string("wrp_q1_wr_avg")."</th>".
        "<th>".locale_string("wrp_q1_matches_avg")."</th>".
        "<th>".locale_string("wrp_q1_players_cnt")."</th>".
        "<th class=\"separator\">".locale_string("wrp_q3_wr_avg")."</th>".
        "<th>".locale_string("wrp_q3_matches_avg")."</th>".
        "<th>".locale_string("wrp_q3_players_cnt")."</th>".
        "<th class=\"separator\">".locale_string("wr_gradient")."</th>".
        "<th>".locale_string("wrp_diff")."</th>".
    "</tr></thead><tbody>";

    if (!empty($report['hero_winrate_spammers'][$hero])) {
      $data = $report['hero_winrate_spammers'][$hero];
      $res['heroid'.$hero] .= "<tr><td>".$data['players_1only']."</td>".
        "<td>".$data['players_1plus']."</td>".
        "<td class=\"separator\">".$data['q1matches']."</td>".
        // "<td>".$data['q2matches']."</td>".
        "<td>".$data['q3matches']."</td>".
        "<td>".$data['max_matches']."</td>".
        "<td class=\"separator\">".number_format($data['q1_wr_avg']*100, 2)."%</td>".
        "<td>".number_format($data['q1_matches_avg'], 2)."</td>".
        "<td>".$data['q1_players']."</td>".
        "<td class=\"separator\">".number_format($data['q3_wr_avg']*100, 2)."%</td>".
        "<td>".number_format($data['q3_matches_avg'], 2)."</td>".
        "<td>".$data['q3_players']."</td>".
        "<td class=\"separator\">".number_format($data['grad']*100, 2)."%</td>".
        "<td>".number_format(($data['q3_wr_avg']-$data['q1_wr_avg'])*100, 2)."%</td>".
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";
  }

  // TEAMS
  if (isset($report['teams'])) {
    $teams = [];
    foreach($report['teams'] as $tid => $team) {
      if (isset($team['pickban'][ $hero ])) {
        $teams[ $tid ] = $team['pickban'][ $hero ];
      }
    }
    
    $res['heroid'.$hero] .= "<div class=\"content-text\"><h1>".locale_string("teams")."</h1></div>";

    $res['heroid'.$hero] .= search_filter_component("profile-$hero-teams");
    $res['heroid'.$hero] .=  "<table id=\"profile-$hero-teams\" class=\"list sortable\"><thead><tr>".
      "<th width=\"1%\"></th>".
      "<th>".locale_string("team")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th class=\"separator\">".locale_string("picks")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "<th class=\"separator\">".locale_string("bans")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "</tr>".
    "</thead><tbody>";

    foreach ($teams as $tid => $data) {
      $res['heroid'.$hero] .=  "<tr>".
        "<td>".team_logo($tid)."</td>".
        "<td>".team_link($tid)."</td>".
        "<td>".$data['matches_total']."</td>".
        "<td class=\"separator\">".$data['matches_picked']."</td>".
        "<td>".number_format(100*$data['winrate_picked'], 2)."%</td>".
        "<td>".number_format(100*$data['matches_picked']/$el['matches_total'], 2)."%</td>".
        "<td class=\"separator\">".$data['matches_banned']."</td>".
        "<td>".number_format(100*$data['winrate_banned'], 2)."%</td>".
        "<td>".number_format(100*$data['matches_banned']/$el['matches_total'], 2)."%</td>".
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";
  }

  // players
  if (isset($report['matches']) && isset($report['players'])) {
    $players = [];
    foreach ($report['matches'] as $mid => $heroes) {
      foreach ($heroes as $data) {
        if ($data['hero'] != $hero) continue;
        if (!isset($players[ $data['player'] ])) {
          $players[ $data['player'] ] = [
            'wins' => 0,
            'matches' => 0,
          ];
        }
        $players[ $data['player'] ]['matches']++;
        if ($data['radiant'] == $report['matches_additional'][$mid]['radiant_win'])
          $players[ $data['player'] ]['wins']++;
      }
    }

    $res['heroid'.$hero] .= "<div class=\"content-text\"><h1>".locale_string("players")."</h1></div>";

    $res['heroid'.$hero] .= search_filter_component("profile-$hero-players");
    $res['heroid'.$hero] .=  "<table id=\"profile-$hero-players\" class=\"list sortable\"><thead><tr>".
      "<th>".locale_string("player")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("ratio")."</th>".
      "</tr>".
    "</thead><tbody>";

    foreach ($players as $pid => $data) {
      $res['heroid'.$hero] .=  "<tr>".
        "<td>".player_link($pid)."</td>".
        "<td>".$data['matches']."</td>".
        "<td>".number_format(100*$data['wins']/$data['matches'], 2)."%</td>".
        "<td>".number_format(100*$data['matches']/$el['matches_total'], 2)."%</td>".
      "</tr>";
    }

    $res['heroid'.$hero] .= "</tbody></table>";
  }
  
  $res['heroid'.$hero] .= "<script>window.onload = () => { ".implode(";\n", $scripts)." };</script>";

  return $res;
}

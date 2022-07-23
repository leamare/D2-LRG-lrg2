<?php

include_once($root."/modules/view/functions/itembuilds.php");

$modules['items']['buildspowerspikes'] = [];

function rg_view_generate_items_buildspowerspikes() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $roleicon_logo_provider, $leaguetag, $linkvars, $use_graphjs, $item_profile_icons_provider, $item_icons_provider;

  if($mod == $parent."buildspowerspikes") $unset_module = true;
  $parent_module = $parent."buildspowerspikes-";
  $res = [];

  $use_graphjs = true;

  if (!isset($report['hero_winrate_timings'])) {
    return null;
  }

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

    $res['overview'] .= "<table class=\"list sortable\"><thead>".
      "<tr><th width=\"1%\"></th><th width=\"15%\" class=\"sortInitialOrder-asc\">".
      locale_string("hero")."</th><th width=\"15%\" class=\"separator\">". 
      locale_string("positions_count")."</th>";
    foreach ($_presetroles as $i => $role) {
      $res['overview'] .= "<th class=\"".($i ? "" : "separator ")."centered sorter-image sortInitialOrder-asc\">".locale_string("position_".$role)."</th>";
    }
    $res['overview'] .= "</tr></thead><tbody>";

    foreach ($hnames as $hid => $hname) {
      $roles = $report['items']['progrole']['data'][$hid] ?? []; 
      $res['overview'] .= "<tr><td>".hero_icon($hid)."</td><td>".hero_link($hid)."</td><td class=\"separator\">";
      $kroles = array_keys($roles);
      usort($kroles, function($a, $b) {
        [$ac, $al] = explode('.', $a); [$bc, $bl] = explode('.', $b);
        if ($ac > $bc) return -1;
        if ($bc > $ac) return 1;
        if ($ac == 1) return $al <=> $bl;
        return $bl <=> $al;
      });

      $res['overview'] .= count($kroles)."</td>";

      foreach ($_presetroles as $i => $role) {
        $res['overview'] .= "<td class=\"".($i ? "" : "separator ")."centered\">".
          (isset($roles[$role]) ?
            "<a href=\"?league=$leaguetag&mod=items-buildspowerspikes-heroid$hid-position_$role".
            (empty($linkvars) ? "" : "&".$linkvars)."\">".
            (isset($roleicon_logo_provider) && isset($roleicons[$role]) ?
              "<img src=\"".str_replace("%ROLE%", $roleicons[$role], $roleicon_logo_provider)."\" alt=\"".$roleicons[$role]."\" />" :
              locale_string("position_$role")
            )."</a>" :
            ""
          ).
        "</td>";
      }

      $res['overview'] .= "</td></tr>";
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

  $report['items']['stats'][$hero] = array_filter($report['items']['stats'][$hero], function($a) {
    return !empty($a);
  });

  [ $build, $tree ] = generate_item_builds($pairs, $report['items']['stats'][$hero], $pbdata);  

  $reslocal = "";

  $reslocal .= "<div id=\"items-powerspikes-$tag\" style=\"width: 90%; margin: 0 auto;\">
    <canvas id=\"canvas\" style=\"width: 100%; height: 800px\"></canvas>
  </div>";
  $reslocal .= "<script>";

  if (is_wrapped($report['hero_winrate_timings'])) {
    $report['hero_winrate_timings'] = unwrap_data($report['hero_winrate_timings']);
  }
  $wrtimes = [
    "{x: ".($report['hero_winrate_timings'][$hero]['q1duration']/60).", y:".$report['hero_winrate_timings'][$hero]['early_wr']."}",
    "{x: ".($report['hero_winrate_timings'][$hero]['q3duration']/60).", y:".$report['hero_winrate_timings'][$hero]['late_wr']."}",
  ];

  $labels = [ 'default' ];

  $items = [  ];
  $items_wr = [  ];
  $time = 0;
  // $wr = $build['stats'][ $build['path'][0] ]['winrate']-$build['stats'][ $build['path'][0] ]['wo_wr_incr'];
  $wr = isset($build['critical'][ $build['path'][0] ]) ?
    $build['critical'][ $build['path'][0] ]['early_wr'] : 
    $report['hero_winrate_timings'][$hero]['early_wr'];
    // $build['stats'][ $build['path'][0] ]['winrate']-$build['stats'][ $build['path'][0] ]['wo_wr_incr'];
  $wr2 = $report['hero_winrate_timings'][$hero]['early_wr'];
  
  $items[] = "{x:0,y:".$wr.", item:0}";
  $items_wr[] = "{x:0,y:".$wr2.", item:0}";

  $prev = 0;
  foreach ($build['path'] as $i => $item) {
    $wr2 = $build['stats'][ $item ]['winrate'];
    if (isset($tree[$prev]) && isset($tree[$prev]['children'][$item])) {
      $wr = $tree[$prev]['children'][$item]['winrate'];
      
    } else {
      break;
      $wr = $build['stats'][ $item ]['winrate'];
      // $wr = $wr + $build['stats'][ $item ]['wo_wr_incr'];
    }
    
    $time+=$build['times'][$i];
    // $time = $build['stats'][$item]['med_time']/60;

    $items[] = "\n{x: ".( $time ).", y: ".( $wr ).", item:'".item_tag($item)."'}";
    $items_wr[] = "\n{x: ".( $time ).", y: ".( $wr2 ).", item:'".item_tag($item)."'}";

    $labels[] = item_tag($item);

    $prev = $item;
    if ($time >= 60) break;
  }

  if ($time < 60) {
    $wr = $wr + ($report['hero_winrate_timings'][$hero]['grad'] * (60-$time));
    $wr2 = $wr2 + ($report['hero_winrate_timings'][$hero]['grad'] * (60-$time));

    if ($wr < 0.3) $wr = 0.3;
    if ($wr2 < 0.3) $wr2 = 0.3;

    // $items[] = "\n{x: ".( 60 ).", y: ".( $wr )."}";
    $items_wr[] = "\n{x: ".( 60 ).", y: ".( ($wr2 + $wr)/2 )."}";
  }

  // {
  //   label: '".locale_string('powerspikes_incr')."',
  //   borderColor: 'rgba(164,64,255,1)',
  //   backgroundColor: 'rgba(164,64,255,0.15)',
  //   data: [".implode(',', $items)."],
  //   pointStyle: [createItemIcon('".implode("'), createItemIcon('", $labels)."')],
  //   spanGaps: true,
  //   borderWidth: 5,
  // },
  // {
  //   label: '".locale_string('wrtimings')."',
  //   backgroundColor: 'rgba(0,0,0,0.35)',
  //   borderColor: 'rgba(0,0,0,1)',
  //   data: [".implode(',', $wrtimes)."],
  //   spanGaps: true,
  //   borderWidth: 3,
  // }

  $reslocal .= "
  const data = {
    // define label tree
    labels: ['".implode("','", $labels)."'],
    datasets: [{
      label: '".locale_string('powerspikes_wr')."',
      backgroundColor: 'rgba(64,164,232,0.35)',
      borderColor: 'rgba(64,164,255,1)',
      data: [".implode(',', $items_wr)."],
      pointStyle: [createItemIcon('".implode("'), createItemIcon('", $labels)."')],
      spanGaps: true,
      borderWidth: 5,
    },
  ]
  };

  function createItemIcon(itemtag) {
    const icon = new Image;
    icon.src = `".str_replace("%HERO%", '${itemtag}', $item_profile_icons_provider ?? $item_icons_provider)."`;
    icon.width = 48;
    icon.height = 48;

    return icon;
  }

  window.onload = () => {
    const ctx = document.getElementById(\"canvas\").getContext(\"2d\");
    const config = {
      type: 'line',
      data: data,
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top',
          },
          title: {
            display: true,
            text: 'Chart.js Line Chart'
          },
          tooltip: {
            usePointStyle: true,
            callbacks: {
              footer: function(context) {
                console.log(context);

                return '123';
              }
            }
          }
        },
        scales: {
          xAxes: [{
            type: 'linear',
            ticks: {
              min: 0,
              max: 60,
            },
          }],
          yAxes: [{
            ticks: {
              min: 0.35,
              max: 1,
            },
          }]
        }
      },
    };

    window.myBar = new Chart(ctx, config);
  };
  </script>";

  $res[$tag][$roletag] .= $reslocal;

  return $res;
}


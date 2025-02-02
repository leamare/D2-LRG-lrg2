<?php 

require_once $root."/libs/SVGGraph/autoloader.php";

$modules['heroes']['daily_wr'] = "";

function __rg_view_generate_heroes_daily_wr_svg($color, $labels, $data, $minmax, &$lastscript) {
  $settings = [
    'auto_fit' => true,
    'back_colour' => 'transparent',
    'back_stroke_width' => 0,
    'stroke_width' => 0,
    'line_stroke_width' => 3,
    'stroke_colour' => $color,
    'sort' => false,
    'show_labels' => false,
    'show_label_amount' => true,
    'link_target' => '_top',
    'label_font' => 'Arial',
    'label_font_size' => '11',
    'tooltip_font_size' => 12,
    'tooltip_back_colour' => 'white',
    'tooltip_callback' => function($d, $k, $v) {
      return "$k - $v%";
    },
    "guideline" => [50, "", "y"],
    'marker_type' => ['circle'],
    'marker_size' => 5,
    'axis_max_v' => $minmax[1],
    'axis_min_v' => $minmax[0],
    'show_grid' => false,
    'show_axes' => false,
    'crosshairs' => false,
  ];
  
  $width = 350;
  $height = 70;
  
  $graph = new Goat1000\SVGGraph\SVGGraph($width, $height, $settings);
  /** @disregard */
  $graph->colours([$color]);

  $vals = [];
  foreach (array_values($data) as $i => $v) {
    $vals[ $labels[$i] ?? $i ] = $v;
  }
  
  /** @disregard */
  $graph->values($vals);

  $lastscript = $graph->fetchJavascript();
  
  return $graph->fetch('LineGraph', false);
}

function __rg_view_generate_heroes_daily_winrates_generate_scripts($color, $labels, $data, $id, $full = false) {
  return "new Chart(document.getElementById(\"$id\").getContext(\"2d\"), {
    type: 'line',
    data: {
      datasets: [{
        type: 'line',
        borderColor: $color,
        fill: false,
        order: 2,
        pointRadius: 3,
        lineTension: 0,
        data: [".implode(',', $data)."]
      }],
      labels: ['".implode("','", $labels)."'],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: {
        display: false,
      },
      scales: {
        xAxes: [{
          ticks: {
            display: false
          },
          gridLines: {
            display: false
          },
        }], 
        yAxes: [{
          ".($full ? "ticks: {min: 0, max: 100, }," : "gridLines: { display: false },")."
        }]
      }
    }
  })";
}

function rg_view_generate_heroes_daily_winrates() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $use_graphjs;
  $res = "";

  $context_main = $report['random'] ?? $report['main'] ?? [];

  $context_total_matches = $context_main['matches'] ?? $context_main["matches_total"] ?? 0;
  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;
  $context =& $report['pickban'];

  $minmax = [
    'w' => [40, 60],
    'pr' => [15, 30],
    'br' => [5, 10],
  ];

  if (!$mp) {
    uasort($report['pickban'], function($a, $b) {
      return $a['matches_picked'] <=> $b['matches_picked'];
    });
    $mp = isset($report['pickban'][ round(sizeof($context)*0.5) ]) ? $report['pickban'][ round(sizeof($context)*0.5) ]['matches_picked'] : 1;
  }
  if (!$mp) $mp = 1;

  if (!$mb) {
    if ($mp > 1) {
      $mb = 1;
    } else {
      uasort($report['pickban'], function($a, $b) {
        return $a['matches_banned'] <=> $b['matches_banned'];
      });
      $mb = isset($report['pickban'][ round(sizeof($context)*0.5) ]) ? $report['pickban'][ round(sizeof($report['pickban'])*0.5) ]['matches_banned'] : 1;
    }
  }
  if (!$mb) $mb = 1;

  if (is_wrapped($report['hero_daily_wr'])) {
    $days = $report['hero_daily_wr']['head'][0];
    sort($days);
    $report['hero_daily_wr'] = unwrap_data($report['hero_daily_wr']);
  } else {
    $days = $report['hero_daily_wr'][ array_keys($report['hero_daily_wr'])[0] ];
    sort($days);
  }

  $use_graphjs = true;

  // $days = [];
  $global_days = [];

  if (count($days) == count($report['days'])) {
    foreach($report['days'] as $d) {
      $global_days[ $d['timestamp'] ] = $d['matches_num'];
      // $days[] = $d['timestamp'];
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

  $labels = [];
  foreach ($days as $timestamp) {
    $labels[] = date(locale_string("date_format"), $timestamp);
  }

  $scripts = [];

  $res = filter_toggles_component('heroes-dailywr', [
    'pickrate' => [
      'value' => number_format(100*$mp/$context_total_matches, 2),
      'label' => 'data_filter_low_values_pickrate'
    ],
    'banrate' => [
      'value' => number_format(100*$mb/$context_total_matches, 2),
      'label' => 'data_filter_low_values_banrate'
    ]
  ], 'heroes-dailywr', 'wide');

  $res .= search_filter_component("heroes-dailywr", true);

  $res .= "<table id=\"heroes-dailywr\" class=\"list wide sortable\"><thead>".
    "<tr class=\"overhead\"><th colspan=\"2\" width=\"10%\"></th>".
    "<th class=\"separator\" colspan=\"4\" width=\"30%\">".locale_string("trends_winrate")."</th>".
    "<th class=\"separator\" colspan=\"4\" width=\"30%\">".locale_string("pickrate")."</th>".
    "<th class=\"separator\" colspan=\"4\" width=\"30%\">".locale_string("banrate")."</th>".
    "</tr>".
    "<tr><th></th>".
    "<th>".locale_string("hero")."</th>".
    "<th class=\"separator\">".locale_string("trends_first")."</th>".
    "<th width=\"20%\"></th>".
    "<th>".locale_string("trends_last")."</th>".
    "<th>".locale_string("trends_diff")."</th>".
    "<th class=\"separator\">".locale_string("trends_first")."</th>".
    "<th width=\"20%\"></th>".
    "<th>".locale_string("trends_last")."</th>".
    "<th>".locale_string("trends_diff")."</th>".
    "<th class=\"separator\">".locale_string("trends_first")."</th>".
    "<th width=\"20%\"></th>".
    "<th>".locale_string("trends_last")."</th>".
    "<th>".locale_string("trends_diff")."</th>".
  "</thead><tbody>";

  $heroes = [];

  foreach ($report['hero_daily_wr'] as $hid => $days_data) {
    $dwr = []; $dm = []; $dmb = []; $prev = null; $prev_b = null;
    $prev_dt = null;
    $first_wr = 0; $first_ms = 0; $first_msb = 0;
    foreach($days as $dt) {
      $dd = $days_data[$dt] ?? [ 'ms' => 0, 'wr' => 0 ];

      if ($prev_dt == null || $global_days[$dt]/$prev_dt > 0.05) {
        $prev_dt = $global_days[$dt];
  //       if (isset($prev_b) && $prev_b*0.15 > ($dd['bn'] ?? 0)) {
  //         $dmb[] = $dmb[ count($dmb)-1 ];
  //       } else {
  //         $prev_b = $dd['bn'] ?? 0;
          $dmb[] = round(100*($dd['bn'] ?? 0)/$global_days[$dt], 2);
  //       }
        if (!$first_msb && ($dd['bn'] ?? 0)) {
          $first_msb = $dmb[0];
        }

  //       if (isset($prev) && $prev*0.15 > $dd['ms']) {
  //         $dwr[] = $dwr[ count($dwr)-1 ];
  //         $dm[] = $dm[ count($dm)-1 ];
  //         
  //         continue;
  //       } else {
  //         $prev = $dd['ms'];
  //       }
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

      foreach ($dm as $i => $m) {
        if ($m) continue;
  
        $prev = $i ? $dwr[$i-1] : null;
        $next = $i<count($dwr)-1 ? $dwr[$i+1] : null;
        $dwr[$i] = (($prev ?? $next) + ($next ?? $prev))/2;
      }
    }

    $el_mp = max($dm); //array_sum($dm)/count($dm);
    $el_mb = max($dmb); //array_sum($dmb)/count($dmb);

    $scripts = '';

    // $dmb = array_map(function($el) { return $el*100; }, $dmb);
    // $first_msb *= 100;

    foreach ($dwr as $i => $v) {
      if($v > $minmax['w'][1]) $minmax['w'][1] = $v;
      if($v < $minmax['w'][0]) $minmax['w'][0] = $v;

      if($dmb[$i] > $minmax['br'][1]) $minmax['br'][1] = $dmb[$i];
      if($dmb[$i] < $minmax['br'][0]) $minmax['br'][0] = $dmb[$i];

      if($dm[$i] > $minmax['pr'][1]) $minmax['pr'][1] = $dm[$i];
      if($dm[$i] < $minmax['pr'][0]) $minmax['pr'][0] = $dm[$i];
    }

    $heroes[$hid] = [
      'dwr' => $dwr,
      'dmb' => $dmb,
      'dm'  => $dm,
      'first_wr' => $first_wr,
      'first_ms' => $first_ms,
      'first_msb' => $first_msb,
      'el_mp' => $el_mp,
      'el_mb' => $el_mb,
    ];

    // winrate
    // $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(92, 176, 255)'", $labels, $dwr, "hero-daily-wr-$hid");

    // bans
    // $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(255, 164, 96)'", $labels, $dmb, "hero-daily-bans-$hid");

    // pickrate
    // $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(128, 224, 96)'", $labels, $dm, "hero-daily-matches-$hid");
  }

  $minmax['w'][2] = 50;
  $minmax['br'][2] = array_sum($minmax['br'])/2;
  $minmax['pr'][2] = array_sum($minmax['pr'])/2;

  foreach ($heroes as $hid => $v) {
    $dwr = $v['dwr'];
    $dm = $v['dm'];
    $dmb = $v['dmb'];
    $el_mp = $v['el_mp'];
    $el_mb = $v['el_mb'];
    $first_wr = $v['first_wr'];
    $first_ms = $v['first_ms'];
    $first_msb = $v['first_msb'];

    $res .= "<tr data-value-pickrate=\"$el_mp\" data-value-banrate=\"$el_mb\">".
    "<td>".hero_portrait($hid)."</td><td>".hero_link($hid)."</td>".
    "<td class=\"separator\">".number_format($first_wr, 2)."%</td>".
    "<td><div style=\"position: relative; width: 100%; height: 70px\">".
    __rg_view_generate_heroes_daily_wr_svg("rgb(92, 176, 255)", $labels, $dwr, $minmax['w'], $scripts).
    "</div></td>".
    "<td>".number_format($dwr[ count($dwr)-1 ], 2)."%</td>".
    "<td>".number_format($dwr[ count($dwr)-1 ]-$first_wr, 2)."%</td>".

    "<td class=\"separator\">".number_format($first_ms, 2)."%</td>".
    "<td><div style=\"position: relative; width: 100%; height: 70px\">".
    __rg_view_generate_heroes_daily_wr_svg("rgb(128, 224, 96)", $labels, $dm, $minmax['pr'], $scripts).
    "</div></td>".
    "<td>".number_format($dm[ count($dm)-1 ], 2)."%</td>".
    "<td>".number_format($dm[ count($dm)-1 ]-$first_ms, 2)."%</td>".

    "<td class=\"separator\">".number_format($first_msb, 2)."%</td>".
    "<td><div style=\"position: relative; width: 100%; height: 70px\">".
    __rg_view_generate_heroes_daily_wr_svg("rgb(255, 164, 96)", $labels, $dmb, $minmax['br'], $scripts).
    "</div></td>".
    "<td>".number_format($dmb[ count($dmb)-1 ], 2)."%</td>".
    "<td>".number_format($dmb[ count($dmb)-1 ]-$first_msb, 2)."%</td>".
  "</tr>";
  }

  $res .= "</tbody></table>";

  // $res .= "<div id=\"hero-daily-wr\" style=\"width: 90%; margin: 0 auto;\">
  //   <canvas id=\"canvas\" style=\"width: 100%; height: ".(sizeof($report['hero_daily_wr'])*4)."vh\"></canvas>
  // </div>";

  $res .= $scripts;

  return $res;
}



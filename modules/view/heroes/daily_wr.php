<?php 

$modules['heroes']['daily_wr'] = "";

function __rg_view_generate_heroes_daily_winrates_generate_scripts($color, $labels, $data, $id) {
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
          gridLines: {
            display: false
          },
        }]
      }
    }
  })";
}

function rg_view_generate_heroes_daily_winrates() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $use_graphjs;
  $res = "";

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

  $res .= "<table class=\"list wide sortable\"><thead>".
    "<tr class=\"overhead\"><th colspan=\"2\" width=\"10%\"></th>".
    "<th class=\"separator\" colspan=\"4\" width=\"30%\">".locale_string("pickrate")."</th>".
    "<th class=\"separator\" colspan=\"4\" width=\"30%\">".locale_string("banrate")."</th>".
    "<th class=\"separator\" colspan=\"4\" width=\"30%\">".locale_string("trends_winrate")."</th></tr>".
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
  foreach ($report['hero_daily_wr'] as $hid => $days_data) {
    $dwr = []; $dm = []; $dmb = []; $prev = null; $prev_b = null;
    $first_wr = 0; $first_ms = 0; $first_msb = 0;
    foreach($days as $dt) {
      $dd = $days_data[$dt] ?? [ 'ms' => 0, 'wr' => 0 ];

      if (isset($prev_b) && $prev_b*0.15 > ($dd['bn'] ?? 0)) {
        $dmb[] = $dmb[ count($dmb)-1 ];
      } else {
        $prev_b = $dd['bn'] ?? 0;
        $dmb[] = round(100*($dd['bn'] ?? 0)/$global_days[$dt], 2);
      }
      if (!$first_msb && ($dd['bn'] ?? 0)) {
        $first_msb = round(100*$dd['bn']/$global_days[$dt], 2);
      }

      if (isset($prev) && $prev*0.15 > $dd['ms']) {
        $dwr[] = $dwr[ count($dwr)-1 ];
        $dm[] = $dm[ count($dm)-1 ];
        
        continue;
      } else {
        $prev = $dd['ms'];
      }
      if (!$first_ms && $dd['ms']) {
        $first_ms = round(100*$dd['ms']/$global_days[$dt], 2);
        $first_wr = $dd['wr']*100;
      }

      $dwr[] = $dd['wr']*100;
      $dm[] = round(100*$dd['ms']/$global_days[$dt], 2);
    }

    $res .= "<tr><td>".hero_portrait($hid)."</td><td>".hero_name($hid)."</td>".
      "<td class=\"separator\">".number_format($first_ms, 2)."%</td>".
      "<td><div style=\"position: relative; width: 100%; height: 70px\"><canvas id=\"hero-daily-matches-$hid\"></canvas></div></td>".
      "<td>".number_format($dm[ count($dm)-1 ], 2)."%</td>".
      "<td>".number_format($dm[ count($dm)-1 ]-$first_ms, 2)."%</td>".

      "<td class=\"separator\">".number_format($first_msb, 2)."%</td>".
      "<td><div style=\"position: relative; width: 100%; height: 70px\"><canvas id=\"hero-daily-bans-$hid\"></canvas></div></td>".
      "<td>".number_format($dmb[ count($dmb)-1 ], 2)."%</td>".
      "<td>".number_format($dmb[ count($dmb)-1 ]-$first_msb, 2)."%</td>".

      "<td class=\"separator\">".number_format($first_wr, 2)."%</td>".
      "<td><div style=\"position: relative; width: 100%; height: 70px\"><canvas id=\"hero-daily-wr-$hid\"></canvas></div></td>".
      "<td>".number_format($dwr[ count($dwr)-1 ], 2)."%</td>".
      "<td>".number_format($dwr[ count($dwr)-1 ]-$first_wr, 2)."%</td>".
    "</tr>";

    // winrate
    $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(92, 176, 255)'", $labels, $dwr, "hero-daily-wr-$hid");

    // bans
    $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(255, 164, 96)'", $labels, $dmb, "hero-daily-bans-$hid");

    // pickrate
    $scripts[] = __rg_view_generate_heroes_daily_winrates_generate_scripts("'rgb(128, 224, 96)'", $labels, $dm, "hero-daily-matches-$hid");
  }
  $res .= "</tbody></table>";

  // $res .= "<div id=\"hero-daily-wr\" style=\"width: 90%; margin: 0 auto;\">
  //   <canvas id=\"canvas\" style=\"width: 100%; height: ".(sizeof($report['hero_daily_wr'])*4)."vh\"></canvas>
  // </div>";

  $res .= "<script>window.onload = () => { ".implode(";\n", $scripts)." };</script>";

  return $res;
}



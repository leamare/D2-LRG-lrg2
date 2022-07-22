<?php 

$modules['heroes']['wrtimings'] = '';

function rg_view_generate_heroes_wrtimings() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $use_graphjs, $use_graphjs_boxplots;
  $res = '';

  $use_graphjs = true;
  $use_graphjs_boxplots = true;

  if (is_wrapped($report['hero_winrate_timings'])) {
    $report['hero_winrate_timings'] = unwrap_data($report['hero_winrate_timings']);
  }

  $rows = "";

  $scripts = [];
  $lineFactor = 0.7;
  $d = 0.25*0.7;
  $matches_med = [];

  foreach ($report['hero_winrate_timings'] as $hid => $data) {
    $matches_med[] = $data['matches'];

    $rows .= "<tr data-value-match=\"".$data['matches']."\" ><td>".hero_portrait($hid)."</td><td>".hero_link($hid)."</td>".
      "<td>".($data['matches'])."</td>".
      "<td class=\"separator\">".convert_time_seconds($data['q1duration'])."</td>".
      "<td>".convert_time_seconds($data['q2duration'])."</td>".
      "<td>".convert_time_seconds($data['q3duration'])."</td>".
      "<td>".convert_time_seconds($data['avg_duration'])."</td>".
      "<td>".convert_time_seconds($data['std_dev'])."</td>".
      "<td class=\"separator\">".number_format($data['early_wr']*100, 2)."%</td>".
      "<td>".number_format($data['winrate_avg']*100, 2)."%</td>".
      "<td>".number_format($data['late_wr']*100, 2)."%</td>".
      "<td>".number_format($data['grad']*100, 2)."%</td>".
      "<td class=\"separator\"><div style=\"position: relative; width: 95%; height: 50px\"><canvas id=\"hero-timings-$hid\"></canvas></div></td>".
      // "<td></td>".
    "</tr>";

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

  sort($matches_med);

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_wrtimings")."</div>".
    "</div>".
  "</details>";

  $res .= filter_toggles_component("heroes-wrtimings", [
    'match' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_matches'
    ]
  ], "heroes-wrtimings", 'wide');

  $res .= search_filter_component("heroes-wrtimings", true);

  $res .= "<table id=\"heroes-wrtimings\" class=\"list wide sortable\"><thead><tr class=\"overhead\">".
      "<th colspan=\"2\"></th>".
      "<th></th>".
      "<th colspan=\"5\" class=\"separator\">".locale_string("duration")."</th>".
      "<th colspan=\"4\" class=\"separator\">".locale_string("trends_winrate")."</th>".
      "<th width=\"20%\" class=\"separator\"></th>".
      // "<th width=\"1%\"></th>".
    "</tr><tr>".
      "<th></th><th>".locale_string("hero")."</th>".
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
      "<th class=\"separator\">".locale_string("chart")."</th>".
      // "<th></th>".
  "</tr></thead><tbody>$rows</tbody></table>";

  $res .= "<script>window.onload = () => { ".implode(";\n", $scripts)." };</script>";

  return $res;
}



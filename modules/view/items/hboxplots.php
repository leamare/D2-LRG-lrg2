<?php

$modules['items']['heroboxplots'] = [];

function rg_view_generate_items_heroboxplots() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars, $use_graphjs, $use_graphjs_boxplots;
  $use_graphjs = true;
  $use_graphjs_boxplots = true;

  if($mod == $parent."heroboxplots") $unset_module = true;
  $parent_module = $parent."heroboxplots-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [];

  $item_ids = array_keys($report['items']['stats']['total']);
  $item_names = [];

  foreach ($item_ids as $item) {
    $item_names[ $item ] = [
      'name' => $meta['items_full'][$item]['localized_name'],
      'tag' => $meta['items_full'][$item]['name']
    ];
    $strings['en']["itemid".$item] = item_name($item);
  }

  uasort($item_names, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  foreach($item_names as $iid => $name) {
    $res["itemid".$iid] = "";

    if(check_module($parent_module."itemid".$iid)) {
      $item = $iid;
      $tag = 'itemid'.$item;
    }
  }

  $data = $report['items']['stats']['total'][$item];

  $res[$tag] .= "<div class=\"content-text\">".locale_string("items_boxplots_timings_desc")."</div>";

  $res['itemid'.$item] .= "<table id=\"items-itemid$item-reference\" class=\"list wide\">";
  $res['itemid'.$item] .= "<thead><tr class=\"overhead\">".
      "<th width=\"12%\" colspan=\"2\"></th>".
      "<th width=\"18%\" colspan=\"3\"></th>".
      "<th class=\"separator\" width=\"18%\" colspan=\"4\">".locale_string("items_winrate_shifts")."</th>".
      "<th class=\"separator\" colspan=\"7\">".locale_string("items_timings")."</th>".
    "</tr><tr>".
    "<th></th>".
    "<th>".locale_string("item")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\">".locale_string("purchases")."</th>".
    "<th data-sorter=\"digit\">".locale_string("purchase_rate")."</th>".
    "<th class=\"separator\" data-sorter=\"digit\">".locale_string("winrate")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_wo_wr_shift")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_early_wr_shift")."</th>".
    "<th data-sorter=\"digit\">".locale_string("items_late_wr_shift")."</th>".
    "<th class=\"separator\" data-sorter=\"time\">".locale_string("item_time_mean")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_min")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_q1")."</th>".
    "<th data-sorter=\"time\">".locale_string("median")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_q3")."</th>".
    "<th data-sorter=\"time\">".locale_string("item_time_max")."</th>".
    "<th data-sorter=\"time\">".locale_string("std_dev")."</th>".
  "</tr></thead><tbody>";
  $res['itemid'.$item] .= "<tr>".
    "<td>".item_icon($item)."</td>".
    "<td>".item_name($item)."</td>".
    "<td class=\"separator\">".$data['purchases']."</td>".
    "<td>".number_format($data['prate']*100, 2)."%</td>".
    "<td class=\"separator\">".number_format($data['winrate']*100, 2)."%</td>".
    "<td>".($data['wo_wr'] < $data['winrate'] ? '+' : '').number_format(($data['winrate']-$data['wo_wr'])*100, 2)."%</td>".
    "<td>".($data['early_wr'] > $data['winrate'] ? '+' : '').number_format(($data['early_wr']-$data['winrate'])*100, 2)."%</td>".
    "<td>".($data['late_wr'] > $data['winrate'] ? '+' : '').number_format(($data['late_wr']-$data['winrate'])*100, 2)."%</td>".
    "<td class=\"separator\">".convert_time_seconds($data['avg_time'])."</td>".
    "<td>".convert_time_seconds($data['min_time'])."</td>".
    "<td>".convert_time_seconds($data['q1'])."</td>".
    "<td>".convert_time_seconds($data['median'])."</td>".
    "<td>".convert_time_seconds($data['q3'])."</td>".
    "<td>".convert_time_seconds($data['max_time'])."</td>".
    "<td>".convert_time_seconds($data['std_dev'])."</td>".
  "</tr>";
  $res['itemid'.$item] .= "</tbody></table>";

  unset($report['items']['stats']['total']);
  $heroes = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    if (!empty($items[$item]) && $items[$item]['q1'] != $items[$item]['q3'])
      $heroes[$hero] = $items[$item];
  }

  uasort($heroes, function($a, $b) {
    return $b['purchases'] <=> $a['purchases'];
  });

  if (empty($heroes)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $res[$tag] .= "<div id=\"items-itemid$item\" style=\"width: 90%; margin: 0 auto;\">
    <canvas id=\"canvas\" style=\"width: 100%; height: ".(sizeof($heroes)*(64+10)-10)."px\"></canvas>
  </div>";
  $res[$tag] .= "<script>";

  $entries = [];
  $labels = [];
  $lines = [];
  $sz = 0;
  $lineFactor = 0.7;
  $d = 0.25*0.7;
  foreach ($heroes as $iid => $line) {
    $entries[] = "{ min: ".$line['min_time'].", q1: ".$line['q1'].", median: ".$line['median'].", q3: ".$line['q3'].", max: ".$line['max_time'].", mean: ".$line['avg_time']." }";
    $labels[] = addslashes(hero_name($iid));
    $line['avg_time'] = $line['avg_time'] > $line['q3'] ? ($line['q1']+$line['q3'])/2 : $line['avg_time'];
    $lines[] = "{
      label: '".hero_tag($iid)."',
      type: 'line',
      borderColor: 'rgba(174,174,174,0.7)',
      fill: false,
      order: 2,
      pointRadius: 1,
      data: [
      {x: ".$line['q1']."+1, y: ".($sz+$lineFactor*(0.5-$line['early_wr']))."}, {x: ".($line['avg_time']).", y: ".($sz+$lineFactor*(0.5-$line['winrate']))."}, {x: ".$line['q3']."-1, y: ".($sz+$lineFactor*(0.5-$line['late_wr']))."}
    ] }";
    $sz++;
  }

  $res[$tag] .= "
  function vh(v) {
    var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
    return (v * h) / 100;
  }
  const vhc = vh(1);
  const boxplotData = {
    // define label tree
    labels: ['".implode("','", $labels)."'],
    datasets: [{
      label: 'Timings',
      backgroundColor: 'rgba(96,96,255,0.55)',
      borderColor: 'rgb(96,128,255)',
      borderWidth: 1,
      outlierColor: '#999999',
      padding: 10,
      itemRadius: 0,
      order: 1,
      data: [ ".implode(", \n", $entries)." ]
    },
    ".implode(", \n", $lines)."
    ]
  };
  window.onload = () => {
    const ctx = document.getElementById(\"canvas\").getContext(\"2d\");
    window.myBar = new Chart(ctx, {
      type: 'horizontalBoxplot',
      data: boxplotData,
      options: {
        responsive: true,
        legend: {
          display: false,
        },
        scales: {
          yAxes: [{
              ticks: {
                  fontColor: 'rgb(174,174,174)',
                  fontSize: 14,
                  stepSize: 1,
                  beginAtZero: true
              }
          }],
          xAxes: [{
              ticks: {
                  fontColor: 'grey',
                  fontSize: 14,
                  stepSize: 240,
                  beginAtZero: true
              }
          }]
        },
        title: {
          display: false,
        },
        tooltips: {
          position: 'nearest',
          filter: function (tooltipItem) {
            return tooltipItem.datasetIndex === 0;
          }
        }
      }
    });
  };
  </script>";

  return $res;
}


<?php

$modules['items']['boxplots'] = [];

function rg_view_generate_items_boxplots() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $linkvars, $use_graphjs, $use_graphjs_boxplots;
  $use_graphjs = true;
  $use_graphjs_boxplots = true;

  if($mod == $parent."boxplots") $unset_module = true;
  $parent_module = $parent."boxplots-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [];

  $res['total'] = '';

  if(check_module($parent_module."total")) {
    $hero = "total";
    $tag = "total";
  }

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      $hero = $hid;
      $tag = "heroid$hid";
    }
  }

  $meta['item_categories'];
  if (isset($_GET['item_cat']) && isset($meta['item_categories'][ $_GET['item_cat'] ])) {
    $cat = $_GET['item_cat'];
  } else {
    $cat = null;
  }

  foreach ($report['items']['stats'][$hero] as $iid => $v) {
    if (empty($v)) unset($report['items']['stats'][$hero][$iid]);
  }

  $ranks = [];

  uasort($report['items']['stats'][$hero], function($a, $b) {
    return $b['purchases'] <=> $a['purchases'];
  });

  $res[$tag] .= "<div class=\"selector-modules-level-4\">";

  if ($hero !== 'total') {
    $data = $report['pickban'][$hero];

    $res[$tag] .= "<table id=\"items-$tag-reference\" class=\"list\">";
    $res[$tag] .= "<thead><tr>".
      "<th></th>".
      "<th>".locale_string("hero")."</th>".
      "<th>".locale_string("matches_picked")."</th>".
      "<th>".locale_string("winrate")."</th>".
    "</tr></thead><tbody>";
    $res[$tag] .= "<tr>".
      "<td>".hero_portrait($hero)."</td>".
      "<td>".hero_name($hero)."</td>".
      "<td>".$data['matches_picked']."</td>".
      "<td>".number_format($data['winrate_picked']*100, 2)."%</td>".
    "</tr></tbody></table><br />";
  }

  $item_cats = [
    'major', 'medium', 'early', 
    // 'neutral_tier_1', 'neutral_tier_2', 'neutral_tier_3', 'neutral_tier_4', 'neutral_tier_5',
  ];

  $items = array_filter($report['items']['stats'][$hero], function($v, $k) use ($cat, &$meta) {
    if ($cat !== null) {
      return in_array($k, $meta['item_categories'][$cat]) && !empty($v) && $v['q1'] != $v['q3'];
    }
    return !empty($v) && $v['q1'] != $v['q3'];
  }, ARRAY_FILTER_USE_BOTH);

  $res[$tag] .= 
    "<span class=\"selector\">".locale_string("items_category_selector").":</span> ".
    "<select onchange=\"select_modules_link(this);\" class=\"select-selectors select-selectors-level-4\">".
    "<option ".($cat === null ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_category_all")."</option>";
  foreach ($item_cats as $ic) {
    $res[$tag] .= "<option ".($cat == $ic ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mod.(empty($linkvars) ? "" : "&".$linkvars)."&item_cat=$ic\">".locale_string("items_category_$ic")."</option>";
  }
  $res[$tag] .= "</select>";
  
  $res[$tag] .= "</div>";

  $res[$tag] .= "<div class=\"content-text\">".locale_string("items_boxplots_timings_desc")."</div>";

  if (empty($items)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $res[$tag] .= "<div id=\"items-boxplots-$tag\" style=\"width: 90%; margin: 0 auto;\">
    <canvas id=\"canvas\" style=\"width: 100%; height: ".(sizeof($items)*4)."vh\"></canvas>
  </div>";
  $res[$tag] .= "<script>";

  $entries = [];
  $labels = [];
  $lines = [];
  $sz = 0;
  $lineFactor = 0.7;
  $d = 0.25*0.7;
  foreach ($items as $iid => $line) {
    $entries[] = "{ min: ".$line['min_time'].", q1: ".$line['q1'].", median: ".$line['median'].", q3: ".$line['q3'].", max: ".$line['max_time'].", mean: ".$line['avg_time']." }";
    $labels[] = addslashes(item_name($iid));
    $line['avg_time'] = $line['avg_time'] > $line['q3'] ? ($line['q1']+$line['q3'])/2 : $line['avg_time'];
    $lines[] = "{
      label: '".item_tag($iid)."',
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
      backgroundColor: 'rgba(20,144,255,0.55)',
      borderColor: 'rgb(20,144,255)',
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


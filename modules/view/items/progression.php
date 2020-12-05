<?php

$modules['items']['progression'] = [];

function rg_view_generate_items_progression() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $visjs_settings, $use_visjs;
  $use_visjs = true;

  if($mod == $parent."progression") $unset_module = true;
  $parent_module = $parent."progression-";
  $res = [];

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }
  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [];

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

  $pairs = [];
  $items = [];
  $max_wr = 0;
  $max_games = 0;
  if (!isset($report['items']['progr'][$hero])) $report['items']['progr'][$hero] = [];
  foreach ($report['items']['progr'][$hero] as $v) {
    if (empty($v)) continue;
    $pairs[] = $v;
    
    if (!in_array($v['item1'], $items)) $items[] = $v['item1'];
    if (!in_array($v['item2'], $items)) $items[] = $v['item2'];

    if ($v['total'] > $max_games) $max_games = $v['total'];
    $diff = abs(($v['winrate'])-0.5);
    if ($diff > $max_wr) {
      $max_wr = $diff;
    }
  }
  $max_wr *= 2;

  usort($pairs, function($a, $b) {
    return $b['total'] <=> $a['total'];
  });

  if (empty($pairs)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $res[$tag] = "<div id=\"items-progr-$tag\" class=\"graph\"></div><script type=\"text/javascript\">";

  // foreach ($pairs as $line) {
  //   $res[$tag] .= "<tr>".
  //     "<td>".item_icon($line['item1'])."</td>".
  //     "<td>".item_name($line['item1'])."</td>".
  //     "<td>".item_icon($line['item2'])."</td>".
  //     "<td>".item_name($line['item2'])."</td>".
  //     "<td>".$line['total']."</td>".
  //     "<td>".number_format(100*$line['winrate'], 2)."%</td>".
  //     "<td>".$line['min_diff']."</td>".
  //   "</tr>";
  // }

  $nodes = '';
  $edges = '';

  foreach ($items as $item) {
    $minute_gr = round($report['items']['stats'][$hero][$item]['median']/240);
    $minute_gr = in_array($item, $meta['item_categories']['early']) && $minute_gr > 3 ? 3 : $minute_gr;
    $nodes .= "{ id: $item, value: ".$report['items']['stats'][$hero][$item]['purchases'].", label: '".addslashes(item_name($item))."'".
      ", title: '".addslashes(item_name($item)).", ".locale_string('avg_time').": ".convert_time_seconds($report['items']['stats'][$hero][$item]['median']).", ".
      locale_string('purchases').": ".$report['items']['stats'][$hero][$item]['purchases'].", ".
      locale_string('winrate').": ".($report['items']['stats'][$hero][$item]['winrate']*100)."%".
      "'".
      ", shape:'circularImage', ".
      "image: '".item_icon_link($item)."', level: ".$minute_gr.
    ", color:{
        background:'rgba(".number_format(255-255*$report['items']['stats'][$hero][$item]['winrate'], 0).",124,".
        number_format(255*$report['items']['stats'][$hero][$item]['winrate'], 0).")', ".
        "border:'rgba(".number_format(255-255*$report['items']['stats'][$hero][$item]['winrate'], 0).",124,".
        number_format(255*$report['items']['stats'][$hero][$item]['winrate'], 0).")',
        highlight: { background:'rgba(".number_format(255-255*$report['items']['stats'][$hero][$item]['winrate'], 0).",124,".
          number_format(255*$report['items']['stats'][$hero][$item]['winrate'], 0).")', ".
          "border:'rgba(".number_format(255-255*$report['items']['stats'][$hero][$item]['winrate'], 0).",124,".
          number_format(255*$report['items']['stats'][$hero][$item]['winrate'], 0).")' }
      }
    }, ";
  }

  foreach ($pairs as $v) {
    $color = "'rgba(".
      round(126-255*($max_wr ? ($v['winrate']-0.5)/$max_wr : 0)).",124,".
      round(126+255*($max_wr ? ($v['winrate']-0.5)/$max_wr : 0)).",".(($v['total']/$max_games)*0.85+0.15).")'";
    $color_hi = "'rgba(".
      round(136-205*($max_wr ? ($v['winrate']-0.5)/$max_wr : 0)).",100,".
      round(136+205*($max_wr ? ($v['winrate']-0.5)/$max_wr : 0)).",1)'";
    $edges .= "{from: ".$v['item1'].", to: ".$v['item2'].", value:".$v['total'].", title:\"".
      $v['total']." ".locale_string("matches").", ".number_format($v['winrate']*100, 2)."% ".locale_string("winrate").
      ", ".locale_string("items_minute_diff").": ".$v['min_diff'].
      "\", color:{color:$color, highlight: $color_hi}},";
  }

  $res[$tag] .= "var nodes = [ ".$nodes." ];";
  $res[$tag] .= "var edges = [ ".$edges." ];";
  $res[$tag] .= "var container = document.getElementById('items-progr-$tag');\n".
    "var data = { nodes: nodes, edges: edges };\n".
    "var options={
      edges: {
        arrows: { to: true }
      },
      nodes: {
        borderWidth:3,
        shape: 'dot',
        font: {color:'#ccc', background: 'rgba(0,0,0,0.5)',size:12},
        shadow: {
          enabled: true
        },
        scaling:{
          label: {
            min:8, max:20
          }
        }
      },
      physics: {
        stabilization: {
          fit: true,
          iterations: 8000
        },
      },
      layout: {
        hierarchical: {
          direction: 'UD',
          sortMethod: 'directed',
          shakeTowards: 'leaves',
          nodeSpacing: 15,
          avoidOverlap: 1,
          levelSeparation: 80,
        },
      }
    };\n".
    "var network = new vis.Network(container, data, options);\n".
    "</script>";

  return $res;
}


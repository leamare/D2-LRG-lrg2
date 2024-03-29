<?php

$modules['items']['progression'] = [];

function rg_view_generate_items_progression() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $visjs_settings, $use_visjs, $leaguetag;
  $use_visjs = true;

  if (stripos($mod, "progrole") !== false) {
    $mod = str_ireplace("progrole", "progression", $mod);
  }

  if($mod == $parent."progression") $unset_module = true;
  $parent_module = $parent."progression-";
  $res = [];

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }
  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $res = [
    'overview' => [ 'all' => '' ]
  ];

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  if(check_module($parent_module."overview")) {
    $hero = 'total';
    $tag = "overview";
  }
  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    if (empty($report['items']['progr'][$hid]) && empty($report['items']['progrole']['data'][$hid])) {
      $res["heroid".$hid] = "";
    } else {
      $res["heroid".$hid] = [];
      $res["heroid".$hid]["all"] = "";
    }

    if(check_module($parent_module."heroid".$hid)) {
      if (empty($report['items']['progr'][$hid]) && empty($report['items']['progrole']['data'][$hid])) {
        $res["heroid".$hid] = [ 'all' => "" ];
      }
      
      $hero = $hid;
      $tag = "heroid$hid";

      if (empty($report['items']['progrole']['data'][$hid])) continue;

      if($mod == $parent_module."heroid".$hid) $unset_module = true;
      $parent_module = $parent_module."heroid".$hid."-";

      $crole = null;

      if (check_module($parent_module."all")) {
        $crole = null;
      }

      if (isset($report['items']['progrole']) && !empty($report['items']['progrole']['data'][$hid])) {
        generate_positions_strings();
        foreach($report['items']['progrole']['data'][$hid] as $role => $pairs) {
          $res["heroid".$hid]["position_".$role] = "";

          if(check_module($parent_module."position_".$role)) {
            $crole = $role;
          }
        }
      }
    }
  }

  $data = $report['pickban'][$hero] ?? null;

  $pairs = [];
  $items = [];
  $max_wr = 0;
  $max_games = 0;
  if (empty($crole)) {
    if (!isset($report['items']['progr'][$hero])) $context = [];
    else $context =& $report['items']['progr'][$hero];
  } else {
    if (!isset($report['items']['progrole']['data'][$hero][$crole])) $context = [];
    else $context =& $report['items']['progrole']['data'][$hero][$crole];
  }

  if (!empty($_GET['cat'])) {
    $i = floor(count($report['items']['progr'][$hero]) * (float)($_GET['cat']));
    $med = $report['items']['progr'][$hero][ $i > 0 ? $i-1 : 0 ]['total'];
  } else {
    $med = 0;
  }

  if ($crole ?? false) {
    $items_matches_1 = [];
    $items_matches = [];
  }

  foreach ($context as $v) {
    if (empty($v)) continue;

    if ($crole ?? false) {
      $v = array_combine($report['items']['progrole']['keys'], $v);
    }

    if ($v['total'] < $med) continue;

    $pairs[] = $v;
    
    if (!in_array($v['item1'], $items)) $items[] = $v['item1'];
    if (!in_array($v['item2'], $items)) $items[] = $v['item2'];

    if ($crole ?? false) {
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

    if ($v['total'] > $max_games) $max_games = $v['total'];
    $diff = abs($v['winrate']-($data ? $data['winrate_picked'] : 0.5));
    if ($diff > $max_wr) {
      $max_wr = $diff;
    }
  }
  $max_wr *= 2;

  if ($crole ?? false) {
    foreach ($items_matches as $iid => $v) {
      $items_matches[$iid] = max($items_matches[$iid] ?? 0, $items_matches_1[$iid] ?? 0);
    }
    unset($items_matches_1);
  }

  usort($pairs, function($a, $b) {
    return $b['total'] <=> $a['total'];
  });

  if (empty($pairs)) {
    $res[$tag][($crole ?? false) ? "position_".$crole : "all"] = "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $reslocal = "";

  $reslocal .= "<div class=\"selector-modules-level-5\">".
    "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=items-progression-$tag-".(($crole ?? false) ? "position_".$crole : "all").(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("items_progression_as_tree").
      "</a>".
    "</span>".
    " | ".
    "<span class=\"selector\">".
      "<a href=\"?league=".$leaguetag."&mod=items-proglist-$tag-".(($crole ?? false) ? "position_".$crole : "all").(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("items_progression_as_list").
      "</a>".
    "</span>".
  "</div>";

  $reslocal .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("items_progression_desc")."</div>".
    "</div>".
  "</details>";

  $reslocal .= "<div id=\"items-progr-$tag\" class=\"graph\"></div><script type=\"text/javascript\">";

  $nodes = '';
  $edges = '';

  foreach ($items as $item) {
    if (empty($report['items']['stats'][$hero][$item])) continue;
    $minute_gr = round($report['items']['stats'][$hero][$item]['median']/180);
    $minute_gr = in_array($item, $meta['item_categories']['early']) && $minute_gr > 3 ? 3 : $minute_gr;
    if ($minute_gr > 14) $minute_gr = 14;
    $diff_raw = $report['items']['stats'][$hero][$item]['winrate'] - $report['items']['stats'][$hero][$item]['wo_wr'];
    $diff = 0.5 + $diff_raw;
    $nodes .= "{ id: $item, value: ".
      (($crole ?? false) ? $items_matches[$item] : $report['items']['stats'][$hero][$item]['purchases']).
      ", label: '".addslashes(item_name($item))."'".
      ", title: '".addslashes(item_name($item)).", ".locale_string('avg_timing').": ".convert_time_seconds($report['items']['stats'][$hero][$item]['median']).", ".
      locale_string('purchases').": ".$report['items']['stats'][$hero][$item]['purchases'].", ".
      (($crole ?? false) ? locale_string('position').": ".$items_matches[$item].", " : "").
      locale_string('winrate').": ".($report['items']['stats'][$hero][$item]['winrate']*100)."%, ".
      locale_string("diff").": ".number_format($diff_raw*100, 2)."%".
      "'".
      ", shape:'circularImage', ".
      "image: '".item_icon_link($item)."', level: ".$minute_gr.
    ", color:{
        background:'rgba(".number_format(255-255*$diff, 0).",124,".
        number_format(255*$diff, 0).")', ".
        "border:'rgba(".number_format(255-255*$diff, 0).",124,".
        number_format(255*$diff, 0).")',
        highlight: { background:'rgba(".number_format(255-255*$diff, 0).",124,".
          number_format(255*$diff, 0).")', ".
          "border:'rgba(".number_format(255-255*$diff, 0).",124,".
          number_format(255*$diff, 0).")' }
      }
    }, ";
  }

  foreach ($pairs as $v) {
    if ($v['item1'] == $v['item2']) continue; // TODO:
    $diff = $v['winrate'] - ($data ? $data['winrate_picked'] : 0.5);
    $color = "'rgba(".
      round(126-255*($max_wr ? $diff/$max_wr : 0)).",124,".
      round(126+255*($max_wr ? $diff/$max_wr : 0)).",".(($v['total']/$max_games)*0.25+0.075).")'";
    $color_hi = "'rgba(".
      round(136-205*($max_wr ? ($v['winrate']-0.5)/$max_wr : 0)).",100,".
      round(136+205*($max_wr ? ($v['winrate']-0.5)/$max_wr : 0)).",".(0.65*$v['total']/$max_games+0.35).")'";
    $edges .= "{from: ".$v['item1'].", to: ".$v['item2'].", value:".$v['total'].", width: ".($v['total']/$max_games).", title:\"".
      addslashes(item_name($v['item1']))." -> ".addslashes(item_name($v['item2']))." - ".
      $v['total']." ".locale_string("matches").", ".number_format($v['winrate']*100, 2)."% ".locale_string("winrate").
      ", ".locale_string("items_minute_diff").": ".$v['min_diff'].
      ", ".locale_string("diff").": ".number_format($diff*100, 2).
      (isset($v['avgord1']) ? "% [".$v['avgord1'].' -> '.$v['avgord2']."]" : "").
      "\", color:{color:$color, highlight: $color_hi}},";
  }

  $reslocal .= "var nodes = [ ".$nodes." ];";
  $reslocal .= "var edges = [ ".$edges." ];";
  $reslocal .= "var container = document.getElementById('items-progr-$tag');\n".
    "var data = { nodes: nodes, edges: edges };\n".
    "var options={
      edges: {
        arrows: { to: true },
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
          nodeSpacing: 25,
          avoidOverlap: 1,
          levelSeparation: 100,
        },
      }
    };\n".
    "var network = new vis.Network(container, data, options);\n".
    "</script>";


  $reslocal .= "<div class=\"content-text\">".locale_string("items_progression_list_desc")."</div>";

  $res[$tag][($crole ?? false) ? "position_".$crole : "all"] = $reslocal;

  return $res;
}


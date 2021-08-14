<?php

$modules['items']['progrole'] = [];

function rg_view_generate_items_progrole() {
  global $leaguetag, $report, $parent, $root, $unset_module, $mod, $meta, $strings, $visjs_settings, $use_visjs, $roleicon_logo_provider;
  $use_visjs = true;

  if($mod == $parent."progrole") $unset_module = true;
  $parent_module = $parent."progrole-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

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

    $res['overview'] .= "<table class=\"list sortable\"><thead><tr><th width=\"1%\"></th><th width=\"15%\">".
      locale_string("hero")."</th><th width=\"15%\">". 
      locale_string("positions_count")."</th><th>".
      locale_string("positions")."</th></tr></thead><tbody>";

    foreach ($report['items']['progrole']['data'] as $hid => $roles) {
      $res['overview'] .= "<tr><td>".hero_icon($hid)."</td><td>".hero_name($hid)."</td><td>";
      $kroles = array_keys($roles);
      usort($kroles, function($a, $b) {
        [$ac, $al] = explode('.', $a); [$bc, $bl] = explode('.', $b);
        if ($ac > $bc) return -1;
        if ($bc > $ac) return 1;
        if ($ac == 1) return $al <=> $bl;
        return $bl <=> $al;
      });

      $res['overview'] .= count($kroles)."</td><td>";

      foreach ($kroles as $role) {
        $res['overview'] .= "<a href=\"?league=$leaguetag&mod=items-progrole-heroid$hid-position_$role".
          (empty($linkvars) ? "" : "&".$linkvars)."\">".
          (isset($roleicon_logo_provider) && isset($roleicons[$role]) ?
            "<img src=\"".str_replace("%ROLE%", $roleicons[$role], $roleicon_logo_provider)."\" alt=\"".$roleicons[$role]."\" />" :
            locale_string("position_$role")
          )."</a> ";
      }

      $res['overview'] .= "</td></tr>";
    }
    $res['overview'] .= "</tbody></table>";

    return $res;
  }

  if (empty($report['items']['progrole']['data'][$hero])) {
    $res["heroid".$hero] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $data = [];

  foreach ($report['items']['progrole']['data'][$hero][$crole] as $elem) {
    $data[] = array_combine($report['items']['progrole']['keys'], $elem);
  }

  $pbdata = $report['pickban'][$hero] ?? null;

  $pairs = [];
  $items = []; $items_matches = []; $items_matches1 = [];
  $max_wr = 0;
  $max_games = 0;

  usort($data, function($a, $b) {
    return $b['total'] <=> $a['total'];
  });

  if (!empty($_GET['cat'])) {
    $i = floor(count($data) * (float)($_GET['cat']));
    $med = $data[ $i > 0 ? $i-1 : 0 ]['total'];
  } else {
    $med = 0;
  }

  foreach ($data as $v) {
    if ($v['total'] < $med) continue;

    $pairs[] = $v;
    
    if (!in_array($v['item1'], $items)) {
      $items[] = $v['item1'];
    }
    
    if (!isset($items_matches[ $v['item1'] ])) {
      $items_matches[ $v['item1'] ] = null;
    }
    if (!isset($items_matches1[ $v['item1'] ])) {
      $items_matches1[ $v['item1'] ] = 0;
    }
    $items_matches1[ $v['item1'] ] += $v['total'];
    
    if (!in_array($v['item2'], $items)) {
      $items[] = $v['item2'];
    }

    if (!isset($items_matches[ $v['item2'] ])) {
      $items_matches[ $v['item2'] ] = 0;
    }
    $items_matches[ $v['item2'] ] += $v['total'];

    if ($v['total'] > $max_games) $max_games = $v['total'];
    $diff = abs($v['winrate']-($pbdata ? $pbdata['winrate_picked'] : 0.5));
    if ($diff > $max_wr) {
      $max_wr = $diff;
    }
  }
  $max_wr *= 2;

  foreach ($items_matches as $iid => $v) {
    if ($v !== null) {
      if (isset($items_matches1[$iid])) {
        $items_matches[$iid] = ($items_matches[$iid] + $items_matches1[$iid]) / 2;
      }
    } else {
      $items_matches[$iid] = $items_matches1[$iid];
    }
  }
  unset($items_matches1);

  if (empty($pairs)) {
    $res["heroid".$hero]["position_".$crole] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $reslocal = "";

  $reslocal .= "<div class=\"content-text\">".locale_string("items_progression_desc")."</div>";

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
    $nodes .= "{ id: $item, value: ".$items_matches[$item].", label: '".addslashes(item_name($item))."'".
      ", title: '".addslashes(item_name($item)).", ".locale_string('avg_timing').": ".convert_time_seconds($report['items']['stats'][$hero][$item]['median']).", ".
      locale_string('purchases').": ".$report['items']['stats'][$hero][$item]['purchases'].", ".
      locale_string('position').": ".$items_matches[$item].", ".
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
    $diff = $v['winrate'] - ($pbdata ? $pbdata['winrate_picked'] : 0.5);
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
      "%".$v['avgord1'].' '.$v['avgord2']."\", color:{color:$color, highlight: $color_hi}},";
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

  $res["heroid".$hero]["position_".$crole] = $reslocal;

  return $res;
}


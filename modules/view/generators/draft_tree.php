<?php

function rg_generator_draft_tree($table_id, &$context, &$context_draft, $limiter) {
  global $use_visjs, $meta;
  $use_visjs = true;

  if (is_wrapped($context)) {
    $context = unwrap_data($context);
  }

  usort($context, function($a, $b) {
    return $b['count'] <=> $a['count'];
  });

  if (!empty($_GET['cat'])) {
    $i = floor(count($context) * (float)($_GET['cat']));
  } else {
    $i = floor(count($context) * 0.35);
  }
  $med = $context[ $i > 0 ? $i-1 : 0 ]['count'];

  $stages_pop = [];

  $pairs = [];
  $items = [];
  $max_wr = 0;
  $max_games = 0;
  foreach ($context as $i => $v) {
    if ($v['count'] < $med) {
      unset($context[$i]);
      continue;
    }

    if (!isset($items[ $v['hero1'] + $v['stage1']*1000 ])) {
      $items[$v['hero1'] + $v['stage1']*1000] = [ $v['hero1'], $v['stage1'] ];
      $stages_pop[ $v['stage1'] ] = ($stages_pop[ $v['stage1'] ] ?? 0) + 1;
    }
    if (!isset($items[ $v['hero2'] + $v['stage2']*1000 ])) {
      $items[$v['hero2'] + $v['stage2']*1000 ] = [ $v['hero2'], $v['stage2'] ];
      $stages_pop[ $v['stage2'] ] = ($stages_pop[ $v['stage2'] ] ?? 0) + 1;
    }

    if ($v['count'] > $max_games) $max_games = $v['count'];
    $diff = abs(($v['wins']/$v['count'])-0.5);
    if ($diff > $max_wr) {
      $max_wr = $diff;
    }
  }
  $max_wr *= 2;

  $context = array_values($context);

  $res  = "<div class=\"content-text\">".locale_string("desc_draft_tree")."</div>";
  $res .= "<div id=\"$table_id\" class=\"graph\"></div><script type=\"text/javascript\">";

  $nodes = '';
  $edges = '';

  $lvlmod = 0;

  $sz = count($context);
  $level_size = pow( max($stages_pop)*max($stages_pop) / ( $sz ), 1.85 );

  $lvlmods = [];
  $prelvl = 0;
  for($i = 0; $i < 9; $i++) {
    if (!isset($stages_pop[$i])) $stages_pop[$i] = 1;
    $lvlmods[$i] = [
      'pre' => $prelvl,
      'sz' => ceil($stages_pop[$i] / $level_size),
      'cnt' => 0
    ];
    $prelvl += $lvlmods[$i]['sz'];
  }

  foreach ($items as $id => $hero) {
    $hid = $hero[0];
    $stage = floor($hero[1] / 2);
    if (!$stage) continue;
    $is_pick = $hero[1] % 2;
    foreach ($context_draft[$is_pick][$stage] as $el) {
      if ($el['heroid'] == $hid) {
        $matches = $el['matches'];
        $wr = $el['winrate'];
        break;
      }
    }
    $diff = $is_pick;

    $lvl = $lvlmods[ $hero[1] ]['pre'] + $lvlmods[ $hero[1] ]['cnt'];
    $lvlmods[ $hero[1] ]['cnt']++;
    if ($lvlmods[ $hero[1] ]['cnt'] == $lvlmods[ $hero[1] ]['sz']) $lvlmods[ $hero[1] ]['cnt'] = 0;

    $nodes .= "{ id: $id, value: $matches*2, label: '".addslashes(hero_name($hid))."'".
      ", title: '".addslashes(hero_name($hid)).", ".
      locale_string('stage').": ".$stage.", ".
      locale_string($is_pick ? 'pick' : 'ban').", ".
      locale_string('matches').": ".$matches.", ".
      locale_string('winrate').": ".($wr*100)."%, ".$lvl.
      "'".
      ", shape:'circularImage', ".
      "image: '".hero_icon_link($hid)."', level: ".$lvl.
    ", color:{
        background:'rgba(".number_format(255-255*$diff, 0).",124,".
        number_format(255*$diff, 0).", 0.5)', ".
        "border:'rgba(".number_format(255-255*$diff, 0).",124,".
        number_format(255*$diff, 0).", 0.5)',
        highlight: { background:'rgba(".number_format(255-255*$diff, 0).",124,".
          number_format(255*$diff, 0).", 1)', ".
          "border:'rgba(".number_format(255-255*$diff, 0).",124,".
          number_format(255*$diff, 0).", 1)' }
      }
    }, ";
  }

  foreach ($context as $v) {
    if ($v['count'] < $med) continue;

    $wr = ($v['wins']/$v['count']);
    $diff = $wr - 0.5;
    $color = "'rgba(".
      round(126-255*($max_wr ? $diff/$max_wr : 0)).",124,".
      round(126+255*($max_wr ? $diff/$max_wr : 0)).",".(0.15*$v['count']/$max_games+0.05).")'";
    $color_hi = "'rgba(".
      round(136-205*($max_wr ? ($wr-0.5)/$max_wr : 0)).",100,".
      round(136+205*($max_wr ? ($wr-0.5)/$max_wr : 0)).",".(0.85*$v['count']/$max_games+0.15).")'";
    $edges .= "{from: ".($v['hero1'] + $v['stage1']*1000).", to: ".($v['hero2'] + $v['stage2']*1000).", value:".$v['count'].", title:\"".
      $v['count']." ".locale_string("matches").", ".number_format($wr*100, 2)."% ".locale_string("winrate").
      ", ".$v['stage1'].' '.$v['stage2'].
      "\", color:{color:$color, highlight: $color_hi}},";
  }

  $res .= "var nodes = [ ".$nodes." ];";
  $res .= "var edges = [ ".$edges." ];";
  $res .= "var container = document.getElementById('$table_id');\n".
    "var data = { nodes: nodes, edges: edges };\n".
    "var options={
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
          direction: 'LR',
          sortMethod: 'directed',
          // shakeTowards: 'leaves',
          nodeSpacing: ".(10*$prelvl/8).",
          // avoidOverlap: 1,
          levelSeparation: 125,
        },
      }
    };\n".
    "var network = new vis.Network(container, data, options);\n".
    "</script>";

  return $res;
}
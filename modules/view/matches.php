<?php

$modules['matches'] = [];

include_once($root."/modules/view/generators/matches_list.php");

function rg_view_generate_matches() {
  global $report, $unset_module, $mod;
  if($mod == "matches") $unset_module = true;
  $res = [];
  $parent = "matches-";

  $res['list'] = "";
  if (check_module($parent."list")) {
    $res['list'] = rg_generator_matches_list("matches-list", $report['matches']);
  }

  $res['cards'] = "";
  if (check_module($parent."cards")) {
    krsort($report['matches']);
    $res['cards'] = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
    $res['cards'] .= "<div class=\"content-cards\">";
    foreach($report['matches'] as $matchid => $match) {
      $res['cards'] .= match_card($matchid);
    }
    $res['cards'] .= "</div>";
  }

  $res['heroes'] = [];
  if (check_module($parent."heroes")) {
    if ($mod == $parent."heroes") $unset_module = true;
    $parent .= "heroes-";

    global $meta, $strings;

    $hnames = [];
    foreach ($meta['heroes'] as $id => $v) {
      $hnames[$id] = $v['name'];
      $strings['en']["heroid".$id] = $v['name'];
    }
  
    uasort($hnames, function($a, $b) {
      if($a == $b) return 0;
      else return ($a > $b) ? 1 : -1;
    });
  
    foreach($hnames as $hid => $name) {
      $res['heroes']["heroid".$hid] = "";
  
      if(check_module($parent."heroid".$hid)) {
        $res['heroes']["heroid".$hid] = rg_generator_hero_matches_list("matches-heroes-$hid", $hid, null, true);
      }
    }
  }

  $res['hbanned'] = [];
  if (check_module($parent."hbanned")) {
    if ($mod == $parent."hbanned") $unset_module = true;
    $parent .= "hbanned-";

    global $meta, $strings;

    $hnames = [];
    foreach ($meta['heroes'] as $id => $v) {
      $hnames[$id] = $v['name'];
      $strings['en']["heroid".$id] = $v['name'];
    }
  
    uasort($hnames, function($a, $b) {
      if($a == $b) return 0;
      else return ($a > $b) ? 1 : -1;
    });
  
    foreach($hnames as $hid => $name) {
      $res['hbanned']["heroid".$hid] = "";
  
      if(check_module($parent."heroid".$hid)) {
        $res['hbanned']["heroid".$hid] = rg_generator_hero_matches_banned_list("matches-hbanned-$hid", $hid, null, true);
      }
    }
  }

  return $res;
}


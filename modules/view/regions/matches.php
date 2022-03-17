<?php

$res["region".$region]["matches"] = [];

include_once($root."/modules/view/generators/matches_list.php");

if(check_module($modstr."-matches")) {
  if($mod == $modstr."-matches") $unset_module = true;
  $reslocal = [];
  $parent = $modstr."-matches-";

  $reslocal['list'] = "";
  if (check_module($parent."list")) {
    $reslocal['list'] = rg_generator_matches_list("matches-list-region$region", $reg_report['matches']);
  }

  $reslocal['cards'] = "";
  if (check_module($parent."cards")) {
    krsort($reg_report['matches']);
    $reslocal['cards'] = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
    $reslocal['cards'] .= "<div class=\"content-cards\">";
    foreach($reg_report['matches'] as $matchid => $match) {
      $reslocal['cards'] .= match_card($matchid);
    }
    $reslocal['cards'] .= "</div>";
  }

  $reslocal['heroes'] = [];
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
      $reslocal['heroes']["heroid".$hid] = "";
  
      if(check_module($parent."heroid".$hid)) {
        $reslocal['heroes']["heroid".$hid] = rg_generator_hero_matches_list("matches-region$region-heroes-$hid", $hid, null, true, $reg_report['matches']);
      }
    }
  }
  
  $res["region".$region]['matches'] = $reslocal;
}

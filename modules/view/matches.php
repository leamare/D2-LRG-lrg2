<?php

$modules['matches'] = [];

include_once($root."/modules/view/generators/matches_list.php");
include_once($root."/modules/view/generators/tickets_list.php");

function rg_view_generate_matches() {
  global $report, $unset_module, $mod;
  if($mod == "matches") $unset_module = true;
  $res = [];
  $parent = "matches-";

  $series_filter = null;
  if (!empty($_GET['gets'])) {
    $series_filter = $_GET['gets'];
  }

  $res['list'] = "";
  if (check_module($parent."list")) {
    $res['list'] = rg_generator_matches_list("matches-list", $report['matches'], $series_filter);
  }

  if (!empty($report['series'])) {
    $res['series'] = "";
    if (check_module($parent."series")) {
      $res['series'] = rg_generator_series_list("matches-series", $report['series']);
    }
  }

  $res['cards'] = "";
  if (check_module($parent."cards")) {
    krsort($report['matches']);
    $res['cards'] = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
    if ($series_filter !== null) {
      $res['cards'] .= "<div class=\"content-text\"><h1>".locale_string("meet_num")." $series_filter</h1></div>";
    }
    $res['cards'] .= "<div class=\"content-cards\">";
    foreach($report['matches'] as $matchid => $match) {
      if ($series_filter !== null && isset($report['match_parts_series_tag'])) {
        $series_tag = $report['match_parts_series_tag'][$matchid] ?? null;
        $sid = ($report['series'][$series_tag]['seriesid'] ?? 0) ? $report['series'][$series_tag]['seriesid'] : $series_tag;
        if ($sid != $series_filter) {
          continue;
        }
      }
  
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

  if (!empty($report['tickets'])) {
    $res['tickets'] = "";
    if (check_module($parent."tickets")) {
      $res['tickets'] = rg_generator_tickets_list("matches-tickets", $report['tickets']);
    }
  }

  return $res;
}


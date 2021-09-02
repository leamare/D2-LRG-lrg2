<?php
if (file_exists($cats_file)) {
  $cats = file_get_contents($cats_file);
  $cats = json_decode($cats, true);
}

include_once("modules/view/__open_cache.php");
include_once("modules/view/__update_cache.php");
include_once("$root/modules/view/functions/convert_patch.php");

/*
function: checktag($reportdata, $tag),
  tags:
    tag
    name {localized}
    desc {localized}
    filters (|| or &&)
    custom_style
    hidden
*/

$modules = "";
$modules .= "<div id=\"content-top\">";

if (!empty($__friends) && !isset($cat)) {
  $modules .= "<div class=\"content-text list-pinned friends-list\"><h1>".locale_string("friends_main")."</h1>";
  foreach($__friends as $pin) {
    $modules .= "<a class=\"category\" href=\"".$pin[1]."\" target=\"_blank\">".$pin[0]."</a>";
  }
  $modules .= "</div>";
}

if (!empty($__pinned)) {
  $modules .= "<div class=\"content-text list-pinned\"><h1>".locale_string("pinned_main")."</h1>";
  foreach($__pinned as $pin) {
    if ($pin[1] && !isset($cats)) continue;
    // if ($pin[1] && !isset($cats[$pin[0]])) continue;
    $modules .= "<a class=\"category".($pin[1] && isset($cat) && $cat == $pin[0] ? " active" : "").
              "\" href=\"?".($pin[1] ? "cat" : "league")."=".$pin[0].(empty($linkvars) ? "" : "&".$linkvars)."\">";
    if ($pin[1]) {
      $modules .= $cats[ $pin[0] ]['names_locales'][$locale] ?? $cats[ $pin[0] ]['name'] ?? locale_string($pin[0]."_reports");
    } else {
      $modules .= $cache["reps"][ $pin[0] ]['localized'][$locale]['name'] ?? $cache["reps"][ $pin[0] ]['name'] ?? $pin[0];
    }
    $modules .= "</a>";
  }
  $modules .= "</div>";
}

if(!empty($cats)) {
  include_once("modules/view/functions/check_filters.php");

  if (isset($cat) && isset($cats[$cat])) {
    $reps = [];
    foreach($cache["reps"] as $tag => $rep) {
      if(check_filters($rep, $cats[$cat]['filters']))
        $reps[$tag] = $rep;
    }
    if(isset($cats[$cat]['custom_style']) && file_exists("res/custom_styles/".$cats[$cat]['custom_style'].".css"))
      $custom_style = $cats[$cat]['custom_style'];
    if(isset($cats[$cat]['custom_logo']) && file_exists("res/custom_styles/logos/".$cats[$cat]['custom_logo'].".css"))
      $custom_logo = $cats[$cat]['custom_logo'];

    if(isset($cats[$cat]['names_locales'][$locale])) $head_name = $cats[$cat]['names_locales'][$locale];
    else $head_name = $cats[$cat]['name'];

    if(isset($cats[$cat]['desc_locales'][$locale])) $head_desc = $cats[$cat]['desc_locales'][$locale];
    else if(isset($cats[$cat]['desc'])) $head_desc = $cats[$cat]['desc'];
  } else if(!isset($cat) || $cat == "main") {
    $head_name = $instance_name;
    $head_desc = $instance_desc;
    if(isset($cats[$hidden_cat])) {
      foreach($cache["reps"] as $tag => $rep) {
        if(!check_filters($rep, $cats[$hidden_cat]['filters']))
          $reps[$tag] = $rep;
      }
    } else {
      $reps = $cache["reps"];
    }
  } else if ($cat == "all") {
    $head_name = $instance_name;
    $head_desc = $instance_desc;
    $reps = $cache["reps"];
  } else if ($cat == "recent") {
    $head_name = locale_string("recent_reports");
    // $recent_last_limit;
    $reps = $cache["reps"];
    uasort($reps, function($a, $b) {
      return ($b['last_update'] ?? 0) - ($a['last_update'] ?? 0);
    });
    if ($recent_last_limit ?? false) {
      $limit = null;
      $i = 0;
      foreach($reps as $k => $v) {
        if (($v['last_update'] ?? 0) < $recent_last_limit) {
          $limit = $i;
          break;
        }
        $i++;
      }
      $r = [];
      $i = 0;
      foreach ($reps as $k => $v) {
        if ($i >= $limit) break;
        $r[$k] = $v;
        $i++;
      }
      $reps = $r;
    }
  } else {
    $head_name = $cat;
    $reps = [];
  }
} else {
  $head_name = $instance_name;
  $head_desc = $instance_desc;
  $reps = $cache["reps"];
}

if(isset($cats) && !empty($cats)) {
  if(isset($cat) || !empty($__pinned)) {
    $modules .= "<div class=\"content-text tagsshow\"><a class=\"category\">".locale_string("show_tags")."</a></div>";
  }

  $modules .= "<div class=\"content-text tagslist ".(isset($cat) || !empty($__pinned) ? "hidden" : "")."\" ".(isset($cat) || !empty($__pinned) ? " style=\"display: none;\"" : "").">";

  $modules .= "<a class=\"category".(isset($cat) && "main" == $cat ? " active" : "").
              "\" href=\"?cat=main".(empty($linkvars) ? "" : "&".$linkvars).
              "\">".locale_string("main_reports")."</a>";
  $modules .= "<a class=\"category".(isset($cat) && "recent" == $cat ? " active" : "").
              "\" href=\"?cat=recent".(empty($linkvars) ? "" : "&".$linkvars).
              "\">".locale_string("recent_reports")."</a>";

  foreach($cats as $tag => $desc) {
    if($tag == $hidden_cat || (isset($desc['hidden']) && $desc['hidden'])) continue;

    $modules .= "<a class=\"category".(isset($cat) && $tag == $cat ? " active" : "")."\" ".
                "href=\"?cat=".$tag.(empty($linkvars) ? "" : "&".$linkvars)."\" ".
                (isset($desc['desc_locales'][$locale]) ? "title=\"".$desc['desc_locales'][$locale]."\"" :
                  (isset($desc['desc']) ? "title=\"".$desc['desc']."\"" : "")
                ).
                ">".
                (isset($desc['names_locales'][$locale]) ? $desc['names_locales'][$locale] :
                  (isset($desc['name']) ? $desc['name'] : $tag)
                ).
                "</a>";
  }

  if(isset($cats[$hidden_cat]))
    $modules .= "<a class=\"category".(isset($cat) && "all" == $cat ? " active" : "").
                "\" href=\"?cat=all".(empty($linkvars) ? "" : "&".$linkvars).
                "\">".locale_string("all_reports")."</a>";

  $modules .= "</div>";
}

if (sizeof($cache['reps']) === 0) {
  $modules .= "<div class=\"content-header\">".locale_string("empty_instance_cap")."</div>".
    "<div class=\"content-text\">".locale_string("empty_instance_desc").".</div>".
  "</div>";
} else if (sizeof($reps) === 0) {
  $modules .= "<div class=\"content-header\">".locale_string("noreports")."</div>".
    "<div class=\"content-text\">".locale_string("noreports_desc").".</div>".
  "</div>";
} else {
  if (!empty($ads_block_main)) $modules .= "<div class=\"ads-block-main\">$ads_block_main</div>";

  if(!isset($cat) && $index_list < sizeof($reps))
    $modules .= "<div class=\"content-header\">".locale_string("noleague_cap")."</div>";
  $modules .= "</div>";

  $modules .= "<table id=\"leagues-list\" class=\"list wide ".(isset($cat) ? "sortable" : "")."\"><thead><tr>";
  $modules .= "<th>".locale_string("league_name")."</th>".
    "<th>".locale_string("league_id")."</th>".
    "<th>".locale_string("league_desc")."</th>".
    "<th>".locale_string("type")."</th>".
    "<th>".locale_string("matches_total")."</th>".
    "<th>".locale_string("patches")."</th>".
    "<th>".locale_string("participants")."</th>".
    "<th>".locale_string("regions")."</th>".
    "<th>".locale_string("days")."</th>".
    "<th>".locale_string("start_date")."</th>".
    "<th>".locale_string("end_date")."</th>".
    "</tr></thead>";

  if (!isset($cat) || $cat !== "recent") {
    uasort($reps, function($a, $b) {
      if (empty($a) && empty($b)) return 0;
      if (empty($a)) return 1;
      if (empty($b)) return -1;
      if($a['last_match']['date'] == $b['last_match']['date']) {
        if($a['first_match']['date'] == $b['first_match']['date']) return 0;
        else return ($a['first_match']['date'] < $b['first_match']['date']) ? -1 : 1;
      } else return ($a['last_match']['date'] < $b['last_match']['date']) ? 1 : -1;
    });
  }

  foreach($reps as $report) {
    if (empty($report)) continue;
    if ($report['short_fname'][0] == '!') continue;
    if(!isset($cat) && $index_list < sizeof($reps)) {
      if(!$index_list) break;
      $index_list--;
    }

    if (isset($latest) && $latest) {
      header("Location: ?league=".$report['tag'].
        (empty($linkvars) ? "" : "&".$linkvars).
        (empty($mod) ? "" : "&mod=".$mod),
        true, 
        302
      );
      exit();
    }

    $event_type = $report['tvt'] ? 'tvt' : (
      isset($report['players']) ? 'pvp' : 'ranked'
    );

    $participants = isset($report['teams']) ? sizeof($report['teams']) : (
      isset($report['players']) ? sizeof($report['players']) : '-'
    );

    if (!empty($report['orgs'])) {
      $report['desc'] .= " - <a target=\"_blank\" href=\"".$report['orgs']."\">".locale_string("website")."</a>";
    }

    // if (!empty($report['links'])) {
    //   foreach($report['links'] as $type => $link) {
    //     $report['desc'] .= " - <a target=\"_blank\" href=\"".$link."\">".$type."</a>";
    //   }
    // }

    if (isset($report['localized']) && isset($report['localized'][$locale])) {
      $report['name'] = $report['localized'][$locale]['name'] ?? $report['name'];
      $report['desc'] = $report['localized'][$locale]['desc'] ?? $report['desc'];
    }

    $modules .= "<tr><td><a href=\"?league=".$report['tag'].(empty($linkvars) ? "" : "&".$linkvars)."\">".$report['name']."</a></td>".
      "<td>".($report['id'] == "" ? "-" : $report['id'])."</td>".
      "<td>".$report['desc']."</td>".
      "<td>".locale_string($event_type)."</td>".
      "<td>".$report['matches']."</td>".
      "<td>".(
        empty($report['patches']) ? '- (1)' : (
          sizeof($report['patches']) == 1 ?
            convert_patch( array_keys($report['patches'])[0] ).' (1)' : 
            convert_patch( min(array_keys($report['patches'])) ).' - '.convert_patch( max(array_keys($report['patches'])) ).' ('.sizeof($report['patches']).')'
        )
      )."</td>".
      "<td>".$participants."</td>".
      "<td>".(isset($report['regions']) ? sizeof($report['regions']) : ' - ')."</td>".
      "<td>".$report['days']."</td>".
      "<td value=\"".$report['first_match']['date']."\" data-matchid=\"".$report['first_match']['mid']."\">".date(locale_string("date_format"), $report['first_match']['date'])."</td>".
      "<td value=\"".$report['last_match']['date']."\" data-matchid=\"".$report['last_match']['mid']."\">".date(locale_string("date_format"), $report['last_match']['date'])."</td></tr>";
  }
  if(!$index_list ) {
    $modules .= "<tr><td></td><td></td><td>...</td><td colspan=\"8\"></td></tr>";
  }

  $modules .= "</table>";
}
?>

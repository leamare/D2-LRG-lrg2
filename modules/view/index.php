<?php
if (file_exists($cats_file)) {
  $cats = file_get_contents($cats_file);
  $cats = json_decode($cats, true);
}

include_once("modules/view/__open_cache.php");
include_once("modules/view/__update_cache.php");
include_once("modules/view/functions/check_filters.php");
include_once("modules/view/functions/create_search_filters.php");
include_once("$root/modules/view/functions/convert_patch.php");

function populate_reps(&$cache, $filters, $exclude_hidden = true) {
  global $cats, $hidden_cat;

  $res = [];

  foreach($cache as $tag => $rep) {
    if(check_filters($rep, $filters)) {
      if ($exclude_hidden && isset($cats[$hidden_cat])) {
        if(check_filters($rep, $cats[$hidden_cat]['filters'])) {
          continue;
        }
      }
      $res[$tag] = $rep;
    }
  }

  return $res;
}

function report_list_element($report) {
  global $locale, $index_list, $reps, $cat, $league_logo_banner_provider, $linkvars, $__lid_fallbacks;

  $iscat = $report['cat'] ?? false;

  if (!$iscat) {
    $event_type = $report['tvt'] ? 'tvt' : (
      isset($report['players']) ? 'pvp' : 'ranked'
    );

    $participants = isset($report['teams']) ? sizeof($report['teams']) : (
      isset($report['players']) ? sizeof($report['players']) : '-'
    );

    $aliases = $report['tag'];
    $aliases .= " ".$event_type;
    if (!empty($report['patches'])) {
      foreach ($report['patches'] as $patch => $sz)
        $aliases .= " ".convert_patch($patch);
    }
    if (!empty($report['regions'])) {
      foreach ($report['regions'] as $reg) {
        $aliases .= " ".locale_string("region".$reg);
      }
    }
  }

  if (!empty($report['orgs'])) {
    $report['desc'] .= " - <a target=\"_blank\" href=\"".$report['orgs']."\">".locale_string("website")."</a>";
  }

  if (isset($report['localized']) && isset($report['localized'][$locale])) {
    $report['name'] = $report['localized'][$locale]['name'] ?? $report['name'];
    $report['desc'] = $report['localized'][$locale]['desc'] ?? $report['desc'];
  }

  $_lid = $report['id'] ?? null;
  if (empty($_lid) && !empty($__lid_fallbacks)) {
    foreach ($__lid_fallbacks as $preg => $lid) {
      if (preg_match($preg, $report['tag'] ?? $cat)) {
        $_lid = $lid;
        break;
      }
    }
  }
  if (empty($_lid)) $_lid = "default";

  $res = "<tr class=\"expandable primary closed\" data-group=\"report-".$report['tag']."\">".
    "<td><span class=\"expand\"></span></td>".
    "<td>".
      "<img class=\"event-logo-list\" src=\"".str_replace('%LID%', $_lid, $league_logo_banner_provider)."\" alt=\"$_lid\" loading=\"lazy\" />".
    "</td>".
    "<td><a href=\"?".($iscat ? "cat" : "league")."=".$report['tag'].(empty($linkvars) ? "" : "&".$linkvars)."\" ".
      ($iscat ? '' : "data-aliases=\"".$aliases."\"").
      ">".$report['name']."</a></td>".
    "<td>".($report['id'] == "" ? "-" : "<a href=\"?lid=".$report['id'].(empty($linkvars) ? "" : "&".$linkvars)."\">".$report['id']."</a>")."</td>".
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
    "<td value=\"".$report['first_match']['date']."\" data-matchid=\"".($report['first_match']['mid'] ?? 0)."\">".date(locale_string("date_format"), $report['first_match']['date'])."</td>".
    "<td value=\"".$report['last_match']['date']."\" data-matchid=\"".($report['last_match']['mid'] ?? 0)."\">".date(locale_string("date_format"), $report['last_match']['date'])."</td>".
    "</tr>".
    "<tr class=\"collapsed secondary tablesorter-childRow\" data-group=\"report-".$report['tag']."\"><td></td><td colspan=11>".$report['desc']."</td></tr>";

  return $res;
}

function report_card_element($report, $smaller = true, $catlabel = false) {
  global $locale, $cat, $league_logo_banner_provider, $league_logo_provider, $__lid_fallbacks, $linkvars;

  $iscat = $report['cat'] ?? false;

  if (!$iscat) {
    $event_type = $report['tvt'] ? 'tvt' : (
      isset($report['players']) ? 'pvp' : 'ranked'
    );

    $participants = isset($report['teams']) ? sizeof($report['teams']) : (
      isset($report['players']) ? sizeof($report['players']) : '-'
    );

    $aliases = $report['tag'];
    $aliases .= " ".$event_type;
    if (isset($report['patches'])) {
      foreach ($report['patches'] as $patch => $sz)
        $aliases .= " ".convert_patch($patch);
    }
    if (isset($report['regions'])) {
      foreach ($report['regions'] as $reg) {
        $aliases .= " ".locale_string("region".$reg);
      }
    }
  }
  if (isset($report['localized']) && isset($report['localized'][$locale])) {
    $report['name'] = $report['localized'][$locale]['name'] ?? $report['name'];
    $report['desc'] = $report['localized'][$locale]['desc'] ?? $report['desc'];
  }

  $_lid = $report['id'] ?? null;
  if (empty($_lid) && !empty($__lid_fallbacks)) {
    foreach ($__lid_fallbacks as $preg => $lid) {
      if (preg_match($preg, $report['tag'] ?? $cat)) {
        $_lid = $lid;
        break;
      }
    }
  }
  if (empty($_lid)) $_lid = "default";

  $extra = "<p>".$report['desc']."</p>".
  "<p>LID: ".($report['id'] == "" ? "-" : "<a href=\"?lid=".$report['id'].(empty($linkvars) ? "" : "&".$linkvars)."\">".$report['id']."</a>")."</p>".
  (isset($report['orgs']) ? "<p><a target=\"_blank\" href=\"".$report['orgs']."\">".locale_string("website")."</a></p>" : "").
  (isset($event_type) ? "<p>".locale_string('type').": ".locale_string($event_type)."</p>" : "").
  (isset($participants) ? "<p>".locale_string('participants').": ".locale_string($event_type)."</p>" : "").
  (isset($report['regions']) ? "<p>".locale_string('regions').": ".sizeof($report['regions'])."</p>" : "").
  (!$iscat ? "<p>".locale_string('days').": ".$report['days']."</p>" : "");


  $res = "<div class=\"card ".($smaller ? 'smaller' : '')."\" data-report=\"report-".$report['tag']."\" ".
      ($iscat ? '' : "data-aliases=\"".$aliases."\"").">".
    "<a class=\"image\" href=\"?".($iscat ? "cat" : "league")."=".$report['tag'].(empty($linkvars) ? "" : "&".$linkvars)."\">".
      // (
      //   $smaller ? 
      //   "<img class=\"event-logo-card\" src=\"".str_replace('%LID%', $_lid, $league_logo_provider)."\" alt=\"$_lid\" />" :
        "<img class=\"event-logo-card\" src=\"".str_replace('%LID%', $_lid, $league_logo_banner_provider)."\" alt=\"$_lid\" loading=\"lazy\" />".
      // ).
      ($iscat && $catlabel ? 
        "<span class=\"cat-label\">".locale_string('category')."</span>" : 
        ""
      ).
    "</a>".
    "<div class=\"content\">".
      "<a class=\"card-name header\" href=\"?".($iscat ? "cat" : "league")."=".$report['tag'].(empty($linkvars) ? "" : "&".$linkvars)."\">".$report['name']."</a>".
      "<div class=\"meta\">".
        "<span class=\"dates\">".
          "<span class=\"starting-date\" value=\"".$report['first_match']['date']."\" data-matchid=\"".($report['first_match']['mid'] ?? 0)."\">".
            (!$report['first_match']['date'] ? locale_string('upcoming') : date(locale_string("date_format"), $report['first_match']['date'])).
          "</span>".
          (
            $report['first_match']['date'] != $report['last_match']['date'] ?
            " - ".
            "<span class=\"ending-date\" value=\"".$report['last_match']['date']."\" data-matchid=\"".($report['last_match']['mid'] ?? 0)."\">".
              // 172800 = 2 days
              (time() - $report['last_match']['date'] < 172800 ? locale_string('ongoing') : date(locale_string("date_format"), $report['last_match']['date'])).
            "</span>" :
            ""
          ).
        "</span>".
        "<span class=\"patches\">".
          (
            empty($report['patches']) ? '-' : (
              sizeof($report['patches']) == 1 ?
                convert_patch( array_keys($report['patches'])[0] ) : 
                convert_patch( min(array_keys($report['patches'])) ).' - '.convert_patch( max(array_keys($report['patches'])) )
            )
          ).
        "</span>".
      "</div>".
      ($smaller ? '' : "<div class=\"description\">".$report['desc']."</div>").
    "</div>".
    "<div class=\"extra content\">".
      "<span class=\"element\">".locale_string($iscat ? 'leag_reports' : 'matches').": ".$report['matches']."</span>".
      "<a class=\"right element\" onclick=\"showModal('".htmlspecialchars(addcslashes($extra, "'"))."', '".htmlspecialchars(addcslashes($report['name'], "'"))."');\">".
        locale_string('details').
      "</a>".
    "</div>".
  "</div>";

  return $res;
}

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

// $modules .= "<div class=\"content-text tabs-container\">";

// $modules .= "<input type=\"radio\" class=\"tab\" id=\"tabs-block-tab1\" name=\"css-tabs\">";

$tabSelected = null;
$tabsNames = [];
$tabsContent = [];
$tabsTags = [];

if (isset($_lid)) {
  $searchstring = ($searchstring ?? " ") . "!lid:".$_lid;
}

if (!empty($searchstring)) {
  $searchfilter = create_search_filters($searchstring);

  $reps = [];
  foreach($cache["reps"] as $tag => $rep) {
    if(check_filters($rep, $searchfilter))
      $reps[$tag] = $rep;
  }

  $head_name = locale_string("search_header");
  $head_desc = htmlspecialchars($searchstring);
} else if (!empty($cats)) {
  if (isset($cat) && isset($cats[$cat])) {
    $reps = populate_reps($cache["reps"], $cats[$cat]['filters'], $cats[$cat]['exclude_hidden'] ?? true);
    
    if(isset($cats[$cat]['custom_style']) && file_exists("res/custom_styles/".$cats[$cat]['custom_style'].".css"))
      $custom_style = $cats[$cat]['custom_style'];
    if(isset($cats[$cat]['custom_logo']) && file_exists("res/custom_styles/logos/".$cats[$cat]['custom_logo'].".css"))
      $custom_logo = $cats[$cat]['custom_logo'];

    if(isset($cats[$cat]['names_locales'][$locale])) $head_name = $cats[$cat]['names_locales'][$locale];
    else $head_name = $cats[$cat]['name'] ?? locale_string('cat_'.$cat);

    if(isset($cats[$cat]['desc_locales'][$locale])) $head_desc = $cats[$cat]['desc_locales'][$locale];
    else if(isset($cats[$cat]['desc'])) $head_desc = isset($cats[$cat]['locale_desc_tag']) ? locale_string($cats[$cat]['locale_desc_tag']) : $cats[$cat]['desc'];

    if (isset($cats[$cat]['lid'])) $social_lid = $cats[$cat]['lid'];
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
      $lu = ($b['last_update'] ?? 0) <=> ($a['last_update'] ?? 0);

      if ($lu) return $lu;

      return ($b['matches'] ?? 0) <=> ($a['matches'] ?? 0);
    });
    if (!($recent_last_limit ?? false)) {
      $recent_last_limit = time() - 14*24*3600;
    }

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
  } else {
    $head_name = $cat;
    $reps = [];
  }
} else {
  $head_name = $instance_name;
  $head_desc = $instance_desc;
  $reps = $cache["reps"];
}

// search block
  $tmp = "<form action=\"?".(empty($linkvars) ? "" : $linkvars)."\" method=\"get\">".
    "<input type=\"text\" name=\"search\" value=\"".htmlspecialchars($searchstring ?? (isset($cat) && $cat != "main" ? "!cat:$cat " : ""))."\" />";
  
  if (!empty($linkvars)) {
    $_vars = explode('&', $linkvars);
    foreach ($_vars as $kv) {
      [$k, $v] = explode('=', $kv);
      $tmp .= "<input type=\"hidden\" name=\"$k\" value=\"".addcslashes($v, '"')."\" />";
    }
  }
  
  $tmp .= "<input type=\"submit\" value=\"".locale_string("search_submit")."\" />";

  if (isset($search_info_link)) {
    $tmp .= "<div class=\"content-text\">".
      "<a href=\"$search_info_link\" target=\"_blank\">".locale_string("search_info_link")."</a>".
    "</div>";
  }

  $tmp .= "</form>";

  // $tabsContent[] = $tmp;
  // $tabsNames[] = locale_string("search");
  // $tabsTags[] = "searchform";

  // if (!empty($searchstring)) $tabSelected = count($tabsNames)-1;
//

// don't need the tabs anymore

// if (!empty($tabsNames)) {
//   $modules .= "<div class=\"tabs-block\">";

//   if ($tabSelected === null) $tabSelected = 0;

//   foreach ($tabsNames as $i => $name) {
//     $modules .= "<input type=\"radio\" class=\"tab\" id=\"tabs-block-tab".($i+1)."\" name=\"css-tabs\" ".($i == $tabSelected ? "checked" : "").">";
//   }

//   $modules .= "<ul class=\"tabs-container\">";
//   foreach ($tabsNames as $i => $name) {
//     $modules .= "<li class=\"tabs-container-tab\"><label for=\"tabs-block-tab".($i+1)."\">$name</label></li>";
//   }
//   $modules .= "</ul>";

//   foreach ($tabsContent as $i => $content) {
//     $tags = $tabsTags[$i] ?? "";
//     $modules .= "<div class=\"tab-content $tags\">$content</div>";
//   }

//   $modules .= "</div>";
// }

$modules .= "<div class=\"content-text tagslist\">".
  "<a class=\"category ".(empty($mod) && empty($cat) && empty($searchstring) ? "active" : "")."\" href=\".".(!empty($linkvars) ? "?".$linkvars : "")."\">".locale_string('pinned_main')."</a>".
  "<a class=\"category ".($cat=="main" ? "active" : "")."\" href=\"?cat=main".(!empty($linkvars) ? "&".$linkvars : "")."\">".locale_string('main_reports')."</a>".
  "<a class=\"category ".(check_module("cats")  ? "active" : "")."\" href=\"?mod=cats".(!empty($linkvars) ? "&".$linkvars : "")."\">".locale_string('categories')."</a>".
  "<label class=\"category ".(!empty($searchstring) ? "active" : "")."\" for=\"search-toggle\">".locale_string('search')."</label>".
"</div>".
"<input type=\"checkbox\" class=\"search-toggle\" id=\"search-toggle\" name=\"search-toggle\" ".(!empty($searchstring) ? "checked" : "").">".
"<div class=\"content-text searchform\">".
  $tmp.
"</div>";

if (!empty($__links)) {
  $modules .= "<div class=\"content-text list-pinned friends-list\">";
  foreach($__links as $pin) {
    $modules .= "<a class=\"category\" href=\"".$pin[1]."\" target=\"_blank\">".
      (isset($pin[2]) ? "<img src=\"".$pin[2]."\" alt=\"favicon\" class=\"category-icon\" />" : "").
      $pin[0]."</a>";
  }
  $modules .= "</div>";
}

if (!empty($__friends) && !(isset($cat) || isset($searchstring))) {
  $modules .= "<div class=\"content-text list-pinned friends-list\"><h1>".locale_string("friends_main")."</h1>";
  foreach($__friends as $pin) {
    $modules .= "<a class=\"category\" href=\"".$pin[1]."\" target=\"_blank\">".
      (isset($pin[2]) ? "<img src=\"".$pin[2]."\" alt=\"favicon\" class=\"category-icon\" />" : "").
      $pin[0]."</a>";
  }
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
  $page = (isset($cat) || isset($searchstring) || empty($cats)) ? 'meow' : (
    (check_module("cats") || empty($__featured_cats)) ? 'cats' : 'index'
  );

  if (!empty($ads_block_main)) $modules .= "<div class=\"ads-block-main\">$ads_block_main</div>";

  if ($page == 'meow') {
  if(!isset($cat) && $index_list < sizeof($reps))
    $modules .= "<div class=\"content-header\">".locale_string("noleague_cap")."</div>";
  $modules .= "</div>";

    $modules .= "<div class=\"table-header-info wide compact\">".
    "<span class=\"table-header-info-name\">".locale_string('reports_count').": ".count($reps)."</span>".
  "</div>";

  if(isset($cat) || $index_list >= sizeof($reps)) {
    $modules .= "<input name=\"filter\" class=\"search-filter wide\" data-table-filter-id=\"leagues-list\" placeholder=\"".locale_string('filter_placeholder')."\" />";
  }

  $modules .= "<table id=\"leagues-list\" class=\"list wide ".(isset($cat) ? "sortable" : "")."\"><thead><tr class=\"overhead\">".
    "<th width=\"50px\"></th>".
    "<th width=\"100px\"></th>".
    "<th>".locale_string("league_name")."</th>".
    "<th>".locale_string("league_id")."</th>".
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
    if (isset($cat) && isset($cats[$cat]) && isset($cats[$cat]['orderby'])) {
      // not my finest creation
      $orderby = $cats[$cat]['orderby'];
      uasort($reps, function($a, $b) use (&$orderby) {
        $res = 0;
        foreach ($orderby as $k => $dir) {
          $res = $dir ? (($b[$k] ?? 0) <=> ($a[$k] ?? 0)) : (($a[$k] ?? 0) <=> ($b[$k] ?? 0));
          if ($res) break;
        }

        return $res;
      });
    } else {
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
  }

  foreach($reps as $report) {
    if (empty($report)) continue;

      $iscat = $report['cat'] ?? false;
      
      if (!$iscat) {
    if ($report['short_fname'][0] == '!') continue;
    if(!(isset($cat) || isset($searchstring)) && $index_list < sizeof($reps)) {
          if(!$index_list) return $res;
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
      }

      $modules .= report_list_element($report);
    }
    if(!$index_list) {
      $modules .= "<tr><td></td><td></td><td>...</td><td colspan=\"9\"></td></tr>";
    }

    $modules .= "</table>";
  }

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

    $aliases = $report['tag'];
    $aliases .= " ".$event_type;
    if (isset($report['patches'])) {
      foreach ($report['patches'] as $patch => $sz)
        $aliases .= " ".convert_patch($patch);
    }
    if (isset($report['regions'])) {
      foreach ($report['regions'] as $reg) {
        $aliases .= " ".locale_string("region".$reg);
      }
    }

    $_lid = $report['id'] ?? null;
    if (empty($_lid) && !empty($__lid_fallbacks)) {
      foreach ($__lid_fallbacks as $preg => $lid) {
        if (preg_match($preg, $report['tag'] ?? $cat)) {
          $_lid = $lid;
          break;
        }
      }
    }
    if (empty($_lid)) $_lid = "default";

    $modules .= "<tr class=\"expandable primary closed\" data-group=\"report-".$report['tag']."\">".
      "<td><span class=\"expand\"></span></td>".
      "<td>".
        "<img class=\"event-logo-list\" src=\"".str_replace('%LID%', $_lid, $league_logo_banner_provider)."\" alt=\"$_lid\" />".
      "</td>".
      "<td><a href=\"?league=".$report['tag'].(empty($linkvars) ? "" : "&".$linkvars)."\" data-aliases=\"".$aliases."\">".$report['name']."</a></td>".
      "<td>".($report['id'] == "" ? "-" : "<a href=\"?lid=".$report['id'].(empty($linkvars) ? "" : "&".$linkvars)."\">".$report['id']."</a>")."</td>".
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
      "<td value=\"".$report['first_match']['date']."\" data-matchid=\"".($report['first_match']['mid'] ?? 0)."\">".date(locale_string("date_format"), $report['first_match']['date'])."</td>".
      "<td value=\"".$report['last_match']['date']."\" data-matchid=\"".($report['last_match']['mid'] ?? 0)."\">".date(locale_string("date_format"), $report['last_match']['date'])."</td>".
      "</tr>".
      "<tr class=\"collapsed secondary tablesorter-childRow\" data-group=\"report-".$report['tag']."\"><td></td><td colspan=11>".$report['desc']."</td></tr>";
  }
  if(!$index_list) {
    $modules .= "<tr><td></td><td></td><td>...</td><td colspan=\"9\"></td></tr>";
  }

  $modules .= "</table>";
}

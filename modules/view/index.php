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
  global $locale, $index_list, $reps, $cat, $league_logo_banner_provider, $linkvars;

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
      "<img class=\"event-logo-list\" src=\"".str_replace('%LID%', $_lid, $league_logo_banner_provider)."\" alt=\"$_lid\" />".
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

function report_card_element($report, $smaller = false) {
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

if (!empty($__pinned)) {
  $tmp = "";
  foreach($__pinned as $pin) {
    if ($pin[1] && !isset($cats)) continue;
    // if ($pin[1] && !isset($cats[$pin[0]])) continue;
    $tmp .= "<a class=\"category".($pin[1] && isset($cat) && $cat == $pin[0] ? " active" : "").
              "\" href=\"?".($pin[1] ? "cat" : "league")."=".$pin[0].(empty($linkvars) ? "" : "&".$linkvars)."\">";
    if ($pin[1]) {
      $tmp .= $cats[ $pin[0] ]['names_locales'][$locale] ?? $cats[ $pin[0] ]['name'] ?? locale_string($pin[0]."_reports");
    } else {
      $tmp .= $cache["reps"][ $pin[0] ]['localized'][$locale]['name'] ?? $cache["reps"][ $pin[0] ]['name'] ?? $pin[0];
    }
    $tmp .= "</a>";

    if ($pin[1] && isset($cat) && $cat == $pin[0]) $tabSelected = 0;
  }

  $tabsContent[] = $tmp;
  $tabsNames[] = locale_string("pinned_main");
  $tabsTags[] = "list-pinned";
}

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

if(isset($cats) && !empty($cats)) {
  // if(isset($cat) || !empty($__pinned)) {
  //   $tmp = "<div class=\"content-text tagsshow\"><a class=\"category\">".locale_string("show_tags")."</a></div>";
  // }

  // $tmp = "<div class=\"content-text tagslist ".(isset($cat) || !empty($__pinned) ? "hidden" : "")."\" ".(isset($cat) || !empty($__pinned) ? " style=\"display: none;\"" : "").">";
  $tmp = "";

  $tmp .= "<a class=\"category".(isset($cat) && "main" == $cat ? " active" : "").
              "\" href=\"?cat=main".(empty($linkvars) ? "" : "&".$linkvars).
              "\">".locale_string("main_reports")."</a>";
  $tmp .= "<a class=\"category".(isset($cat) && "recent" == $cat ? " active" : "").
              "\" href=\"?cat=recent".(empty($linkvars) ? "" : "&".$linkvars).
              "\">".locale_string("recent_reports")."</a>";
  // $tmp .= "<a class=\"category".(isset($cat) && "ongoing" == $cat ? " active" : "").
  //             "\" href=\"?cat=ongoing".(empty($linkvars) ? "" : "&".$linkvars).
  //             "\">".locale_string("ongoing_reports")."</a>";

  if ($tabSelected === null && isset($cat) && ($cat == "main" || $cat == "recent" || $cat == "ongoing")) {
    $tabSelected = count($tabsNames);
  }

  foreach($cats as $tag => $desc) {
    if($tag == $hidden_cat || (isset($desc['hidden']) && $desc['hidden'])) continue;

    $tmp .= "<a class=\"category".(isset($cat) && $tag == $cat ? " active" : "")."\" ".
                "href=\"?cat=".$tag.(empty($linkvars) ? "" : "&".$linkvars)."\" ".
                (isset($desc['desc_locales'][$locale]) ? "title=\"".$desc['desc_locales'][$locale]."\"" :
                  (isset($desc['desc']) ? "title=\"".$desc['desc']."\"" : (
                    !empty($desc['locale_desc_tag']) ? "title=\"".locale_string($desc['locale_desc_tag'])."\"" : ""
                  ))
                ).
                ">".
                (isset($desc['names_locales'][$locale]) ? $desc['names_locales'][$locale] :
                  (isset($desc['name']) ? $desc['name'] : locale_string("cat_".$tag))
                ).
                "</a>";

    if ($tabSelected === null && isset($cat) && $tag == $cat) {
      $tabSelected = count($tabsNames);
    }
  }

  if(isset($cats[$hidden_cat]))
    $tmp .= "<a class=\"category".(isset($cat) && "all" == $cat ? " active" : "").
                "\" href=\"?cat=all".(empty($linkvars) ? "" : "&".$linkvars).
                "\">".locale_string("all_reports")."</a>";

  $tabsContent[] = $tmp;
  $tabsNames[] = locale_string("all_tags");
  $tabsTags[] = "tagslist";
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

  $tabsContent[] = $tmp;
  $tabsNames[] = locale_string("search");
  $tabsTags[] = "searchform";

  if (!empty($searchstring)) $tabSelected = count($tabsNames)-1;
//

if (!empty($tabsNames)) {
  $modules .= "<div class=\"tabs-block\">";

  if ($tabSelected === null) $tabSelected = 0;

  foreach ($tabsNames as $i => $name) {
    $modules .= "<input type=\"radio\" class=\"tab\" id=\"tabs-block-tab".($i+1)."\" name=\"css-tabs\" ".($i == $tabSelected ? "checked" : "").">";
  }

  $modules .= "<ul class=\"tabs-container\">";
  foreach ($tabsNames as $i => $name) {
    $modules .= "<li class=\"tabs-container-tab\"><label for=\"tabs-block-tab".($i+1)."\">$name</label></li>";
  }
  $modules .= "</ul>";

  foreach ($tabsContent as $i => $content) {
    $tags = $tabsTags[$i] ?? "";
    $modules .= "<div class=\"tab-content $tags\">$content</div>";
  }

  $modules .= "</div>";
}

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
  if (!empty($ads_block_main)) $modules .= "<div class=\"ads-block-main\">$ads_block_main</div>";

  if(!isset($cat) && $index_list < sizeof($reps))
    $modules .= "<div class=\"content-header\">".locale_string("noleague_cap")."</div>";
  $modules .= "</div>";

  $modules .= "<div class=\"table-header-info wide compact\" data-table=\"$table_id\">".
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
    if ($report['short_fname'][0] == '!') continue;
    if(!(isset($cat) || isset($searchstring)) && $index_list < sizeof($reps)) {
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

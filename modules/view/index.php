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
    else $head_name = $cats[$cat]['name'] ?? locale_string(isset($cats[$cat]['locale_name_tag']) ? $cats[$cat]['locale_name_tag'] : 'cat_'.$cat);

    if(isset($cats[$cat]['desc_locales'][$locale])) $head_desc = $cats[$cat]['desc_locales'][$locale];
    else $head_desc = isset($cats[$cat]['locale_desc_tag']) ? locale_string($cats[$cat]['locale_desc_tag']) : ($cats[$cat]['desc'] ?? null);

    if (isset($cats[$cat]['lid'])) $social_lid = $cats[$cat]['lid'];
  } else if(!isset($cat) || $cat == "main") {
    // $head_name = $instance_name;
    // $head_desc = $instance_desc;
    $head_name = $cat == "main" ? locale_string('main_reports') : $instance_name;
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
  // } else if ($cat == "recent") {
  //   $head_name = locale_string("recent_reports");
  //   // $recent_last_limit;
  //   $reps = $cache["reps"];
  //   uasort($reps, function($a, $b) {
  //     $lu = ($b['last_update'] ?? 0) <=> ($a['last_update'] ?? 0);

  //     if ($lu) return $lu;

  //     return ($b['matches'] ?? 0) <=> ($a['matches'] ?? 0);
  //   });
  //   if (!($recent_last_limit ?? false)) {
  //     $recent_last_limit = time() - 14*24*3600;
  //   }

  //   $limit = null;
  //   $i = 0;
  //   foreach($reps as $k => $v) {
  //     if (($v['last_update'] ?? 0) < $recent_last_limit) {
  //       $limit = $i;
  //       break;
  //     }
  //     $i++;
  //   }
  //   $r = [];
  //   $i = 0;
  //   foreach ($reps as $k => $v) {
  //     if ($i >= $limit) break;
  //     $r[$k] = $v;
  //     $i++;
  //   }
  //   $reps = $r;
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

  // main page as a list - present for search results, category or absent of any categories
  if ($page == 'meow') {
    if(!isset($cat) && $index_list < sizeof($reps))
      $modules .= "<div class=\"content-header\">".locale_string("noleague_cap")."</div>";
    $modules .= "</div>";

    $modules .= "<div class=\"table-header-info wide compact spaced\">".
      "<span class=\"table-header-info-name\">".locale_string('reports_count').": ".count($reps)."</span>".
      ($cat != "main" ? "<a class=\"right\" href=\"?cat=$cat&latest".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string('latest_permalink')."</a>" : "").
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

    // if (!isset($cat) || $cat !== "recent") {
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

  // categories list
  if ($page == 'cats') {
    $head_name = locale_string("categories_list");
    $head_desc = locale_string("categories_list_desc");
    
    $cats_c = [];

    $groups = [
      '_' => []
    ];

    foreach ($cats as $tag => $val) {
      if (isset($hidden_cat) && $tag == $hidden_cat) continue;
      if ($val['hidden'] ?? false) continue;
      
      $reps = populate_reps($cache["reps"], $val['filters'], $val['exclude_hidden'] ?? true);

      if (!empty($val['groups'])) {
        foreach($val['groups'] as $gr) {
          $groups[$gr][] = $tag;
        }
      } else {
        $groups['_'][] = $tag;
      }

      $reps_f = array_filter(
        $reps, function($a) {
          return $a['first_match']['date'];
        }
      );
  
      $cats_c[$tag] = [
        'tag' => $tag,
        'cat' => true,
        'id' => $val['lid'] ?? null,
        'name' => ($val['names_locales'] ?? [])[$locale] ?? $val['name'] ?? locale_string(isset($val['locale_name_tag']) ? $val['locale_name_tag'] : 'cat_'.$tag),
        'desc' => ($val['desc_locales'] ?? [])[$locale] ?? $val['desc'] ?? null,
        'matches' => count($reps),
        'patches' => [], // TODO:,
        'regions' => null, // TODO:,
        'first_match' => [
          'date' => !empty($reps_f) ? min(array_map(function($a) { return $a['first_match']['date']; }, $reps_f)) : 0,
          'mid' => !empty($reps_f) ? min(array_map(function($a) { return $a['first_match']['mid']; }, $reps_f)) : 0,
        ],
        'last_match' => [
          'date' => !empty($reps) ? max(array_map(function($a) { return $a['last_match']['date']; }, $reps)) : 0,
          'mid' => !empty($reps) ? max(array_map(function($a) { return $a['last_match']['mid']; }, $reps)) : 0,
        ],
      ];
      $cats_c[$tag]['days'] = ceil(($cats_c[$tag]['last_match']['date'] - $cats_c[$tag]['first_match']['date']) / (3600*24));

      foreach ($reps as $rep) {
        foreach ($rep['patches'] as $pid => $ms) {
          $cats_c[$tag]['patches'][$pid] = ($cats_c[$tag]['patches'][$pid] ?? 0) + $ms;
        }
      }
  
      if (isset($val['names_locales'])) {
        foreach ($val['names_locales'] as $loc => $str) {
          if (!isset($cats_c[$tag]['localized'][$loc])) $cats_c[$tag]['localized'][$loc] = [];
          $cats_c[$tag]['localized'][$loc]['name'] = $str;
        }
      }
      if (isset($val['desc_locales'])) {
        foreach ($val['desc_locales'] as $loc => $str) {
          if (!isset($cats_c[$tag]['localized'][$loc])) $cats_c[$tag]['localized'][$loc] = [];
          $cats_c[$tag]['localized'][$loc]['desc'] = $str;
        }
      }
  
      if ($val['locale_desc_tag'] ?? false) {
        $cats_c[$tag]['desc'] = locale_string($val['locale_desc_tag']);
      }

      if (!empty($__lid_fallbacks)) {
        foreach ($__lid_fallbacks as $preg => $lid) {
          if (preg_match($preg, $cats_c[$tag]['tag'])) {
            $cats_c[$tag]['id'] = $lid;
            break;
          }
        }
      }
    }

    if (isset($_GET['list'])) {
      $modules .= "<div class=\"table-header-info wide compact\">".
        "<span class=\"table-header-info-name\">".locale_string('cats_count').": ".count($cats_c)."</span>".
      "</div>";

      if(isset($cat) || $index_list >= sizeof($cats_c)) {
        $modules .= "<input name=\"filter\" class=\"search-filter wide\" data-table-filter-id=\"leagues-list\" placeholder=\"".locale_string('filter_placeholder')."\" />";
      }

      $modules .= "<table id=\"leagues-list\" class=\"list wide ".(isset($cat) ? "sortable" : "")."\"><thead><tr class=\"overhead\">".
        "<th width=\"50px\"></th>".
        "<th width=\"100px\"></th>".
        "<th>".locale_string("cat_name")."</th>".
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
      
      foreach($cats_c as $report) {
        if (empty($report)) continue;

        $iscat = $report['cat'] ?? false;
        
        if (!$iscat) {
          if ($report['short_fname'][0] == '!') continue;
          if(!(isset($cat) || isset($searchstring)) && $index_list < sizeof($reps)) {
            if(!$index_list) return $res;
            $index_list--;
          }
        }
        
        $modules .= report_list_element($report);
      }

      $modules .= "</table>";
    } else {
      uksort($groups, function($a, $b) use (&$cats_groups_priority) {
        if ($a == '_') return 1;
        if ($b == '_') return -1;

        if (!empty($cats_groups_priority) && (isset($cats_groups_priority[$a]) || isset($cats_groups_priority[$b]))) {
          $r = ($cats_groups_priority[$a] ?? 999) <=> ($cats_groups_priority[$b] ?? 999);
          if ($r) return $r;
        }

        return $a <=> $b;
      });

      foreach ($groups as $gt => $gl) {
        if (empty($gl)) continue;

        $modules .= "<div class=\"compact-section-header wide compact primary\">".
          "<span class=\"group-name\">".
            (isset($cats_groups_icons) && isset($cats_groups_icons[$gt]) ?
              "<img class=\"inline-icon\" src=\"".str_replace('%LID%', $cats_groups_icons[$gt], $league_logo_provider)."\" alt=\"$cats_groups_icons[$gt]\" />" : 
              ""
            ).
            (isset($cats_groups_names) && isset($cats_groups_names[$gt]) ?
              $cats_groups_names[$gt] : 
              locale_string($gt == '_' ? 'untagged_cats' : $gt)
            ).
          "</span>".
        "</div>";

        if (empty($gl)) {
          // $modules .= "<div class=\"content-text left wide compact\">".locale_string('noreports')."</div>";
        } else {
          $modules .= "<div class=\"report-cards-container\">";
  
          foreach($gl as $tag) {
            $report = $cats_c[$tag] ?? null;
            
            if (empty($report)) continue;
    
            $iscat = $report['cat'] ?? false;
            
            if (!$iscat) {
              if ($report['short_fname'][0] == '!') continue;
              if(!(isset($cat) || isset($searchstring)) && $index_list < sizeof($reps)) {
                if(!$index_list) return $res;
                $index_list--;
              }
            }
            
            $modules .= report_card_element($report, true);
          }
  
          $modules .= "</div>";
        }
      }
    }
  }

  // cards index page
  if ($page == 'index') {
    foreach ($__featured_cats as $tag => $section) {
      if ($section['type'] == 0) { // category alias
        $limit = $section['limit'] ?? null;

        $cat_tag = $section['value'] ?? $section['name'] ?? $tag;

        $cat = $cats[$section['value']] ?? $cats[$cat_tag] ?? [];

        $name = ($cat['names_locales'] ?? [])[$locale] ?? 
          $cat['name'] ?? 
          locale_string($section['loc_name'] ?? $section['name'] ?? "cat_".$tag);

        if ($cat_tag == "main") {
          $reps = $cache['reps'];
        } else if (empty($cat)) {
          $reps = populate_reps($cache['reps'], [], $cat['exclude_hidden'] ?? true);
        } else {
          $reps = populate_reps($cache['reps'], $cat['filters'], $cat['exclude_hidden'] ?? true);
        }

        if (isset($cat['orderby']) || isset($section['orderby'])) {
          // not my finest creation
          $orderby = $section['orderby'] ?? $cat['orderby'];
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
            return $b['last_match']['date'] <=> $a['last_match']['date'];
          });
        }

        if (isset($cat['id']) || isset($cat['icon'])) {
          $section['icon_lid'] = $section['icon_lid'] ?? $cat['icon'] ?? $cat['icon'];
        }
      } else if ($section['type'] == 1) { // array
        $name = $section['name'] ?? locale_string($tag);

        $reps = [];
        foreach ($section['value'] as $el) {
          if ($el[1]) { // category
            if (!isset($cats[$el[0]])) continue;

            $val = $cats[$el[0]];

            $reps_c = populate_reps($cache["reps"], $val['filters'], $val['exclude_hidden'] ?? true);
            
            $cat = [
              'tag' => $el[0],
              'cat' => true,
              'id' => $val['lid'] ?? null,
              'name' => ($val['names_locales'] ?? [])[$locale] ?? $val['name'] ?? locale_string("cat_".$tag),
              'desc' => ($val['desc_locales'] ?? [])[$locale] ?? $val['desc'] ?? null,
              'matches' => count($reps_c),
              'patches' => [],
              'regions' => null, // TODO:,
              'first_match' => [
                'date' => !empty($reps_c) ? min(array_map(function($a) { return $a['first_match']['date']; }, array_filter(
                  $reps_c, function($a) {
                    return $a['first_match']['date'];
                  }
                ))) : 0,
                'mid' => !empty($reps_c) ? min(array_map(function($a) { return $a['first_match']['mid']; }, array_filter(
                  $reps_c, function($a) {
                    return $a['first_match']['mid'];
                  }
                ))) : 0,
              ],
              'last_match' => [
                'date' => !empty($reps_c) ? max(array_map(function($a) { return $a['last_match']['date']; }, $reps_c)) : 0,
                'mid' => !empty($reps_c) ? max(array_map(function($a) { return $a['last_match']['mid']; }, $reps_c)) : 0,
              ],
            ];
            $cat['days'] = ceil(($cat['last_match']['date'] - $cat['first_match']['date']) / (3600*24));

            foreach ($reps_c as $rep) {
              foreach ($rep['patches'] as $pid => $ms) {
                $cat['patches'][$pid] = ($cat['patches'][$pid] ?? 0) + $ms;
              }
            }
        
            if (isset($val['names_locales'])) {
              foreach ($val['names_locales'] as $loc => $str) {
                if (!isset($cat['localized'][$loc])) $cat['localized'][$loc] = [];
                $cat['localized'][$loc]['name'] = $str;
              }
            }
            if (isset($val['desc_locales'])) {
              foreach ($val['desc_locales'] as $loc => $str) {
                if (!isset($cat['localized'][$loc])) $cat['localized'][$loc] = [];
                $cat['localized'][$loc]['desc'] = $str;
              }
            }
        
            if ($val['locale_desc_tag'] ?? false) {
              $cat['desc'] = locale_string($val['locale_desc_tag']);
            }

            if (!empty($__lid_fallbacks)) {
              foreach ($__lid_fallbacks as $preg => $lid) {
                if (preg_match($preg, $cat['tag'])) {
                  $cat['id'] = $lid;
                  break;
                }
              }
            }

            $reps[] = $cat;
          } else {
            if (!isset($cache['reps'][$el[0]])) continue;

            $reps[] = $cache['reps'][$el[0]];
          }
        }
      }

      $icon = $section['icon_lid'] ?? null;

      if ($icon) {
        $name = ($icon ? 
          "<img class=\"inline-icon\" src=\"".str_replace('%LID%', $icon, $league_logo_provider)."\" alt=\"$icon\" />" : 
          ""
        ).$name;
      }

      $modules .= "<div class=\"compact-section-header wide compact primary\">".
        ($section['type'] == 0 ?
          "<a class=\"group-name\" href=\"?cat=".($section['linkto'] ?? $cat_tag).(empty($linkvars) ? "" : "&".$linkvars)."\">".$name."</a>" :
          "<span class=\"group-name\">".$name."</span>"
        ).
      "</div>";

      if (empty($reps)) {
        $modules .= "<div class=\"content-text left wide compact\">".locale_string('noreports')."</div>";
      } else {
        $modules .= "<div class=\"report-cards-container\">";

        $cnt = count($reps);
        $section_limit = isset($section['limit']) && ($cnt > $section['limit']);
        if ($section_limit) {
          $reps = array_slice($reps, 0, $section['limit']);
        }
        
        foreach($reps as $report) {
          if (empty($report)) continue;
  
          $iscat = $report['cat'] ?? false;
          
          if (!$iscat) {
            if ($report['short_fname'][0] == '!') continue;
          }
          
          $modules .= report_card_element($report, true, true);
        }

        if ($section_limit && ($section['see_more_block'] ?? true) && $section['type'] == 0) {
          $modules .= "<div class=\"card see-more\"><div class=\"content\">".
            "<a class=\"header card-name\" href=\"?cat=".($section['linkto'] ?? $cat_tag).(empty($linkvars) ? "" : "&".$linkvars)."\">".
              locale_string("see_more_cats").
            "</a>".
          "</div></div>";
        }

        $modules .= "</div>";
      }
    }
  }
}

<?php

function join_selectors($modules, $level, $parent="") {
  if (!is_array($modules)) return $modules;

  global $lrg_use_get;
  global $lrg_get_depth;
  global $level_codes;
  global $mod, $_rawmod;
  global $strings;
  global $leaguetag;
  global $max_tabs;
  global $linkvars;
  global $_earlypreview_banlist, $_earlypreview, $vw_section_markers, $_earlypreview_teaser;
  global $carryon;
  global $locale;

  $out = "";
  $first = true;
  $unset_selector = false;
  $iconalias = true;
  
  if (empty($_earlypreview_banlist)) $_earlypreview_banlist = [];
  if (empty($vw_section_markers)) $vw_section_markers = [];

  if(empty($parent)) {
    $selectors = explode("-", $mod);
    if(!isset($modules[$selectors[0]])) $unset_selector = true;
  } else {
    $selectors = explode("-", substr($mod, strlen($parent)+1));
    if(!isset($modules[$selectors[0]])) $unset_selector = true;
  }
  # reusing assets Kappa
  $selectors = array();
  $selectors_num = sizeof($modules);

  if (!isset($level_codes[$level])) {
    $level_codes[$level] = end($level_codes);
  }

  foreach($modules as $modtag => $module) {
    $mod_type = 0;
    $mod_islink = false;
    $section_marker = '';
    $disabled = false;
    $might_be_updated = false;

    if ($modtag[0] == "&") {
      $mod_islink = true;
      $modtag = substr($modtag, 1);
      $mn = $module['link'];
      $mod_type = $module['type'] ?? 0;
      $startline_check_res = false;
    } else {
      $mn = (empty($parent) ? "" : $parent."-" ).$modtag;
      $startline_check_res = stripos($mod, $mn) === 0 && (
          (strlen($mod) == strlen($mn)) ||
          (strlen($mod) > strlen($mn) && $mod[strlen($mn)] == '-')
        );
      if (is_array($module)) $mod_type = 1;
    }

    if (!$_earlypreview) {
      foreach ($_earlypreview_banlist as $section) {
        if ((is_string($section) && $section[0] == '/' && preg_match($section, $mn)) || ($section == $mn)) {
          continue 2;
        }
      }
    }

    foreach ($vw_section_markers as $marker => $sections) {
      foreach ($sections as $section) {
        if (is_string($section) && $section[0] == '/') {
          if (preg_match($section, $mn)) {
            $section_marker = "<span class=\"section-marker section-marker-".$marker."\">".locale_string("section_marker_".$marker)."</span>";
          } else if (is_array($module)) {
            foreach ($module as $child => $child_module) {
              if (preg_match($section, $mn.'-'.$child)) {
                $section_marker = "<span class=\"section-marker section-marker-updated\">".locale_string("section_marker_updated")."</span>";
                break;
              }
            }
          }
        } else {
          if ($section == $mn) {
            $section_marker = "<span class=\"section-marker section-marker-".$marker."\">".locale_string("section_marker_".$marker)."</span>";
          } else if (strpos($section, $mn.'-') === 0 && is_array($module) && !(in_array($section, $_earlypreview_teaser) && !$_earlypreview)) {
            $child = substr($section, strlen($mn)+1);
            if (isset($module[$child])) {
              $section_marker = "<span class=\"section-marker section-marker-updated\">".locale_string("section_marker_updated")."</span>";
              break;
            }
          }
        }
        if (!empty($section_marker)) break;
      }
    }

    if (!$_earlypreview) {
      foreach ($_earlypreview_teaser as $section) {
        if ((is_string($section) && $section[0] == '/' && preg_match($section, $mn)) || ($section == $mn)) {
          $disabled = true;
          $section_marker = "<span class=\"section-marker section-marker-upcoming\">".locale_string("section_marker_upcoming")."</span>";
          break;
        }
      }
    }

    $carryon_change = "";

    foreach ($carryon as $match => $repl) {
      if (preg_match($match, $mn)) {
        $repcnt = 0;
        $carryon_change = preg_replace($repl, "", $_rawmod, -1, $repcnt);
        if (!$repcnt) $carryon_change = "";
        break;
      }
    }


    $modname = locale_string($modtag);
    $child_indicator = false;

    if($mod_type) {
      if (strpos($parent, "profiles") == strlen($parent)-8) {
        $modname .= "";
        // FIXME: Remove this block later, using class-based modules with a parameter
        //       either show child indicator or not
      } else if ($selectors_num < $max_tabs) {
        $child_indicator = true;
        // $modname .= ""; //" &#9776;";
      } else {
        $child_indicator = true;
        $modname .= " ...";
      }
    }

    if ($iconalias) {
      $data_aliases = null;
      $data_icon = null;

      if (strpos($modtag, 'heroid') !== false) {
        global $meta;
        $mods = explode('-', $modtag);
        foreach ($mods as $m) {
          if (strpos($m, 'heroid') !== false) {
            $hid = (int)str_replace('heroid', '', $m);
            break;
          }
        }
        if (isset($hid)) {
          $data_aliases = hero_aliases($hid);
          $data_icon = hero_icon_link($hid);
        }
      }

      if ((strpos($modtag, 'team') !== false || strpos($modtag, 'optid') !== false) && $modtag != "teams") {
        global $meta;
        $mods = array_reverse(explode('-', $mn));
        foreach ($mods as $m) {
          if (strpos($m, 'optid') == 0) {
            $tid = (int)str_replace('optid', '', $m);
            if ($tid) break;
          }
          if (strpos($m, 'team') == 0) {
            $tid = (int)str_replace('team', '', $m);
            if ($tid) break;
          }
        }
        if (isset($tid)) {
          global $team_logo_provider;
          $data_aliases = team_tag($tid);
          $data_icon = str_replace('%TEAM%', $tid, $team_logo_provider);
        }
      }

      if (strpos($modtag, 'itemid') !== false) {
        global $meta;
        $mods = explode('-', $mn);
        foreach ($mods as $m) {
          if (strpos($m, 'itemid') === 0) {
            $iid = (int)str_replace('itemid', '', $m);
            break;
          }
        }
        if (isset($iid)) {
          $data_aliases = item_tag($iid)." $m";
          if (is_special_locale($locale)) {
            $data_aliases = item_name($iid, true)." ".$data_aliases;
          }
          $data_icon = item_icon_link($iid);
        }
      }
    }

    if ($selectors_num < $max_tabs) {
      $icon = $data_icon ? 
        "<img class=\"selector-icon\" alt=\"$modname image\" src=\"$data_icon\" / > " : 
        "";

      if($lrg_use_get && $lrg_get_depth > $level) {
        if ( $startline_check_res )
          $selectors[] = "<span class=\"selector active".($child_indicator ? " has-children" : "")."\">".
            "<a href=\"?league=$leaguetag&mod=$mn".$carryon_change.(empty($linkvars) ? "" : "&".$linkvars)."\">".$icon.$modname.$section_marker."</a>".
          "</span>";
        else
          $selectors[] = "<span class=\"selector".
            ($unset_selector ? " active" : "").
            ($child_indicator ? " has-children" : "").
            "\"><a href=\"".($disabled ? "#" : "?league=".$leaguetag."&mod=".$mn.$carryon_change.
            (empty($linkvars) ? "" : "&".$linkvars)).
          "\">".$icon.$modname.$section_marker."</a></span>";
      } else {
        $selectors[] = "<span class=\"mod-".$level_codes[$level][1]."-selector selector".
                            ($first ? " active" : "")."\" ".
                            ($disabled ? "#" : "onclick=\"switchTab(event, 'module-".$mn.$carryon_change."', 'mod-".$level_codes[$level][1]."');\">").
                            $icon.locale_string($modname).$section_marker."</span>";
      }
    } else {
      if($lrg_use_get && $lrg_get_depth > $level) {

        if (stripos($mod, $mn) === 0 && (
              (strlen($mod) == strlen($mn)) ||
              (strlen($mod) > strlen($mn) && $mod[strlen($mn)] == '-')
            ) )
          $selectors[] = "<option selected=\"selected\" value=\"".($disabled ? "#" : "?league=".$leaguetag."&mod=".$mn.$carryon_change."&".
          (empty($linkvars) ? "" : "&".$linkvars)).
          "\" ".
          (isset($data_aliases) ? 'data-aliases="'.$data_aliases.'" ' : '').
          (isset($data_icon) ? 'data-icon="'.$data_icon.'" ' : '').
          ">".$modname.$section_marker."</option>";
        else
          $selectors[] = "<option".($unset_selector ? "selected=\"selected\"" : "")." value=\"".($disabled ? "#" : "?league=".$leaguetag."&mod=".$mn.$carryon_change.(empty($linkvars) ? "" : "&".$linkvars)).
            "\" ".
            (isset($data_aliases) ? 'data-aliases="'.$data_aliases.'" ' : '').
            (isset($data_icon) ? 'data-icon="'.$data_icon.'" ' : '').
          ">".$modname.$section_marker."</option>";
      } else {
        $selectors[] = "<option value=\"".($disabled ? "#" : "module-".$mn.$carryon_change).
          "\" ".
          (isset($data_aliases) ? 'data-aliases="'.$data_aliases.'" ' : '').
          (isset($data_icon) ? 'data-icon="'.$data_icon.'" ' : '').
        ">".$modname.$section_marker."</option>";
      }
    }
    if(!$mod_islink && ($startline_check_res || !$lrg_use_get || $lrg_get_depth < $level+1 || $unset_selector)) {
      if (!$disabled) {
        if(is_array($module)) {
          $module = join_selectors($module, $level+1, $mn);
        }
      } else {
        $module = "<div class=\"content-header\">".locale_string("no_access_error_title")."</div>".
          "<div class=\"content-text\">".locale_string("no_access_error_desc").".</div>".
        "</div>";
      }
      $out .= "<div id=\"module-".$mn.$carryon_change."\" class=\"selector-module mod-".$level_codes[$level][1].($first ? " active" : "")."\">".$module."</div>";
      $first = false;
      $unset_selector = false;
    }
  }

  if ($selectors_num < $max_tabs)
    return "<div class=\"selector-modules".(empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])."\">".implode(" | ", $selectors)."</div>".$out;
  else
  if($lrg_use_get && $lrg_get_depth > $level)
    return "<div class=\"selector-modules".(empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])."\">".
        "<div class=\"custom-selector\">".
        "<select onchange=\"select_modules_link(this);\" class=\"select-selectors select-selectors".(empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])." \"".
        "data-placeholder=\"".locale_string('filter_placeholder')."\">".
        implode("", $selectors)."</select></div></div>".$out;
    else
    return "<div class=\" selector-modules".(empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])."\">".
        "<div class=\"custom-selector\">".
        "<select onchange=\"switchTab(event, this.value, 'mod-".$level_codes[$level][1]."');\" class=\"select-selectors select-selectors".
        (empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])."\" ".
        "data-placeholder=\"".locale_string('filter_placeholder')."\">".
        implode("", $selectors)."</select></div></div>".$out;
}


<?php

function join_selectors($modules, $level, $parent="") {
  if (!is_array($modules)) return $modules;

  global $lrg_use_get;
  global $lrg_get_depth;
  global $level_codes;
  global $mod;
  global $strings;
  global $leaguetag;
  global $max_tabs;
  global $linkvars;
  global $_earlypreview_banlist, $_earlypreview;

  $out = "";
  $first = true;
  $unset_selector = false;

  if (empty($_earlypreview_banlist)) $_earlypreview_banlist = [];

  if(empty($parent)) {
    $selectors = explode("-", $mod);
    if(!isset($modules[$selectors[0]])) $unset_selector = true;
  } else {
    $selectors = explode("-", str_replace($parent."-", "", $mod));
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
    
    if (!$_earlypreview && in_array($mn, $_earlypreview_banlist)) {
      continue;
    }

    $modname = locale_string($modtag);
    if($mod_type) {
      if (strpos($parent, "profiles") == strlen($parent)-8)
        $modname .= "";
        // FIXME: Remove this block later, using class-based modules with a parameter
        //       either show child indicator or not
      else if ($selectors_num < $max_tabs)
        $modname .= " &#9776;";
      else
        $modname .= " ...";
    }

    if ($selectors_num < $max_tabs) {
      if($lrg_use_get && $lrg_get_depth > $level) {
        if ( $startline_check_res )
          $selectors[] = "<span class=\"selector active\">".$modname."</span>";
        else
          $selectors[] = "<span class=\"selector".($unset_selector ? " active" : "").
                            "\"><a href=\"?league=".$leaguetag."&mod=".$mn.
                            (empty($linkvars) ? "" : "&".$linkvars).
                            "\">".$modname."</a></span>";
      } else {
        $selectors[] = "<span class=\"mod-".$level_codes[$level][1]."-selector selector".
                            ($first ? " active" : "")."\" onclick=\"switchTab(event, 'module-".$mn."', 'mod-".$level_codes[$level][1]."');\">".
                            locale_string($modname)."</span>";
      }
    } else {
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
          $data_aliases = ($meta['heroes'][ $hid ]['alt'] ?? "") . " " . ($meta['heroes'][ $hid ]['aliases'] ?? "");
          $data_icon = hero_icon_link($hid);
        }
      }

      if (strpos($modtag, 'team') !== false || strpos($modtag, 'optid') !== false) {
        global $meta;
        $mods = explode('-', $mn);
        foreach ($mods as $m) {
          if (strpos($m, 'team') == 0) {
            $tid = (int)str_replace('team', '', $m);
            if ($tid) break;
          }
          if (strpos($m, 'optid') == 0) {
            $tid = (int)str_replace('optid', '', $m);
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
          $data_icon = item_icon_link($iid);
        }
      }

      if($lrg_use_get && $lrg_get_depth > $level) {

        if (stripos($mod, $mn) === 0 && (
              (strlen($mod) == strlen($mn)) ||
              (strlen($mod) > strlen($mn) && $mod[strlen($mn)] == '-')
            ) )
          $selectors[] = "<option selected=\"selected\" value=\"?league=".$leaguetag."&mod=".$mn."&".
          (empty($linkvars) ? "" : "&".$linkvars)
          ."\" ".
          (isset($data_aliases) ? 'data-aliases="'.$data_aliases.'" ' : '').
          (isset($data_icon) ? 'data-icon="'.$data_icon.'" ' : '').
          ">".$modname."</option>";
        else
          $selectors[] = "<option".($unset_selector ? "selected=\"selected\"" : "")." value=\"?league=".$leaguetag."&mod=".$mn.(empty($linkvars) ? "" : "&".$linkvars)
            ."\" ".
            (isset($data_aliases) ? 'data-aliases="'.$data_aliases.'" ' : '').
            (isset($data_icon) ? 'data-icon="'.$data_icon.'" ' : '').
          ">".$modname."</option>";
      } else {
        $selectors[] = "<option value=\"module-".$mn."\" ".
          (isset($data_aliases) ? 'data-aliases="'.$data_aliases.'" ' : '').
          (isset($data_icon) ? 'data-icon="'.$data_icon.'" ' : '').
        ">".$modname."</option>";
      }
    }
    if(!$mod_islink && ($startline_check_res || !$lrg_use_get || $lrg_get_depth < $level+1 || $unset_selector)) {
      if(is_array($module)) {
        $module = join_selectors($module, $level+1, $mn);
      }
      $out .= "<div id=\"module-".$mn."\" class=\"selector-module mod-".$level_codes[$level][1].($first ? " active" : "")."\">".$module."</div>";
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


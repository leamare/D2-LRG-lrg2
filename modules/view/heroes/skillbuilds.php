<?php

include_once($root."/modules/view/generators/skill_component.php");

if (!empty($report['skill_builds']['matches'])) {
  $modules['heroes']['skillbuilds'] = [];
}

function skillbuild_render_skills_section($data) {
  $res = "<div class=\"content-header\">".locale_string("skillbuild_skills")."</div>";

  $res .= "<table class=\"list sortable skillbuild-priority-table\"><caption>".locale_string("skillbuild_priority")."</caption><thead><tr>".
    "<th class=\"sorter-false\">".locale_string("skillbuild_priority")."</th>".
    "<th>".locale_string("ratio")."</th>".
    "<th>".locale_string("matches")."</th>".
    "<th>".locale_string("winrate")."</th>".
    "<th>".locale_string("rank")."</th>".
    "<th>".locale_string("skillbuild_max_level")."</th>".
  "</tr></thead><tbody>";
  foreach ($data['priority'] as $row) {
    $res .= "<tr>".
      "<td>".skillbuild_priority_component($row['priority'], $data['ultimate'], $row['skill_stats'], 64)."</td>".
      "<td>".number_format($row['ratio'] * 100, 2)."%</td>".
      "<td>".$row['matches']."</td>".
      "<td>".number_format($row['winrate'] * 100, 2)."%</td>".
      "<td>".number_format($row['rank'], 2)."</td>".
      "<td>".$row['max_level']."</td>".
    "</tr>";
  }
  $res .= "</tbody></table>";

  $res .= "<div class=\"skillbuild-columns\">";

  $res .= "<div class=\"skillbuild-column\"><table class=\"list sortable\"><caption>".locale_string("skillbuild_skills")."</caption><thead><tr>".
    "<th class=\"sorter-false\" width=\"1%\"></th>".
    "<th class=\"sorter-false\">".locale_string("skillbuild_skills")."</th>".
    "<th>".locale_string("skillbuild_first_point_avg")."</th>".
    "<th>".locale_string("skillbuild_maxed_at_avg")."</th>".
  "</tr></thead><tbody>";
  foreach ($data['skills'] as $s) {
    $res .= "<tr>".
      "<td>".skillbuild_component($s['skill'])."</td>".
      "<td>".spell_name($s['skill'])."</td>".
      "<td>".($s['first_point_avg'] !== null ? number_format($s['first_point_avg'], 2) : '-')."</td>".
      "<td>".($s['maxed_at_avg'] !== null ? number_format($s['maxed_at_avg'], 2) : '-')."</td>".
    "</tr>";
  }
  $res .= "</tbody></table></div>";

  $attr = $data['attributes'];
  $res .= "<div class=\"skillbuild-column\"><table class=\"list\"><caption>".locale_string("skillbuild_attributes")."</caption><tbody>";
  if (!empty($attr) && $attr['matches']) {
    $res .= "<tr><td>".locale_string("skillbuild_attr_taken_rate")."</td><td>".number_format($attr['taken_rate'] * 100, 2)."%</td></tr>".
      "<tr><td>".locale_string("skillbuild_attr_avg_count")."</td><td>".number_format($attr['avg_count'], 3)."</td></tr>".
      ($attr['first_point_avg'] !== null ?
        "<tr><td>".locale_string("skillbuild_attr_first_point_avg")."</td><td>".number_format($attr['first_point_avg'], 2)."</td></tr>" : ""
      ).
      ($attr['taken_winrate'] !== null ?
        "<tr><td>".locale_string("skillbuild_attr_taken_winrate")."</td><td>".number_format($attr['taken_winrate'] * 100, 2)."% (".$attr['taken_matches']." ".locale_string("matches").")</td></tr>" : ""
      );
  } else {
    $res .= "<tr><td>".locale_string("stats_no_elements")."</td></tr>";
  }
  $res .= "</tbody></table></div>";

  $res .= "</div>";

  return $res;
}

function skillbuild_render_talents_section($hero, $data) {
  if (empty($data['tiers'])) return "";

  $res = "<div class=\"content-header\">".locale_string("skillbuild_talents")."</div>";
  $res .= skilltalent_tree_component($hero, $data['tiers'], false);
  return $res;
}

function skillbuild_render_hero_role($hero, $rid) {
  $data = skillbuild_collect($hero, $rid);

  if (empty($data['priority'])) {
    return "<div class=\"content-text\">".locale_string("skillbuilds_empty")."</div>";
  }

  $res = "<div class=\"content-header\">".locale_string("overview")."</div>";
  $res .= skillbuild_render_overview($hero, $rid, $data);
  $res .= skillbuild_render_explainer();
  $res .= skillbuild_render_skills_section($data);
  $res .= skillbuild_render_talents_section($hero, $data);

  return $res;
}

function rg_view_generate_heroes_skillbuilds() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $roleicon_logo_provider, $leaguetag, $linkvars;

  if ($mod == $parent."skillbuilds") $unset_module = true;
  $parent_module = $parent."skillbuilds-";

  generate_positions_strings();

  $res = [ 'overview' => "" ];

  $matches0 = sb_unwrap_role($report['skill_builds']['matches'], 0);

  $role_matches = [];
  foreach (range(1, 5) as $rid) {
    $role_matches[$rid] = sb_unwrap_role($report['skill_builds']['matches'], $rid);
  }

  $hnames = $meta['heroes'];
  uasort($hnames, function($a, $b) {
    if ($a['name'] == $b['name']) return 0;
    return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $hero = null;
  $crole = 0;
  $roletag = "total";

  if (check_module($parent_module."overview")) {
    $hero = null;
  }

  foreach ($hnames as $hid => $name) {
    if (!isset($matches0[$hid])) continue;

    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = [];

    if (check_module($parent_module."heroid".$hid)) {
      $hero = $hid;

      if ($mod == $parent_module."heroid".$hid) $unset_module = true;
      $parent_module = $parent_module."heroid".$hid."-";

      $res["heroid".$hid]["total"] = "";
      if (check_module($parent_module."total")) {
        $crole = 0;
        $roletag = "total";
      }

      foreach (range(1, 5) as $rid) {
        if (empty($role_matches[$rid][$hid])) continue;
        $rolekey = "position_".ROLES_IDS_SIMPLE[$rid];
        $res["heroid".$hid][$rolekey] = "";
        if (check_module($parent_module.$rolekey)) {
          $crole = $rid;
          $roletag = $rolekey;
        }
      }
    }
  }

  if ($hero === null) {
    // Same table shape as the items-builds index (modules/view/items/builds.php):
    // hero, role count, one column per role showing the role icon, most-played role.
    if (isset($roleicon_logo_provider)) {
      $roleicons = [
        "0.1" => "hardsupporticon",
        "0.3" => "softsupporticon",
        "1.1" => "safelaneicon",
        "1.2" => "midlaneicon",
        "1.3" => "offlaneicon",
      ];
    }

    $res['overview'] .= "<div class=\"content-header\">".locale_string("skillbuilds_overview_header")."</div>";
    $res['overview'] .= search_filter_component("filterable-heroes-skillbuilds");

    $_presetroles = [ '1.1', '1.2', '1.3', '0.3', '0.1' ];

    $res['overview'] .= "<table id=\"filterable-heroes-skillbuilds\" class=\"list sortable\"><thead>".
      "<tr><th width=\"1%\"></th><th width=\"15%\" class=\"sortInitialOrder-asc\">".locale_string("hero")."</th>".
      "<th width=\"15%\" class=\"separator\">".locale_string("positions_count")."</th>";
    foreach ($_presetroles as $i => $role) {
      $res['overview'] .= "<th class=\"".($i ? "" : "separator ")."centered sorter-image sortInitialOrder-asc\">".locale_string("position_".$role)."</th>";
    }
    $res['overview'] .= "<th class=\"separator\">".locale_string('common_position')."</th>";
    $res['overview'] .= "</tr></thead><tbody>";

    foreach ($hnames as $hid => $hname) {
      if (!isset($matches0[$hid])) continue;

      $roles_present = 0;
      foreach ($_presetroles as $role) {
        $rid = array_search($role, ROLES_IDS_SIMPLE);
        if (!empty($role_matches[$rid][$hid])) $roles_present++;
      }

      $res['overview'] .= "<tr><td>".hero_icon($hid)."</td><td>".
        "<a href=\"?league=$leaguetag&mod=$parent_module"."heroid$hid".(empty($linkvars) ? "" : "&".$linkvars)."\">".hero_name($hid)."</a>".
        "</td><td class=\"separator\">".$roles_present."</td>";

      $max_role = [ null, 0 ];

      foreach ($_presetroles as $i => $role) {
        $rid = array_search($role, ROLES_IDS_SIMPLE);
        $has_role = !empty($role_matches[$rid][$hid]);

        $res['overview'] .= "<td class=\"".($i ? "" : "separator ")."centered\">".
          ($has_role ?
            "<a href=\"?league=$leaguetag&mod=$parent_module"."heroid$hid-position_$role".(empty($linkvars) ? "" : "&".$linkvars)."\">".
            (isset($roleicon_logo_provider) && isset($roleicons[$role]) ?
              "<img src=\"".str_replace("%ROLE%", $roleicons[$role], $roleicon_logo_provider)."\" alt=\"".$roleicons[$role]."\" />" :
              locale_string("position_$role")
            ).
            "</a>" :
            ""
          ).
        "</td>";

        if ($has_role) {
          $m = $role_matches[$rid][$hid]['matches'];
          if ($max_role[1] < $m) $max_role = [ $role, $m ];
        }
      }

      $res['overview'] .= "<td class=\"separator\">".
        ($max_role[0] ? locale_string("position_".$max_role[0]) : '-').
      "</td></tr>";
    }

    $res['overview'] .= "</tbody></table>";
    return $res;
  }

  $res["heroid".$hero][$roletag] = skillbuild_render_hero_role($hero, $crole);

  return $res;
}

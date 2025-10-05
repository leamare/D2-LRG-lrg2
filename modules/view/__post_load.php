<?php
function generate_positions_strings() {
  global $strings;

  for ($i=1; $i>=0; $i--) {
    $strings['en']["position_$i.0"] = ($i ? locale_string("core") : locale_string("support"));
    for ($j=1; $j<6 && $j>0; $j++) {
      //if (!$i) { $j = 0; }
      if(!isset($strings['en']["position_$i.$j"]))
        $strings['en']["position_$i.$j"] = ($i ? locale_string("core") : locale_string("support"))." ".locale_string("lane_$j");

      //if (!$i) { break; }

      register_locale_string(
        locale_string("data_filter_role_mp", [ 'ROLE' => locale_string("position_$i.$j") ]),
        "data_filter_positions_{$i}.{$j}_mp"
      );
    }
  }
}

if ((stripos($mod, "position") !== FALSE || stripos($mod, "role") !== FALSE) && isset($strings['en'])) {
  generate_positions_strings();
}

# legacy name for Radiant Winrate
if (compare_ver($report['ana_version'], array(1,1,1,-4,0)) < 0) {
    $strings[$locale]['rad_wr'] = $strings[$locale]['radiant_wr'];
}

if(isset($report['versions'])) {
  foreach($report['versions'] as $k => $v) {
    $mode = (int)($k/100);
    if(!isset($meta->versions[$mode])) {
        for($i = $mode; $i > 0; $i--) {
            if(isset($meta['versions'][$i])) {
                break;
            }
        }
        $diff = $mode - $i;
        $parent_patch = explode(".", $meta->versions[$i]);
        $parent_patch[1] = (int)$parent_patch[1] + $diff;
        if ($parent_patch[1] < 10)
            $parent_patch[1] = "0".$parent_patch[1];
        $meta->versions[$mode] = implode(".", $parent_patch);

        unset($diff);
        unset($parent_patch);
    }
  }
}

if (isset($report['provider_override'])) {
  if (isset($report['provider_override']['_icons'])) {
    $icons_provider = $report['provider_override']['_icons'];
    unset($report['provider_override']['_icons']);
  }
  if (isset($report['provider_override']['_portraits'])) {
    $portraits_provider = $report['provider_override']['_portraits'];
    unset($report['provider_override']['_portraits']);
  }
  $links_providers = $report['provider_override'];
}

if (isset($report['localized']) && isset($report['localized'][$locale])) {
  $report['orig_name'] = $report['league_name'];
  $report['league_name'] = $report['localized'][$locale]['name'] ?? $report['league_name'];
  $report['league_desc'] = $report['localized'][$locale]['desc'] ?? $report['league_desc'];
}

if ((!empty($report['teams']) || ($report['settings']['series_id_priority'] ?? false)) && !empty($report['matches']) && !empty($report['match_participants_teams'])) {
  include_once("$root/modules/commons/series.php");

  [ $series, $_report_add ] = generate_series_data($report);

  $report['series'] = $series;
  foreach ($_report_add as $k => $v) {
    $report[$k] = $v;
  }
}

if (!empty($report['settings']['heroes_snapshot'])) {
  $meta['heroes'];
  $diff = array_diff(array_keys($meta['heroes']), $report['settings']['heroes_snapshot']);
  foreach ($diff as $hid) {
    if (!isset($report['pickban'][$hid]))
      unset($meta['heroes'][$hid]);
  }
} else if (!empty($report['settings']['heroes_exclude'])) {
  $meta['heroes'];
  foreach ($report['settings']['heroes_exclude'] as $hid) {
    if (!isset($report['pickban'][$hid]))
      unset($meta['heroes'][$hid]);
  }
}

if (isset($report["first_match"]) && isset($report["last_match"]) && compare_ver($report['ana_version'], [ 2,23,0,0,0 ]) < 0) {
  $meta['heroes'];

  $last_match = $report["last_match"]["date"];
  $first_match = $report["first_match"]["date"];

  $is_cm_only = ( isset($report["modes"][2]) || isset($report["modes"][8]) ) && count($report["modes"]) < 3;
  
  foreach ($meta['heroes'] as $hid => $data) {
    if (!isset($data['released'])) continue;

    $line = null;
    $last = null;
    $in_cm = false;

    foreach ($data['released'] as $l) {
      if ($l[0] > $last_match) break;

      $last = $l;

      $in_cm = (bool)($l[2] ?? 0);

      if ($l[0] > $first_match) {
        continue;
      }
      $line = $l;
    }

    if (!$line) {
      $line = $last;
    }

    if ($is_cm_only) {
      if (!$in_cm) {
        unset($meta['heroes'][$hid]);
      }
    } else {
      if (!$line) {
        unset($meta['heroes'][$hid]);
      }
    }
  }
}

if (isset($leaguetag) && !empty($reports_earlypreview_ban)) {
  $match = false;

  if (in_array(strtolower($leaguetag), $reports_earlypreview_ban)) {
    $match = true;
  }
  if ($match && isset($reports_earlypreview_ban_time) && (intval($report['last_match']['date'] ?? 0) - $reports_earlypreview_ban_time < 0)) {
    $match = false;
  }
  if ($match && !empty($reports_earlypreview_ban_exclude) && in_array($leaguetag, $reports_earlypreview_ban_exclude)) {
    $match = false;
  }

  if ($match) {
    if (empty($vw_section_markers)) {
      $vw_section_markers = [
        'upcoming' => [],
      ];
    }

    foreach (($reports_earlypreview_ban_sections['wv']['hidden'] ?? []) as $_bmod) {
      $_earlypreview_banlist[] = $_bmod;
    }
    foreach (($reports_earlypreview_ban_sections['wv']['teaser'] ?? []) as $_bmod) {
      $_earlypreview_teaser[] = $_bmod;
      $vw_section_markers['upcoming'][] = $_bmod;
    }
    foreach (($reports_earlypreview_ban_sections['wa'] ?? []) as $_bmod) {
      $_earlypreview_wa_ban[] = $_bmod;
    }
  }
}

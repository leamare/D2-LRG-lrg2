<?php
function generate_positions_strings() {
  global $strings;

  for ($i=1; $i>=0; $i--) {
    for ($j=1; $j<6 && $j>0; $j++) {
      if (!$i) { $j = 0; }
      if(!isset($strings['en']["position_$i.$j"]))
        $strings['en']["position_$i.$j"] = ($i ? locale_string("core") : locale_string("support"))." ".locale_string("lane_$j");

      if (!$i) { break; }
    }
  }
}

if (stripos($mod, "positions") !== FALSE && isset($strings['en'])) {
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
  $report['league_name'] = $report['localized'][$locale]['name'] ?? $report['league_name'];
  $report['league_desc'] = $report['localized'][$locale]['desc'] ?? $report['league_desc'];
}

if (!empty($report['teams']) && !empty($report['matches']) && !empty($report['match_participants_teams'])) {
  $partCnts = [];
  $report['match_parts_strings'] = [];
  $mids = array_keys($report['matches']);
  sort($mids);

  foreach ($mids as $mid) {
    $teams = [ $report['match_participants_teams'][$mid]['radiant'] ?? 0, $report['match_participants_teams'][$mid]['dire'] ?? 0 ];
    $teamsStr = team_tag( min($teams) ).' ⚔ '.team_tag( max($teams) );

    if (!isset($partCnts[$teamsStr])) $partCnts[$teamsStr] = 0;
    $partCnts[$teamsStr]++;
    $cnt = 0;
    
    $report['match_parts_strings'][$mid] = $teamsStr.' - '.locale_string('game_num').' '.$cnt;
  }
}
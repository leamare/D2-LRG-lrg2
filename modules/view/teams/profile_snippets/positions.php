<?php 


$parent_module = $context_mod."team".$tid."-heroes-positions-";
if ($mod == $context_mod."team".$tid."-heroes-positions") $unset_module = true;
$res["team".$tid]['heroes']['positions']['overview'] = "";
for ($i=1; $i>=0; $i--) {
  for ($j=0; $j<6 && $j>=0; $j++) {
    // if (!$i) { $j = 0; }
    if(!empty($context[$tid]['hero_positions'][$i][$j]))
      $res["team".$tid]['heroes']['positions']["position_$i.$j"]  = "";

    // if (!$i) { break; }
  }
}

if (check_module($parent_module."overview")) {
  generate_positions_strings();
  $res["team".$tid]['heroes']['positions']["overview"] = "";

  $res["team".$tid]['heroes']['positions']["overview"] .= "<div class=\"content-text\"><span class=\"caption\">".locale_string("active_roster").":</span> ";
  $player_pos = [];
  foreach($context[$tid]['active_roster'] as $player) {
    if (!isset($report['players'][$player])) continue;
    $player_pos[$player] = reset($report['players_additional'][$player]['positions']);
  }
  uasort($context[$tid]['active_roster'], function($a, $b) use ($player_pos) {
    if (!isset($player_pos[$a]['core']) || !isset($player_pos[$b]['core'])) return 0;
    if ($player_pos[$a]['core'] > $player_pos[$b]['core']) return -1;
    if ($player_pos[$a]['core'] < $player_pos[$b]['core']) return 1;
    if ($player_pos[$a]['lane'] < $player_pos[$b]['lane']) return ($player_pos[$a]['core'] ? -1 : 1)*1;
    if ($player_pos[$a]['lane'] > $player_pos[$b]['lane']) return ($player_pos[$a]['core'] ? 1 : -1)*1;
    return 0;
  });
  $pl = [];
  foreach($context[$tid]['active_roster'] as $player) {
    if (!isset($report['players'][$player])) continue;
    $position = $player_pos[$player];
    $pl[] = "<span class=\"player\">".player_name($player).
    (isset($position['core']) ? " (".($position['core'] ? locale_string("core") : locale_string("support")).
      ($position['lane'] ? " ".locale_string( "lane_".$position['lane'] ) : '').')' : ''
    )."</span>";
  }
  $res["team".$tid]['heroes']['positions']["overview"] .= implode(', ', $pl)."</div>";

  include_once($root."/modules/view/generators/positions_overview.php");
  $res["team".$tid]['heroes']['positions']["overview"] .= rg_generator_positions_overview("team$tid-heroes-positions-overview", $context[$tid]['hero_positions']);
  $res["team".$tid]['heroes']['positions']["overview"] .= "<div class=\"content-text\">".locale_string("desc_heroes_positions")."</div>";
}
{
  include_once($root."/modules/view/generators/summary.php");

  for ($i=1; $i>=0; $i--) {
    for ($j=0; $j<6 && $j>=0; $j++) {
      // if (!$i) { $j = 0; }

      if (!check_module($parent_module."position_$i.$j") || empty($context[$tid]['hero_positions'][$i][$j])) {
        // if (!$i) { break; }
        continue;
      }

      $res["team".$tid]['heroes']['positions']["position_$i.$j"] = "";

      $player_pos = [];
      foreach($context[$tid]['active_roster'] as $player) {
        if (!isset($report['players'][$player])) continue;
        foreach ($report['players_additional'][$player]['positions'] as $pos) {
          if ($pos['core'] == $i && $pos['lane'] == $j) {
            $player_pos[$player] = $pos;
            break;
          }
        }
      }
      uasort($player_pos, function($a, $b) {
        return $b['matches'] <=> $a['matches'];
      });

      $res["team".$tid]['heroes']['positions']["position_$i.$j"] .= "<div class=\"content-text\"><span class=\"caption\">".locale_string("team_roster_position").":</span> ";
      $pl = [];
      foreach ($player_pos as $player => $pos) {
        if (!isset($report['players'][$player])) continue;
        $pl[] = "<span class=\"player\">".player_name($player)." (".($pos['matches']).' '.
                  locale_string( "matches" ).")</span>";
      }
      $res["team".$tid]['heroes']['positions']["position_$i.$j"] .= implode(', ', $pl)."</div>";

      if(isset($report['hero_positions_matches']) && isset($context[$tid]['matches']) && isset($report['matches'])) {
        foreach($context[$tid]['hero_positions'][$i][$j] as $hid => $matches) {
          if (!isset($context[$tid]['hero_positions'][$i][$j][$hid])) continue;
          
          $matches = [];
          foreach($context[$tid]['matches'] as $match => $v) {
            $radiant = ( $report['match_participants_teams'][$match]['radiant'] ?? 0 ) == $tid ? 1 : 0;
            foreach ($report['matches'][$match] as $l) {
              if ($l['radiant'] != $radiant) continue;
              if ($l['hero'] == $hid) {
                $matches[] = $match;
                break;
              }
            }
          }

          $context[$tid]['hero_positions'][$i][$j][$hid]['matchlinks'] = "<a onclick=\"showModal('".
              htmlspecialchars(join_matches($matches)).
              "', '".locale_string("matches")." - ".addcslashes(hero_name($hid)." - ".locale_string("position_$i.$j"), "'")."');\">".
              locale_string("matches")."</a>";
        }
      }

      $res["team".$tid]['heroes']['positions']["position_$i.$j"] .= rg_generator_summary("team$tid-heroes-positions-$i-$j", $context[$tid]['hero_positions'][$i][$j], true, true);
      $res["team".$tid]['heroes']['positions']["position_$i.$j"] .= "<div class=\"content-text\">".locale_string("desc_heroes_positions")."</div>";
      // if (!$i) { break; }
    }
  }
}
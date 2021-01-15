<?php

function rg_view_generate_teams_profiles($context, $context_mod, $foreword = "") {
  global $mod, $root, $strings, $unset_module, $report, $icons_provider;
  $res = [];
  if($mod == substr($context_mod, 0, strlen($context_mod)-1)) $unset_module = true;

  if(!is_array(array_values($context)[0])) {
    foreach($context as $team_id => &$content) {
      $content = $report['teams'][$team_id];
    }
  }

  foreach ($context as $tid => $team) {
    if(isset($report['teams_interest'])) {
      if (!in_array($tid, $report['teams_interest']) && !check_module($context_mod."team".$tid)) 
        continue;
    }

    $res['team'.$tid] = [];
    $strings['en']["team".$tid] = team_name($tid);

    if(check_module($context_mod."team".$tid)) {
      if($mod == $context_mod."team".$tid) $unset_module = true;

      $multiplier = $report['teams'][$tid]['matches_total'] / $report['random']['matches_total'];

      if (isset($context[$tid]['averages'])) {
        $res["team".$tid]['overview'] = "";

        if(check_module($context_mod."team".$tid."-overview")) {
          $percentages = [
            "rad_ratio",
            "radiant_wr",
            "dire_wr"
          ];

          $res["team".$tid]['overview'] .= $foreword;
          $res["team".$tid]['overview'] .= "<div class=\"content-cards\">".team_card($tid, true)."</div>";

          if (!empty($report['match_participants_teams'])) {
            $matches = [];
            $player_pos = [];
            foreach($context[$tid]['active_roster'] as $player) {
              if (!isset($report['players'][$player])) continue;
              if (!empty($report['players_additional']))
                $player_pos[$player] = reset($report['players_additional'][$player]['positions']);
              $matches[ $player ] = [];
            }
            if (!empty($report['players_additional'])) {
              uksort($matches, function($a, $b) use ($player_pos) {
                if (!isset($player_pos[$a]['core']) || !isset($player_pos[$b]['core'])) return 0;
                if ($player_pos[$a]['core'] > $player_pos[$b]['core']) return -1;
                if ($player_pos[$a]['core'] < $player_pos[$b]['core']) return 1;
                if ($player_pos[$a]['lane'] < $player_pos[$b]['lane']) return ($player_pos[$a]['core'] ? -1 : 1)*1;
                if ($player_pos[$a]['lane'] > $player_pos[$b]['lane']) return ($player_pos[$a]['core'] ? 1 : -1)*1;
                return 0;
              });
            }

            foreach($context[$tid]['matches'] as $match => $v) {
              $radiant = ( $report['match_participants_teams'][$match]['radiant'] ?? 0 ) == $tid ? 1 : 0;
              foreach ($report['matches'][$match] as $l) {
                if ($l['radiant'] != $radiant) continue;
                if (!isset($matches[ $l['player'] ])) continue;
                $matches[ $l['player'] ][ $l['hero'] ] = ( $matches[ $l['player'] ][ $l['hero'] ] ?? 0 ) + 1;
              }
            }

            $res["team".$tid]['overview'] .= "<div class=\"content-cards unique-heroes-card\">".
              "<h1>".locale_string("team_players_unique_heroes")."</h1>";
            
            foreach ($matches as $player => $heroes) {
              arsort($heroes);
              $res["team".$tid]['overview'] .= "<div class=\"line\"><span class=\"caption\">".player_name($player)."</span>: ";
              foreach ($heroes as $hero => $num) {
                $res["team".$tid]['overview'] .= "<a title=\"".hero_name($hero)." - ".locale_string('matches_s')." ".$num." - ".locale_string('total')." ".$context[$tid]['pickban'][$hero]['matches_picked']." - ".
                  locale_string('winrate_s')." ".($context[$tid]['pickban'][$hero]['winrate_picked']*100)."%\">".
                  hero_icon($hero)."</a>";
              }
              $res["team".$tid]['overview'] .= "</div>";
            }
            
            $res["team".$tid]['overview'] .= "</div>";
          }

          $res["team".$tid]['overview'] .= "<div class=\"content-text\">".
            "<a href=\"https://www.dotabuff.com/esports/teams/$tid\">Dotabuff</a> / ".
            "<a href=\"https://www.opendota.com/teams/$tid\">OpenDota</a>".
            //"<a href=\"https://www.opendota.com/teams/$tid\">Stratz</a> / ".
          "</div>";

          if(isset($report['teams'][$tid]['regions'])) {
            asort($report['teams'][$tid]['regions']);
            $region_line = "";
            foreach($report['teams'][$tid]['regions'] as $region => $m) {
              if(!empty($region_line)) $region_line .= ", ";
              $region_line .= region_link($region)." (".$m.")";
            }
            $res["team".$tid]['overview'] .= "<div class=\"content-text\">".locale_string("regions").": ".
                $region_line.
                "</div>";
          }

          $res["team".$tid]['overview'] .= "<table id=\"teams-$tid-avg-table\" class=\"list\"> ";
          foreach ($context[$tid]['averages'] as $key => $value) {
            $res["team".$tid]['overview'] .= "<tr><td>".
                locale_string( $key )."</td><td>".number_format($value*(in_array($key, $percentages) ? 100 : 1), 2).
                (in_array($key, $percentages) ? "%" : "").
                "</td></tr>";
          }
          $res["team".$tid]['overview'] .= "</table>";
          $res["team".$tid]['overview'] .= "<div class=\"content-text\">".locale_string("desc_teams")."</div>";
        }
      }

      if (isset($context[$tid]['draft'])) {
        $res["team".$tid]['draft'] = "";

        if(check_module($context_mod."team".$tid."-draft")) {
          include_once("$root/modules/view/generators/draft.php");
          $res["team".$tid]['draft'] = rg_generator_draft("team$tid-draft", $context[$tid]['pickban'], $context[$tid]['draft'], $context[$tid]['matches_total']);
        }
      }
      if (isset($context[$tid]['draft_vs'])) {
        $res["team".$tid]['vsdraft'] = "";

        if(check_module($context_mod."team".$tid."-vsdraft")) {
          include_once("$root/modules/view/generators/draft.php");
          $res["team".$tid]['vsdraft'] = rg_generator_draft("team$tid-vsdraft", $context[$tid]['pickban_vs'], $context[$tid]['draft_vs'], $context[$tid]['matches_total']);
        }
      }

      $res["team".$tid]['heroes'] = [];
      if ($mod == $context_mod."team".$tid."-heroes") $unset_module = true;

      if (isset($context[$tid]['hero_positions'])) {
        $res["team".$tid]['heroes']['positions'] = [];

        if(check_module($context_mod."team".$tid."-heroes-positions")) {
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
                        "', '".locale_string("matches")." - ".hero_name($hid)." - ".locale_string("position_$i.$j")."');\">".
                        locale_string("matches")."</a>";
                  }
                }

                $res["team".$tid]['heroes']['positions']["position_$i.$j"] .= rg_generator_summary("team$tid-heroes-positions-$i-$j", $context[$tid]['hero_positions'][$i][$j], true, true);
                $res["team".$tid]['heroes']['positions']["position_$i.$j"] .= "<div class=\"content-text\">".locale_string("desc_heroes_positions")."</div>";
                // if (!$i) { break; }
              }
            }
          }
        }
      }
      if (isset($context[$tid]['hero_graph']) && $report['settings']['heroes_combo_graph']) {
        $res["team".$tid]['heroes']['meta_graph'] = "";
        if(check_module($context_mod."team".$tid."-heroes-meta_graph")) {
          include_once("$root/modules/view/generators/meta_graph.php");
          $locale_settings = ["lim" => ceil($report['settings']['limiter_triplets']*$multiplier)+1,
              "per" => "35%"
          ];
          $res["team".$tid]['heroes']['meta_graph'] = "<div class=\"content-text\">".locale_string("desc_meta_graph", $locale_settings)."</div>";
          $res["team".$tid]['heroes']['meta_graph'] .= rg_generator_meta_graph("team$tid-heroes-meta-graph", $context[$tid]['hero_graph'], $context[$tid]['pickban']);
          $res["team".$tid]['heroes']['meta_graph'] .= "<div class=\"content-text\">".locale_string("desc_meta_graph_add", $locale_settings)."</div>";
        }
      }
      if ((isset($context[$tid]['hero_pairs']) && !empty($context[$tid]['hero_pairs'])) ||
          (isset($context[$tid]['hero_triplets']) && !empty($context[$tid]['hero_triplets']))) {
        $res["team".$tid]['heroes']['combos'] = [];

        if(check_module($context_mod."team".$tid."-heroes-combos")) {
          $parent_module = $context_mod."team".$tid."-heroes-combos-";
          if ($mod == $context_mod."team".$tid."-heroes-combos") $unset_module = true;
          include_once($root."/modules/view/generators/combos.php");

          if(isset($context[$tid]['hero_pairs'])) {
            $res["team".$tid]['heroes']['combos']['pairs'] = "";
            if (check_module($parent_module."pairs")) {
              $res["team".$tid]['heroes']['combos']['pairs'] =  "<div class=\"content-text\">".locale_string("desc_heroes_pairs",
                                [ "limh"=> ceil($report['settings']['limiter_triplets']*$multiplier)+1 ] )."</div>";
              $res["team".$tid]['heroes']['combos']['pairs'] .=  rg_generator_combos("hero-pairs",
                                                 $context[$tid]['hero_pairs'],
                                                 (isset($context[$tid]['hero_pairs_matches']) ? $context[$tid]['hero_pairs_matches'] : [])
                                               );

            }
          }
          if(isset($context[$tid]['hero_triplets']) && !empty($context[$tid]['hero_triplets'])) {
            $res["team".$tid]['heroes']['combos']['trios'] = "";
            if (check_module($parent_module."trios")) {
              $res["team".$tid]['heroes']['combos']['trios'] =  "<div class=\"content-text\">".locale_string("desc_heroes_trios",
                                [ "liml"=> ceil($report['settings']['limiter_triplets']*$multiplier)+1 ] )."</div>";
              $res["team".$tid]['heroes']['combos']['trios'] .= rg_generator_combos("hero-trios",
                                                 $context[$tid]['hero_triplets'],
                                                 (isset($context[$tid]['hero_triplets_matches']) ? $context[$tid]['hero_triplets_matches'] : [])
                                               );
            }
          }
        }
      }

      if (isset($context[$tid]['players_draft'])) {
        $res["team".$tid]['players'] = [];
        if ($mod == $context_mod."team".$tid."-players") $unset_module = true;

        if (isset($context[$tid]['players_draft'])) {
            $res["team".$tid]['players']['draft'] = "";

            if(check_module($context_mod."team".$tid."-players-draft")) {
              $res["team".$tid]['players']['draft'] = rg_generator_draft("team$tid-players-draft",
                                                                          $context[$tid]['players_draft_pb'],
                                                                          $context[$tid]['players_draft'],
                                                                          $context[$tid]['matches_total'],
                                                                        false);
            }
        }
      }



      if (isset($context[$tid]['matches']) && isset($report['matches'])) {
        $res["team".$tid]['matches'] = "";

        if(check_module($context_mod."team".$tid."-matches")) {
          $res["team".$tid]['matches'] = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
          $res["team".$tid]['matches'] .= "<div class=\"content-cards\">";
          foreach($context[$tid]['matches'] as $matchid => $match) {
            $res["team".$tid]['matches'] .= match_card($matchid);
          }
          $res["team".$tid]['matches'] .= "</div>";
        }
      }
      if (isset($report['players'])) {
        $res["team".$tid]['roster'] = "";

        if(check_module($context_mod."team".$tid."-roster")) {
          generate_positions_strings();
          $res["team".$tid]['roster'] = "<div class=\"content-text\">".locale_string("desc_roster")."</div>";
          $res["team".$tid]['roster'] .= "<div class=\"content-cards\">";
          foreach($context[$tid]['active_roster'] as $player) {
            $res["team".$tid]['roster'] .= player_card($player);
          }
          $res["team".$tid]['roster'] .= "</div>";
        }
      }
    }
  }

  return $res;
}

?>

<?php

function rg_view_generate_teams_profiles($context, $context_mod, $foreword = "") {
  global $mod, $root, $strings, $unset_module, $report, $icons_provider, $lg_version, $roleicon_logo_provider;
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
            "dire_wr",
            "diversity",
          ];

          $context[$tid]['averages']['diversity'] = teams_diversity_recalc($context[$tid]);

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
                if (!isset($matches[ $l['player'] ][ $l['hero'] ])) $matches[ $l['player'] ][ $l['hero'] ] = [ 'ms' => [], 'c' => 0, 'w' => 0 ];
                $matches[ $l['player'] ][ $l['hero'] ]['ms'][] = $match;
                $matches[ $l['player'] ][ $l['hero'] ]['c']++;
                $matches[ $l['player'] ][ $l['hero'] ]['w'] += ! ($report['matches_additional'][$match]['radiant_win'] XOR $radiant);
              }
            }

            if (isset($context[$tid]['pickban'])) {
              $heroes = [];
              $teams = count($context);
              uasort($report['pickban'], function($a, $b) {
                return $b['matches_picked'] <=> $a['matches_picked'];
              });
              $mp = array_values($report['pickban'])[ ceil(count($report['pickban']) * 0.5) ]['matches_picked'];
              foreach ($context[$tid]['pickban'] as $hid => $data) {
                if ($report['pickban'][$hid]['matches_picked'] > $mp || !$data['matches_picked']) continue;
                $ref_ratio = $report['pickban'][$hid]['matches_picked']/$teams;
                if ($data['matches_picked'] > $ref_ratio && ($data['matches_picked'] - $ref_ratio)/$ref_ratio > 1.5) {
                  // $data['ratio'] = $ref_ratio;
                  $data['ratio'] = $data['matches_picked']/$report['pickban'][$hid]['matches_picked'];
                  $heroes[$hid] = $data;
                }
              }
              uasort($heroes, function($a, $b) {
                return $b['ratio'] <=> $a['ratio'];
              });

              $res["team".$tid]['overview'] .= "<div class=\"content-cards specific-heroes-card\">".
              "<h1>".locale_string("team_specific_heroes")."</h1>";

              if (count($heroes)) {
                foreach($heroes as $hid => $data) {
                  $title = addcslashes(hero_name($hid), "'")." - ".locale_string('total')." ".$report['pickban'][$hid]['matches_picked']." - ".locale_string('team')." ".$data['matches_picked']." - ".
                    locale_string('winrate_s')." ".round($data['winrate_picked']*100, 2)."% - ".locale_string('ratio')." ".round($data['ratio']*100, 2)."%";
                  $res["team".$tid]['overview'] .= "<a title=\"".$title."\">".hero_icon($hid)."</a>";
                }
              } else {
                $res["team".$tid]['overview'] .= locale_string('none');
              }

              $res["team".$tid]['overview'] .= "</div>";
            }

            // FIRST PLAYED BY THE TEAM
            // no need to recalculate this here (could move it out to postload for instance)
            // but honestly
            // I don't care, this shit is about to be rewritten again anyway
            if (!empty($context[$tid]['matches']) && isset($report['matches'])) {
              $first_matches_heroes = [];

              ksort($report['matches']);
              foreach ($report['matches'] as $mid => $heroes) {
                foreach ($heroes as $v) {
                  if (!isset($first_matches_heroes[$v['hero']])) {
                    $first_matches_heroes[$v['hero']] = $mid;
                  }
                }
              }

              $__posdummy = [
                '1.1' => [],
                '1.2' => [],
                '1.3' => [],
                '0.0' => [],
              ];

              if (isset($report['hero_positions_matches'])) {
                $first_matches_heroes_positions = $__posdummy;
                foreach ($first_matches_heroes_positions as $rolestring => &$arr) {
                  [ $isCore, $lane ] = explode('.', $rolestring);
                  if ($lane == 0) {
                    $report['hero_positions_matches'][$isCore][0] = [];
                    foreach ($report['hero_positions_matches'][$isCore] as $k => $vs) {
                      foreach ($vs as $hid => $v) {
                        if (!isset($report['hero_positions_matches'][$isCore][0][$hid]))
                          $report['hero_positions_matches'][$isCore][0][$hid] = [];
                        $report['hero_positions_matches'][$isCore][0][$hid] = array_merge($report['hero_positions_matches'][$isCore][0][$hid], $v);
                      }
                    }
                  }
                  foreach ($report['hero_positions_matches'][$isCore][$lane] as $hid => $v) {
                    $first_matches_heroes_positions[$rolestring][$hid] = min($v);
                  }
                }
              }

              if (isset($context[$tid]['regions']) && isset($report['regions_data'])) {
                $first_matches_heroes_regions = [];
                foreach ($context[$tid]['regions'] as $rid => $ms) {
                  $first_matches_heroes_regions[$rid] = [];
                  foreach ($report['regions_data'][$rid]['matches'] as $mid => $s) {
                    if (empty($mid)) continue;
                    foreach ($report['matches'][$mid] as $v) {
                      if (!isset($first_matches_heroes_regions[$rid][$v['hero']])) {
                        $first_matches_heroes_regions[$rid][$v['hero']] = $mid;
                      }
                    }
                  }
                }

                if (isset($report['hero_positions_matches'])) {
                  $first_matches_heroes_positions_regions = [];
                  
                  foreach ($context[$tid]['regions'] as $rid => $ms) {
                    $first_matches_heroes_positions_regions[$rid] = $__posdummy;

                    foreach ($first_matches_heroes_positions_regions[$rid] as $rolestring => &$arr) {
                      [ $isCore, $lane ] = explode('.', $rolestring);
                      if ($lane == 0) {
                        $report['hero_positions_matches'][$isCore][0] = [];
                        foreach ($report['hero_positions_matches'][$isCore] as $k => $vs) {
                          foreach ($vs as $hid => $v) {
                            if (!isset($report['hero_positions_matches'][$isCore][0][$hid]))
                              $report['hero_positions_matches'][$isCore][0][$hid] = [];
                            $report['hero_positions_matches'][$isCore][0][$hid] = $report['hero_positions_matches'][$isCore][0][$hid] + $v;
                          }
                        }
                      }
                      foreach ($report['hero_positions_matches'][$isCore][$lane] as $hid => $v) {
                        $_matches = array_intersect($v, array_keys($report['regions_data'][$rid]['matches']));
                        if (empty($_matches)) continue;
                        $first_matches_heroes_positions_regions[$rid][$rolestring][$hid] = min($_matches);
                      }
                    }
                  }
                }
              }

              $fp_filter = function($a, $k) use (&$context, &$report, &$tid) {
                $radiant = null;
                foreach ($report['matches'][$a] as $i => $hero) {
                  if ($hero['hero'] == $k) {
                    $radiant = $hero['radiant'];
                    break;
                  }
                }

                return isset($context[$tid]['matches'][$a]) && array_search($tid, $report['match_participants_teams'][$a]) == ($radiant ? 'radiant' : 'dire');
              };

              $res["team".$tid]['overview'] .= "<div class=\"content-cards unique-heroes-card\">".
                "<h1>".locale_string("team_first_picked_by")."</h1>";
              
              $res["team".$tid]['overview'] .= "<div class=\"line\"><span class=\"caption\">".locale_string('team_first_total')."</span>: ";
              $first_matches_heroes = array_filter($first_matches_heroes, $fp_filter, ARRAY_FILTER_USE_BOTH);
              if (empty($first_matches_heroes)) {
                $res["team".$tid]['overview'] .= locale_string('stats_no_elements');
              } else {
                foreach ($first_matches_heroes as $hid => $mid) {
                  $title = addcslashes(hero_name($hid), "'");
                  $res["team".$tid]['overview'] .= "<a title=\"".$title."\" ".
                  "onclick=\"showModal('".htmlspecialchars(join_matches([$mid]))."', '".$title."')\"".
                  ">".hero_icon($hid)."</a>";
                }
              }
              $res["team".$tid]['overview'] .= "</div>";

              $roleicons = [
                "0.0" => "hardsupporticon",
                "1.1" => "safelaneicon",
                "1.2" => "midlaneicon",
                "1.3" => "offlaneicon",
              ];
              generate_positions_strings();

              if (isset($first_matches_heroes_positions)) {
                $res["team".$tid]['overview'] .= "<div class=\"line\"><span class=\"caption\">".locale_string('team_first_total_role')."</span>: ";

                $line = "";
                foreach ($first_matches_heroes_positions as $role => $rolems) {
                  $rolems = array_filter($rolems, $fp_filter, ARRAY_FILTER_USE_BOTH);
                  if (empty($rolems)) continue;

                  $line .= "<span class=\"role-heroes\">".
                    "<img class=\"roleicon\" src=\"".str_replace("%ROLE%", $roleicons[$role], $roleicon_logo_provider)."&size=smaller\" alt=\"".$roleicons[$role]."\" />: [ ";
                  foreach ($rolems as $hid => $mid) {
                    $title = addcslashes(hero_name($hid)." - ".locale_string("position_$role"), "'");
                    $line .= "<a title=\"".$title."\" ".
                    "onclick=\"showModal('".htmlspecialchars(join_matches([$mid]))."', '".$title."')\"".
                    ">".hero_icon($hid)."</a>";
                  }
                  $line .= " ]</span> ";
                }
                if (empty($line)) $line = locale_string('stats_no_emelents');
                $res["team".$tid]['overview'] .= $line."</div>";
              }

              if (isset($first_matches_heroes_regions)) {
                $res["team".$tid]['overview'] .= "<div class=\"line\"><span class=\"caption\">".locale_string('team_first_region')."</span>: ";

                $line = "";
                foreach ($first_matches_heroes_regions as $region => $rolems) {
                  $rolems = array_filter($rolems, $fp_filter, ARRAY_FILTER_USE_BOTH);
                  foreach ($rolems as $hid => $mid) {
                    $title = addcslashes(hero_name($hid)." - ".locale_string("region$region"), "'");
                    $line .= "<a title=\"".$title."\" ".
                    "onclick=\"showModal('".htmlspecialchars(join_matches([$mid]))."', '".$title."')\"".
                    ">".hero_icon($hid)."</a>";
                  }
                }
                if (empty($line)) $line = locale_string('stats_no_emelents');
                $res["team".$tid]['overview'] .= $line."</div>";
              }

              if (isset($first_matches_heroes_positions_regions)) {
                $res["team".$tid]['overview'] .= "<div class=\"line\"><span class=\"caption\">".locale_string('team_first_region_positions')."</span>: ";

                $line = "";
                foreach ($first_matches_heroes_positions_regions as $region => $roles) {
                  $line .= "<span class=\"heroes-icons-wrapper\"> { ";
                  foreach ($roles as $role => $rolems) {
                    $rolems = array_filter($rolems, $fp_filter, ARRAY_FILTER_USE_BOTH);
                    if (empty($rolems)) continue;
  
                    $line .= "<span class=\"role-heroes\">".
                      "<img class=\"roleicon\" src=\"".str_replace("%ROLE%", $roleicons[$role], $roleicon_logo_provider)."&size=smaller\" alt=\"".$roleicons[$role]."\" />: [ ";
                    foreach ($rolems as $hid => $mid) {
                      $title = addcslashes(hero_name($hid)." - ".locale_string("position_$role"), "'");
                      $line .= "<a title=\"".$title."\" ".
                      "onclick=\"showModal('".htmlspecialchars(join_matches([$mid]))."', '".$title."')\"".
                      ">".hero_icon($hid)."</a>";
                    }
                    $line .= " ]</span> ";
                  }
                  $line .= " } </span> ";
                }
                if (empty($line)) $line = locale_string('stats_no_emelents');
                $res["team".$tid]['overview'] .= $line."</div>";
              }

              $res["team".$tid]['overview'] .= "</div>";
            }
            
            $res["team".$tid]['overview'] .= "<div class=\"content-cards unique-heroes-card\">".
              "<h1>".locale_string("team_players_unique_heroes")."</h1>";
            
            foreach ($matches as $player => $heroes) {
              uasort($heroes, function($a, $b) {
                return $b['c'] <=> $a['c'];
              });
              $res["team".$tid]['overview'] .= "<div class=\"line\"><span class=\"caption\">".player_name($player)."</span>: ";
              foreach ($heroes as $hero => $arr) {
                $ms = $arr['ms'];
                sort($ms);
                $num = $arr['c'];
                $wr = $arr['w']/$arr['c'];
                $title = addcslashes(hero_name($hero), "'")." - ".locale_string('matches_s')." ".$num." (".locale_string('total')." ".
                  (isset($context[$tid]['pickban'][$hero]) ? $context[$tid]['pickban'][$hero]['matches_picked'] : $num).") - ".
                  locale_string('winrate_s')." ".round($wr*100, 2)."%";
                $res["team".$tid]['overview'] .= "<a title=\"".$title."\" ".
                  "onclick=\"showModal('".htmlspecialchars(join_matches($ms))."', '".addcslashes(player_name($player), "'")." - $title')\"".
                  ">".hero_icon($hero)."</a>";
              }
              $res["team".$tid]['overview'] .= "</div>";
            }
            
            $res["team".$tid]['overview'] .= "</div>";
          }

          $res["team".$tid]['overview'] .= "<div class=\"content-text\">".
            "<a href=\"https://www.dotabuff.com/esports/teams/$tid\">Dotabuff</a> / ".
            "<a href=\"https://www.opendota.com/teams/$tid\">OpenDota</a> / ".
            "<a href=\"https://www.stratz.com/teams/$tid\">Stratz</a>".
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

          $res["team".$tid]['overview'] .= "<div class=\"content-header\">".locale_string("draft")."</div>";
          $res["team".$tid]['overview'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
            "<div class=\"explain-content\">".
              "<div class=\"line\">".locale_string("desc_pickban_teams_profile")."</div>".
            "</div>".
          "</details>";
          include_once("$root/modules/view/generators/pickban_teams.php");
          $res["team".$tid]['overview'] .= rg_generator_team_pickban_profile($context[$tid]);


          $res["team".$tid]['overview'] .= "<div class=\"content-header\">".locale_string("averages")."</div>";
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

      if (isset($context[$tid]['pickban'])) {
        $res["team".$tid]['pickban'] = "";

        if(check_module($context_mod."team".$tid."-pickban")) {
          include_once("$root/modules/view/generators/pickban_teams.php");
          $res["team".$tid]['pickban'] = rg_generator_team_pickban("team$tid-pickban", $context[$tid]);
        }
      }
      if (isset($context[$tid]['draft'])) {
        $res["team".$tid]['draft'] = "";

        if(check_module($context_mod."team".$tid."-draft")) {
          include_once("$root/modules/view/generators/draft.php");
          $res["team".$tid]['draft'] = rg_generator_draft("team$tid-draft", $context[$tid]['pickban'], $context[$tid]['draft'], $context[$tid]['matches_total']);
        }
      }
      if (isset($context[$tid]['draft_tree'])) {
        $res["team".$tid]['draft_tree'] = "";

        if(check_module($context_mod."team".$tid."-draft_tree")) {
          include_once("$root/modules/view/generators/draft_tree.php");
          $res["team".$tid]['draft_tree'] = rg_generator_draft_tree("team$tid-draft-tree", $context[$tid]['draft_tree'], $context[$tid]['draft'], $report['settings']['limiter_triplets']);
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
        }

        // $res["team".$tid]['heroes']['rolepickban'] = [];
      }
      if (isset($context[$tid]['hero_graph']) && $report['settings']['heroes_combo_graph']) {
        $res["team".$tid]['heroes']['meta_graph'] = "";
        if(check_module($context_mod."team".$tid."-heroes-meta_graph")) {
          include_once("$root/modules/view/generators/meta_graph.php");
          $locale_settings = ["lim" => ceil($report['settings']['limiter_triplets']*$multiplier)+1,
              "per" => "35%"
          ];
          $res["team".$tid]['heroes']['meta_graph'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
            "<div class=\"explain-content\">".
              "<div class=\"line\">".locale_string("desc_meta_graph", $locale_settings)."</div>".
              "<div class=\"line\">".locale_string("desc_meta_graph_add", $locale_settings)."</div>".
            "</div>".
          "</details>";
          $res["team".$tid]['heroes']['meta_graph'] .= rg_generator_meta_graph("team$tid-heroes-meta-graph", $context[$tid]['hero_graph'], $context[$tid]['pickban']);
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

            if (compare_release_ver($report['ana_version'], [ 2, 18, 0, 0, 0 ]) < 0) {
              foreach ($context[$tid]['players_draft_pb'] as $id => $el) {
                if (!in_array($id, $context[$tid]['active_roster'])) {
                  unset($context[$tid]['players_draft_pb'][$id]);
                }
              }
            }

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
        $res["team".$tid]['matches'] = [];

        include_once($root."/modules/view/generators/matches_list.php");

        if(check_module($context_mod."team".$tid."-matches")) {
          if($mod == $context_mod."team".$tid."-matches") $unset_module = true;
          $reslocal = [];
          $parent = $context_mod."team".$tid."-matches-";

          $reslocal['list'] = "";
          if (check_module($parent."list")) {
            $reslocal['list'] = rg_generator_matches_list("matches-list-team$tid", $context[$tid]['matches']);
          }

          $reslocal['cards'] = "";
          if (check_module($parent."cards")) {
            krsort($context[$tid]['matches']);
            $reslocal['cards'] = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
            $reslocal['cards'] .= "<div class=\"content-cards\">";
            foreach($context[$tid]['matches'] as $matchid => $match) {
              $reslocal['cards'] .= match_card($matchid);
            }
            $reslocal['cards'] .= "</div>";
          }

          $reslocal['heroes'] = [];
          if (check_module($parent."heroes")) {
            if ($mod == $parent."heroes") $unset_module = true;
            $parent .= "heroes-";
  
            global $meta, $strings;
  
            $hnames = [];
            foreach ($meta['heroes'] as $id => $v) {
              $hnames[$id] = $v['name'];
              $strings['en']["heroid".$id] = $v['name'];
            }
          
            uasort($hnames, function($a, $b) {
              if($a == $b) return 0;
              else return ($a > $b) ? 1 : -1;
            });
          
            foreach($hnames as $hid => $name) {
              $reslocal['heroes']["heroid".$hid] = "";
          
              if(check_module($parent."heroid".$hid)) {
                $reslocal['heroes']["heroid".$hid] = rg_generator_hero_matches_list(
                  "matches-team$tid-heroes-$hid", $hid, null, true, $context[$tid]['matches']
                );
              }
            }
          }

          $res["team".$tid]['matches'] = $reslocal;
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

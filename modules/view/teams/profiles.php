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
          include_once __DIR__ . "/profile_snippets/overview.php";
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
          include_once __DIR__ . "/profile_snippets/positions.php";
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

      if (isset($report['tvt'])) {
        if (isset($report['match_participants_teams'])) {
          $res["team".$tid]['opponents'] = "";

          if(check_module($context_mod."team".$tid."-opponents")) {
            include_once("$root/modules/view/generators/tvt_unwrap_data.php");
            include_once __DIR__ . "/profile_snippets/opponents.php";
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

          if (isset($report['match_participants_teams'])) {
            $reslocal['opponents'] = [];
            if (check_module($parent."opponents")) {
              if ($mod == $parent."opponents") $unset_module = true;
              $parent .= "opponents-";
    
              $mteams = [];
              foreach ($context[$tid]['matches'] as $mid => $data) {
                if (!isset($report['match_participants_teams'][$mid])) continue;

                $opid = $report['match_participants_teams'][$mid]['radiant'] == $tid ?
                   ($report['match_participants_teams'][$mid]['dire'] ?? 0) : 
                   ($report['match_participants_teams'][$mid]['radiant'] ?? 0);
                
                if (!isset($mteams[$opid])) $mteams[$opid] = [];
                $mteams[$opid][] = $mid;
              }

              krsort($mteams);

              foreach ($mteams as $opid => $matches) {
                if (!$opid) $optag = "unteamed";
                else $optag = "optid$opid";

                $reslocal['opponents'][$optag] = "";

                register_locale_string(team_name($opid), $optag);

                if (check_module($parent.$optag)) {
                  $reslocal['opponents'][$optag] = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
                  $reslocal['opponents'][$optag] .= "<div class=\"content-cards\">";
                  rsort($matches);
                  foreach($matches as $matchid) {
                    $reslocal['opponents'][$optag] .= match_card($matchid);
                  }
                  $reslocal['opponents'][$optag] .= "</div>";
                }
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


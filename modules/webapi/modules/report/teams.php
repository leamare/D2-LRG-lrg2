<?php 

include_once(__DIR__ . "/../../../view/generators/tvt_unwrap_data.php");
include_once(__DIR__ . "/../../../view/functions/teams_diversity_recalc.php");

$repeatVars['teams'] = ['team'];

$endpoints['teams'] = function($mods, $vars, &$report) use (&$endpoints, &$repeatVars) {
  if (!isset($report['teams']))
    throw new \Exception("No teams in the report ");

  if (in_array("cards", $mods)) {
    $res = [
      '__endp' => 'teams-cards',
    ];
    $tids = array_keys($report['teams']);
    foreach($tids as $tid) {
      $res[] = team_card($tid);
    }
    return $res;
  }

  if (in_array("grid", $mods)) {
    $tvt = rg_generator_tvt_unwrap_data($report['tvt'], $report['teams']);

    if(!sizeof($tvt)) return null;

    if (isset($report['match_participants_teams'])) {
      foreach ($report['match_participants_teams'] as $mid => $teams) {
        if (!isset($tvt[$teams['dire']][$teams['radiant']]['matchids'])) {
          $tvt[$teams['dire']][$teams['radiant']]['matchids'] = [];
        }
        $tvt[$teams['dire']][$teams['radiant']]['matchids'][] = $mid;
  
        if (!isset($tvt[$teams['radiant']][$teams['dire']]['matchids'])) {
          $tvt[$teams['radiant']][$teams['dire']]['matchids'] = [];
        }
        $tvt[$teams['radiant']][$teams['dire']]['matchids'][] = $mid;
      }
    }

    if (in_array("raw", $mods))
      return $tvt;
    if (in_array("source", $mods))
      return $report['tvt'];
  
    $team_ids = array_keys($tvt);

    $res = [
      '__endp' => 'teams-grid',
      '__stopRepeater' => 'team',
    ];
  
    foreach($tvt as $tid => $teamline) {
      if (!empty($report['teams_interest']) && !in_array($tid, $report['teams_interest'])) continue;
      $res[$tid] = [];
      for($i=0, $end = sizeof($team_ids); $i<$end; $i++) {
        if (!empty($report['teams_interest']) && !in_array($tid, $report['teams_interest'])) continue;
        if($tid != $team_ids[$i]) {
          $res[$tid][ $team_ids[$i] ] = [
            "matches" => $tvt[$tid][$team_ids[$i]]['matches'],
            "winrate" => $teamline[$team_ids[$i]]['winrate'],
            "won" => $tvt[$tid][$team_ids[$i]]['won'],
            "lost" => $tvt[$tid][$team_ids[$i]]['lost'],
          ];
          if (isset($context[$tid][$team_ids[$i]]['matchids'])) {
            $res[$tid][ $team_ids[$i] ]['matches'] = [];
            foreach ($context[$tid][$team_ids[$i]]['matchids'] as $mid) {
              $res[$tid][ $team_ids[$i] ]['matches'][] = match_card_min($mid);
            }
          }
        }
      }
    }

    return $res;
  }

  if (in_array("profiles", $mods)) {
    $res = [
      '__endp' => 'teams-profiles',
      '__stopRepeater' => [ 'playerid', 'heroid', 'itemid' ],
      'teams_list' => $report['teams_interest'] ?? array_keys($report['teams'])
    ];
    if (empty($vars['team'])) {
      $vars['team'] = reset($res['teams_list']);
    }

    $res['card'] = team_card($vars['team']);

    if (!isset($report['teams'][ $vars['team'] ]['averages']))
      return $res;

    if (isset($report['teams'][ $vars['team'] ]['averages']['hero_pool'])) 
      $report['teams'][ $vars['team'] ]['averages']['diversity'] = teams_diversity_recalc($report['teams'][ $vars['team'] ]);

    $res['averages'] = $report['teams'][ $vars['team'] ]['averages'];

    if(isset($report['teams'][ $vars['team'] ]['regions'])) {
      asort($report['teams'][ $vars['team'] ]['regions']);
      $res['regions'] = $report['teams'][ $vars['team'] ]['regions'];
    } else {
      $res['regions'] = null;
    }

    // FIRST PLAYED BY THE TEAM
    // no need to recalculate this here (could move it out to postload for instance)
    // but honestly
    // I don't care, this shit is about to be rewritten again anyway
    if (!empty($report['teams'][ $vars['team'] ]['matches']) && isset($report['matches'])) {
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

      if (isset($report['teams'][ $vars['team'] ]['regions']) && isset($report['regions_data'])) {
        $first_matches_heroes_regions = [];
        foreach ($report['teams'][ $vars['team'] ]['regions'] as $rid => $ms) {
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
          
          foreach ($report['teams'][ $vars['team'] ]['regions'] as $rid => $ms) {
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
                $matches = array_intersect($v, array_keys($report['regions_data'][$rid]['matches']));
                if (empty($matches)) continue;
                $first_matches_heroes_positions_regions[$rid][$rolestring][$hid] = min($matches);
              }
            }
          }
        }
      }

      $fp_filter = function($a, $k) use (&$vars, &$report) {
        $radiant = null;
        foreach ($report['matches'][$a] as $i => $hero) {
          if ($hero['hero'] == $k) {
            $radiant = $hero['radiant'];
            break;
          }
        }

        return isset($report['teams'][ $vars['team'] ]['matches'][$a]) && array_search($vars['team'], $report['match_participants_teams'][$a]) == ($radiant ? 'radiant' : 'dire');
      };

      // ACTUAL TEAM FILTERING

      $res['first_picked_by'] = [];

      $res['first_picked_by']['total'] = [];
      $first_matches_heroes = array_filter($first_matches_heroes, $fp_filter, ARRAY_FILTER_USE_BOTH);
      foreach ($first_matches_heroes as $hid => $mid) {
        $res['first_picked_by']['total'][$hid] = match_card_min($mid);
      }

      if (isset($first_matches_heroes_positions)) {
        $res['first_picked_by']['total_positions'] = [];

        foreach ($first_matches_heroes_positions as $role => $rolems) {
          $rolems = array_filter($rolems, $fp_filter, ARRAY_FILTER_USE_BOTH);
          if (empty($rolems)) continue;
          $res['first_picked_by']['total_positions'][$role] = [];

          foreach ($rolems as $hid => $mid) {
            $res['first_picked_by']['total_positions'][$role][$hid] = match_card_min($mid);
          }
        }
      } else {
        $res['first_picked_by']['total_positions'] = null;
      }

      if (isset($first_matches_heroes_regions)) {
        $res['first_picked_by']['regions'] = [];

        foreach ($first_matches_heroes_regions as $region => $rolems) {
          $res['first_picked_by']['regions'][$region] = [];
          $rolems = array_filter($rolems, $fp_filter, ARRAY_FILTER_USE_BOTH);
          foreach ($rolems as $hid => $mid) {
            $res['first_picked_by']['regions'][$region][$hid] = match_card_min($mid);
          }
        }
      } else {
        $res['first_picked_by']['regions'] = null;
      }

      if (isset($first_matches_heroes_positions_regions)) {
        $res['first_picked_by']['regions_positions'] = [];

        foreach ($first_matches_heroes_positions_regions as $region => $roles) {
          $res['first_picked_by']['regions_positions'][$region] = [];
          foreach ($roles as $role => $rolems) {
            $rolems = array_filter($rolems, $fp_filter, ARRAY_FILTER_USE_BOTH);
            if (empty($rolems)) continue;

            $res['first_picked_by']['regions_positions'][$region][$role] = [];

            foreach ($rolems as $hid => $mid) {
              $res['first_picked_by']['regions_positions'][$region][$role][$hid] = match_card_min($mid);
            }
          }
        }
      } else {
        $res['first_picked_by']['regions_positions'] = null;
      }
    } else {
      $res['first_picked_by'] = null;
    }

    if (!empty($report['match_participants_teams'])) {
      $res['unique_heroes'] = [];

      $matches = [];
      $player_pos = [];
      foreach($report['teams'][ $vars['team'] ]['active_roster'] as $player) {
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

      if (isset($report['teams'][ $vars['team'] ]['pickban'])) {
        $heroes = [];
        $teams = count($report['teams']);
        uasort($report['pickban'], function($a, $b) {
          return $b['matches_picked'] <=> $a['matches_picked'];
        });
        $mp = array_values($report['pickban'])[ ceil(count($report['pickban']) * 0.5) ]['matches_picked'];
        foreach ($report['teams'][ $vars['team'] ]['pickban'] as $hid => $data) {
          if ($report['pickban'][$hid]['matches_picked'] > $mp || !$data['matches_picked']) continue;
          $ref_ratio = $report['pickban'][$hid]['matches_picked']/$teams;
          if ($data['matches_picked'] > $ref_ratio && ($data['matches_picked'] - $ref_ratio)/$ref_ratio > 1.5) {
            // $data['ratio'] = $ref_ratio;
            $data['ratio'] = round($data['matches_picked']/$report['pickban'][$hid]['matches_picked'], 4);
            $heroes[$hid] = $data;
          }
        }
        uasort($heroes, function($a, $b) {
          return $b['ratio'] <=> $a['ratio'];
        });

        $res['specific_heroes'] = $heroes;
      }

      foreach($report['teams'][ $vars['team'] ]['matches'] as $match => $v) {
        $radiant = ( $report['match_participants_teams'][$match]['radiant'] ?? 0 ) == $vars['team'] ? 1 : 0;
        foreach ($report['matches'][$match] as $l) {
          if ($l['radiant'] != $radiant) continue;
          if (!isset($matches[ $l['player'] ])) continue;
          if (!isset($matches[ $l['player'] ][ $l['hero'] ])) $matches[ $l['player'] ][ $l['hero'] ] = [ 'ms' => [], 'c' => 0, 'w' => 0 ];
          $matches[ $l['player'] ][ $l['hero'] ]['ms'][] = $match;
          $matches[ $l['player'] ][ $l['hero'] ]['c']++;
          $matches[ $l['player'] ][ $l['hero'] ]['w'] += ! ($report['matches_additional'][$match]['radiant_win'] XOR $radiant);
        }
      }

      foreach ($matches as $player => $heroes) {
        arsort($heroes);
        $pl = [
          'role' => $player_pos[ $player ],
          'heroes' => []
        ];
        foreach ($heroes as $hero => $num) {
          $pl['heroes'][$hero] = [
            'played' => $num['c'],
            'total' => isset($report['teams'][ $vars['team'] ]['pickban'][$hero]) ? $report['teams'][ $vars['team'] ]['pickban'][$hero]['matches_picked'] : $num['c'],
            'winrate' => round($num['w']/$num['c'], 4)
          ];
          if ($vars['include_matches']) {
            $pl['heroes'][$hero]['matches'] = [];
            foreach ($matches[ $l['player'] ][ $l['hero'] ]['ms'] as $mid) 
              $pl['heroes'][$hero]['matches'][] = match_card($mid);
          }
        }
        $res['unique_heroes'][$player] = $pl;
      }
    } else {
      $res['unique_heroes'] = null;
    }

    $pb = rg_create_team_pickban_data(
      $report['teams'][ $vars['team'] ]['pickban'], 
      $report['teams'][ $vars['team'] ]['pickban_vs'] ?? [], 
      $report['teams'][ $vars['team'] ]['matches_total']
    );

    $res['pickban'] = [];
    $keys = [ 'rank', 'arank', 'rank_vs', 'arank_vs' ];
    foreach ($keys as $k) {
      uasort($pb, function($a, $b) use ($k) { return $b[$k] <=> $a[$k]; });
      $res['pickban'][$k] = array_slice($pb, 0, 7, true);
    }

    return $res;
  }

  if (in_array("participants", $mods)) 
    return $endpoints['participants']($mods, $vars, $report);
  
  return $endpoints['summary']($mods, $vars, $report);
};

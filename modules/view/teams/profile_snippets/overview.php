<?php 


$percentages = [
  "rad_ratio",
  "radiant_wr",
  "dire_wr",
  "diversity",

  "opener_ratio",
  "opener_pick_winrate",

  "opener_pick_radiant_winrate",
  "opener_pick_dire_winrate",
  "follower_pick_radiant_winrate",
  "follower_pick_dire_winrate",
  "follower_pick_radiant_ratio",
  "opener_pick_radiant_ratio",
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
      $matches[ $l['player'] ][ $l['hero'] ]['w'] += ($report['matches_additional'][$match]['radiant_win'] == $radiant);
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
        "onclick=\"showModal('".htmlspecialchars(join_matches_add([$mid], true, $hid, true))."', '".$title."')\"".
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
          "onclick=\"showModal('".htmlspecialchars(join_matches_add([$mid], true, $hid, true))."', '".$title."')\"".
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
          "onclick=\"showModal('".htmlspecialchars(join_matches_add([$mid], true, $hid, true))."', '".$title."')\"".
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
            "onclick=\"showModal('".htmlspecialchars(join_matches_add([$mid], true, $hid, true))."', '".$title."')\"".
            ">".hero_icon($hid)."</a>";
          }
          $line .= " ]</span> ";
        }
        $line .= " } </span> ";
      }
      if (empty($line)) $line = locale_string('stats_no_elements');
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
        "onclick=\"showModal('".htmlspecialchars(join_matches_add($ms, true, $hero, true))."', '".addcslashes(player_name($player), "'")." - $title')\"".
        ">".hero_icon($hero)."</a>";
    }
    $res["team".$tid]['overview'] .= "</div>";
  }

  // records
  if (isset($report['records'])) {
    $_records = [];

    $tags = isset($report['regions_data']) ? array_keys($report['regions_data']) : [];
    array_unshift($tags, null);

    foreach ($tags as $reg) {
      if (!$reg) {
        $context_records = $report['records'];
        $context_records_ext = $report['records_ext'] ?? [];
      } else {
        $context_records = $report['regions_data'][$reg]['records'];
        $context_records_ext = $report['regions_data'][$reg]['records_ext'] ?? [];
      }

      if (is_wrapped($context_records_ext)) {
        $context_records_ext = unwrap_data($context_records_ext);
      }

      foreach ($context_records as $rectag => $record) {
        if (strpos($rectag, "_team") === false) continue;
  
        if ($record['playerid'] == $tid) {
          $record['tag'] = $rectag;
          $record['placement'] = 1;
          $record['region'] = $reg;
          $_records[] = $record;
        } else if (!empty($context_records_ext)) {
          foreach ($context_records_ext[$rectag] ?? [] as $i => $rec) {
            if (!is_array($rec)) continue;
            if ($rec['playerid'] == $tid) {
              $rec['tag'] = $rectag;
              $rec['placement'] = $i+2;
              $rec['region'] = $reg;
              $_records[] = $rec;
            }
          }
        }
      }
    }

    if (empty($_records)) {
      $res["team".$tid]['overview'] .= "<div class=\"content-text\"><h1>".locale_string("records")."</h1>".locale_string("stats_no_elements")."</a></div>";
    } else {
      $res["team".$tid]['overview'] .= "<table id=\"team-profile-tid$tid-records\" class=\"list wide\"><caption>".locale_string("records")."</caption><thead>".
        "<tr class=\"overhead\">".
          "<th>".locale_string("record")."</th>".
          "<th>".locale_string("match")."</th>".
          "<th>".locale_string("value")."</th>".
        "</tr>".
      "</thead><tbody>";
      foreach ($_records as $record) {
        $res["team".$tid]['overview'] .= "<tr>".
          "<td>".
            ( isset($record['item_id']) ? item_full_link($record['item_id']) : locale_string($record['tag']) ).
            ($record['placement'] == 1 ? '' : ' #'.$record['placement']).
            ($record['region'] ? " (".locale_string("region".$record['region']).")" : '').
          "</td>".
          "<td>".($record['matchid'] ? match_link($record['matchid']) : '-')."</td>".
          "<td>".(
            strpos($record['tag'], "duration") !== FALSE || strpos($record['tag'], "_len") !== FALSE ||
            strpos($record['tag'], "_time") !== FALSE ||
            strpos($record['tag'], "shortest") !== FALSE || strpos($record['tag'], "longest") !== FALSE ?
            convert_time($record['value']) :
            ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
          )."</td>".
        "</tr>";
      }
      $res["team".$tid]['overview'] .= "</tbody></table>";
    }
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
if (!empty($context[$tid]['add_info'])) {
  foreach ($context[$tid]['add_info'] ?? [] as $key => $value) {
    $res["team".$tid]['overview'] .= "<tr><td>".locale_string( $key )."</td><td>".
      (strpos($key, "_match") !== FALSE ? match_link($value) : $value).
    "</td></tr>";
  }
}
$res["team".$tid]['overview'] .= "</table>";

$res["team".$tid]['overview'] .= "<div class=\"content-text\">".locale_string("desc_teams")."</div>";
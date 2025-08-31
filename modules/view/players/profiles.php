<?php

$modules['players']['profiles'] = [];

function rg_view_generate_players_profiles() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $player_photo_provider, $links_providers;

  if($mod == $parent."profiles") $unset_module = true;
  $parent_module = $parent."profiles-";
  $res = [];

  $pids = array_keys($report['players_summary']);
  $pnames = [];
  foreach ($pids as $id) {
    $pnames[$id] = player_name($id, false);
    $strings['en']["playerid".$id] = player_name($id, false);
  }

  uasort($pnames, function($a, $b) {
    if($a == $b) return 0;
    
    return strcasecmp($a, $b);
  });

  foreach($pnames as $pid => $name) {
    $res["playerid".$pid] = "";

    if(check_module($parent_module."playerid".$pid)) {
      $player = $pid;
    }
  }

  if (empty($player)) return "";

  $data = $report['players_summary'][$player];

  if (isset($data['hero_damage_per_min_s']) && $data['gpm'] && !isset($data['damage_to_gold_per_min_s'])) {
    $data = array_insert_before($data, "gpm", [
      "damage_to_gold_per_min_s" => ($data['hero_damage_per_min_s'] ?? 0)/($data['gpm'] ?? 1),
    ]);
  }

  $res['playerid'.$player] .= "<div class=\"profile-header\">".
    "<div class=\"profile-image\"><img src=\"".str_replace("%HERO%", $player, $player_photo_provider ?? "")."\" /></div>".
    "<div class=\"profile-name\">".player_link($player)."</div>".
    "<div class=\"profile-content\">".
      "<div class=\"profile-stats\">".
        "<div class=\"profile-statline\"><label>".locale_string("matches")."</label>: ".number_format($data['matches_s'])."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("winrate")."</label>: ".number_format($data['winrate_s']*100, 2)."%</div>".
        "<div class=\"profile-statline\"><label>".locale_string("hero_pool")."</label>: ".number_format($data['hero_pool'])."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("diversity")."</label>: ".number_format($data['diversity']*100, 2)."%</div>".
        "<div class=\"profile-statline\"><label>".locale_string("duration")."</label>: ".convert_time($data['duration'])."</div>".
      "</div>".
      "<div class=\"profile-stats\">".
        "<div class=\"profile-statline\"><label>".locale_string("kda")."</label>: ".(
          isset($data['kills_s']) ? 
          number_format($data['kills_s'], 2)."/".
          number_format($data['deaths_s'], 2)."/".
          number_format($data['assists_s'], 2)." (".number_format($data['kda'], 2).")"
           : number_format($data['kda'], 2)
        ).
        "</div>".
        "<div class=\"profile-statline\"><label>".locale_string("gpm")."/".locale_string("xpm")."</label>: ".
          number_format($data['gpm'], 0)."/".number_format($data['xpm'], 0)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("lh_at10")."</label>: ".number_format($data['lh_at10'], 1)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("lasthits_per_min_s")."</label>: ".number_format($data['lasthits_per_min_s'], 1)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("damage_to_gold_per_min")."</label>: ".number_format($data['damage_to_gold_per_min_s'], 3)."</div>".
      "</div>".
      "<div class=\"profile-stats\">".
        "<div class=\"profile-statline\"><label>".locale_string("heal_per_min")."</label>: ".number_format($data['heal_per_min_s'], 2)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("hero_damage_per_min")."</label>: ".number_format($data['hero_damage_per_min_s'], 1)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("tower_damage_per_min")."</label>: ".number_format($data['tower_damage_per_min_s'], 1)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("taken_damage_per_min")."</label>: ".number_format($data['taken_damage_per_min_s'], 1)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("stuns")."</label>: ".round($data['stuns'], 2)."</div>".
      "</div>".
    "</div>".
    // "<div class=\"profile-content\">";
  "</div>";

  // played heroes
  if (isset($report['matches'])) {
    $heroes = [];
    
    foreach ($report['matches'] as $mid => $mheroes) {
      foreach ($mheroes as $hero) {
        if ($hero['player'] != $player) continue;
        if (!isset($heroes[ $hero['hero'] ])) {
          $heroes[ $hero['hero'] ] = [
            'wins' => 0,
            'matches' => 0,
            'matchlinks' => []
          ];
        }
        $heroes[ $hero['hero'] ]['matches']++;
        if ($hero['radiant'] == $report['matches_additional'][$mid]['radiant_win'])
          $heroes[ $hero['hero'] ]['wins']++;
        $heroes[ $hero['hero'] ]['matchlinks'][] = $mid;
      }
    }

    uasort($heroes, function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $res['playerid'.$player] .= "<div class=\"content-cards specific-heroes-card\"><h1>".locale_string('heroes')."</h1>";
    foreach ($heroes as $hid => $data) {
      $res['playerid'.$player] .= "<a title=\"".hero_name($hid)." - ".$data['matches']." ".locale_string('matches').
      " - ".number_format(100*$data['wins']/$data['matches'], 2)."% ".locale_string('winrate').
      "\">".hero_icon($hid)."</a>";
    }
    $res['playerid'.$player] .= "</div>";

    $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-heroes\" class=\"list sortable\"><thead><tr>".
      "<th width=\"2%\"></th>".
      "<th>".locale_string("hero")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("matchlinks")."</th>".
    "</tr></thead>";

    $res['playerid'.$player] .= "<tbody>";
    foreach ($heroes as $hid => $data) {
      $res['playerid'.$player] .= "<tr><td>".hero_portrait($hid)."</td>".
        "<td>".hero_link($hid)."</td>".
        "<td>".number_format($data['matches'])."</td>".
        "<td>".number_format(100*$data['wins']/$data['matches'], 2)."%</td>".
        "<td><a onclick=\"showModal('".
          htmlspecialchars(join_matches($data['matchlinks'])).
          "', '".addcslashes(player_name($player)." - ".hero_name($hid), "'")."');\">".
          locale_string("matches").
        "</a></td>";
    }
    
    $res['playerid'.$player] .= "</tbody></table>";
  }

  // fantasy_mvp stats
  if (isset($report['fantasy']) && isset($report['fantasy']['players_mvp'])) {
    if (is_wrapped($report['fantasy']['players_mvp'])) {
      $report['fantasy']['players_mvp'] = unwrap_data($report['fantasy']['players_mvp']);
    }
    if (isset($report['fantasy']['players_mvp'][$player])) {
      $mvp_data = $report['fantasy']['players_mvp'][$player];
      
      // MVP Awards table
      $res['playerid'.$player] .= "<table class=\"list\"><caption>".locale_string("fantasy")."</caption><thead><tr>".
        "<th>".locale_string("total_awards")."</th>".
        "<th>".locale_string("mvp_awards")."</th>".
        "<th>".locale_string("mvp_losing_awards")."</th>".
        "<th>".locale_string("core_awards")."</th>".
        "<th>".locale_string("support_awards")."</th>".
        "<th>".locale_string("lvp_awards")."</th>".
      "</tr></thead><tbody><tr>".
        "<td>".number_format($mvp_data['total_awards'], 1)."</td>".
        "<td>".number_format($mvp_data['mvp'], 1)."</td>".
        "<td>".number_format($mvp_data['mvp_losing'], 1)."</td>".
        "<td>".number_format($mvp_data['core'], 1)."</td>".
        "<td>".number_format($mvp_data['support'], 1)."</td>".
        "<td>".number_format($mvp_data['lvp'], 1)."</td>".
      "</tr></tbody></table>";
      
      // MVP Points table
      $res['playerid'.$player] .= "<table class=\"list\"><thead><tr>".
        "<th>".locale_string("total_points_fantasy")."</th>".
        "<th>".locale_string("kda_fantasy")."</th>".
        "<th>".locale_string("farm_fantasy")."</th>".
        "<th>".locale_string("combat_fantasy")."</th>".
        "<th>".locale_string("objectives_fantasy")."</th>".
      "</tr></thead><tbody><tr>".
        "<td>".number_format($mvp_data['total_points'], 2)."</td>".
        "<td>".number_format($mvp_data['kda'], 2)."</td>".
        "<td>".number_format($mvp_data['farm'], 2)."</td>".
        "<td>".number_format($mvp_data['combat'], 2)."</td>".
        "<td>".number_format($mvp_data['objectives'], 2)."</td>".
      "</tr></tbody></table>";
    }
  }

  // records
  if (isset($report['records'])) {
    $player_records = [];

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
        if (strpos($rectag, "_team") !== false) continue;
  
        if ($record['playerid'] == $player) {
          $record['tag'] = $rectag;
          $record['placement'] = 1;
          $record['region'] = $reg;
          $player_records[] = $record;
        }
        if (!empty($context_records_ext)) {
          foreach ($context_records_ext[$rectag] ?? [] as $i => $rec) {
            if (empty($rec)) continue;
            if ($rec['playerid'] == $player) {
              $rec['tag'] = $rectag;
              $rec['placement'] = $i+2;
              $rec['region'] = $reg;
              $player_records[] = $rec;
            }
          }
        }
      }
    }

    if (isset($report['items']) && isset($report['items']['records']) && isset($heroes)) {
      if (is_wrapped($report['items']['records'])) {
        $report['items']['records'] = unwrap_data($report['items']['records']);
      }

      foreach ($report['items']['records'] as $item => $records) {
        foreach ($heroes as $hero => $data) {
          if (!isset($records[$hero])) continue;

          if (!in_array($records[$hero]['match'], $data['matchlinks'])) continue;
  
          $player_records[] = [
            'tag' => $meta['items_full'][$item]['name']."_time",
            'placement' => 1,
            'region' => null,
            'matchid' => $records[$hero]['match'],
            'value' => $records[$hero]['time']/60,
            'playerid' => $player,
            'item_id' => $item,
            'heroid' => $hero,
          ];
        }
      }
    }

    // region records

    if (empty($player_records)) {
      $res['playerid'.$player] .= "<div class=\"content-text\"><h1>".locale_string("records")."</h1>".locale_string("stats_no_elements")."</a></div>";
    } else {
      $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-records\" class=\"list\"><caption>".locale_string("records")."</caption><thead>".
        "<tr>".
          "<th>".locale_string("record")."</th>".
          "<th>".locale_string("match")."</th>".
          "<th>".locale_string("value")."</th>".
          "<th>".locale_string("hero")."</th>".
        "</tr>".
      "</thead><tbody>";
      foreach ($player_records as $record) {
        $res['playerid'.$player] .= "<tr>".
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
          "<td>".($record['heroid'] ? hero_full($record['heroid']) : '-')."</td>".
        "</tr>";
      }
      $res['playerid'.$player] .= "</tbody></table>";
    }
  }

  // haverages
  if (isset($report['averages_players'])) {
    $_haverages = [];

    $tags = isset($report['regions_data']) ? array_keys($report['regions_data']) : [];
    array_unshift($tags, null);

    foreach ($tags as $reg) {
      if (!$reg) {
        $context_havg = $report['averages_players'] ?? $report['haverages_players'];
      } else {
        $context_havg = $report['regions_data'][$reg]['haverages_players'] ?? $report['regions_data'][$reg]['averages_players'];
      }

      foreach ($context_havg as $tag => $pls) {
        foreach ($pls as $i => $pl) {
          if ($pl['playerid'] == $player) {
            $_haverages[] = [
              "tag" => $tag,
              "region" => $reg,
              "placement" => $i+1,
              "value" => $pl['value'],
            ];
          }
        }
      }
    }

    if (!empty($_haverages)) {
      $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-haverages\" class=\"list\"><caption>".locale_string("haverages")."</caption><thead>".
        "<tr>".
          "<th>".locale_string("record")."</th>".
          "<th>".locale_string("value")."</th>".
        "</tr>".
      "</thead><tbody>";
      foreach ($_haverages as $record) {
        $res['playerid'.$player] .= "<tr>".
          "<td>".
            ( isset($record['item_id']) ? item_full_link($record['item_id']) : locale_string($record['tag']) ).
            ($record['placement'] == 1 ? '' : ' #'.$record['placement']).
            ($record['region'] ? " (".locale_string("region".$record['region']).")" : '').
          "</td>".
          "<td>".(
            strpos($record['tag'], "duration") !== FALSE || strpos($record['tag'], "_len") !== FALSE ||
            strpos($record['tag'], "_time") !== FALSE ||
            strpos($record['tag'], "shortest") !== FALSE || strpos($record['tag'], "longest") !== FALSE ?
            convert_time($record['value']) :
            ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
          )."</td>".
        "</tr>";
      }
      $res['playerid'.$player] .= "</tbody></table>";
    } else {
      $res['playerid'.$player] .= "<div class=\"content-text\"><h1>".locale_string("haverages")."</h1>".locale_string("stats_no_elements")."</a></div>";
    }
  }

  // drafts data
  if (isset($report['players_draft'])) {
    $draft = [];
    $players_bans_disable = true;

    if (!empty($report['draft']) && !empty($report['matches_additional'])) {
      include_once __DIR__."/../functions/players_bans_estimate.php";

      $player_pb = [];
      $player_pb[ $player ] = [
        'matches_picked' => $report['players_summary'][$player]['matches_s'],
        'winrate_picked' => $report['players_summary'][$player]['winrate_s'],
        'matches_banned' => 0,
        'winrate_banned' => 0,
        'matches_total' => $report['players_summary'][$player]['matches_s'],
      ];
      
      if (!empty($report['teams']) && !empty($report['match_participants_teams'])) {
        $players_bans_disable = false;
  
        estimate_players_draft_processor_tvt_report($player_pb);
      } else {
        $players_bans_disable = false;
  
        estimate_players_draft_processor_pvp_report($player_pb);
      }
    }

    $draft_bans = [];

    foreach ($report['players_draft'][1] as $stage => $players) {
      $draft[$stage] = null;
      foreach ($players as $pl) {
        if ($pl['playerid'] == $player) $draft[$stage] = $pl;
      }
    }
    foreach ($report['players_draft'][0] as $stage => $players) {
      $draft_bans[$stage] = null;
      foreach ($players as $pl) {
        if ($pl['playerid'] == $player) $draft_bans[$stage] = $pl;
      }
    }

    $cs = $players_bans_disable ? 2 : 4;

    $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-draft\" class=\"list\"><caption>".locale_string("stage")."</caption><thead>";
    $res['playerid'.$player] .= "<tr class=\"overhead\">";
    foreach (array_keys($draft) as $stg) {
      $res['playerid'.$player] .= "<th".($stg > 1 ? " class=\"separator\"" : "")." colspan=\"$cs\">".locale_string("stage")." $stg</th>";
    }
    $res['playerid'.$player] .= "</tr>";
    $res['playerid'.$player] .= "<tr>";
    foreach (array_keys($draft) as $stg) {
      $res['playerid'.$player] .= "<th".($stg > 1 ? " class=\"separator\"" : "").">".locale_string("matches")."</th><th>".locale_string("winrate")."</th>";
      if (!$players_bans_disable) {
        $res['playerid'.$player] .= "<th>".locale_string("bans")."</th><th>".locale_string("winrate")."</th>";
      }
    }
    $res['playerid'.$player] .= "</tr></thead>";

    $res['playerid'.$player] .= "<tbody><tr>";
    foreach ($draft as $stg => $data) {
      if (!$data) $data = ['matches' => 0, 'winrate' => 0];
      if ($draft_bans[$stg]) {
        $data_bans = $draft_bans[$stg];
      } else {
        $data_bans = ['matches' => 0, 'winrate' => 0];
      }
      if ($data['matches']) {
        $res['playerid'.$player] .= "<td".($stg > 1 ? " class=\"separator\"" : "").">".$data['matches']."</td><td>".number_format($data['winrate']*100, 2)."%</td>";
      } else {
        $res['playerid'.$player] .= "<td".($stg > 1 ? " class=\"separator\"" : "").">-</td><td>-</td>";
      }
      if (!$players_bans_disable) {
        if ($data_bans['matches']) {
          $res['playerid'.$player] .= "<td>".$data_bans['matches']."</td><td>".number_format($data_bans['winrate']*100, 2)."%</td>";
        } else {
          $res['playerid'.$player] .= "<td>-</td><td>-</td>";
        }
      }
    }
    $res['playerid'.$player] .= "</tr></tbody></table>";
    if (!$players_bans_disable) {
      $res['playerid'.$player] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_draft_targeted_bans_estimate_explainer")."</div>".
        "</div>".
      "</details>";
    }
  }

  // positions
  if (isset($report['player_positions'])) {
    $roles = [];

    foreach ($report['player_positions'] as $isCore => $lanes) {
      foreach ($lanes as $lane => $players) {
        if (isset($players[$player])) $roles["$isCore.$lane"] = $players[$player];
      }
    }

    generate_positions_strings();

    uasort($roles, function($a, $b) {
      return $b['matches_s'] <=> $a['matches_s'];
    });

    $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-positions\" class=\"list\"><caption>".locale_string("positions")."</caption><thead><tr>".
      "<th>".locale_string("position")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("hero_pool")."</th>".
      "<th>".locale_string("kda")."</th>".
      "<th>".locale_string("gpm")."</th>".
      "<th>".locale_string("xpm")."</th>".
    "</tr></thead>";

    $res['playerid'.$player] .= "<tbody>";
    foreach ($roles as $role => $data) {
      $res['playerid'.$player] .= "<tr>".
        "<td>".locale_string("position_$role")."</td>".
        "<td>".number_format($data['matches_s'], 0)."</td>".
        "<td>".number_format($data['winrate_s']*100, 2)."%</td>".
        "<td>".number_format($data['hero_pool'], 0)."</td>".
        "<td>".number_format($data['kda'], 2)."</td>".
        "<td>".number_format($data['gpm'], 0)."</td>".
        "<td>".number_format($data['xpm'], 0)."</td>".
      "</tr>";
    }
    $res['playerid'.$player] .= "</tbody></table>";
  }

  // team
  if (isset($report['teams'])) {
    $teams = [];
    foreach ($report['teams'] as $tid => $data) {
      if (in_array($player, $data['active_roster'])) {
        $teams[$tid] = [
          'id' => $tid,
          'name' => $data['name'],
          'tag' => $data['tag'],
        ];
        if (isset($data['players_draft_pb'])) {
          $teams[$tid]['matches'] = $data['players_draft_pb'][ $player ]['matches_total'];
          $teams[$tid]['winrate'] = $data['players_draft_pb'][ $player ]['winrate_picked'];
        }
      }
    }

    uasort($teams, function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-teams\" class=\"list\"><caption>".locale_string("teams")."</caption><thead><tr>".
      "<th width=\"1%\"></th>".
      "<th>".locale_string("team")."</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
    "</tr></thead>";

    $res['playerid'.$player] .= "<tbody>";
    foreach ($teams as $data) {
      $res['playerid'.$player] .= "<tr>".
        "<td>".team_logo($data['id'])."</td>".
        "<td>".team_link($data['id'])."</td>".
        "<td>".(isset($data['matches']) ? number_format($data['matches'], 0) : '-')."</td>".
        "<td>".(isset($data['winrate']) ? number_format($data['winrate']*100, 2) : '-')."%</td>".
      "</tr>";
    }
    $res['playerid'.$player] .= "</tbody></table>";
  }

  return $res;
}

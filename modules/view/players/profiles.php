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
    else return ($a > $b) ? 1 : -1;
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

  // drafts data
  if (isset($report['players_draft'])) {
    $draft = [];
    foreach ($report['players_draft'][1] as $stage => $players) {
      $draft[$stage] = null;
      foreach ($players as $pl) {
        if ($pl['playerid'] == $player) $draft[$stage] = $pl;
      }
    }

    $res['playerid'.$player] .= "<table id=\"player-profile-pid$player-draft\" class=\"list\"><caption>".locale_string("stage")."</caption><thead>";
    $res['playerid'.$player] .= "<tr class=\"overhead\">";
    foreach (array_keys($draft) as $stg) {
      $res['playerid'.$player] .= "<th".($stg > 1 ? " class=\"separator\"" : "")." colspan=\"2\">".locale_string("stage")." $stg</th>";
    }
    $res['playerid'.$player] .= "</tr>";
    $res['playerid'.$player] .= "<tr>";
    foreach (array_keys($draft) as $stg) {
      $res['playerid'.$player] .= "<th".($stg > 1 ? " class=\"separator\"" : "").">".locale_string("matches")."</th><th>".locale_string("winrate")."</th>";
    }
    $res['playerid'.$player] .= "</tr></thead>";

    $res['playerid'.$player] .= "<tbody><tr>";
    foreach ($draft as $stg => $data) {
      if (!$data) $data = ['matches' => 0, 'winrate' => 0];
      if ($data['matches']) {
        $res['playerid'.$player] .= "<td".($stg > 1 ? " class=\"separator\"" : "").">".$data['matches']."</td><td>".number_format($data['winrate']*100, 2)."%</td>";
      } else {
        $res['playerid'.$player] .= "<td".($stg > 1 ? " class=\"separator\"" : "").">-</td><td>-</td>";
      }
    }
    $res['playerid'.$player] .= "</tr></tbody></table>";
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

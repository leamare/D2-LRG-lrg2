<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/series_link.php");

function rg_generator_matches_list($table_id, &$context, $series_filter = null) {
  global $report;
  if(!sizeof($context)) return "";
  $matches = array_keys($context);

  $res = "";
  if ($series_filter !== null) {
    $res .= "<div class=\"table-column-toggles\">".
      "<span class=\"table-column-toggles-name\">".series_matches_link($series_filter, "list")."</span>".
    "</div>";
  }
  $res .= search_filter_component($table_id);
  $res .= "<table id=\"$table_id\" class=\"list sortable\"><thead><tr>".
          "<th>".locale_string("match")."</th>".
          "<th data-sortInitialOrder=\"asc\">".locale_string("radiant")."</th>".
          "<th data-sortInitialOrder=\"asc\">".locale_string("dire")."</th>".
          "<th class=\"sorter-valuesort\">".locale_string("duration")."</th>".
          "<th>".locale_string("kills_combined")."</th>".
          (!empty($report['series']) ? "<th>".locale_string("meet_num")."</th>" : "").
          "<th class=\"sorter-valuesort\">".locale_string("date")."</th>".
        "</tr></thead><tbody>";
  foreach($matches as $mid) {
    if ($series_filter !== null && isset($report['match_parts_series_tag'])) {
      $series_tag = $report['match_parts_series_tag'][$mid] ?? null;
      $sid = ($report['series'][$series_tag]['seriesid'] ?? 0) ? $report['series'][$series_tag]['seriesid'] : $series_tag;
      if ($sid != $series_filter) {
        continue;
      }
    }

    if(isset($report['teams']) && isset($report['match_participants_teams'][$mid])) {
      if(isset($report['match_participants_teams'][$mid]['radiant']) &&
         isset($report['teams'][ $report['match_participants_teams'][$mid]['radiant'] ]['name']))
        $team_radiant = team_link($report['match_participants_teams'][$mid]['radiant']);
      else $team_radiant = "Radiant";
      if(isset($report['match_participants_teams'][$mid]['dire']) &&
         isset($report['teams'][ $report['match_participants_teams'][$mid]['dire'] ]['name']))
        $team_dire = team_link($report['match_participants_teams'][$mid]['dire']);
      else $team_dire = "Dire";
    } else {
      $team_radiant = locale_string("radiant");
      $team_dire = locale_string("dire");
    }

    $duration = (int)($report['matches_additional'][$mid]['duration']/3600);

    $duration = $duration ? $duration.":".(
          (int)($report['matches_additional'][$mid]['duration']%3600/60) < 10 ?
          "0".(int)($report['matches_additional'][$mid]['duration']%3600/60) :
          (int)($report['matches_additional'][$mid]['duration']%3600/60)
        ) : ((int)($report['matches_additional'][$mid]['duration']%3600/60));

    $duration = $duration.":".(
      (int)($report['matches_additional'][$mid]['duration']%60) < 10 ?
      "0".(int)($report['matches_additional'][$mid]['duration']%60) :
      (int)($report['matches_additional'][$mid]['duration']%60)
    );

    if (!empty($report['series'])) {
      $series_tag = $report['match_parts_series_tag'][$mid] ?? null;
      $series_id = ($report['series'][$series_tag]['seriesid'] ?? 0) ? $report['series'][$series_tag]['seriesid'] : $series_tag;
    }

    $res .= "<tr>".
            "<td>".$mid."</td>".
            "<td value=\"".(isset($report['match_participants_teams'][$mid]['radiant']) ? $report['match_participants_teams'][$mid]['radiant'] : 0)."\">".
              $team_radiant."</td>".
            "<td value=\"".(isset($report['match_participants_teams'][$mid]['dire']) ? $report['match_participants_teams'][$mid]['dire'] : 0)."\">".
              $team_dire."</td>".
            "<td value=\"".$report['matches_additional'][$mid]['duration']."\">".$duration."</td>".
            "<td>".($report['matches_additional'][$mid]['radiant_score']+$report['matches_additional'][$mid]['dire_score'])."</td>".
            (!empty($report['series']) ? "<td>".series_matches_link($series_id, "list")."</td>" : "").
            "<td value=\"".$report['matches_additional'][$mid]['date']."\">".
              date(locale_string("time_format")." ".locale_string("date_format"), $report['matches_additional'][$mid]['date'])."</td>".
            "</tr>";
  }
  $res .= "</tbody></table>";

  return $res;
}

function rg_generator_hero_matches_list($table_id, $hero, $limit = null, $wide = false, $mlist_raw = null) {
  global $report;

  if (empty($report['matches']) && empty($report['matches_additional'])) return "";

  if (empty($mlist_raw)) {
    $matcheslist = $report['matches'];
  } else {
    $matcheslist = [];
    foreach ($mlist_raw as $mid => $match) {
      $matcheslist[$mid] = $report['matches'][$mid];
    }
  }

  $keys = [
    'is_radiant' => true,
    'allies' => true,
    'opponents' => true,
    'radiant_win' => true,
    'duration' => true,
    'player' => true,
    'team' => false,
    'role' => false,
    'variant' => false,
  ];

  $matches = [];

  foreach ($matcheslist as $mid => $data) {
    usort($data, function($a, $b) {
      return $a['radiant'] <=> $b['radiant'];
    });

    $heroes = array_map(function($a) {
      return $a['hero'];
    }, $data);

    $index = array_search($hero, $heroes);

    if ($index !== false) {
      $radiant = $data[$index]['radiant'];
      if (!$keys['variant'] && !empty($data[$index]['var'])) {
        $keys['variant'] = true;
      }

      $matches[$mid] = [
        'is_radiant' => $radiant,
        'allies' => array_map(function($a) {
            return $a['hero'];
          }, array_filter($data, function($a) use ($radiant) { return $a['radiant'] == $radiant; })
        ),
        'opponents' => array_map(function($a) {
            return $a['hero'];
          }, array_filter($data, function($a) use ($radiant) { return $a['radiant'] != $radiant; })
        ),
        'radiant_win' => $report['matches_additional'][$mid]['radiant_win'],
        'duration' => $report['matches_additional'][$mid]['duration'],
        'player' => $data[$index]['player'],
        'variant' => $data[$index]['var'] ?? null,
      ];

      continue;
    }
  }

  if (isset($report['match_participants_teams'])) {
    $keys['team'] = true;

    foreach ($matches as $mid => $data) {
      if (isset($report['match_participants_teams'][$mid])) {
        $matches[$mid]['team_self'] = $report['match_participants_teams'][$mid][$data['is_radiant'] ? 'radiant' : 'dire'] ?? 'self';
        $matches[$mid]['team_enemy'] = $report['match_participants_teams'][$mid][$data['is_radiant'] ? 'dire' : 'radiant'] ?? 'enemy';
      } else {
        $matches[$mid]['team_self'] = 'self';
        $matches[$mid]['team_enemy'] = 'enemy';
      }
    }
  }

  if (isset($report['hero_positions_matches'])) {
    $keys['role'] = true;
    generate_positions_strings();

    for($i=0; $i<=1; $i++) {
      for($j=0; $j<=5; $j++) {
        if (!isset($report['hero_positions_matches'][$i][$j][$hero])) continue;

        foreach ($report['hero_positions_matches'][$i][$j][$hero] as $mid) {
          if (!isset($matches[$mid])) continue;
          $matches[$mid]['role'] = "$i.$j";
          $matches[$mid]['rolenum'] = $i ? $j : ($j == 1 ? 5 : 4);
        }
      }
    }
  }

  $res = "";

  if (!$limit || $limit > 10) {
    $res .= search_filter_component($table_id, $wide);
  }

  krsort($matches);

  $res .= "<table id=\"$table_id\" class=\"list sortable ".($wide ? 'wide' : '')."\"><thead><tr>".
    "<th class=\"sorter-valuesort\">".locale_string("match")."</th>".
    "<th>".locale_string("player")."</th>".
    ($keys['role'] ? "<th class=\"sorter-valuesort\">".locale_string("position")."</th>" : "").
    ($keys['variant'] ? "<th class=\"sorter-valuesort\">".locale_string("facet")."</th>" : "").
    "<th>".locale_string("side")."</th>".
    "<th>".locale_string("allies")."</th>".
    "<th>".locale_string("enemy")."</th>".
    "<th>".locale_string("won")."</th>".
    "<th class=\"sorter-valuesort\">".locale_string("duration")."</th>".
  "</tr>".
  "</thead><tbody>";

  if ($limit) {
    $i = 0;
  }

  if ($keys['variant']) {
    global $locale;
    include_locale($locale, "facets");
  }

  foreach ($matches as $mid => $data) {
    $posnum = $data['rolenum'];

    $res .= "<tr ".($keys['team'] ? "data-aliases=\"".team_name($data['team_self'])." ".team_name($data['team_enemy'])."\"" : "").">".
      "<td value=\"$mid\">".match_link($mid)."</td>".
      "<td>".player_link($data['player'])."</td>".
      ($keys['role'] ? "<td value=\"$posnum\">".locale_string(isset($data['role']) ? "position_".$data['role'] : "none")."</td>" : "").
      ($keys['variant'] ? "<td value=\"{$data['variant']}\">".facet_full_element($hero, $data['variant'] ?? 0)."</td>" : "").
      "<td>".locale_string($data['is_radiant'] ? 'radiant' : 'dire')."</td>".
      "<td>";
    
    foreach ($data['allies'] as $h) {
      $res .= "<a ".($h == $hero ? "class=\"hero-self\"" : "")." title=\"".hero_name($h)."\" data-aliases=\"".hero_tag($h)." ".hero_aliases($h)."\">".
        hero_icon($h).
      "</a>";
    }

    $res .= "</td><td>";

    foreach ($data['opponents'] as $h) {
      $res .= "<a ".($h == $hero ? "class=\"hero-self\"" : "")." title=\"".hero_name($h)."\" data-aliases=\"".hero_tag($h)." ".hero_aliases($h)."\">".
        hero_icon($h).
      "</a>";
    }

    $res .= "</td><td>".locale_string(!($data['radiant_win'] xor $data['is_radiant']) ? 'won' : 'lost')."</td>".
      "<td value=\"{$data['duration']}\">".convert_time_seconds($data['duration'])."</td>".
    "</tr>";

    if ($limit) {
      $i++;
      if ($i == $limit) break;
    }
  }

  $res .= "</tbody></table>";

  return $res;
}

function rg_generator_hero_matches_banned_list($table_id, $hero, $limit = null, $wide = false, $mlist_raw = null) {
  global $report;

  if (empty($report['matches']) && empty($report['matches_additional'])) return "";

  if (empty($mlist_raw)) {
    $matcheslist = $report['matches'];
  } else {
    $matcheslist = [];
    foreach ($mlist_raw as $mid => $match) {
      $matcheslist[$mid] = $report['matches'][$mid];
    }
  }

  $matches = [];

  foreach ($matcheslist as $mid => $data) {
    if (!isset($report['matches_additional'][$mid]['bans'])) continue;

    foreach ($report['matches_additional'][$mid]['bans'] as $bs) {
      foreach ($bs as $b) {
        if ($b[0] == $hero) {
          $matches[] = $mid;
          continue 3;
        }
      }
    }
  }

  rsort($matches);
  $res = "<div class=\"content-text\">".locale_string("desc_matches")."</div>";
  $res .= "<div class=\"content-cards\">";
  foreach($matches as $matchid) {
    $res .= match_card($matchid);
  }
  $res .= "</div>";

  return $res;
}

function rg_generator_series_list($table_id, $series) {
  global $report;
  if (empty($series)) return "";

  $groups = [
    // '_index' => [],
    'teams' => [],
    'heroes' => [],
    'time' => [],
  ];

  $priorities = [
    // '_index' => 0,
    'teams' => 1,
    'heroes' => 2,
    'time' => 0,
  ];

  $res = "";
  $res .= table_columns_toggle($table_id, array_keys($groups), true, $priorities, true);
  $res .= search_filter_component($table_id, true);
  $res .= "<table id=\"$table_id\" class=\"list sortable wide\"><thead><tr class=\"overhead\">".
    "<th data-col-group=\"_index\"></th>".
    "<th data-col-group=\"_index\"></th>".
    "<th class=\"separator\" colspan=\"2\" data-col-group=\"teams\">".locale_string("teams")."</th>".
    "<th data-col-group=\"teams\"></th>".
    "<th data-col-group=\"teams\"></th>".
    "<th class=\"separator\" colspan=\"3\" data-col-group=\"heroes\">".locale_string("series_heroes")."</th>".
    "<th class=\"separator\" colspan=\"2\" data-col-group=\"time\">".locale_string("series_duration")."</th>".
    "<th class=\"separator\" data-col-group=\"time\" class=\"sorter-valuesort\"></th>".
  "</tr><tr>".
    "<th data-col-group=\"_index\">".locale_string("meet_num")."</th>".
    "<th data-col-group=\"_index\">".locale_string("matches")."</th>".
    "<th class=\"separator\" width=\"10%\" data-col-group=\"teams\"></th>".
    "<th width=\"10%\" data-col-group=\"teams\"></th>".
    "<th data-col-group=\"teams\">".locale_string("winner")."</th>".
    "<th data-col-group=\"teams\">".locale_string("score")."</th>".
    "<th class=\"separator\" data-col-group=\"heroes\">".locale_string("picks")."</th>".
    "<th data-col-group=\"heroes\">".locale_string("bans")."</th>".
    "<th data-col-group=\"heroes\">".locale_string("series_heroes_other")."</th>".
    "<th class=\"separator\" data-col-group=\"time\" class=\"sorter-valuesort\">".locale_string("series_duration_delta")."</th>".
    "<th data-col-group=\"time\" class=\"sorter-valuesort\">".locale_string("series_duration_playtime")."</th>".
    "<th class=\"separator\" data-col-group=\"time\" class=\"sorter-valuesort\">".locale_string("start_date")."</th>".
  "</tr></thead><tbody>";

  foreach ($series as $series_tag => $series_data) {
    $series_id = ($series_data['seriesid'] ?? 0) ? $series_data['seriesid'] : $series_tag;
    $matches_count = count($series_data['matches']);

    $start_date = [];
    $end_date = [];
    $playtime = 0;

    $scores = [];
    $winner = null;
    $heroes_picks = [];
    $heroes_bans = [];
    $heroes_both = [];

    foreach ($series_data['matches'] as $match) {
      if (!isset($report['match_participants_teams'][$match])) continue;
      if (!isset($scores[$report['match_participants_teams'][$match]['radiant'] ?? 0]))  {
        $scores[$report['match_participants_teams'][$match]['radiant'] ?? 0] = 0;
      }
      $scores[$report['match_participants_teams'][$match]['radiant'] ?? 0] += $report['matches_additional'][$match]['radiant_win'] ? 1 : 0;
      if (!isset($scores[$report['match_participants_teams'][$match]['dire'] ?? 0]))  {
        $scores[$report['match_participants_teams'][$match]['dire'] ?? 0] = 0;
      }
      $scores[$report['match_participants_teams'][$match]['dire'] ?? 0] += $report['matches_additional'][$match]['radiant_win'] ? 0 : 1;

      foreach ($report['matches'][$match] as $l) {
        $heroes_picks[$l['hero']] = ($heroes_picks[$l['hero']] ?? 0) + 1;
      }
      foreach (($report['matches_additional'][$match]['bans'] ?? []) as $t) {
        foreach ($t as $b) {
          $heroes_bans[$b[0]] = ($heroes_bans[$b[0]] ?? 0) + 1;
        }
      }

      $playtime += $report['matches_additional'][$match]['duration'];
      $start_date[] = $report['matches_additional'][$match]['date'];
      $end_date[] = $report['matches_additional'][$match]['date'] + $report['matches_additional'][$match]['duration'];
    }

    $start_date_unix = !empty($start_date) ? min($start_date) : 0;
    $end_date_unix = !empty($end_date) ? max($end_date) : 0;
    $total_duration = $end_date_unix-$start_date_unix;

    if ($matches_count > 1) {
      $heroes_list = array_merge(array_keys($heroes_picks), array_keys($heroes_bans));
      $heroes_list = array_unique($heroes_list);
      foreach ($heroes_list as $hero) {
        if (($heroes_picks[$hero] ?? 0) + ($heroes_bans[$hero] ?? 0) == $matches_count && isset($heroes_bans[$hero]) && isset($heroes_picks[$hero])) {
          $heroes_both[$hero] = true;
        }
      }
      $heroes_picks = array_filter($heroes_picks, function($hero) use ($matches_count) {
        return $hero == $matches_count;
      });
      $heroes_bans = array_filter($heroes_bans, function($hero) use ($matches_count) {
        return $hero == $matches_count;
      });
    } else {
      $heroes_picks = [];
      $heroes_bans = [];
      $heroes_both = [];
    }

    $non_tie_factor = ($matches_count > 1 && ((array_sum($scores)/2) != max($scores))) || $matches_count == 1;

    if (!empty($scores) && $non_tie_factor) {
      $winner = array_search(max($scores), $scores);
    } else {
      $winner = null;
    }

    $teams = array_filter(array_keys($scores), function($team) use ($winner) {
      return $team != 0;
    });

    $res .= "<tr>".
      "<td value=\"$series_tag\" data-col-group=\"_index\">".series_matches_link($series_id, "cards")."</td>".
      "<td data-col-group=\"_index\">$matches_count</td>";
    for ($i = 0; $i < 2; $i++) {
      $res .= "<td ".($i==0 ? "class=\"separator\"" : "")." data-col-group=\"teams\">".($teams[$i] ? team_link($teams[$i]) : locale_string("team")." $i")."</td>";
    }

    $res .= "<td data-col-group=\"teams\">".($winner ? team_link_short($winner) : "<span class=\"placeholder\">".locale_string("tie")."</span>")."</td>".
      "<td data-col-group=\"teams\">".(implode("-", $scores))."</td>".
      "<td class=\"separator\" data-col-group=\"heroes\">".implode("", array_map(function($hero) {
        return hero_icon($hero);
      }, array_keys($heroes_picks)))."</td>".
      "<td data-col-group=\"heroes\">".implode("", array_map(function($hero) {
        return hero_icon($hero);
      }, array_keys($heroes_bans)))."</td>".
      "<td data-col-group=\"heroes\">".implode("", array_map(function($hero) {
        return hero_icon($hero);
      }, array_keys($heroes_both)))."</td>".
      "<td class=\"separator\" value=\"$total_duration\" data-col-group=\"time\">".convert_time_seconds($total_duration)."</td>".
      "<td value=\"$playtime\" data-col-group=\"time\">".convert_time_seconds($playtime)."</td>".
      "<td class=\"separator\" value=\"$start_date_unix\" data-col-group=\"time\">".date(locale_string("time_format")." ".locale_string("date_format"), $start_date_unix)."</td>".
    "</tr>";
  }

  $res .= "</tbody></table>";

  return $res;
}
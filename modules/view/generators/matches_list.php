<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_matches_list($table_id, &$context) {
  global $report;
  if(!sizeof($context)) return "";
  $matches = array_keys($context);

  $res = search_filter_component($table_id);
  $res .= "<table id=\"$table_id\" class=\"list sortable\"><thead><tr>".
          "<th>".locale_string("match")."</th>".
          "<th data-sortInitialOrder=\"asc\">".locale_string("radiant")."</th>".
          "<th data-sortInitialOrder=\"asc\">".locale_string("dire")."</th>".
          "<th class=\"sorter-valuesort\">".locale_string("duration")."</th>".
          "<th>".locale_string("kills_combined")."</th>".
          "<th class=\"sorter-valuesort\">".locale_string("date")."</th>".
        "</tr></thead><tbody>";
  foreach($matches as $mid) {
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

    $res .= "<tr>".
            "<td>".$mid."</td>".
            "<td value=\"".(isset($report['match_participants_teams'][$mid]['radiant']) ? $report['match_participants_teams'][$mid]['radiant'] : 0)."\">".
              $team_radiant."</td>".
            "<td value=\"".(isset($report['match_participants_teams'][$mid]['dire']) ? $report['match_participants_teams'][$mid]['dire'] : 0)."\">".
              $team_dire."</td>".
            "<td value=\"".$report['matches_additional'][$mid]['duration']."\">".$duration."</td>".
            "<td>".($report['matches_additional'][$mid]['radiant_score']+$report['matches_additional'][$mid]['dire_score'])."</td>".
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
      ($keys['variant'] ? "<td value=\"{$data['variant']}\">".facet_full_element($hero, $data['variant'])."</td>" : "").
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
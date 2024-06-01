<?php

function join_matches($matches) {
  $output = [];
  foreach($matches as $match) {
    $output[] = match_link($match);
  }
  // return implode(", ", $output);

  return "<div class=\"match-link-modal\">".implode("</div><div class=\"match-link-modal\">", $output)."</div>";
}

function join_matches_add($matches, $ishero, $id) {
  global $report;

  if (!isset($report['matches_additional']) || !isset($report['match_participants_teams']) || !isset($report['matches'])) {
    return join_matches($matches);
  }

  $output = [];
  foreach($matches as $match) {
    $rw = $report['matches_additional'][$match]['radiant_win'];
    $rad = null;
    foreach ($report['matches'][$match] as $l) {
      if ($l[ $ishero ? 'hero' : 'player' ] == $id) {
        $rad = $l['radiant'];
        break;
      }
    }

    if ($rad !== null) {
      $output[] = match_link(
        $match,
        $rad ? 
          $report['match_participants_teams'][$match]['radiant'] ?? -1 :
          $report['match_participants_teams'][$match]['dire'] ?? -2,
        $rw == $rad
      );
    } else {
      $output[] = match_link($match);
    }
  }
  // return implode(", ", $output);

  return "<div class=\"match-link-modal\">".implode("</div><div class=\"match-link-modal\">", $output)."</div>";
}

function join_matches_team($matches, $team) {
  global $report;

  $output = [];
  foreach($matches as $match) {
    $iswin = null;

    if (isset($report['matches_additional'][$match])) {
      $iswin = $report['matches_additional'][$match]['radiant_win'] == (($report['match_participants_teams'][$match]['radiant'] ?? 0) == $team);
    }

    $output[] = match_link($match, $team, $iswin);
  }
  return "<div class=\"match-link-modal\">".implode("</div><div class=\"match-link-modal\">", $output)."</div>";
}

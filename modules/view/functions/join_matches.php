<?php

function join_matches($matches) {
  $output = [];
  foreach($matches as $match) {
    $output[] = match_link($match);
  }
  // return implode(", ", $output);

  return "<div class=\"match-link-modal\">".implode("</div><div class=\"match-link-modal\">", $output)."</div>";
}

function join_matches_add($matches, $ishero, $id, $variants = false) {
  global $report;

  // !isset($report['match_participants_teams']) || 
  if (!isset($report['matches_additional']) || !isset($report['matches'])) {
    return join_matches($matches);
  }

  $output = [];
  foreach($matches as $match) {
    $rw = $report['matches_additional'][$match]['radiant_win'];
    $rad = null;
    $variant = null;
    foreach ($report['matches'][$match] as $l) {
      if ($l[ $ishero ? 'hero' : 'player' ] == $id) {
        $rad = $l['radiant'];
        $variant = $l['var'] ?? null;
        break;
      }
    }

    if ($rad !== null) {
      $output[] = "<span class=\"match-link-modal\">".
        ($variants && $ishero ? 
          facet_micro_element($id, $variant, false).' ' :
          ''
        ).
        match_link(
          $match,
          $rad ? 
            $report['match_participants_teams'][$match]['radiant'] ?? -1 :
            $report['match_participants_teams'][$match]['dire'] ?? -2,
          $rw == $rad
        ).
      "</span>";
    } else {
      $output[] = "<span class=\"match-link-modal\">".
        match_link($match).
      "</span>";
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

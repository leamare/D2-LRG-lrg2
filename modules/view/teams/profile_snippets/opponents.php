<?php 

$tvt = rg_generator_tvt_unwrap_data($report['tvt'], $report['teams']);

$tvt[0] = [];

foreach ($report['match_participants_teams'] as $mid => $teams) {
  if (empty($teams['dire'])) $teams['dire'] = 0;
  if (empty($teams['radiant'])) $teams['radiant'] = 0;

  if (isset($report['matches_additional'])) {
    if (!isset($tvt[$teams['dire']][$teams['radiant']])) {
      $tvt[$teams['dire']][$teams['radiant']] = [
        'matches' => 0,
        'wins' => 0,
        'matchids' => []
      ];
    }

    if (!$teams['radiant']) {
      $tvt[$teams['dire']][$teams['radiant']]['matches']++;
      if ($report['matches_additional'][$mid]['radiant_win']) 
        $tvt[$teams['dire']][$teams['radiant']]['wins']++;
    }

    if (!isset($tvt[$teams['radiant']][$teams['dire']])) {
      $tvt[$teams['radiant']][$teams['dire']] = [
        'matches' => 0,
        'wins' => 0,
        'matchids' => []
      ];
    }

    if (!$teams['dire']) {
      $tvt[$teams['radiant']][$teams['dire']]['matches']++;
      if (!$report['matches_additional'][$mid]['radiant_win']) 
        $tvt[$teams['radiant']][$teams['dire']]['wins']++;
    }
  }

  if (!isset($tvt[$teams['dire']][$teams['radiant']]['matchids'])) {
    $tvt[$teams['dire']][$teams['radiant']]['matchids'] = [];
  }
  $tvt[$teams['dire']][$teams['radiant']]['matchids'][] = $mid;

  if (!isset($tvt[$teams['radiant']][$teams['dire']]['matchids'])) {
    $tvt[$teams['radiant']][$teams['dire']]['matchids'] = [];
  }
  $tvt[$teams['radiant']][$teams['dire']]['matchids'][] = $mid;
}

uasort($tvt[$tid], function ($a, $b) {
  return $b['matches'] <=> $a['matches'];
});

global $leaguetag;

$res["team".$tid]['opponents'] .= search_filter_component("teams-$tid-opponents");

$res["team".$tid]['opponents'] .= "<table id=\"teams-$tid-opponents\" class=\"list sortable\">
  <thead><tr>".
    "<th></th>".
    "<th data-sortInitialOrder=\"asc\">".locale_string("team_name")."</th>".
    "<th>".locale_string("matches")."</th>".
    "<th>".locale_string("winrate")."</th>".
    (isset($report['match_participants_teams']) ? "<th>".locale_string("matchlinks")."</th>" : "").
  "</tr></thead><tbody>";

foreach ($tvt[$tid] as $opid => $data) {
  if (!$data['matches']) continue;
  if (!isset($data['winrate'])) $data['winrate'] = $data['wins']/$data['matches'];

  $optag = $opid ? "optid$opid" : "unteamed";

  $res["team".$tid]['opponents'] .= "<tr>".
    "<td>".team_logo($opid)."</td>".
    "<td>".team_link($opid)."</td>".
    "<td>".$data['matches']."</td>".
    "<td>".number_format($data['winrate']*100,2)."%</td>".
    // (isset($data['matchids']) ? "<td><a onclick=\"showModal('".
    //   htmlspecialchars(join_matches($data['matchids'])).
    //   "', '".locale_string("matches")." - ".addcslashes(team_name($tid)." vs. ".team_name($opid), "'")."');\">".
    //   locale_string("matches")."</a></td>" : ""
    // ).
    (isset($data['matchids']) 
      ? "<td><a href=\"?league=$leaguetag&mod=teams-profiles-team$tid-matches-opponents-$optag".(empty($linkvars) ? "" : "&".$linkvars)."\">".
      locale_string("matches")."</a></td>" : ""
    ).
  "</tr>";
}

$res["team".$tid]['opponents'] .= "</tbody></table>";
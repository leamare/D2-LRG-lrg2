<?php 

include_once(__DIR__ . "/../../view/generators/tvt_unwrap_data.php");

$endpoints['teams'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (in_array("cards", $mods)) {
    $res = [];
    $tids = array_keys($report['teams']);
    foreach($tids as $tid) {
      $res[] = team_card($tid);
    }
    return $res;
  }

  if (in_array("grid", $mods)) {
    $tvt = rg_generator_tvt_unwrap_data($report['tvt'], $report['teams']);

    if(!sizeof($tvt)) return null;

    if (in_array("raw", $mods))
      return $tvt;
    if (in_array("source", $mods))
      return $report['tvt'];
  
    $team_ids = array_keys($tvt);

    $res = [];
  
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
          if (isset($context[$tid][$team_ids[$i]]['matchids']))
            $res[$tid][ $team_ids[$i] ]['matches'] = $context[$tid][$team_ids[$i]]['matchids'];
        }
      }
    }

    return $res;
  }

  if (in_array("participants", $mods)) 
    return $endpoints['participants']($mods, $vars, $report);
  return $endpoints['teams_raw']($mods, $vars, $report);
};

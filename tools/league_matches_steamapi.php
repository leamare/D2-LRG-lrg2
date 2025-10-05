<?php
require_once('head.php');

$_file = !empty($options['o']) ? $options['o'] : "matchlists/$lrg_league_tag.list";

$_ticket = !empty($options['T']) ? $options['T'] : $lg_settings['league_id'];

$_force_all = isset($options['f']) ? true : false;

$matches = [];
$i = 0;

if(empty($_ticket)) $out = "";
else {
  $request = "https://api.steampowered.com/IDOTA2Match_570/GetMatchHistory/v0001/?key=".$steamapikey."&league_id=".$_ticket;
  echo "[ ] Requested...";

  $matches = array();
  $response = json_decode(file_get_contents($request), true);

  do {
    echo "OK [".sizeof($response['result']['matches'])."] ";
    if(isset($last_matchid)) $last_tail = $last_matchid;
    else $last_tail = 0;

    foreach($response['result']['matches'] as $r_match) {
      $last_matchid = $r_match['match_id'];

      if (!$_force_all) {
        if($lg_settings['time_limit_after'] != null && $lg_settings['time_limit_after'] > $r_match['start_time'])
          continue;
        if($lg_settings['time_limit_after'] != null && $lg_settings['time_limit_before'] < $r_match['start_time'])
          continue;
        if($lg_settings['match_limit_after'] != null && $lg_settings['match_limit_after'] > $r_match['match_id'])
          continue;
        if($lg_settings['match_limit_before'] != null && $lg_settings['match_limit_before'] < $r_match['match_id'])
          continue;
      }

      if(!in_array($r_match['match_id'], $matches)) {
        $matches[] = $r_match['match_id'];
      }
    }
    if (isset($last_matchid))
      $response = json_decode(file_get_contents($request."&start_at_match_id=".$last_matchid), true);
  } while (isset($last_matchid) && sizeof($response['result']['matches']) > 2 && $last_matchid != $last_tail);

  $out = implode("\n", $matches);
}

file_put_contents($_file, $out);

echo "\n";

<?php

function fetch_league_info($league_id) {
  $api_url = "https://www.dota2.com/webapi/IDOTA2League/GetLeaguesData/v001?league_ids={$league_id},&delay_seconds=0";
  
  try {
    $response = @file_get_contents($api_url);
  
    if ($response === false) {
    return null;
  }
  
  $data = json_decode($response, true);
  if (!isset($data['leagues']) || empty($data['leagues'])) {
    return null;
  }
  
  $league = $data['leagues'][0];
  $info = $league['info'] ?? null;
  
  if (!$info) {
    return null;
  }
  
  return [
    'ticket_id' => $info['league_id'] ?? $league_id,
    'name' => $info['name'] ?? "League #{$league_id}",
    'url' => $info['url'] ?? null,
    'description' => $info['description'] ?? null,
  ];
  } catch (\Exception $e) {
    return null;
  }
}
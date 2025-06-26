<?php

function generate_fantasy_mvp($match, $matchlines, $adv_matchlines) {
  if (empty($adv_matchlines)) {
    return [ [], [] ];
  }
  
  $fantasy = [];
  $mvp = [];
  $players_points = [];

  $dMult = 25 / (((int)$match['duration']) / 60);
  $dMultRev = 2 - $dMult;

  foreach ($matchlines as $i => $pl) {
    $pts = [];
    
    $res = 0;
    $apl = $adv_matchlines[$i];

    $pts['hero_id'] = $pl['heroid'];
    $pid = $pts['player_id'] = $pl['playerid'];

    $pts['role'] = $apl['role'];
    $pts['isWon'] = $pl['isRadiant'] == $match['radiantWin'];
    $pts['isCore'] = $apl['isCore']; 

    $pts['kills'] = $pl['kills'] * 1.25 * (1+(0.35*($apl['role']-2)));
    $pts['deaths'] = -($pl['deaths'] ? 2 + $pl['deaths'] * 1.5 : 0) / (1+(0.1*($apl['role']-3)));
    $pts['buybacks'] = -$apl['buybacks'] * 0.25;
    $pts['assists'] = $pl['assists'] * 1.25 * ($apl['role'] < 3 ? 1 : 1+(0.2*($apl['role']-2)));
    $pts['creeps'] = ($pl['lastHits'] + $pl['denies']) * 0.0825 * (1+(0.1*($apl['role']-2))) * $dMult;
    
    $pts['gpm'] = $pl['gpm'] * 0.055 * ($apl['role'] < 3 ? 1 : 1+(0.1*($apl['role']-2)));
    $pts['xpm'] = $pl['xpm'] * 0.035 * ($apl['role'] < 3 ? 1 : 1+(0.1*($apl['role']-2)));

    if ($pl['heroid'] == 73) {
      $pts['gpm'] *= 0.65;
      $pts['xpm'] *= 0.65;
      $pts['creeps'] *= 0.65;
    }

    // $pts['obs_placed'] = log(1 + ($pl['obs_placed'])) * 7;
    $pts['obs_placed'] = $apl['wards'] * 2.0 * $dMult;

    $pts['stacks'] = $apl['stacks'] * 1.5;
    // $pts['runes'] = $pl['rune_pickups'] * 1.5;
    $pts['stuns'] = ($apl['stuns'] * 0.65) * ($apl['role'] < 3 ? 1 : 1+(0.1*($apl['role']-2)));
    $pts['teamfight_part'] = $apl['teamfight_part'] * 25 * $dMultRev;

    // $pts['smokes'] = ($pl['item_uses']['smoke_of_deceit'] ?? 0) * 2.5;

    $pts['damage'] = $pl['heroDamage'] * 0.0008 * (1+(0.2*($apl['role']-3)));

    if ($pl['heroid'] == 8) {
      $pl['heal'] *= 0.2;
    }
    $pts['healing'] = $pl['heal'] * 0.005;
    if ($pl['heroid'] == 94) {
      $apl['damage_taken'] /= 0.075;
    }
    $pts['damage_taken'] = $apl['damage_taken'] * 0.00075;

    if ($pl['heroDamage'] - $apl['damage_taken'] > 0.2*$pl['heroDamage']) {
      $pts['hero_damage_taken_bonus'] = $pl['heroDamage'] * (($pl['heroDamage'] - $apl['damage_taken'])/($pl['heroDamage']+1)) * 0.00075;
      $pts['hero_damage_taken_bonus'] *= 1 + (3-abs($apl['role'] - 3)) * 0.1;
    } else if ($pl['heroDamage'] - $apl['damage_taken'] < -0.2*($pl['heroDamage']+$apl['damage_taken'])) {
      $pts['hero_damage_taken_penalty'] = -($apl['damage_taken']-$pl['heroDamage']) * 0.00045 * (1-0.2*$apl['role']);
    } else {
      $pts['damage_taken'] *= 1.25 * max($apl['damage_taken'], $pl['heroDamage'])/(min($apl['damage_taken'], $pl['heroDamage'])+1);
    }
    $pts['damage_taken'] *= 1 + (3-abs($apl['role'] - 3))*0.125;

    $pts['tower_damage'] = $pl['towerDamage'] * 0.0035 * (1+(0.5*($apl['role']-2))) * $dMultRev;

    $pts['obs_kills'] = $apl['wards_destroyed'] * 1.75 * ($apl['role'] < 3 ? 1 : 1+(0.2*($apl['role']-2))) * $dMultRev;
    // $res += $pl['sentry_kills'] * 0.2;
    $pts['cour_kills'] = $apl['couriers_killed'] * 0.75 * $dMultRev;

    $_pts = $pts;
    unset($_pts['hero_id']);
    unset($_pts['player_id']);
    unset($_pts['role']);
    unset($_pts['isWon']);
    unset($_pts['isCore']);
    
    $res = array_sum($_pts);// * ($pts['multi'] ?? 1);

    $fantasy[ $pid ] = $res;
    $pts['total_points'] = $res;
    $players_points[ $pid ] = $pts;
  }
  
  // $mvp[$m]['lvp'] = array_search(min($fantasy[$m]), $fantasy[$m]);

  $cores = []; $supports = []; $losing = []; $winning = [];

  foreach ($players_points as $pid => $data) {
    if ($data['isCore']) {
      $cores[ $pid ] = $fantasy[ $pid ];
    } else {
      $supports[ $pid ] = $fantasy[ $pid ];
    }
    if (!$data['isWon']) {
      $losing[ $pid ] = $fantasy[ $pid ];
    } else {
      $winning[ $pid ] = $fantasy[ $pid ];
    }
  }

  $mvp['mvp'] = array_search(max($winning), $winning);

  if (isset($cores[ $mvp['mvp'] ])) unset($cores[ $mvp['mvp'] ]);
  if (isset($supports[ $mvp['mvp'] ])) unset($supports[ $mvp['mvp'] ]);

  $mvp['mvp_losing'] = array_search(max($losing), $losing);

  if (isset($cores[ $mvp['mvp_losing'] ])) unset($cores[ $mvp['mvp_losing'] ]);
  if (isset($supports[ $mvp['mvp_losing'] ])) unset($supports[ $mvp['mvp_losing'] ]);

  $mvp['core'] = array_search(max($cores), $cores);
  $mvp['support'] = array_search(max($supports), $supports);
  $mvp['lvp'] = array_search(min($fantasy), $fantasy);

  asort($fantasy);
  asort($winning);
  asort($losing);

  $avg = array_sum($losing)/5;
  $second = array_values($fantasy)[1];
  $min = min($fantasy);

  if (($avg - $min) < ($avg * 0.275) || $second - $min < $second * 0.175) {
    $mvp['lvp'] = 0;
  }

  // $avg = (array_values($fantasy[$m])[ floor(count($fantasy[$m]) * 0.5) ] + array_values($fantasy[$m])[ ceil(count($fantasy[$m]) * 0.5) ]) / 2;
  // if (($avg - min($fantasy[$m])) < ($avg * 0.45)) {
  //   $mvp[$m]['lvp'] = 0;
  // }

  $t_points = [];
  $t_awards = [];

  foreach ($players_points as $pid => $data) {
    $t_points[] = [
      'matchid' => $match['matchid'],
      'playerid' => $pid,
      'heroid' => $data['hero_id'],
      'total_points' => $data['total_points'],
      'kills' => $data['kills'],
      'deaths' => $data['deaths'],
      'assists' => $data['assists'],
      'creeps' => $data['creeps'],
      'gpm' => $data['gpm'],
      'xpm' => $data['xpm'],
      'obs_placed' => $data['obs_placed'],
      'stacks' => $data['stacks'],
      'stuns' => $data['stuns'],
      'teamfight_part' => $data['teamfight_part'],
      'damage' => $data['damage'],
      'healing' => $data['healing'],
      'damage_taken' => $data['damage_taken'],
      'hero_damage_taken_bonus' => $data['hero_damage_taken_bonus'] ?? 0,
      'hero_damage_taken_penalty' => $data['hero_damage_taken_penalty'] ?? 0,
      'tower_damage' => $data['tower_damage'],
      'obs_kills' => $data['obs_kills'],
      'cour_kills' => $data['cour_kills'],
      'buybacks' => $data['buybacks'],
    ];
    
  }

  foreach ($mvp as $key => $pid) {
    if (!$pid) continue;

    $line = [
      'matchid' => $match['matchid'],
      'playerid' => $pid,
      'heroid' => $players_points[ $pid ]['hero_id'],
      'total_points' => $players_points[ $pid ]['total_points'],
      'mvp' => 0,
      'mvp_losing' => 0,
      'core' => 0,
      'support' => 0,
      'lvp' => 0,
    ];

    $line[$key] = 1;
    $t_awards[] = $line;
  }

  return [ $t_points, $t_awards ];
}

function create_fantasy_mvp_tables(&$conn) {
  echo "[ ] Creating table `fantasy_mvp_points`...";
  $conn->query("CREATE TABLE `fantasy_mvp_points` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `heroid` smallint(6) NOT NULL,
    `total_points` DOUBLE NOT NULL,
    `kills` FLOAT NOT NULL,
    `deaths` FLOAT NOT NULL,
    `assists` FLOAT NOT NULL,
    `creeps` FLOAT NOT NULL,
    `gpm` FLOAT NOT NULL,
    `xpm` FLOAT NOT NULL,
    `obs_placed` FLOAT NOT NULL,
    `stacks` FLOAT NOT NULL,
    `stuns` FLOAT NOT NULL,
    `teamfight_part` FLOAT NOT NULL,
    `damage` FLOAT NOT NULL,
    `healing` FLOAT NOT NULL,
    `damage_taken` FLOAT NOT NULL,
    `hero_damage_taken_bonus` FLOAT NOT NULL,
    `hero_damage_taken_penalty` FLOAT NOT NULL,
    `tower_damage` FLOAT NOT NULL,
    `obs_kills` FLOAT NOT NULL,
    `cour_kills` FLOAT NOT NULL,
    `buybacks` FLOAT NOT NULL,
    KEY `fantasy_breakdown_matchid_heroid_IDX` (`matchid`,`heroid`) USING BTREE,
    UNIQUE KEY `fantasy_breakdown_matchid_playerid_IDX` (`matchid`,`playerid`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  if ($conn->connect_error) {
    throw new Exception("[F] Can't create table `fantasy_mvp_points`: ".$conn->connect_error."\n");
  }
  echo "OK\n";

  $conn->query("ALTER TABLE `fantasy_mvp_points` ADD PRIMARY KEY (`matchid`,`playerid`);");
  if ($conn->connect_error) {
    throw new Exception("[F] Can't add key to `fantasy_mvp_points`: ".$conn->connect_error."\n");
  }

  echo "[ ] Creating table `fantasy_mvp_awards`...";
  $conn->query("CREATE TABLE `fantasy_mvp_awards` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `heroid` smallint(6) NOT NULL,
    `total_points` FLOAT NOT NULL,
    `mvp` tinyint(1) NOT NULL,
    `mvp_losing` tinyint(1) NOT NULL,
    `core` tinyint(1) NOT NULL,
    `support` tinyint(1) NOT NULL,
    `lvp` tinyint(1) NOT NULL,
    KEY `fantasy_awards_matchid_heroid_IDX` (`matchid`,`heroid`) USING BTREE,
    UNIQUE KEY `fantasy_awards_matchid_playerid_IDX` (`matchid`,`playerid`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  if ($conn->connect_error) {
    throw new Exception("[F] Can't create table `fantasy_mvp_awards`: ".$conn->connect_error."\n");
  }
  echo "OK\n";

  $conn->query("ALTER TABLE `fantasy_mvp_awards` ADD PRIMARY KEY (`matchid`,`playerid`);");
  if ($conn->connect_error) {
    throw new Exception("[F] Can't add key to `fantasy_mvp_awards`: ".$conn->connect_error."\n");
  }
}

function load_mvp_from_live_cache(string $file, array $matchlines) {
  if (!file_exists($file)) {
    return [ [], [] ];
  }
  $cache = json_decode(file_get_contents($file), true);

  if (empty($cache)) {
    return [ [], [] ];
  }

  
  $t_points = [];
  $t_awards = [];

  $match = reset($cache['result']);
  $matchid = $cache['matches'][0];

  foreach ($matchlines as $i => $pl) {
    $match['detailed_breakdowns']['players'][ $pl['playerid'] ]['hero_id'] = $pl['heroid'];
  }

  foreach ($match['detailed_breakdowns']['players'] as $pid => $data) {
    $t_points[] = [
      'matchid' => $matchid,
      'playerid' => $pid,
      'heroid' => $data['hero_id'],
      'total_points' => $match['players_points'][$pid],
      'kills' => $data['breakdown']['kills'],
      'deaths' => $data['breakdown']['deaths'],
      'assists' => $data['breakdown']['assists'],
      'creeps' => $data['breakdown']['creeps'],
      'gpm' => $data['breakdown']['gpm'],
      'xpm' => $data['breakdown']['xpm'],
      'obs_placed' => $data['breakdown']['obs_placed'],
      'stacks' => $data['breakdown']['stacks'],
      'stuns' => $data['breakdown']['stuns'],
      'teamfight_part' => $data['breakdown']['teamfight_part'],
      'damage' => $data['breakdown']['damage'],
      'healing' => $data['breakdown']['healing'],
      'damage_taken' => $data['breakdown']['damage_taken'],
      'hero_damage_taken_bonus' => $data['breakdown']['hero_damage_taken_bonus'] ?? 0,
      'hero_damage_taken_penalty' => $data['breakdown']['hero_damage_taken_penalty'] ?? 0,
      'tower_damage' => $data['breakdown']['tower_damage'],
      'obs_kills' => $data['breakdown']['obs_kills'],
      'cour_kills' => $data['breakdown']['cour_kills'],
      'buybacks' => $data['breakdown']['buybacks'],
    ];
  }

  foreach ($match['awards'] as $award => $pid) {
    if (!$pid) continue;

    $line = [
      'matchid' => $matchid,
      'playerid' => $pid,
      'heroid' => $match['detailed_breakdowns']['players'][ $pid ]['hero_id'],
      'total_points' => $match['players_points'][$pid],
      'mvp' => 0,
      'mvp_losing' => 0,
      'core' => 0,
      'support' => 0,
      'lvp' => 0,
    ];

    $line[$award] = 1;
    $t_awards[] = $line;
  }

  return [ $t_points, $t_awards ];
}
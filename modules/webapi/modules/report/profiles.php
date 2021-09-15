<?php 

$repeatVars['profiles'] = ['team', 'heroid', 'playerid', 'itemid'];

$endpoints['profiles'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  // hero
  if (in_array("heroes", $mods)) {
    if (!isset($vars['heroid'])) {
      throw new \Exception("Need to specify hero");
    }
    $res = [];

    $res['__endp'] = 'heroes-profiles';
    $res['__stopRepeater'] = ['team', 'playerid', 'itemid'];

    // summary
    if (is_wrapped($report['hero_summary'])) $report['hero_summary'] = unwrap_data($report['hero_summary']);
    if (!isset($report['hero_summary'][ $vars['heroid'] ])) throw new \Exception("Hero `${$vars['heroid']}` is not in the report");
    $res['summary'] = $report['hero_summary'][ $vars['heroid'] ];

    // pickban data
    $pb = $endpoints['pickban']($mods, $vars, $report);
    $res['pickban'] = $pb['pickban'][ $vars['heroid'] ];

    // drafts data
    if (isset($report['draft'])) {
      $draft = $endpoints['draft']($mods, $vars, $report);
      $res['draft'] = [];
      $res['draft']['total'] = $draft['total'][ $vars['heroid'] ];
      $res['draft']['stages'] = [];
      foreach($draft['stages'][ $vars['heroid'] ] as $i => $stage) {
        $res['draft']['stages'][$i] = $stage ?? null;
      }
    }

    // pairs
    if (isset($report['hph'])) {
      $pairs = $endpoints['hph']($mods, $vars, $report);
      $res['pairs'] = $pairs['pairs'];
    }

    // counters
    if (isset($report['hvh'])) {
      $pairs = $endpoints['hvh']($mods, $vars, $report);
      $res['counters'] = $pairs['opponents'];
    }

    // laning
    if (isset($report['hero_laning'])) {
      $res['laning'] = $endpoints['laning']($mods, $vars, $report);
    }

    // game length data
    if (isset($report['hero_winrate_timings'])) {
      $res['duration'] = $endpoints['wrtimings']($mods, $vars, $report)[ $vars['heroid'] ];
    }

    // player experience
    if (isset($report['hero_winrate_spammers'])) {
      $res['wrplayers'] = $endpoints['wrplayers']($mods, $vars, $report)[ $vars['heroid'] ];
    }

    // factions
    if (isset($report['hero_sides'])) {
      $req = $endpoints['sides']($mods, $vars, $report);
      $res['sides'][0] = $req[0][ $vars['heroid'] ] ?? null;
      $res['sides'][1] = $req[1][ $vars['heroid'] ] ?? null;
    }

    // positions
    if (isset($report['hero_positions'])) {
      $req = $endpoints['positions']($mods, $vars, $report);
      unset($req['total']);
      $res['positions'] = [];
      foreach($req as $role => $heroes) {
        $res['positions'][$role] = $heroes[ $vars['heroid'] ] ?? null;
      }
    }

    // trends
    if (isset($report['hero_daily_wr'])) {
      $req = $endpoints['daily_wr']($mods, $vars, $report);
      $res['daily_wr'] = $req[ $vars['heroid'] ];
    }

    // items
    if (isset($report['items'])) {
      $req = $endpoints['items-stats']($mods, $vars, $report);
      $res['items'] = $req['items'];
    }

    // teams
    if (isset($report['teams'])) {
      $res['teams'] = [];
      foreach($report['teams'] as $tid => $team) {
        if (isset($team['pickban'][ $vars['heroid'] ])) {
          $res['teams'][ $tid ] = $team['pickban'][ $vars['heroid'] ];
        }
      }
    }

    // players
    if (isset($report['matches']) && isset($report['players'])) {
      $res['players'] = [];
      foreach ($report['matches'] as $mid => $heroes) {
        foreach ($heroes as $hero) {
          if ($hero['hero'] != $vars['heroid']) continue;
          if (!isset($res['players'][ $hero['player'] ])) {
            $res['players'][ $hero['player'] ] = [
              'name' => player_name($hero['player']),
              'wins' => 0,
              'matches' => 0,
            ];
          }
          $res['players'][ $hero['player'] ]['matches']++;
          if ($hero['radiant'] == $report['matches_additional'][$mid]['radiant_win'])
            $res['players'][ $hero['player'] ]['wins']++;
        }
      }
    }

    // regions: summary, pickban
    if (isset($report['regions_data'])) {
      $res['regions'] = [];

      foreach($report['regions_data'] as $rid => $data) {
        if (!isset($data['pickban'][ $vars['heroid'] ])) continue;

        $res['regions'][$rid] = [];
        $res['regions'][$rid]['pickban'] = $data['pickban'][ $vars['heroid'] ];
        
        if (isset($data['hero_positions'])) {
          $res['regions'][$rid]['positions'] = [];
          foreach($data['hero_positions'] as $sup => $lanes) {
            if (empty($lanes)) continue;
            foreach($lanes as $lane => $heroes) {
              if (!isset($heroes[ $vars['heroid'] ])) continue;
              $res['regions'][$rid]['positions'][$sup.'.'.$lane] = $heroes[ $vars['heroid'] ];
            }
          }
        }
      }
    }

    return $res;
  }

  // player
  if (in_array("players", $mods)) {
    if (!isset($vars['playerid'])) {
      throw new \Exception("Need to specify player");
    }
    if (!isset($report['players'])) {
      throw new \Exception("Players data is not available for this report");
    }

    $res = [];

    $res['__endp'] = 'players-profiles';
    $res['__stopRepeater'] = ['team', 'heroid', 'itemid'];

    // if (empty($vars['gets']) || $vars['gets'] == '*') {
    //   // $vars['gets'] = [ 'total', 'heroes', 'heroes-matches', 'heroes-rank-top', 'heroes-rank-bot', 'records', 'records-best' ];
    //   $vars['gets'] = [ 'total', 'heroes-purchases', 'heroes-rank-top', 'heroes-rank-bot', 'records-best' ];
    // }

    $res['name'] = player_name($vars['playerid'], false);
    $res['name_tagged'] = player_name($vars['playerid']);

    // summary
    if (is_wrapped($report['players_summary'])) $report['players_summary'] = unwrap_data($report['players_summary']);
    if (!isset($report['players_summary'][ $vars['playerid'] ])) throw new \Exception("Player `${$vars['playerid']}` is not in the report");
    $res['summary'] = $report['players_summary'][ $vars['playerid'] ];

    // drafts data
    if (isset($report['players_draft'])) {
      $draft = $endpoints['draft']($mods, $vars, $report);
      $res['draft'] = [];
      $res['draft']['total'] = $draft['total'][ $vars['playerid'] ];
      $res['draft']['stages'] = $draft['stages'][ $vars['playerid'] ];
    }

    // positions
    if (isset($report['player_positions'])) {
      $req = $endpoints['positions']($mods, $vars, $report);
      unset($req['total']);
      $res['positions'] = [];
      foreach($req as $role => $heroes) {
        $res['positions'][$role] = $heroes[ $vars['playerid'] ] ?? null;
      }
    }

    // pairs
    if (isset($report['ppp'])) {
      $pairs = $endpoints['ppp']($mods, $vars, $report);
      $res['pairs'] = $pairs['pairs'];
    }

    // pvp
    if (isset($report['pvp'])) {
      $pairs = $endpoints['pvp']($mods, $vars, $report);
      $res['pvp'] = $pairs['opponents'];
    }

    // factions
    if (isset($report['players_sides'])) {
      $req = $endpoints['sides']($mods, $vars, $report);
      $res['sides'][0] = $req[0][ $vars['playerid'] ] ?? null;
      $res['sides'][1] = $req[1][ $vars['playerid'] ] ?? null;
    }

    // team
    if (isset($report['teams'])) {
      $res['teams'] = [];
      foreach ($report['teams'] as $tid => $data) {
        if (in_array($vars['playerid'], $data['active_roster'])) {
          $res['teams'][$tid] = [
            'id' => $tid,
            'name' => $data['name'],
            'tag' => $data['tag'],
          ];
          if (isset($data['players_draft_pb'])) {
            $res['teams'][$tid]['stats'] = [
              'matches' => $data['players_draft_pb'][ $vars['playerid'] ]['matches_total'],
              'winrate' => $data['players_draft_pb'][ $vars['playerid'] ]['winrate_picked'],
            ];
          }
        }
      }
    }

    // played heroes
    if (isset($report['matches'])) {
      $res['heroes'] = [];
      
      foreach ($report['matches'] as $mid => $heroes) {
        foreach ($heroes as $hero) {
          if ($hero['player'] != $vars['playerid']) continue;
          if (!isset($res['heroes'][ $hero['hero'] ])) {
            $res['heroes'][ $hero['hero'] ] = [
              'wins' => 0,
              'matches' => 0,
            ];
          }
          $res['heroes'][ $hero['hero'] ]['matches']++;
          if ($hero['radiant'] == $report['matches_additional'][$mid]['radiant_win'])
            $res['heroes'][ $hero['hero'] ]['wins']++;
        }
      }
    }

    return $res;
  }

  // team
  if (in_array("teams", $mods)) {
    return $endpoints['teams']($mods, $vars, $report);
  }

  // item
  if (in_array("items", $mods)) {
    if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['stats']))
      throw new \Exception("No items stats data");

    if (!isset($vars['item'])) {
      throw new \Exception("Need to specify item");
    }

    if (empty($vars['gets']) || $vars['gets'] == '*') {
      // $vars['gets'] = [ 'total', 'heroes', 'heroes-matches', 'heroes-rank-top', 'heroes-rank-bot', 'records', 'records-best' ];
      $vars['gets'] = [ 'total', 'heroes-purchases', 'heroes-rank-top', 'heroes-rank-bot', 'records-best' ];
    }

    $res = [];

    $res['__endp'] = 'heroes-profiles';
    $res['__stopRepeater'] = ['team', 'playerid', 'heroid'];

    $req = $endpoints['items-heroes']($mods, $vars, $report);

    if (in_array('total', $vars['gets'])) {
      $res['total'] = $req['total'];
    }

    $heroes = $req['heroes'];
    if (in_array('heroes', $vars['gets'])) {
      $res['heroes'] = $heroes;
    }

    if (in_array('heroes-purchases', $vars['gets'])) {
      uasort($heroes, function($a, $b) {
        return $b['purchases'] <=> $a['purchases'];
      });

      $res['heroes-purchases'] = array_slice($heroes, 0, 10, true);
    }

    if (in_array('heroes-rank-top', $vars['gets'])) {
      uasort($heroes, function($a, $b) {
        return $b['rank'] <=> $a['rank'];
      });

      $res['heroes-rank-top'] = array_slice($heroes, 0, 10, true);
    }

    if (in_array('heroes-rank-bot', $vars['gets'])) {
      uasort($heroes, function($a, $b) {
        return $a['rank'] <=> $b['rank'];
      });

      $res['heroes-rank-bot'] = array_slice($heroes, 0, 10, true);
    }

    if (isset($report['items']['records'])) {
      $req = $endpoints['items-records']($mods, $vars, $report);

      if (in_array('records', $vars['gets'])) {
        $res['records'] = $req['records'];
      }

      if (in_array('records-best', $vars['gets'])) {
        uasort($req['records'], function($a, $b) {
          return $b['diff'] <=> $a['diff'];
        });

        $res['records-best'] = array_slice($req['records'], 0, 10, true);
      }
    }
    
    return $res;
  }
};

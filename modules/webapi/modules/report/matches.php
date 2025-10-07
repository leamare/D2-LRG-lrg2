<?php 

$repeatVars['matches'] = ['team', 'optid'];

#[Endpoint(name: 'matches')]
#[Description('List match cards filtered by team/region/player/hero/series')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ModlineVar(name: 'optid', schema: ['type' => 'integer'], description: 'Opponent team id')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Player id')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'variant', schema: ['type' => 'integer'], description: 'Hero variant id')]
#[GetParam(name: 'gets', required: false, schema: ['type' => 'array','items' => ['type' => 'integer']], description: 'Series ids to filter')]
#[ReturnSchema(schema: 'MatchesResult')]
class Matches extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $meta;
  if (empty($report['matches'])) 
    throw new Exception("No matches available for this report");

  $res = [];

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ]['matches'];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ]['matches'];
  } else if (isset($vars['gets']) && !empty($report['series']) && isset($report['match_parts_series_tag'])) {
    $context = [];

    foreach($report['matches'] as $matchid => $match) {
      $series_tag = $report['match_parts_series_tag'][$matchid] ?? null;
      $sid = ($report['series'][$series_tag]['seriesid'] ?? 0) ? $report['series'][$series_tag]['seriesid'] : $series_tag;
      if (!in_array($sid, $vars['gets'])) {
        continue;
      }
      $context[$matchid] = true;
    }
  } else {
    $context =& $report['matches'];
  }

  if ($vars['team'] ?? false)
    $res['card'] = team_card($vars['team']);

  $res['matches'] = [];

  if (isset($vars['playerid']) || isset($vars['heroid'])) {
    $positions = [];

    for($i=0; $i<=1; $i++) {
      for($j=0; $j<=5; $j++) {
        $list = [];

        if (isset($report['hero_positions_matches']) && isset($vars['heroid'])) {
          if (isset($report['hero_positions_matches'][$i][$j][ $vars['heroid'] ])) {
            $list = $report['hero_positions_matches'][$i][$j][ $vars['heroid'] ];
          }
        }
        if (isset($report['player_positions_matches']) && isset($vars['playerid'])) {
          if (!isset($report['player_positions_matches'][$i][$j][ $vars['playerid'] ])) {
            $list = $list + $report['player_positions_matches'][$i][$j][ $vars['playerid'] ];
          }
        }

        array_unique($list);

        foreach ($list as $mid) {
          $positions[$mid] = "$i.$j";
        }
      }
    }

    if (isset($vars['playerid']) && isset($vars['heroid'])) {

    }
  }

  if (in_array("unteamed", $mods)) {
    $vars['optid'] = 0;
  }

  foreach ($context as $id => $data) {
    if (isset($report['matches_additional']) && isset($vars['team']) && isset($vars['region'])) {
      $region = $meta['clusters'][ $report['matches_additional'][$id]['cluster'] ];
      if ($region != $vars['region']) continue;
    }

    if (isset($vars['optid']) && isset($report['match_participants_teams'])) {
      if (!in_array($vars['optid'], $report['match_participants_teams'][$id]) && !(!$vars['optid'] && count($report['match_participants_teams'][$id]) < 2)) {
        continue;
      }
    }

    if (isset($vars['playerid']) && isset($vars['heroid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $pl) {
        if ($pl['player'] == $vars['playerid'] && $pl['hero'] == $vars['heroid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, false)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    } else if (isset($vars['heroid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $pl) {
        if ($pl['hero'] == $vars['heroid']) {
          if (isset($vars['variant']) && $pl['var'] != $vars['variant']) {
            continue;
          }
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, true)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    } else if (isset($vars['playerid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $slot => $pl) {
        if ($pl['player'] == $vars['playerid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, false)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    }

    $card = match_card($id);
    if (isset($positions)) {
      $card['position'] = $positions[$id] ?? null;
    }
    $res['matches'][] = $card;
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('MatchesResult', TypeDefs::obj([
    'card' => TypeDefs::obj([]),
    'matches' => TypeDefs::arrayOf(TypeDefs::obj([]))
  ]));
}

function check_positions_matches($id, $ishero) {
  global $vars, $report;
  $type = $ishero ? 'hero' : 'player';

  if (isset($report[$type.'_positions_matches']) && isset($vars['position'])) {
    $position = explode('.', $vars['position']);
    if ($position[1]) {
      $pm = $report[$type.'_positions_matches'][ (int)$position[0] ][ (int)$position[1] ][ $vars[$type.'id'] ] ?? [];
    } else {
      $pm = [];
      foreach ($report[$type.'_positions_matches'][ (int)$position[0] ] as $players) {
        if (isset($players[ $vars[$type.'id'] ]))
          $pm = $pm + $players[ $vars[$type.'id'] ];
      }
    }
    if (!in_array($id, $pm))
      return false;
  }
  return true;
}
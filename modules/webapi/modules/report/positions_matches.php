<?php 

#[Endpoint(name: 'positions_matches')]
#[Description('Match list for a given hero/player at a specific position, optionally per team/region')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Core.lane dotted pair like 1.2')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Player id')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ReturnSchema(schema: 'PositionsMatchesResult')]
class PositionsMatches extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (in_array("players", $mods))
    $type = "player";
  else 
    $type = "hero";

  if (!isset($report[$type.'_positions_matches']))
    throw new UserInputException("The report does not support $type positions matches.");

  if (isset($vars['team']) && !isset($report['match_participants_teams']))
    throw new UserInputException("The report does not support $type positions matches for teams.");

  // positions context
  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ][$type.'_positions_matches'];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ][$type.'_positions_matches'];
  } else {
    $context =& $report[$type.'_positions_matches'];
  }

  if (!isset($vars[$type.'id']))
    throw new UserInputException("No ID specified.");

  if (!isset($vars['position']))
    throw new UserInputException("Position was not specified.");

  $position = explode('.', $vars['position']);

  if (empty($vars['team'])) {
    $res = $context[ (int)$position[0] ][ (int)$position[1] ][ $vars[$type.'id'] ] ?? [];
  } else if (isset($report['teams'][ $vars['team'] ]['matches']) && isset($report['matches'])) {
    $res = [];

    if (isset($report['teams'][ $vars['team'] ][$type.'_positions'][ (int)$position[0] ][ (int)$position[1] ][ $vars[$type.'id'] ])) {
      foreach($report['teams'][ $vars['team'] ]['matches'] as $match => $v) {
        $radiant = ( $report['match_participants_teams'][$match]['radiant'] ?? 0 ) == $vars['team']  ? 1 : 0;
        foreach ($report['matches'][$match] as $l) {
          if ($l['radiant'] != $radiant) continue;
          if ($l[$type] == $vars[$type.'id']) {
            $res[] = $match;
            break;
          }
        }
      }
    }
  }

  $r = [];
  foreach ($res as $match)
    $r[] = match_card_min($match);

  return $r;

  throw new UserInputException("Positions matches requires a position specified.");
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('PositionsMatchesResult', TypeDefs::arrayOf(TypeDefs::obj([])));
}

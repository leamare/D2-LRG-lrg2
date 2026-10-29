<?php

include_once(__DIR__ . "/../../../view/functions/tier_lists.php");
include_once(__DIR__ . "/../../../view/functions/meta_levels.php");

#[Endpoint(name: 'tierlists')]
#[Description('Tier lists (S..E): heroes meta overall or per position (tierlists-heroes-meta), players or teams on a hero (tierlists-heroes-players / tierlists-heroes-teams), heroes of a player (tierlists-players-heroes) or of a team (tierlists-teams-heroes), with the plain rank span each tier covers')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Core.lane dotted pair like 1.2 -- heroes tier list for one position')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id -- required for the players and teams on a hero tier lists')]
#[ModlineVar(name: 'playerid', schema: ['type' => 'integer'], description: 'Player id -- required for the heroes of a player tier list')]
#[ModlineVar(name: 'team', schema: ['type' => 'integer'], description: 'Team id -- required for the heroes of a team tier list')]
#[GetParam(name: 'hero', required: false, schema: ['type' => 'integer'], description: 'Hero id, same as heroid in the modline')]
#[ReturnSchema(schema: 'TierListsResult')]
class Tierlists extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;

  if (is_wrapped($report['hero_positions'] ?? null)) {
    $report['hero_positions'] = unwrap_data($report['hero_positions']);
  }

  $tokens = array_values(array_reverse($mods));
  $pos = function($token) use ($tokens) {
    $i = array_search($token, $tokens, true);
    return $i === false ? null : $i;
  };
  $p_heroes = $pos("heroes");
  $p_players = $pos("players");
  $p_teams = $pos("teams");

  $heroes_of_player = $p_players !== null && $p_heroes !== null && $p_players < $p_heroes;
  $heroes_of_team = $p_teams !== null && $p_heroes !== null && $p_teams < $p_heroes;

  if ($heroes_of_player) {
    $pid = $vars['playerid'] ?? null;
    if (!$pid || is_array($pid)) throw new UserInputException("A single `playerid` is required for the heroes of a player tier list");

    $stats = tier_list_hero_stats_by_player($report)[$pid] ?? null;
    if (empty($stats)) throw new UserInputException("No data for this player");

    $games = 0;
    foreach ($stats as $st) $games += $st['matches'];

    $data = tier_list_entity_heroes($stats, $games);
    if ($data === null) throw new UserInputException("No data for this player");

    return [
      'type' => 'heroes',
      'player' => (int)$pid,
      'tiers' => $data['tiers'],
      'ranges' => $this->ranges($data['ranges']),
    ];
  }

  if ($heroes_of_team) {
    $tid = $vars['team'] ?? null;
    if (!$tid || is_array($tid)) throw new UserInputException("A single `team` id is required for the heroes of a team tier list");
    if (empty($report['teams'][$tid])) throw new UserInputException("No data for this team");

    [ $stats, $games ] = tier_list_team_heroes_stats($report['teams'][$tid]);

    $data = tier_list_entity_heroes($stats, $games);
    if ($data === null) throw new UserInputException("No data for this team");

    return [
      'type' => 'heroes',
      'team' => (int)$tid,
      'tiers' => $data['tiers'],
      'ranges' => $this->ranges($data['ranges']),
    ];
  }

  if ($p_players !== null || $p_teams !== null) {
    $on_players = $p_players !== null;

    $hid = $vars['heroid'] ?? $_GET['hero'] ?? null;
    if (!$hid || is_array($hid)) throw new UserInputException("A single `hero` id is required for the players and teams tier lists");

    $stats = $on_players
      ? tier_list_player_hero_stats($report)
      : tier_list_team_hero_stats($report);

    if (empty($stats[$hid])) throw new UserInputException("No data for this hero");

    $data = tier_list_entities_by_record($stats[$hid]);
    if ($data === null) throw new UserInputException("No data for this hero");

    return [
      'type' => $on_players ? 'players' : 'teams',
      'hero' => (int)$hid,
      'tiers' => $data['tiers'],
      'ranges' => $this->ranges($data['ranges']),
    ];
  }

  if (isset($vars['position'])) {
    if (is_array($vars['position'])) throw new UserInputException("A single position is required");
    $p = explode(".", $vars['position']);
    $data = tier_list_heroes_position($report, (int)$p[0], (int)($p[1] ?? 0));
    if ($data === null) throw new UserInputException("No data for this position");

    return [
      'type' => 'heroes',
      'position' => implode('.', $p),
      'tiers' => $data['tiers'],
      'ranges' => $this->ranges($data['ranges']),
    ];
  }

  $data = tier_list_heroes_overall($report);
  if ($data === null) throw new UserInputException("This report has no pick/ban data");

  return [
    'type' => 'heroes',
    'tiers' => $data['tiers'],
    'ranges' => $this->ranges($data['ranges']),
  ];
}

private function ranges($ranges) {
  $out = [];
  foreach ($ranges as $tier => $r) {
    $out[$tier] = [ 'min' => round($r[0], 2), 'max' => round($r[1], 2) ];
  }
  return $out;
}

}

if (is_docs_mode()) {
  SchemaRegistry::register('TierRange', TypeDefs::obj([
    'min' => TypeDefs::num(),
    'max' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('TierListsResult', TypeDefs::obj([
    'type' => TypeDefs::literal(['heroes', 'players', 'teams']),
    'position' => TypeDefs::str(),
    'hero' => TypeDefs::int(),
    'player' => TypeDefs::int(),
    'team' => TypeDefs::int(),
    'tiers' => TypeDefs::mapOf(TypeDefs::arrayOf(TypeDefs::int())),
    'ranges' => TypeDefs::mapOf('TierRange'),
  ], [ 'type', 'tiers', 'ranges' ]));
}

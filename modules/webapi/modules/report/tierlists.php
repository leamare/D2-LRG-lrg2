<?php

include_once(__DIR__ . "/../../../view/functions/tier_lists.php");
include_once(__DIR__ . "/../../../view/functions/meta_levels.php");

#[Endpoint(name: 'tierlists')]
#[Description('Tier lists (S..E) for heroes overall, heroes per position, or players/teams on a given hero, with the plain rank span each tier covers')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Core.lane dotted pair like 1.2 -- heroes tier list for one position')]
#[GetParam(name: 'hero', required: false, schema: ['type' => 'integer'], description: 'Hero id -- required for the players and teams tier lists')]
#[ReturnSchema(schema: 'TierListsResult')]
class Tierlists extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;

  if (is_wrapped($report['hero_positions'] ?? null)) {
    $report['hero_positions'] = unwrap_data($report['hero_positions']);
  }

  if (in_array("players", $mods) || in_array("teams", $mods)) {
    $hid = $vars['hero'] ?? $_GET['hero'] ?? null;
    if (!$hid) throw new UserInputException("A `hero` id is required for the players and teams tier lists");

    $stats = in_array("players", $mods)
      ? tier_list_player_hero_stats($report)
      : tier_list_team_hero_stats($report);

    if (empty($stats[$hid])) throw new UserInputException("No data for this hero");

    $data = tier_list_entities_by_record($stats[$hid]);
    if ($data === null) throw new UserInputException("No data for this hero");

    return [
      'type' => in_array("players", $mods) ? 'players' : 'teams',
      'hero' => (int)$hid,
      'tiers' => $data['tiers'],
      'ranges' => $this->ranges($data['ranges']),
    ];
  }

  if (isset($vars['position'])) {
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

# tier => { min, max } -- the plain rank span, before the meta-level and pickrate corrections
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
    'tiers' => TypeDefs::mapOf(TypeDefs::arrayOf(TypeDefs::int())),
    'ranges' => TypeDefs::mapOf('TierRange'),
  ], [ 'type', 'tiers', 'ranges' ]));
}

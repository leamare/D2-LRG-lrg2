<?php 

#[Endpoint(name: 'fantasy')]
#[Description('Fantasy MVP breakdown for players or heroes. When the team variable is set, returns data scoped to that team only.')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ModlineVar(name: 'team',   schema: ['type' => 'integer'], description: 'Team id – when set, returns team-scoped MVP data')]
#[ReturnSchema(schema: 'FantasyResult')]
class Fantasy extends EndpointTemplate {
  public function process() {
    $mods = $this->mods; $vars = $this->vars; $report = $this->report;
    $res = [];

    $tid = $vars['team'] ?? null;

    if ($tid) {
      if (!isset($report['teams'][$tid]))
        throw new UserInputException("Unknown team $tid");

    if (in_array("players", $mods)) {
      if (isset($report['teams'][$tid]['players_mvp'])) {
        $res = $report['teams'][$tid]['players_mvp'];
        if (is_wrapped($res)) $res = unwrap_data($res);
      } else {
        $players_all = $report['fantasy']['players_mvp'] ?? null;
        if (empty($players_all))
          throw new UserInputException("No players MVP data in this report");
        if (is_wrapped($players_all)) $players_all = unwrap_data($players_all);
        $roster = $report['teams'][$tid]['active_roster'] ?? $report['teams'][$tid]['roster'] ?? [];
        $res = array_intersect_key($players_all, array_flip($roster));
      }
      
      $res['__endp'] = "teams-players-fantasy";
      } else if (in_array("heroes", $mods)) {
        if (!isset($report['teams'][$tid]['heroes_mvp']))
          throw new UserInputException("No heroes MVP data for team $tid");
        $data = $report['teams'][$tid]['heroes_mvp'];
        if (is_wrapped($data)) $data = unwrap_data($data);
        $res = $data;
        $res['__endp'] = "teams-heroes-fantasy";
      } else {
        throw new UserInputException("What kind of fantasy data do you need?");
      }
    } else {
      // ── Report / region context ──────────────────────────────────────────────
      if (isset($vars['region'])) {
        $context =& $report['regions_data'][ $vars['region'] ];
      } else {
        $context =& $report;
      }

      if (in_array("players", $mods)) {
        if (is_wrapped($context['fantasy']['players_mvp'])) $context['fantasy']['players_mvp'] = unwrap_data($context['fantasy']['players_mvp']);
        $res = $context['fantasy']['players_mvp'];
        $res['__endp'] = "players-fantasy";
      } else if (in_array("heroes", $mods)) {
        if (is_wrapped($context['fantasy']['heroes_mvp'])) $context['fantasy']['heroes_mvp'] = unwrap_data($context['fantasy']['heroes_mvp']);
        $res = $context['fantasy']['heroes_mvp'];
        $res['__endp'] = "heroes-fantasy";
      } else {
        throw new UserInputException("What kind of fantasy data do you need?");
      }
    }

    return $res;
  }
}

if (is_docs_mode()) {
  SchemaRegistry::register('FantasyMvpEntry', TypeDefs::obj([
    'matches_s'    => TypeDefs::int(),
    'total_awards' => TypeDefs::num(),
    'mvp'          => TypeDefs::int(),
    'mvp_losing'   => TypeDefs::int(),
    'core'         => TypeDefs::int(),
    'support'      => TypeDefs::int(),
    'lvp'          => TypeDefs::int(),
    'total_points' => TypeDefs::num(),
    'kda'          => TypeDefs::num(),
    'farm'         => TypeDefs::num(),
    'combat'       => TypeDefs::num(),
    'objectives'   => TypeDefs::num(),
  ], ['matches_s', 'total_awards']));

  // Map of hero/player id → FantasyMvpEntry
  SchemaRegistry::register('FantasyResult', TypeDefs::mapOf('FantasyMvpEntry'));
}

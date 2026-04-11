<?php 

#[Endpoint(name: 'summary')]
#[Description('Summary of teams, players, or heroes for report/region')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'SummaryResult')]
class Summary extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("teams", $mods)) {
    include_once(__DIR__ . "/../../../view/functions/teams_diversity_recalc.php");

    $context_k = array_keys($context['teams']);
    foreach($context_k as $team_id) {
      if (isset($report['teams_interest']) && !in_array($team_id, $report['teams_interest'])) continue;

      if (isset($report['teams'][ $team_id ]['averages']) || isset($report['teams'][ $team_id ]['averages']['hero_pool'])) 
        $report['teams'][ $team_id ]['averages']['diversity'] = teams_diversity_recalc($report['teams'][ $team_id ]);

      $t = [
        "team_id" => $team_id,
        "team_name" => team_name($team_id),
        "team_tag" => team_tag($team_id),
        "matches_total" => $report['teams'][$team_id]['matches_total'],
        "winrate" => round( $report['teams'][$team_id]['matches_total'] ? 
          $report['teams'][$team_id]['wins']*100/$report['teams'][$team_id]['matches_total']
          : 0,2)
      ];
      $res[] = array_merge($t, $report['teams'][$team_id]['averages']);
    }
    $res['__endp'] = "teams-summary";
  } else if (in_array("players", $mods)) {
    if (!isset($context['players_summary'])) throw new UserInputException("No player data in this report");
    if (is_wrapped($context['players_summary'])) $context['players_summary'] = unwrap_data($context['players_summary']);
    if (isset($report['players_additional']) && isset($report['player_positions'])) {
      foreach($context['players_summary'] as $id => $player) {
        if (!isset($report['players_additional'][$id]['positions'])) continue;
        $position = reset($report['players_additional'][$id]['positions']);
        $position = is_array($position) ? $position["core"].".".$position["lane"] : "0.0";
        $context['players_summary'][$id]['common_position'] = $position;
      }
    }
    $res = $context['players_summary'];
    $res['__endp'] = "players-summary";
    if (isset($report['pvp']) && isset($report['players_additional'])) {
      include_once(__DIR__ . "/../../../commons/volatility.php");
      include_once(__DIR__ . "/../../functions/pvp_unwrap_data.php");
      $winrates = [];
      foreach ($report['players_additional'] as $id => $pl) {
        if (!isset($pl['matches']) || $pl['matches'] <= 0) continue;
        $winrates[$id] = ['matches' => $pl['matches'], 'winrate' => $pl['won'] / max(1, $pl['matches'])];
      }
      $pvp = rg_generator_pvp_unwrap_data($report['pvp'], $winrates, false);
      foreach ($res as $playerid => &$entry) {
        if (!is_numeric($playerid) || !isset($pvp[$playerid])) continue;
        $vol = rg_volatility_metrics($pvp[$playerid]);
        $entry['volatility_normalized_relative']      = $vol['normalized_relative'];
        $entry['volatility_normalized_total']         = $vol['normalized_total'];
        $entry['volatility_normalized_avg_advantage'] = $vol['normalized_avg_advantage'];
      }
      unset($entry, $pvp, $winrates);
    }
  } else if (in_array("heroes", $mods)) {
    if (is_wrapped($context['hero_summary'])) $context['hero_summary'] = unwrap_data($context['hero_summary']);
    $res = $context['hero_summary'];
    $res['__endp'] = "heroes-summary";
    if (isset($report['hvh']) && isset($report['pickban'])) {
      include_once(__DIR__ . "/../../../commons/volatility.php");
      include_once(__DIR__ . "/../../functions/pvp_unwrap_data.php");
      $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);
      foreach ($res as $heroid => &$entry) {
        if (!is_numeric($heroid) || !isset($hvh[$heroid])) continue;
        $vol = rg_volatility_metrics($hvh[$heroid]);
        $entry['volatility_normalized_relative']      = $vol['normalized_relative'];
        $entry['volatility_normalized_total']         = $vol['normalized_total'];
        $entry['volatility_normalized_avg_advantage'] = $vol['normalized_avg_advantage'];
      }
      unset($entry, $hvh);
    }
  } else {
    throw new UserInputException("What kind of summary do you need?");
  }

  $keys = array_keys( array_values($res)[0] );
  if (in_array("hero_damage_per_min_s", $keys) && in_array("gpm", $keys) && !in_array("damage_to_gold_per_min_s", $keys)) {
    foreach ($res as $id => $el) {
      if (!is_numeric($id)) continue;
      $res[$id] = array_insert_before($res[$id], "gpm", [
        "damage_to_gold_per_min_s" => ($res[$id]['hero_damage_per_min_s'] ?? 0)/($res[$id]['gpm'] ?? 1),
      ]);
    }
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('TeamSummary', TypeDefs::obj([
    'team_id' => TypeDefs::int(),
    'team_name' => TypeDefs::str(),
    'team_tag' => TypeDefs::str(),
    'matches_total' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('SummaryResult', TypeDefs::oneOf([
    TypeDefs::arrayOf('TeamSummary'),
    TypeDefs::mapOfIdKeys(TypeDefs::obj([]))
  ]));
}

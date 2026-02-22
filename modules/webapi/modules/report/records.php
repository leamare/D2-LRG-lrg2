<?php 

$repeatVars['records'] = ['region'];

#[Endpoint(name: 'records')]
#[Description('Records list with optional region context')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'RecordsResult')]
class Records extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  if (isset($vars['region']) && isset($report['regions_data'][ $vars['region'] ])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else $context =& $report;

  $data = $context['records'] ?? null;
  $data_ext = $context['records_ext'] ?? [];

  if (empty($data))
    throw new UserInputException("Problem occured when fetching records.");
  
  if (!empty($data_ext)) {
    $data_ext = unwrap_data($data_ext);
  }

  $res = [];
  
  foreach ($data as $k => $rec) {
    $res[$k] = [];

    $src = array_merge([ $rec ], $data_ext[$k] ?? []);

    foreach ($src as $v) {
      if (empty($v)) continue;

      if ($v['matchid'] && !empty($report['match_participants_teams']))
        $v['match_card_min'] = match_card_min($v['matchid']);
      else 
        $v['match_card_min'] = null;

      if (!$v['heroid'])
        $v['heroid'] = null;

      if (!$v['matchid'])
        $v['matchid'] = null;

      if ($v['playerid']) {
        if (strpos($k, '_team')) {
          $v['name'] = team_name($v['playerid']);
          $v['teamid'] = $v['playerid'];
          $v['playerid'] = null;
        } else {
          $v['name'] = player_name($v['playerid']);
        }
      } else {
        $v['playerid'] = null;
      }

      $res[$k][] = $v;
    }
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('RecordItem', TypeDefs::obj([
    'name' => TypeDefs::str(),
    'playerid' => TypeDefs::int(),
    'teamid' => TypeDefs::int(),
    'heroid' => TypeDefs::int(),
    'value' => TypeDefs::num(),
    'matchid' => TypeDefs::int(),
    'match_card_min' => TypeDefs::obj([]),
  ]));

  // SchemaRegistry::register('RecordsResult', TypeDefs::mapWithPattern('RecordItem', '^[a-zA-Z_][a-zA-Z0-9_]*$'));
  SchemaRegistry::register('RecordsResult', TypeDefs::mapWithKeys('RecordItem', [
    'kills',
    'deaths',
    'assists',
    'wards',
    'last_hits',
    'denies',
    'gold',
    'gold_spent',
    'gold_per_min',
    'xp_per_min',
    'gold_spent_per_min',
  ]));
}
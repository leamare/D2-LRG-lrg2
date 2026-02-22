<?php 

$repeatVars['records_single'] = ['region'];

#[Endpoint(name: 'records_single')]
#[Description('Top single entries per record key, optionally per region; routes to items-records for items')]
#[ModlineVar(name: 'region', schema: ['type' => 'integer'], description: 'Region id')]
#[ReturnSchema(schema: 'RecordsSingleResult')]
class RecordsSingle extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if (in_array('items', $mods)) {
    $res = $endpoints['items-records']($mods, $vars, $report);
    $res['__endp'] = "items-records";
    return $res;
  }

  // parse mods for region ID
  // check if region persists
  if (isset($vars['region']) && isset($report['regions_data'][ $vars['region'] ])) {
    $data = $report['regions_data'][ $vars['region'] ]['records'] ?? null;
  } else $data = $report['records'] ?? null;

  if (empty($data))
    throw new UserInputException("Problem occured when fetching records.");

  $res = [];
  
  foreach ($data as $k => $v) {
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
    
    $res[$k] = $v;
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('RecordSingleEntry', TypeDefs::obj([
    'name' => TypeDefs::str(),
    'playerid' => TypeDefs::int(),
    'teamid' => TypeDefs::int(),
    'heroid' => TypeDefs::int(),
    'value' => TypeDefs::num(),
    'matchid' => TypeDefs::int(),
    'match_card_min' => TypeDefs::obj([]),
  ]));
  SchemaRegistry::register('RecordsSingleResult', TypeDefs::mapOf('RecordSingleEntry'));
}
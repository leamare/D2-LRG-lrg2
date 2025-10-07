<?php 

#[Endpoint(name: 'milestones')]
#[Description('Milestones totals and per-entity top lists')]
#[ReturnSchema(schema: 'MilestonesResult')]
class Milestones extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  $data = $report['milestones'] ?? null;

  if (empty($data))
    throw new \Exception("Milestones are not available for this report.");

  $res = [
    'total' => [],
    'players' => null,
    'teams' => null,
    'heroes' => $data['heroes'] ?? null
  ];

  foreach ($data['total'] as $k => $v) {
    $res['total'][$k] = $v[0];
  }

  if (!empty($data['players'])) {
    $res['players'] = [];
    foreach ($data['players'] as $k => $d) {
      $res['players'][$k] = [];
      foreach ($d as $pid => $v) {
        $res['players'][$k][] = [
          'playerid' => $pid,
          'name' => player_name($pid),
          'value' => $v
        ];
      }
    }
  }

  if (!empty($data['teams'])) {
    $res['teams'] = [];
    foreach ($data['teams'] as $k => $d) {
      $res['teams'][$k] = [];
      foreach ($d as $tid => $v) {
        $res['teams'][$k][] = [
          'teamid' => $tid,
          'name' => team_name($tid),
          'tag' => team_tag($tid),
          'value' => $v
        ];
      }
    }
  }

  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('MilestoneEntry', TypeDefs::obj([
    'playerid' => TypeDefs::int(),
    'teamid' => TypeDefs::int(),
    'name' => TypeDefs::str(),
    'tag' => TypeDefs::str(),
    'value' => TypeDefs::num(),
  ]));

  SchemaRegistry::register('MilestonesResult', TypeDefs::obj([
    'total' => TypeDefs::mapOf(TypeDefs::num()),
    'players' => TypeDefs::mapOf(TypeDefs::arrayOf('MilestoneEntry')),
    'teams' => TypeDefs::mapOf(TypeDefs::arrayOf('MilestoneEntry')),
    'heroes' => TypeDefs::mapOf(TypeDefs::num()),
  ]));
}
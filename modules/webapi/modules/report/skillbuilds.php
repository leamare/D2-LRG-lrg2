<?php

$repeatVars['heroes-skillbuilds'] = ['heroid'];

// sb_unwrap_role/sb_unwrap_rows/sb_priority_ranking/sb_decode_priority live in
// modules/view/functions/skillbuilds.php, auto-imported into both this (webapi)
// and the view runtime - see that file for why.

function sb_matches_from_priority($rows) {
  $matches = array_sum(array_column($rows, 'matches'));
  $wins = array_sum(array_column($rows, 'wins'));
  return [
    'matches' => $matches,
    'winrate' => $matches ? round($wins / $matches, 4) : 0,
  ];
}

#[Endpoint(name: 'heroes-skillbuilds')]
#[Description('Skill build stats for a hero: priority, first point/maxed at per skill, talents, attribute (stat) points - by role or total')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Role code (core.lane), omitted for total')]
#[ReturnSchema(schema: 'SkillBuildsResult')]
class HeroesSkillbuilds extends EndpointTemplate {
public function process() {
  $vars = $this->vars; $report = $this->report;

  if (empty($report['skill_builds']['priority']))
    throw new UserInputException("No skill builds data");

  $rid = 0;
  if (isset($vars['position'])) {
    $found = array_search($vars['position'], ROLES_IDS_SIMPLE);
    if ($found !== false) $rid = (int)$found;
  }

  $priorityByHero = sb_unwrap_rows($report['skill_builds']['priority'], $rid);

  if (!isset($vars['heroid'])) {
    return array_values(array_map('intval', array_keys($priorityByHero)));
  }

  $hero = (int)$vars['heroid'];

  if (!isset($priorityByHero[$hero]) && $rid !== 0) {
    $rid = 0;
    $priorityByHero = sb_unwrap_rows($report['skill_builds']['priority'], $rid);
  }

  if (!isset($priorityByHero[$hero])) return [];

  $priority = sb_decode_featured_build(sb_decode_priority($priorityByHero[$hero]));
  sb_priority_ranking($priority);

  $section = $report['skill_builds']['matches'] ?? [];

  $matches = sb_unwrap_role($section, $rid);

  $res = [
    'hero' => $hero,
    'role' => $rid,
    'matches' => $matches[$hero] ?? sb_matches_from_priority($priority),
    'priority' => $priority,
    'skills' => sb_unwrap_rows($report['skill_builds']['skills'], $rid)[$hero] ?? [],
    'attributes' => sb_unwrap_role($report['skill_builds']['attributes'], $rid)[$hero] ?? [],
    'ultimate' => $report['skill_builds']['ultimate'][$hero] ?? null,
  ];

  if (isset($report['skill_builds']['talents'])) {
    $res['talents'] = sb_unwrap_rows($report['skill_builds']['talents'], $rid)[$hero] ?? [];
  }

  return $res;
}
}

#[Endpoint(name: 'heroes-skillbuilds-list')]
#[Description('List heroes with available skill build stats')]
class HeroesSkillbuildsList extends EndpointTemplate {
public function process() {
  $report = $this->report;
  if (empty($report['skill_builds']['priority']))
    throw new UserInputException("No skill builds data");

  return array_values(array_map('intval', array_keys(sb_unwrap_role($report['skill_builds']['priority'], 0))));
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('SkillBuildPriorityRow', TypeDefs::obj([
    'priority' => TypeDefs::mapOfIdKeys(TypeDefs::int()), // null for the folded "others" bucket
    'matches' => TypeDefs::int(),
    'wins' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
    'ratio' => TypeDefs::num(),
    'max_level' => TypeDefs::int(),
    'rank' => TypeDefs::num(),
    'featured_build' => TypeDefs::arrayOf(TypeDefs::int()), // most common exact level-up sequence for this variant
  ]));
  SchemaRegistry::register('SkillBuildSkillRow', TypeDefs::obj([
    'skill' => TypeDefs::int(),
    'first_point_avg' => TypeDefs::num(),
    'maxed_at_avg' => TypeDefs::num(),
    'matches' => TypeDefs::int(),
  ]));
  SchemaRegistry::register('SkillBuildTalentRow', TypeDefs::obj([
    'tier' => TypeDefs::int(),
    'level' => TypeDefs::int(),
    'talent' => TypeDefs::int(),
    'matches' => TypeDefs::int(),
    'wins' => TypeDefs::int(),
    'winrate' => TypeDefs::num(),
    'pick_rate' => TypeDefs::num(),
    'skilled_at_avg' => TypeDefs::num(),
  ]));
  SchemaRegistry::register('SkillBuildAttributes', TypeDefs::obj([
    'matches' => TypeDefs::int(),
    'taken_rate' => TypeDefs::num(),
    'avg_count' => TypeDefs::num(),
    'first_point_avg' => TypeDefs::num(),
    'taken_matches' => TypeDefs::int(),
    'taken_winrate' => TypeDefs::num(),
  ]));
  SchemaRegistry::register('SkillBuildsResult', TypeDefs::oneOf([
    TypeDefs::arrayOf(TypeDefs::int()),
    TypeDefs::obj([
      'hero' => TypeDefs::int(),
      'role' => TypeDefs::int(),
      'matches' => TypeDefs::obj(['matches' => TypeDefs::int(), 'winrate' => TypeDefs::num()]),
      'priority' => TypeDefs::arrayOf('SkillBuildPriorityRow'),
      'skills' => TypeDefs::arrayOf('SkillBuildSkillRow'),
      'talents' => TypeDefs::arrayOf('SkillBuildTalentRow'),
      'attributes' => TypeDefs::ref('SkillBuildAttributes'),
      'ultimate' => TypeDefs::int(),
    ])
  ]));
  SchemaRegistry::register('SkillBuildsListResult', TypeDefs::arrayOf(TypeDefs::int()));
}

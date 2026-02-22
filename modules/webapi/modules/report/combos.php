<?php 

function rg_generate_hero_pairs(&$context, $limiter) {
  $r = [];

  foreach ($context as $hid1 => $heroes) {
    foreach ($heroes as $hid2 => $line) {
      if (empty($line) || $line === true)
        continue;
      if ($line['matches'] <= $limiter)
        continue;
      
      $line['heroid1'] = $hid1;
      $line['heroid2'] = $hid2;
      $line['expectation'] = $line['exp'];
      unset($line['exp']);

      $r[] = $line;
    }
  }

  return $r;
}

// Local reusable schema registrations are lazily loaded in docs mode after the class.

#[Endpoint(name: 'combos')]
#[Description('Hero or player pairs/trios/lane combos')]
#[GetParam(name: 'mod', required: true, schema: ['type' => 'string'], description: 'Modline must include either heroes or players; optional trios/lane_combos')]
#[GetParam(name: 'league', required: true, schema: ['type' => 'string'], description: 'Report tag')]
#[ReturnSchema(schema: 'CombosResult')]
class Combos extends EndpointTemplate {
public function process() {
  global $endpoints;
  if (in_array('items', $this->mods)) {
    $res = $endpoints['items-combos']($this->mods, $this->vars, $this->report);
    $res['__endp'] = "items-combos";
    return $res;
  }

  $res = [];

  if (isset($this->vars['team'])) {
    $context =& $this->report['teams'][ $this->vars['team'] ];
  } else if (isset($this->vars['region'])) {
    $context =& $this->report['regions_data'][ $this->vars['region'] ];
  } else {
    $context =& $this->report;

    if (!empty($this->report['hph']) && is_wrapped($this->report['hph'])) {
      $this->report['hph'] = unwrap_data($this->report['hph']);
    }

    if (in_array("heroes", $this->mods) && empty($this->report['hero_pairs']) && isset($this->report['hph'])) {
      $context['hero_pairs'] = rg_generate_hero_pairs($this->report['hph'], $this->report['settings']['limiter_combograph']);
    }
  }

  if (in_array("heroes", $this->mods)) {
    $type = "hero";
  } else if (in_array("players", $this->mods)) {
    $type = "player";
  } else {
    throw new UserInputException("No module specified");
  }

  if (in_array("trios", $this->mods)) {
    $res['type'] = "trios";
    $res['data'] = $context[$type.'_triplets'] ?? $context[$type.'_trios'];
  } else if (in_array("lane_combos", $this->mods)) {
    $res['type'] = "lane_combos";
    $res['data'] = $context[$type.'_lane_combos'];
  } else {
    $res['type'] = "pairs";
    $res['data'] = $context[$type.'_pairs'];
  }

  return $res;
}

}

// Register schemas after class, only in docs mode
if (is_docs_mode()) {
    SchemaRegistry::register('PairStats', TypeDefs::obj([
        'heroid1' => TypeDefs::int(),
        'heroid2' => TypeDefs::int(),
        'matches' => TypeDefs::int(),
        'winrate' => TypeDefs::num(),
        'wins' => TypeDefs::num(),
        'wr_diff' => TypeDefs::num(),
        'expectation' => TypeDefs::num(),
        'dev_pct' => TypeDefs::num(),
    ]));

    SchemaRegistry::register('CombosResult', TypeDefs::oneOf([
        TypeDefs::obj([ 'type' => TypeDefs::literal(['pairs']), 'data' => TypeDefs::arrayOf('PairStats') ]),
        TypeDefs::obj([ 'type' => TypeDefs::literal(['trios']), 'data' => TypeDefs::arrayOf(TypeDefs::obj([])) ]),
        TypeDefs::obj([ 'type' => TypeDefs::literal(['lane_combos']), 'data' => TypeDefs::arrayOf(TypeDefs::obj([])) ]),
    ]));
}

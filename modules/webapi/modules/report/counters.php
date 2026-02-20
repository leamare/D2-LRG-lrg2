<?php 

// Local reusable schema registrations are lazily loaded in docs mode after the class.

#[Endpoint(name: 'counters')]
#[Description('Hero vs hero counters list or graph')]
#[GetParam(name: 'mod', required: true, schema: ['type' => 'string'], description: 'Modline must include heroes; optional graph')]
#[GetParam(name: 'league', required: true, schema: ['type' => 'string'], description: 'Report tag')]
#[ReturnSchema(schema: 'CountersResult')]
class Counters extends EndpointTemplate {
public function process() {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  $res = [];

  if (!in_array("heroes", $this->mods)) {
    throw new \Exception("Incorrect module specified");
  }

  $hvh = []; 
  $devs = [];
  $games = [];
  $wr = [];

  if (is_wrapped($this->report['hvh'])) {
    $this->report['hvh'] = unwrap_data($this->report['hvh']);
  }
  
  foreach ($this->report['hvh'] as $line) {
    if ($line['matches'] < $this->report['settings']['limiter_combograph']) 
      continue;
    
    $p = [
      'heroid1' => $line['heroid1'],
      'heroid2' => $line['heroid2'],
      'matches' => $line['matches'],
      'winrate' => $line['h1winrate'],
      'wins' => round($line['h1winrate']*$line['matches']),
      'wr_diff' => round($line['h1winrate']-$this->report['pickban'][$line['heroid1']]['winrate_picked'], 5),
      'expectation' => $line['exp'],
      'dev_pct' => round( ($line['matches'] - $line['exp']) / $line['matches'], 5)
    ];
    
    $games[] = $line['matches'];
    $wr[] = $line['h1winrate'];
    $hvh[] = $p;
    $devs[] = ($p['matches']-$p['expectation']);
  }
  uasort($hvh, function($a, $b) { return $b['matches'] <=> $a['matches']; });

  if (in_array("graph", $this->mods)) {
    sort($devs);
    $med_deviation = $devs[ round( count($devs) * 0.75 ) ];
    $hvh = array_filter($hvh, function($a) use ($med_deviation) { return ($a['matches'] - $a['expectation']) > $med_deviation; });

    $d = [];

    foreach ($hvh as $l) {
      if (!isset($d[ $l['heroid1'] ])) {
        $d[ $l['heroid1'] ] = [
          'hero_id' => $l['heroid1'],
          'matches' => $this->report['pickban'][ $l['heroid1'] ]['matches_total'],
          'matches_picked' => $this->report['pickban'][ $l['heroid1'] ]['matches_picked'],
          'winrate_picked' => $this->report['pickban'][ $l['heroid1'] ]['winrate_picked']
        ];
      }
      if (!isset($d[ $l['heroid2'] ])) {
        $d[ $l['heroid2'] ] = [
          'hero_id' => $l['heroid2'],
          'matches' => $this->report['pickban'][ $l['heroid2'] ]['matches_total'],
          'matches_picked' => $this->report['pickban'][ $l['heroid2'] ]['matches_picked'],
          'winrate_picked' => $this->report['pickban'][ $l['heroid2'] ]['winrate_picked']
        ];
      }
    }

    $res['type'] = "graph";
    $res['data'] = [
      "limiter" => $this->report['settings']['limiter_combograph'],
      "max_wr" => max($wr),
      "max_games" => max($games),
      "nodes" => $d,
      "pairs" => array_values($hvh)
    ];
  } else {
    $res['type'] = "pairs";
    $res['data'] = $hvh;
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

    SchemaRegistry::register('GraphNode', TypeDefs::obj([
        'hero_id' => TypeDefs::int(),
        'matches' => TypeDefs::int(),
        'matches_picked' => TypeDefs::int(),
        'winrate_picked' => TypeDefs::num(),
    ]));

    SchemaRegistry::register('CountersResult', TypeDefs::oneOf([
        TypeDefs::obj([ 'type' => TypeDefs::literal(['pairs']), 'data' => TypeDefs::arrayOf('PairStats') ]),
        TypeDefs::obj([ 'type' => TypeDefs::literal(['graph']), 'data' => TypeDefs::obj([
            'limiter' => TypeDefs::int(),
            'max_wr' => TypeDefs::num(),
            'max_games' => TypeDefs::int(),
            'nodes' => TypeDefs::mapOf('GraphNode'),
            'pairs' => TypeDefs::arrayOf('PairStats'),
        ]) ]),
    ]));
}

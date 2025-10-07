<?php 

include_once(__DIR__ . "/items/overview.php");
include_once(__DIR__ . "/items/stats.php");
include_once(__DIR__ . "/items/icritical.php");
include_once(__DIR__ . "/items/heroes.php");
include_once(__DIR__ . "/items/icombos.php");
include_once(__DIR__ . "/items/progression.php");
include_once(__DIR__ . "/items/irecords.php");
include_once(__DIR__ . "/items/progrole.php");
include_once(__DIR__ . "/items/builds.php");
include_once(__DIR__ . "/items/stitems.php");
include_once(__DIR__ . "/items/sticonsumables.php");
include_once(__DIR__ . "/items/stibuilds.php");
include_once(__DIR__ . "/items/profile.php");

$repeatVars['items'] = ['heroid', 'itemid', 'position'];

#[Endpoint(name: 'items', isRouter: true)]
#[Description('Items router: forwards to specific items endpoints')]
#[ModlineVar(name: 'heroid', schema: ['type' => 'integer'], description: 'Hero id')]
#[ModlineVar(name: 'itemid', schema: ['type' => 'integer'], description: 'Item id')]
#[ModlineVar(name: 'position', schema: ['type' => 'string'], description: 'Position code')]
class ItemsRouter extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $endpoints;
  if ((!isset($report['items']) || empty($report['items']['pi'])) &&
    empty($report['starting_items']) &&
    empty($report['starting_items_players'])
  ) {
    throw new \Exception("No items data");
  }

  // Forward to best matching endpoint class via existing map
  $mapping = [
    'heroes' => 'items-heroes', 'heroboxplots' => 'items-heroes', 'hboxplots' => 'items-heroes',
    'combos' => 'items-combos', 'icombos' => 'items-combos',
    'stats' => 'items-stats', 'boxplots' => 'items-stats',
    'records' => 'items-records', 'irecords' => 'items-records',
    'progression' => 'items-progression', 'proglist' => 'items-progression',
    'progrole' => 'items-progrole',
    'builds' => 'items-builds',
    'icritical' => 'items-critical',
    'stitems' => 'items-stitems',
    'sticonsumables' => 'items-sticonsumables',
    'stibuilds' => 'items-stibuilds',
  ];

  foreach ($mapping as $key => $endp) {
    if (in_array($key, $mods)) {
      $fn = $endpoints[$endp];
      $res = $fn($mods, $vars, $report);
      $res['__endp'] = $endp;
      if (isset($vars['heroid'])) $res['__stopRepeater'] = 'heroid';
      if (isset($vars['itemid'])) $res['__stopRepeater'] = 'itemid';
      return $res;
    }
  }

  $res = $endpoints['items-overview']($mods, $vars, $report);
  $res['__endp'] = 'items-overview';
  $res['__stopRepeater'] = true;
  return $res;
}
}

// Back-compat adapter for runtime execution
$endpoints['items'] = function($mods, $vars, &$report) {
  $inst = new ItemsRouter($mods, $vars, $report);
  return $inst->process();
};

if (is_docs_mode()) {
  SchemaRegistry::register('ItemsRouterResult', TypeDefs::obj([]));
}
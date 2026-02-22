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
include_once(__DIR__ . "/items/enchantments.php");
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
    throw new UserInputException("No items data");
  }

  // Forward to best matching endpoint; each entry: [endpoint, __stopRepeater]
  $mapping = [
    'heroes'         => ['items-heroes',       'heroid'],
    'heroboxplots'   => ['items-heroes',       'heroid'],
    'hboxplots'      => ['items-heroes',       'heroid'],
    'combos'         => ['items-combos',       'itemid'],
    'icombos'        => ['items-combos',       'itemid'],
    'stats'          => ['items-stats',        'itemid'],
    'boxplots'       => ['items-stats',        'itemid'],
    'records'        => ['items-records',      'itemid'],
    'irecords'       => ['items-records',      'itemid'],
    'progression'    => ['items-progression',  'itemid'],
    'proglist'       => ['items-progression',  'itemid'],
    'progrole'       => ['items-progrole',     'itemid'],
    'builds'         => ['items-builds',       'itemid'],
    'icritical'      => ['items-critical',     'itemid'],
    'stitems'        => ['items-stitems',      'itemid'],
    'sticonsumables' => ['items-sticonsumables','itemid'],
    'stibuilds'      => ['items-stibuilds',    'itemid'],
    'enchantments'   => ['items-enchantments', 'heroid'],
  ];

  foreach ($mapping as $key => [$endp, $stopRepeater]) {
    if (in_array($key, $mods)) {
      $res = $endpoints[$endp]($mods, $vars, $report);
      $res['__endp'] = $endp;
      $res['__stopRepeater'] = $stopRepeater;
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
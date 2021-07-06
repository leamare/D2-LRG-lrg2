<?php 

$meta = new lrg_metadata;
$endpoints = [];
$repeatVars = [];

// Specify endpoints folders for specific conditions

if (!empty($report)) {
  include_once(__DIR__ . "/../../modules/view/__post_load.php");
  include_once(__DIR__ . "/../../modules/view/generators/pickban_teams.php");

  if(empty($mod)) $mod = "";

  $__dir = __DIR__ . "/modules/report/";

  $endpoints['__fallback'] = function() use (&$endpoints) {
    return $endpoints['info'];
  };
} else {
  $__dir = __DIR__ . "/modules/main/";

  $endpoints['__fallback'] = function() use (&$endpoints) {
    return $endpoints['list'];
  };
}

// Load endpoints

$__list = scandir($__dir);
foreach ($__list as $file) {
  if ($file[0] == '.' || !strpos($file, '.php')) continue;
  include_once($__dir . "draft_tree.php");
}

// Modlink processor

$mod = str_replace("/", "-", $mod);
$modline = array_reverse(explode("-", $mod));

// Variables and repeaters

include_once(__DIR__ . "/execute.php");
include_once(__DIR__ . "/variables.php");
include_once(__DIR__ . "/repeaters.php");

if (empty($endp_name)) {
  $endp = $endpoints['__fallback']();
  $endp_name = array_search($endp, $endpoints);
} else $endp = $endpoints[$endp_name];

$repeaters = $repeatVars[ $endp_name ] ?? [];

if (!empty($repeaters)) {
  $result = repeater($repeaters, $modline, $endp, $vars, $report);
} else {
  $result = execute($modline, $endp, $vars, $report);
}

if (isset($result['__endp'])) {
  $endp_name = $result['__endp'];
  unset($result['__endp']);
}

if (isset($result['__stopRepeater'])) {
  unset($result['__stopRepeater']);
}
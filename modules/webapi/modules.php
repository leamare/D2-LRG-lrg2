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
  $__fallback = 'info';
} else {
  $__dir = __DIR__ . "/modules/main/";
  $__fallback = 'list';
}

// Load endpoints

$__list = scandir($__dir);
foreach ($__list as $file) {
  if ($file[0] == '.' || !strpos($file, '.php')) continue;
  include_once($__dir . $file);
}

$endpoints['__fallback'] = function() use (&$endpoints, $__fallback) {
  return $endpoints[$__fallback];
};

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

$disabled = false;
if (!$_earlypreview) {
  if (in_array($mod, ($_earlypreview_wa_ban ?? [])) || in_array($endp_name, ($_earlypreview_wa_ban ?? []))) {
    $disabled = true;
  } else {
    foreach ($_earlypreview_wa_ban ?? [] as $ban) {
      if (strpos($mod, $ban) !== false) {
        $disabled = true;
        break;
      }
    }
  }
}

if (!$disabled) {
  if (!empty($repeaters)) {
    $result = repeater($repeaters, $modline, $endp, $vars, $report);
  } else {
    $result = execute($modline, $endp, $vars, $report);
  }
} else {
  $result = [
    'errors' => [
      'Not allowed (403)',
    ],
  ];
}

if (isset($result['__endp'])) {
  $endp_name = $result['__endp'];
  unset($result['__endp']);
}

if (isset($result['__stopRepeater'])) {
  unset($result['__stopRepeater']);
}
<?php 

$meta = new lrg_metadata;
$endpoints = [];
$endpointObjects = [];
$repeatVars = [];

include_once(__DIR__ . "/endpoint_template.php");

// Specify endpoints folders for specific conditions

$dirs = [];

if (!empty($report)) {
  include_once(__DIR__ . "/../../modules/view/__post_load.php");
  include_once(__DIR__ . "/../../modules/view/generators/pickban_teams.php");

  if(empty($mod)) $mod = "";

  $__dirs[] = __DIR__ . "/modules/report/";
  $__fallback = 'info';
} else {
  $__dirs[] = __DIR__ . "/modules/main/";
  $__fallback = 'list';

  if (is_docs_mode()) {
    $__dirs[] = __DIR__ . "/modules/report/";
  }
}

// Load endpoints

foreach ($__dirs as $__dir) {
  $__list = scandir($__dir);
  foreach ($__list as $file) {
    if ($file[0] == '.' || !strpos($file, '.php')) continue;
    include_once($__dir . $file);
  }
}

$endpoints['__fallback'] = function() use (&$endpoints, $__fallback) {
  return $endpoints[$__fallback];
};

// Modlink processor

$mod = str_replace("/", "-", $mod);
$modline = array_reverse(explode("-", $mod));

// Load framework pieces and register endpoint classes BEFORE parsing variables
include_once(__DIR__ . "/attributes.php");
include_once(__DIR__ . "/schema.php");

// Discover endpoint classes in loaded files
$declared = get_declared_classes();
foreach ($declared as $class) {
  if (is_subclass_of($class, 'EndpointTemplate')) {
    $ref = new \ReflectionClass($class);
    $endpointAttr = $ref->getAttributes('Endpoint');
    if (!empty($endpointAttr)) {
      $args = $endpointAttr[0]->getArguments();
      $name = $args['name'] ?? strtolower($ref->getShortName());
    } else {
      $name = strtolower($ref->getShortName());
    }
    $endpointObjects[$name] = function(&$mods, &$vars, &$report) use ($class) {
      return new $class($mods, $vars, $report);
    };
  }
}

// Back-compat adapters: expose class endpoints via $endpoints[] closures
foreach ($endpointObjects as $name => $factory) {
  if (!isset($endpoints[$name])) {
    $endpoints[$name] = function(&$mods, &$vars, &$report) use ($factory) {
      $obj = $factory($mods, $vars, $report);
      return $obj->process();
    };
  }
}

// Variables and repeaters
include_once(__DIR__ . "/execute.php");
include_once(__DIR__ . "/variables.php");
include_once(__DIR__ . "/repeaters.php");

if (empty($endp_name)) {
  $endp = $endpoints['__fallback']();
  $endp_name = array_search($endp, $endpoints, true);
} else {
  $endp = $endpoints[$endp_name] ?? null;
}

if ($endp === null) {
  throw new UserInputException("Endpoint not found: ".$endp_name);
}

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

// OpenAPI generator support
$__openapi = isset($_GET['openapi']);
if ($__openapi) {
  $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

  if (!empty($GLOBALS['_webapi_root'])) {
    $baseUrl = rtrim($GLOBALS['_webapi_root']);
  } else {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ($_SERVER['SCRIPT_NAME'] ?? '/rg_webapi.php');
    $baseUrl = $scheme."://".$host.$requestPath;
  }

  $paths = [];
  foreach ($endpointObjects as $name => $factory) {
    $obj = $factory($modline, $vars, $report);
    $pathKey = "/".str_replace('_', '-', $name);
    $item = $obj->toOpenApiPathItem();
    $paths[$pathKey] = [ 'get' => $item ];
  }

  $components = [
    'schemas' => [
      ...SchemaRegistry::all()
    ]
  ];

  $openapi = [
    'openapi' => '3.0.3',
    'info' => [
      'title' => 'LRG2 Web API',
      'version' => implode('.', array_slice($lg_version, 0, 3)),
      'description' => 'Automatically generated schema of modline endpoints.'
    ],
    'servers' => [ [ 'url' => $baseUrl ] ],
    'paths' => $paths,
    'components' => $components,
  ];

  header('Content-Type: application/json');
  echo json_encode($openapi, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}
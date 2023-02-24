<?php 

$isApi = true;

$resp = [
  "modline" => null,
  "vars" => null,
  "endpoint" => null,
  "version" => null,
  "result" => null,
];

$imports_ignore = [
  "schema.php",
  "readline.php",
  "recursive_scandir.php",
  "check_directory.php",
];

$lg_version = [ 2, 25, 1, 0, 0 ];

$imports = scandir("modules/commons/");
foreach ($imports as $f) {
  if ($f[0] == '.' || in_array($f, $imports_ignore)) continue;
  include_once("modules/commons/$f");
}

$localesMap = include_once("locales/map.php");

$postrun = [];

$locales = [];
foreach ($localesMap as $lc => $lv) {
  if ($lv['alias'] ?? false) continue;
  $locales[ $lc.($lv['beta'] ?? false ? '_beta' : '') ] = ( $lv['name'] ?? $lc ).($lv['beta'] ?? false ? ' (beta)' : '');
}

$def_locale = isset($localesMap['def']) ? $localesMap['def']['alias'] : 'en';

$isBetaLocale = false;
$locale = $_COOKIE['loc'] ?? GetLanguageCodeISO6391();
$origLocale = GetLanguageCodeISO6391();

if (isset($localesMap[ $locale ]) && ($localesMap[ $locale ]['alias'] ?? false)) {
  $locale = $localesMap[ $locale ]['alias'];
}

if (strpos($locale, '_beta')) {
  $isBetaLocale = true;
  $locale = str_replace("_beta", "", $locale);
}

$linkvars = [];

if(isset($_REQUEST['loc']) && !empty($_REQUEST['loc'])) {
  $locale = $_REQUEST['loc'];
  $linkvars[] = array("loc", $_REQUEST['loc']);
}

// require_once('locales/en.php');
include_locale('en');
if(strtolower($locale) != "en") {
  include_locale($locale) or $locale = "en";
}
if (isset($strings[$locale]) && isset($strings[$locale]['__fallback'])) {
  $fallback_locale = $strings[$locale]['__fallback'];
  if (!isset($strings[$fallback_locale])) {
    include_locale($fallback_locale);
  }
}

$host_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 
                "https" : "http") . "://" . $_SERVER['HTTP_HOST'] .  
                dirname($_SERVER['REQUEST_URI']); 

$include_descriptor = isset($_REQUEST['desc']);
$include_team = isset($_REQUEST['teamcard']);

// View functions imports
$imports_api = scandir("modules/webapi/functions/");
$imports = scandir("modules/view/functions/");
foreach ($imports as $f) {
  if ($f[0] == '.' || in_array($f, $imports_ignore) || in_array($f, $imports_api)) continue;
  include_once("modules/view/functions/$f");
}
foreach ($imports_api as $f) {
  if ($f[0] == '.' || in_array($f, $imports_ignore)) continue;
  include_once("modules/webapi/functions/$f");
}

// Additional imports
if (file_exists("modules/__imports/view") && is_dir("modules/__imports/view")) {
  $imports = scandir("modules/__imports/view/");
  foreach ($imports as $f) {
    if ($f[0] == '.' || in_array($f, $imports_ignore)) continue;
    include_once("modules/__imports/view/$f");
  }
}
if (file_exists("modules/__imports/api") && is_dir("modules/__imports/api")) {
  $imports = scandir("modules/__imports/api/");
  foreach ($imports as $f) {
    if ($f[0] == '.' || in_array($f, $imports_ignore)) continue;
    include_once("modules/__imports/api/$f");
  }
}

include_once("rg_report_out_settings.php");

set_error_handler(
  function ($severity, $message, $file, $line) {
    if (strpos($message, 'file_get_contents')) {
      $dt = strrpos($message, '):');
      $message = substr($message, $dt+3, strlen($message)-$dt-5);
    }
    throw new ErrorException($message, $severity, $severity, $file, $line);
  }
);

# PRESETS
include_once("modules/view/__preset.php");

/* INITIALISATION */

$root = dirname(__FILE__);

if(isset($_GET['mod'])) $mod = strtolower($_GET['mod']);
else $mod = "";

if(isset($_GET['cat']) && !empty($_GET['cat'])) {
  $cat = $_GET['cat'];
} else $cat = null;

if(isset($_GET['league']) && !empty($_GET['league'])) {
  $leaguetag = $_GET['league'];
} else $leaguetag = "";

if (!empty($leaguetag)) {
  if(file_exists($reports_dir."/".$report_mask_search[0].$leaguetag.$report_mask_search[1])) {
    $report = file_get_contents($reports_dir."/".$report_mask_search[0].$leaguetag.$report_mask_search[1])
        or die("[F] Can't open $teaguetag, probably no such report\n");
    $report = json_decode($report, true);
  } else {
    $lightcache = true;
    include("modules/view/__open_cache.php");
    include("modules/view/__update_cache.php");
    if(isset($cache['reps'][$leaguetag]['file'])) {
      $report = file_get_contents($reports_dir."/".$cache['reps'][$leaguetag]['file'])
          or die("[F] Can't open $leaguetag, probably no such report\n");
      $report = json_decode($report, true);
    } else $leaguetag = "";
  }
} else $report = [];

include_once(__DIR__ . "/modules/webapi/modules.php");


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
//header("Access-Control-Allow-Headers: X-Requested-With");
header('Access-Control-Allow-Headers: token, Content-Type');

// set_error_handler(
//   function ($severity, $message, $file, $line) {
//     throw new \Exception($severity.' - '.$message.'('.$file.':'.$line.')', $severity);
//   }
// );

$resp['modline'] = $mod;
$resp['vars'] = $vars;
$resp['endpoint'] = $endp_name;
$resp['report'] = !empty($leaguetag) ? $leaguetag : null;
$resp['version'] = parse_ver($lg_version);
$resp['result'] = $result ?? [];

if (!empty($report) && $include_descriptor) {
  $resp['report_desc'] = get_report_descriptor($report, true);
}
if (!empty($report) && $include_team && !empty($vars['team']) && !is_array($vars['team'])) {
  $resp['team_card'] = team_card($vars['team']);
}

if (!empty($postrun)) {
  foreach ($postrun as $cb) {
    $cb($resp);
  }
}

echo json_encode($resp, (isset($_REQUEST['pretty']) ? JSON_PRETTY_PRINT : 0) 
  | JSON_INVALID_UTF8_SUBSTITUTE 
  | JSON_UNESCAPED_UNICODE
  | JSON_NUMERIC_CHECK 
  //| JSON_THROW_ON_ERROR
);

require_once("modules/view/__post_render.php");
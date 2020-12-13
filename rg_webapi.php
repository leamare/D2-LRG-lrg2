<?php 

$resp = [
  "modline" => null,
  "vars" => null,
  "endpoint" => null,
  "version" => null,
  "result" => null,
];

//$locale = $_COOKIE['loc'] ?? GetLanguageCodeISO6391();

if(isset($_REQUEST['loc']) && !empty($_REQUEST['loc'])) {
  $locale = $_REQUEST['loc'];
}

$include_descriptor = isset($_REQUEST['desc']);

include_once("rg_report_out_settings.php");
include_once("modules/commons/versions.php");
$lg_version = [ 2, 13, 0, 0, 0 ];

include_once("modules/commons/locale_strings.php");

require_once('locales/en.php');
if(strtolower($locale) != "en" && file_exists('locales/'.$locale.'.php'))
  require_once('locales/'.$locale.'.php');
else $locale = "en";

include_once("modules/commons/merge_mods.php");
include_once("modules/commons/metadata.php");
include_once("modules/commons/wrap_data.php");

# FUNCTIONS
include_once("modules/view/functions/modules.php");
include_once("modules/view/functions/report_descriptor.php");

include_once("modules/view/functions/team_name.php");
include_once("modules/view/functions/player_name.php");
include_once("modules/view/functions/hero_name.php");

include_once("modules/webapi/functions/player_card.php");
include_once("modules/webapi/functions/team_card.php");
include_once("modules/webapi/functions/match_card.php");

include_once("modules/view/functions/join_selectors.php");
include_once("modules/view/functions/links.php");
include_once("modules/view/functions/join_matches.php");

include_once("modules/view/functions/has_pair.php");
include_once("modules/view/functions/check_filters.php");

include_once("modules/view/generators/pvp_unwrap_data.php");

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

set_error_handler(
  function ($severity, $message, $file, $line) {
    if (strpos($message, 'file_get_contents')) {
      $dt = strrpos($message, '):');
      $message = substr($message, $dt+3, strlen($message)-$dt-5);
    }
    throw new ErrorException($message, $severity, $severity, $file, $line);
  }
);

include_once(__DIR__ . "/modules/webapi/modules.php");


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
//header("Access-Control-Allow-Headers: X-Requested-With");
header('Access-Control-Allow-Headers: token, Content-Type');

set_error_handler(
  function ($severity, $message, $file, $line) {
    throw new \Exception($severity.' - '.$message.'('.$file.':'.$line.')', $severity);
  }
);

$resp['modline'] = $mod;
$resp['vars'] = $vars;
$resp['endpoint'] = $endp_name;
$resp['version'] = parse_ver($lg_version);
$resp['result'] = $result ?? [];

if (!empty($report) && $include_descriptor) {
  $resp['report_desc'] = get_report_descriptor($report, true);
}

echo json_encode($resp, (isset($_REQUEST['pretty']) ? JSON_PRETTY_PRINT : 0) 
  | JSON_INVALID_UTF8_SUBSTITUTE 
  | JSON_UNESCAPED_UNICODE
  | JSON_NUMERIC_CHECK 
  //| JSON_THROW_ON_ERROR
);

<?php 

$meta = new lrg_metadata;
$endpoints = [];

if (isset($report)) {
  include_once("modules/view/__post_load.php");

  if(empty($mod)) $mod = "";

  include_once(__DIR__ . "/modules/overview.php");
  include_once(__DIR__ . "/modules/records.php");
  include_once(__DIR__ . "/modules/haverages.php");
  include_once(__DIR__ . "/modules/participants.php");
  include_once(__DIR__ . "/modules/matches.php");
  include_once(__DIR__ . "/modules/combos.php");
  include_once(__DIR__ . "/modules/meta_graph.php");
  include_once(__DIR__ . "/modules/party_graph.php");
  include_once(__DIR__ . "/modules/pickban.php");
  include_once(__DIR__ . "/modules/draft.php");
  include_once(__DIR__ . "/modules/vsdraft.php");
  include_once(__DIR__ . "/modules/positions.php");
  include_once(__DIR__ . "/modules/positions_matches.php");
  include_once(__DIR__ . "/modules/pvp.php");
  include_once(__DIR__ . "/modules/hvh.php");
  include_once(__DIR__ . "/modules/summary.php");
  include_once(__DIR__ . "/modules/matchcards.php");
  include_once(__DIR__ . "/modules/teams_raw.php");
  // TODO: teams-cards
  include_once(__DIR__ . "/modules/roster.php");

  $endpoints['__fallback'] = function() use (&$endpoints) {
    return $endpoints['overview'];
  };
} else {
  // TODO: fallback basic response
  // TODO: list of reports + category
  include_once(__DIR__ . "/modules/metadata.php");
  include_once(__DIR__ . "/modules/locales.php");
  include_once(__DIR__ . "/modules/getcache.php");
  include_once(__DIR__ . "/modules/raw.php");
}

$mod = strtolower(str_replace("/", "-", $mod));
$modline = array_reverse(explode("-", $mod));
$vars = [];

foreach ($modline as $ml) {
  if (!isset($endp) && isset($endpoints[$ml])) {
    $endp = $endpoints[$ml];
    break;
  }
  if (strpos($ml, "region") && $ml != "regions") $vars['region'] = (int)str_replace("region", "", $ml);
  if (strpos($ml, "position_")) $vars['position'] = str_replace("position_", "", $ml);
  if (strpos($ml, "heroid")) $vars['heroid'] = (int)str_replace("heroid", "", $ml);
  if (strpos($ml, "playerid")) $vars['playerid'] = (int)str_replace("playerid", "", $ml);
  if (strpos($ml, "team") && $ml != "teams") $vars['team'] = (int)str_replace("team", "", $ml);
  if (isset($_GET['gets'])) $vars['gets'] = explode(",", strtolower($_GET['gets']));
  if (isset($_GET['rep'])) $vars['rep'] = strtolower($_GET['rep']);
}
if (empty($endp))
  $endp = $endpoints['__fallback']();

try {
  $result = $endp($modline, $vars, $report ?? []);
} catch (\Throwable $e) {
  $result = [
    'error' => $e->getMessage()
  ];
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
//header("Access-Control-Allow-Headers: X-Requested-With");
header('Access-Control-Allow-Headers: token, Content-Type');


echo json_encode($result, (isset($_REQUEST['pretty']) ? JSON_PRETTY_PRINT : 0) 
  | JSON_INVALID_UTF8_SUBSTITUTE 
  | JSON_UNESCAPED_UNICODE
  //| JSON_THROW_ON_ERROR
);

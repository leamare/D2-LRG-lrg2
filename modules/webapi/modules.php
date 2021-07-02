<?php 

$meta = new lrg_metadata;
$endpoints = [];
$repeatVars = [];

if (!empty($report)) {
  include_once(__DIR__ . "/../../modules/view/__post_load.php");
  include_once(__DIR__ . "/../../modules/view/generators/pickban_teams.php");

  if(empty($mod)) $mod = "";

  include_once(__DIR__ . "/modules/info.php");
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
  include_once(__DIR__ . "/modules/rolepickban.php");
  include_once(__DIR__ . "/modules/positions_matches.php");
  include_once(__DIR__ . "/modules/pvp.php");
  include_once(__DIR__ . "/modules/hvh.php");
  include_once(__DIR__ . "/modules/hph.php");
  include_once(__DIR__ . "/modules/summary.php");
  include_once(__DIR__ . "/modules/sides.php");
  include_once(__DIR__ . "/modules/matchcards.php");
  include_once(__DIR__ . "/modules/teams_raw.php");
  include_once(__DIR__ . "/modules/teams.php");
  include_once(__DIR__ . "/modules/roster.php");
  include_once(__DIR__ . "/modules/laning.php");
  include_once(__DIR__ . "/modules/counters.php");
  include_once(__DIR__ . "/modules/daily_wr.php");
  include_once(__DIR__ . "/modules/wrtimings.php");
  include_once(__DIR__ . "/modules/wrplayers.php");
  include_once(__DIR__ . "/modules/items.php");
  include_once(__DIR__ . "/modules/profiles.php");
  include_once(__DIR__ . "/modules/draft_tree.php");

  $endpoints['__fallback'] = function() use (&$endpoints) {
    return $endpoints['info'];
  };
} else {
  include_once(__DIR__ . "/modules/list.php");
  include_once(__DIR__ . "/modules/metadata.php");
  include_once(__DIR__ . "/modules/locales.php");
  include_once(__DIR__ . "/modules/getcache.php");
  include_once(__DIR__ . "/modules/raw.php");

  $endpoints['__fallback'] = function() use (&$endpoints) {
    return $endpoints['list'];
  };
}

$mod = str_replace("/", "-", $mod);
$modline = array_reverse(explode("-", $mod));

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
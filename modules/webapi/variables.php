<?php 

$vars = [];

foreach ($modline as $ml) {
  if (!isset($endp_name) && isset($endpoints[$ml])) {
    $endp_name = $ml;
  }
  if (strpos($ml, "region") !== FALSE && $ml != "regions") {
    $ml = str_replace("region", "", $ml);
    if (strpos($ml, ",") !== FALSE) {
      $vars['region'] = explode(',', $ml);
    } else if ($ml == '*' && !empty($report['regions_data'])) {
      $vars['region'] = array_keys($report['regions_data']);
    } else {
      $vars['region'] = (int)$ml;
    }
  }

  if (strpos($ml, "position_") !== FALSE) {
    $ml = str_replace("position_", "", $ml);
    if (strpos($ml, ",") !== FALSE) {
      $vars['position'] = explode(',', $ml);
    } else if ($ml == '*') {
      $vars['position'] = [ '0.1', '0.3', '1.1', '1.2', '1.3' ];
    } else {
      $vars['position'] = $ml;
    }
  }

  if (strpos($ml, "heroid") !== FALSE) {
    $ml = str_replace("heroid", "", $ml);
    if (strpos($ml, ",") !== FALSE) {
      $vars['heroid'] = explode(',', $ml);
    } else if ($ml == '*') {
      $vars['heroid'] = array_keys($report['pickban']);
    } else {
      $vars['heroid'] = (int)$ml;
    }
  }

  if (strpos($ml, "playerid") !== FALSE) {
    $ml = str_replace("playerid", "", $ml);
    if (strpos($ml, ",") !== FALSE) {
      $vars['playerid'] = explode(',', $ml);
    } else if ($ml == '*' && !empty($report['players'])) {
      $vars['playerid'] = array_keys($report['players']);
    } else {
      $vars['playerid'] = (int)$ml;
    }
  }
  
  if ((strpos($ml, "team") !== FALSE && $ml != "teams") || (strpos($ml, "teamid") !== FALSE)) {
    $ml = str_replace("team", "", str_replace("teamid", "", $ml));

    if (strpos($ml, ",") !== FALSE) {
      $vars['team'] = explode(',', $ml);
    } else if ($ml == '*' && !empty($report['teams'])) {
      $vars['team'] = $report['teams_interest'] ?? array_keys($report['teams']);
    } else {
      $vars['team'] = (int)$ml;
    }
  }

  if (strpos($ml, "optid") !== FALSE) {
    $ml = str_replace("optid", "", $ml);

    if (strpos($ml, ",") !== FALSE) {
      $vars['optid'] = explode(',', $ml);
    } else if ($ml == '*' && !empty($report['teams'])) {
      $vars['optid'] = $report['teams_interest'] ?? array_keys($report['teams']);
    } else {
      $vars['optid'] = (int)$ml;
    }
  }

  if (strpos($ml, "itemid") !== FALSE) {
    $ml = str_replace("item", "", str_replace("itemid", "", $ml));
    if (strpos($ml, ",") !== FALSE) {
      $vars['item'] = explode(',', $ml);
    } else if ($ml == '*' && isset($report['items']) && !empty($report['items']['stats']['total'])) {
      $vars['item'] = array_keys($report['items']['stats']['total']);
    } else {
      $vars['item'] = (int)$ml;
    }
  }

  //if (isset($vars['team'])) $vars['teamid'] = $vars['team']; 
}

if (isset($_GET['cat']) && !empty($_GET['cat'])) {
  $ml = $_GET['cat'];

  if (strpos($ml, ",") !== FALSE) {
    $vars['cat'] = explode(',', $ml);
  // } else if ($ml == '*') {
  //   $vars['cat'] = array_keys();
  } else {
    $vars['cat'] = (int)$ml;
  }
}

// $vars['cat'] = $_GET['cat'];

if (isset($_GET['gets'])) $vars['gets'] = explode(",", strtolower($_GET['gets']));
if (isset($_GET['rep'])) $vars['rep'] = strtolower($_GET['rep']);
if (isset($_GET['search'])) $vars['search'] = $_GET['search'];
if (isset($_GET['item_cat']) && !empty($_GET['item_cat'])) $vars['item_cat'] = explode(',', $_GET['item_cat']);
$vars['simple_matchcard'] = isset($_GET['simple_matchcard']);
$vars['include_matches'] = isset($_GET['include_matches']);

$repeaters = [];

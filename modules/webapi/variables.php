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

  if (strpos($ml, "variant") !== FALSE) {
    $ml = str_replace("variant", "", $ml);
    if (strpos($ml, ",") !== FALSE) {
      $vars['variant'] = explode(',', $ml);
    } else {
      $vars['variant'] = is_numeric($ml) ? +$ml : null;
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
    $vars['cat'] = $ml;
  }
}

// $vars['cat'] = $_GET['cat'];

if (isset($_GET['latest']) && $getlatest) {
  $vars['latest'] = $getlatest;
  if (!empty($leaguetag)) $vars['latest_report'] = $leaguetag;
}

if (isset($_GET['gets'])) $vars['gets'] = explode(",", strtolower($_GET['gets']));
if (isset($_GET['rep'])) $vars['rep'] = strtolower($_GET['rep']);
if (isset($_GET['search'])) $vars['search'] = $_GET['search'];
if (isset($_GET['item_cat']) && !empty($_GET['item_cat'])) $vars['item_cat'] = explode(',', $_GET['item_cat']);
$vars['simple_matchcard'] = isset($_GET['simple_matchcard']);
$vars['include_matches'] = isset($_GET['include_matches']);

// Fallback GET parameters for modline variables (do not override if already parsed from modline)
// helper closure for parsing lists or ints
$parse_list_or_int = function($val) {
  if (strpos($val, ',') !== false) return array_map('intval', explode(',', $val));
  if ($val === '*') return '*';
  return is_numeric($val) ? (int)$val : $val;
};

if (isset($_GET['region']) && !isset($vars['region'])) {
  $v = $_GET['region'];
  if ($v === '*') {
    $vars['region'] = !empty($report['regions_data']) ? array_keys($report['regions_data']) : [];
  } else if (strpos($v, ',') !== false) {
    $vars['region'] = array_map('intval', explode(',', $v));
  } else {
    $vars['region'] = (int)$v;
  }
}

if (isset($_GET['position']) && !isset($vars['position'])) {
  $v = $_GET['position'];
  $vars['position'] = (strpos($v, ',') !== false) ? explode(',', $v) : $v;
}

if (isset($_GET['heroid']) && !isset($vars['heroid'])) {
  $v = $_GET['heroid'];
  if ($v === '*') $vars['heroid'] = isset($report['pickban']) ? array_keys($report['pickban']) : [];
  else if (strpos($v, ',') !== false) $vars['heroid'] = array_map('intval', explode(',', $v));
  else $vars['heroid'] = (int)$v;
}

if (isset($_GET['variant']) && !isset($vars['variant'])) {
  $vars['variant'] = $parse_list_or_int($_GET['variant']);
}

if (isset($_GET['playerid']) && !isset($vars['playerid'])) {
  $v = $_GET['playerid'];
  if ($v === '*') $vars['playerid'] = isset($report['players']) ? array_keys($report['players']) : [];
  else if (strpos($v, ',') !== false) $vars['playerid'] = array_map('intval', explode(',', $v));
  else $vars['playerid'] = (int)$v;
}

if ((isset($_GET['team']) || isset($_GET['teamid'])) && !isset($vars['team'])) {
  $v = $_GET['team'] ?? $_GET['teamid'];
  if ($v === '*') $vars['team'] = isset($report['teams']) ? ($report['teams_interest'] ?? array_keys($report['teams'])) : [];
  else if (strpos($v, ',') !== false) $vars['team'] = array_map('intval', explode(',', $v));
  else $vars['team'] = (int)$v;
}

if (isset($_GET['optid']) && !isset($vars['optid'])) {
  $v = $_GET['optid'];
  if ($v === '*') $vars['optid'] = isset($report['teams']) ? ($report['teams_interest'] ?? array_keys($report['teams'])) : [];
  else if (strpos($v, ',') !== false) $vars['optid'] = array_map('intval', explode(',', $v));
  else $vars['optid'] = (int)$v;
}

if ((isset($_GET['itemid']) || isset($_GET['item'])) && !isset($vars['itemid']) && !isset($vars['item'])) {
  $v = $_GET['itemid'] ?? $_GET['item'];
  if ($v === '*') $vars['itemid'] = (isset($report['items']['stats']['total']) ? array_keys($report['items']['stats']['total']) : []);
  else if (strpos($v, ',') !== false) $vars['itemid'] = array_map('intval', explode(',', $v));
  else $vars['itemid'] = (int)$v;
}

$repeaters = [];

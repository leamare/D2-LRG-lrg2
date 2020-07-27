<?php 

include_once("rg_report_out_settings.php");
include_once("modules/commons/versions.php");
$lg_version = [ 2, 4, 3, 0, 0 ];

include_once("modules/commons/locale_strings.php");
include_once("modules/commons/merge_mods.php");
include_once("modules/commons/metadata.php");

# FUNCTIONS
include_once("modules/view/functions/modules.php");
include_once("modules/view/functions/report_descriptor.php");

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

include_once("modules/view/functions/team_name.php");
include_once("modules/view/functions/player_name.php");
include_once("modules/view/functions/hero_name.php");

include_once(__DIR__ . "/modules/webapi/modules.php");
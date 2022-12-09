<?php

ini_set('memory_limit', '1024M');

include_once("modules/commons/locale_strings.php");
include_once("modules/commons/array_pslice.php");
include_once("modules/commons/get_language_code_iso6391.php");

$localesMap = include_once("locales/map.php");

$locales = [];
foreach ($localesMap as $lc => $lv) {
  if ($lv['alias'] ?? false) continue;
  $locales[ $lc.($lv['beta'] ?? false ? '_beta' : '') ] = ( $lv['name'] ?? $lc ).($lv['beta'] ?? false ? ' (beta)' : '');
}

$def_locale = isset($localesMap['def']) ? $localesMap['def']['alias'] : 'en';

$isBetaLocale = false;
$locale = $_COOKIE['loc'] ?? GetLanguageCodeISO6391();

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

include_once("rg_report_out_settings.php");

include_once("modules/commons/versions.php");
$lg_version = [ 2, 25, 1, 0, 0 ];

include_once("modules/commons/merge_mods.php");
include_once("modules/commons/metadata.php");
include_once("modules/commons/wrap_data.php");

# FUNCTIONS
include_once("modules/view/functions/modules.php");

include_once("modules/view/functions/player_card.php");
include_once("modules/view/functions/team_card.php");
include_once("modules/view/functions/match_card.php");
include_once("modules/view/functions/filter_toggles.php");
include_once("modules/view/functions/search_filter_component.php");
include_once("modules/view/functions/table_columns_toggle.php");

include_once("modules/view/functions/join_selectors.php");
include_once("modules/view/functions/links.php");
include_once("modules/view/functions/join_matches.php");

include_once("modules/view/functions/team_name.php");
include_once("modules/view/functions/player_name.php");
include_once("modules/view/functions/hero_name.php");

include_once("modules/view/functions/has_pair.php");
include_once("modules/view/functions/process_menu.php");

# PRESETS
include_once("modules/view/__preset.php");

/* INITIALISATION */

$root = dirname(__FILE__);

$linkvars = [];
$carryon = [];

$_earlypreview = empty($previewcode) ? true : false;

if ($lrg_use_get) {
  if(isset($_GET['mod'])) $mod = $_GET['mod'];
  else $mod = "";

  $_lid = null;

  if(isset($_GET['league']) && !empty($_GET['league'])) {
    $leaguetag = $_GET['league'];
  } else $leaguetag = "";

  if(isset($_GET['loc']) && !empty($_GET['loc'])) {
    $locale = $_GET['loc'];
    $linkvars[] = array("loc", $_GET['loc']);
  }
  if(isset($_GET['stow']) && !empty($_GET['stow'])) {
    $override_style = $_GET['stow'];
    $linkvars[] = array("stow", $_GET['stow']);
  }
  if(!empty($previewcode) && isset($_GET['earlypreview']) && ($_GET['earlypreview'] == $previewcode)) {
    $linkvars[] = [ "earlypreview", $previewcode ];
    $_earlypreview = true;
  }
  if(isset($_GET['cat']) && !empty($_GET['cat'])) {
    $cat = $_GET['cat'];
    //$linkvars[] = array("cat", $_GET['cat']);
  } //else $cat = "";
  if(isset($_GET['search']) && !empty($_GET['search'])) {
    $searchstring = $_GET['search'];
  }

  if(isset($_GET['latest'])) $latest = true;
  if(isset($_GET['lid'])) $_lid = $_GET['lid'];
}

$_rawmod = $mod;

$use_visjs = false;
$use_graphjs = false;
$use_graphjs_boxplots = false;

for($i=0,$sz=sizeof($linkvars); $i<$sz; $i++)
  $linkvars[$i] = implode("=", $linkvars[$i]);
$linkvars = implode("&", $linkvars);


if (!empty($leaguetag)) {
  $lightcache = true;
  include_once("modules/view/__open_cache.php");
  // this is kind of pointless, but I need it to load anyway
  // and it's not like in real life it's not loaded all the time

  if(file_exists($reports_dir."/".$report_mask_search[0].$leaguetag.$report_mask_search[1])) {
    $report = file_get_contents($reports_dir."/".$report_mask_search[0].$leaguetag.$report_mask_search[1])
        or die("[F] Can't open $leaguetag, probably no such report\n");
    $report = json_decode($report, true);
  } else {
    if(isset($cache['reps'][$leaguetag]['file'])) {
      $report = file_get_contents($reports_dir."/".$cache['reps'][$leaguetag]['file'])
          or die("[F] Can't open $leaguetag, probably no such report\n");
      $report = json_decode($report, true);
    } else $leaguetag = "";
  }
}

$meta = new lrg_metadata;

if (isset($report)) {
  $output = "";

  include_once("modules/view/__post_load.php");

  if(isset($report['settings']['custom_style']) && file_exists("res/custom_styles/".$report['settings']['custom_style'].".css"))
    $custom_style = $report['settings']['custom_style'];
  if(isset($report['settings']['custom_logo']) && file_exists("res/custom_styles/logos/".$report['settings']['custom_logo'].".css"))
    $custom_logo = $report['settings']['custom_logo'];

  $modules = [];
  # module => array or ""
  include_once("modules/view/overview.php");

  if ($report['random']['matches_total']) {
    if (isset($report['records']))
      include_once("modules/view/records.php");

    if (isset($report['milestones']))
      include_once("modules/view/milestones.php");

    if (isset($report['averages_heroes']) || isset($report['pickban']) || isset($report['draft']) || isset($report['hero_positions']) ||
        isset($report['hero_sides']) || isset($report['hero_pairs']) || isset($report['hero_triplets']))
          include_once("modules/view/heroes.php");

    if (!empty($report['items']) && !empty($report['items']['pi']))
      include_once("modules/view/items.php");

    if (isset($report['players']) || isset($report['players_summary']))
      include_once("modules/view/players.php");

    if (isset($report['teams'])) {
      include_once("modules/view/teams.php");
    }

    if (isset($report['regions_data']))
      include_once("modules/view/regions.php");

    if (isset($report['matches']))
      include_once("modules/view/matches.php");

    if (isset($report['players']))
      include_once("modules/view/participants.php");
  }

  if(empty($mod)) $unset_module = true;
  else $unset_module = false;

  $h3 = array_rand($report['random']);


  # overview
  if (check_module("overview")) {
    merge_mods($modules['overview'], rg_view_generate_overview());
  }

  # records
  if (isset($modules['records']) && check_module("records")) {
    merge_mods($modules['records'], rg_view_generate_records());
  }

  # milestones
  if (isset($modules['milestones']) && check_module("milestones")) {
    merge_mods($modules['milestones'], rg_view_generate_milestones());
  }

  # heroes
  if (isset($modules['heroes']) && check_module("heroes")) {
    merge_mods($modules['heroes'], rg_view_generate_heroes());
  }

  # items
  if (isset($modules['items']) && check_module("items") && !empty($report['items'])) {
    merge_mods($modules['items'], rg_view_generate_items());
  }

  # players
  if (isset($modules['players']) && check_module("players")) {
    merge_mods($modules['players'], rg_view_generate_players());
  }

  # teams
  if (isset($modules['teams']) && check_module("teams")) {
    merge_mods($modules['teams'], rg_view_generate_teams());
  }

  if (isset($modules['regions']) && check_module("regions")) {
    merge_mods($modules['regions'], rg_view_generate_regions());
  }

  # matches
  if (isset($modules['matches']) && check_module("matches")) {
    merge_mods($modules['matches'], rg_view_generate_matches());
  }

  # participants
  if(isset($modules['participants']) && check_module("participants")) {
    merge_mods($modules['participants'], rg_view_generate_participants());
  }
} else {
  include_once("modules/view/index.php");
}

if (file_exists("rg_report_out_prerender.php"))
  include_once("rg_report_out_prerender.php");

include_once("modules/view/__template.php");


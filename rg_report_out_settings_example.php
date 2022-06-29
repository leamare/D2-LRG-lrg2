<?php
/* SETTINGS */

$lrg_use_get = true;
$lrg_get_depth = 6;
$locale = $locale ?? $def_locale;

$max_tabs = 12;

$previewcode = 123;
$_earlypreview_banlist = [
  // 'items-builds'
];


$recent_last_limit = time() - 14*24*3600;

$custom_head = "";

$custom_body = "";

$custom_content = "";

$custom_footer = "";

$support_me_block = "";

$ads_block = "";

$ads_block_main = "";

$title_links = array(
  // array( "link" => "",
  //       "title" => "",
  //       "text"
 //)
);

$main_path = "/rg_report_web.php";

$default_style = "";
$noleague_style = "";

$instance_title = locale_string("main_default_instance_title");
$instance_name = locale_string("main_specgg_instance_name");
$instance_desc = locale_string("main_default_intance_desc");

$instance_title_postfix = locale_string("main_default_desc");
$instance_long_desc = locale_string("main_default_desc_long");
$title_separator = "-";

# main page settings
$reports_dir = "reports";
$report_mask = "/(.*)\/?report_(.*)\.json/";
$report_mask_search = ["report_", ".json"];

$cache_file = "res/cachelist.json";
$cats_file = "res/meowslist.json";

$hidden_cat = "hidden";

$index_list = 5; #-1 all, 0 none, other - number of reports on main page
$title_slice_max = 4;

// $link_provider = "stratz.com"; //opendota.com dotabuff.com
$links_providers = [
  'Dotabuff' => "dotabuff.com",
  'Stratz' => "stratz.com",
  'OpenDota' => "opendota.com",
];

$search_info_link = "https://spectral.gg/lrg2search";

$portraits_provider = "https://courier.spectral.gg/images/dota/portraits/%HERO%.png";
$icons_provider = "https://courier.spectral.gg/images/dota/icons/%HERO%.png";

$item_icons_provider = "https://courier.spectral.gg/images/dota/items/%HERO%.png";
$item_profile_icons_provider = "https://courier.spectral.gg/images/dota/profile_badges/%HERO%.png?size=!source";

$link_provider_icon = "https://courier.spectral.gg/images/other/link_providers/%LPN%.png?size=smaller";
$league_logo_provider = "https://courier.spectral.gg/images/dota/leagues/%LID%_ticket.png?size=smaller";
$league_logo_banner_provider = "https://courier.spectral.gg/images/dota/leagues/%LID%_banner.png";
$team_logo_provider = "https://courier.spectral.gg/images/dota/teams/%TEAM%.png";
$player_photo_provider = "https://courier.spectral.gg/images/dota/players/%HERO%.png?size=smaller";
$hero_renderer_provider = "https://courier.spectral.gg/images/dota/renderers/%HERO%.png?size=smaller";

$roleicon_logo_provider = "https://courier.spectral.gg/images/dota/roles/%ROLE%_alt.png?size=smaller";

$__pinned = [
  // [ "report", false ],
  // [ "category", true ],
];

$__friends = [
  // [ "Name", "link", "icon link" ],
];

$__links = [
  // [ "Name", "link", "icon link" ],
];

$__lid_fallbacks = [
  "/^imm_ranked/" => "ranked",
  "/^competitive/" => "dpc"
];

$social_lid_fallback = $host_link."/res/custom_styles/assets/spectral/header2.jpg";
<?php
/* SETTINGS */

$lrg_use_get = true;
$lrg_get_depth = 6;
$def_locale = "en";
$locale = $locale ?? $def_locale;
$locales = [
  "en" => "English",
  "ru" => "Русский"
];

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

$support_me_block = "asdas";

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

$postfixes = [
  'en' => "Title postfix",
  'ru' => "Постфикс заголовка"
];

$long_descriptions = [
  'en' => "Long description",
  'ru' => "Длинное описание заголовка"
];

$instance_titles = [
  'en' => "LRG",
  //'ru' => "Длинное описание заголовка"
];

$instance_names = [
  'en' => "League Report Generator Instance",
  'ru' => "Инстанс генератора отчётов"
];

$instance_descs = [
  'en' => "Header description",
  'ru' => "Описание в шапке"
];

$instance_title = $instance_titles[ $locale ] ?? $instance_titles[ $def_locale ];
$instance_name = $instance_names[ $locale ] ?? $instance_names[ $def_locale ];
$instance_desc = $instance_descs[ $locale ] ?? $instance_descs[ $def_locale ];

$instance_title_postfix = $postfixes[ $locale ] ?? $postfixes[ $def_locale ];
$instance_long_desc = $long_descriptions[ $locale ] ?? $long_descriptions[ $def_locale ];
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

$link_provider = "stratz.com"; //opendota.com dotabuff.com
$links_providers = [
  'DB' => "dotabuff.com",
  'Stratz' => "stratz.com",
  'OD' => "opendota.com",
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
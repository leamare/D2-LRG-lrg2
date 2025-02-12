<?php
/* SETTINGS */

// used for logger requests
$projectName = "LRG2-dev";

// old variables, set the depth of the report at which dynamic module switching enables
$lrg_use_get = true;
$lrg_get_depth = 6;

// currently used locale
$locale = $locale ?? $def_locale;

// max number of tabs before they collapse into dropdown select
$max_tabs = 12;

// early preview settings: access code and hidden sections
$previewcode = 123;
$_earlypreview_banlist = [
  // 'recordext'
];
$_earlypreview_teaser = [
  // 'items-sticonsumables',
];

$_earlypreview_wa_ban = [
  // 'sticonsumables',
];

$hide_sti_block = true;

$vw_section_markers = [
  'new' => [
    'items-stitems',
    'items-stibuilds',
  ],
  'alpha' => [
    'items-buildspowerspikes'
  ],
  'beta' => [
    'items-irecords',
  ],
  'upcoming' => $_earlypreview_teaser,
];

$reports_earlypreview_ban = [
  'imm_ranked_737b',
];
$reports_earlypreview_ban_time = time() - 280*24*60*60;
$reports_earlypreview_ban_sections = [
  'wv' => [
    'hidden' => [
      'items-stibuilds',
    ],
    'teaser' => [
      'items-sticonsumables',
    ],
  ],
  'wa' => [
    'sticonsumables',
  ],
];

// custom content
$custom_head = "";
$custom_body = "";
$custom_content = "";
$custom_footer = "";

// custom information blocks
$support_me_block = "";
$ads_block = "";
$ads_block_main = "";

// additional title links
$title_links = array(
  // array( "link" => "",
  //       "title" => "",
  //       "text"
 //)
);

// root path of the instance
$main_path = "/rg_report_web.php";

// default styles
$default_style = "";
$noleague_style = "";

// instance title and description
$instance_title = locale_string("main_default_instance_title");
$instance_name = locale_string("main_specgg_instance_name");
$instance_desc = locale_string("main_default_intance_desc");

$instance_title_postfix = locale_string("main_default_desc");
$instance_long_desc = locale_string("main_default_desc_long");
$title_separator = "-";

// main page settings
$reports_dir = "reports";
$report_mask = "/(.*)\/?report_(.*)\.json/";
$report_mask_search = ["report_", ".json"];

// cache file location
$cache_file = "res/cachelist.json";
// categories description location
$cats_file = "res/meowslist.json";
// hidden reports category
$hidden_cat = "hidden";
// untrustworthy events
// $shady_cat = "shameonyou";

// legacy main page - number of reports: -1 all, 0 none, other - number of reports on main page
$index_list = 5;
$title_slice_max = 4;
$match_card_records_cnt = 12;

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

$cats_groups_priority = [
  'meta' => 0,
  'ranked' => 1,
  'tis' => 2,
  'valve' => 3,
  'series' => 4,
];

$cats_groups_names = [
  'valve' => locale_string('cat_group_valve'),
  'ranked' => locale_string('ranked'),
  'meta' => locale_string('cat_group_meta'),
  'tis' => "The Internationals",
  'series' => locale_string('cat_group_series'),
  'amateur' => locale_string('cat_amateur'),
  'archive' => locale_string('cat_group_archive'),
  'basic' => locale_string('cat_group_basic'),
  'dpc' => locale_string('cat_group_dpc'),
  'orgs' => locale_string('cat_group_orgs'),
];

$cats_groups_icons = [
  'valve' => 'valve',
  'ranked' => 'ranked',
  'tis' => 'int',
  'dpc' => 'dpc',
  'meta' => 'spectral',
  'series' => 'trophy',
  'amateur' => 'brawl',
  'archive' => 'archive',
  'basic' => 'element',
  'orgs' => 'star',
];

$cats_groups_hidden = [
  'spectral', 'scrims',
];

$__featured_cats = [
  'pinned_main' => [
    'type' => 1, // array
    'value' => [
      [ 'imm_ranked_meta_last_7', false ],
      [ 'ongoing', true ],
      [ 'recent', true ],
    ],
    'icon_lid' => "star",
  ],
  'recent' => [
    'type' => 0, // category alias
    'value' => 'recent',
    'limit' => 7, // 0 = no limit
    'icon_lid' => "live",
  ],
  'ongoing' => [
    'type' => 0, // category alias
    'value' => 'ongoing',
    'limit' => 7, // 0 = no limit
    'icon_lid' => "live",
  ],
  'upcoming' => [
    'type' => 0,
    'value' => 'upcoming',
    'see_more_block' => true,
    'icon_lid' => "upcoming",
  ],
  'main' => [
    'type' => 0,
    'value' => 'main_nometa',
    'loc_name' => 'main_reports',
    'linkto' => 'main',
    'limit' => 28,
  ],
];
<?php

$modules['tierlists']['players'] = [];

function rg_view_generate_tierlists_players() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  if ($mod == $parent."players") $unset_module = true;
  $parent_module = $parent."players-";

  include_once($root."/modules/view/generators/tier_list.php");

  $res = [];
  $stats = tier_list_player_hero_stats($report);

  $hnames = $meta["heroes"];
  uasort($hnames, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
  });

  foreach ($hnames as $hid => $name) {
    if (empty($stats[$hid])) continue;

    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if (!check_module($parent_module."heroid".$hid)) continue;

    $data = tier_list_entities_by_record($stats[$hid]);

    $res["heroid".$hid] = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_tierlists_players")."</div>".
        "</div>".
      "</details>";

    if ($data === null) {
      $res["heroid".$hid] .= "<div class=\"content-text\">".locale_string("stats_empty")."</div>";
      continue;
    }

    $hero_stats = $stats[$hid];
    $res["heroid".$hid] .= rg_generator_tier_list(
      "tierlists-players-$hid",
      $data,
      function($pid) use ($hero_stats) { return lrg_player_card($pid, $hero_stats[$pid] ?? null); },
      'tier_unplaced', 'players'
    );
  }

  return $res;
}

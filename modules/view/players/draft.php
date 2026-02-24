<?php
//echo dirname(__FILE__);
include_once($root."/modules/view/generators/draft.php");

$modules['players']['draft'] = "";

function rg_view_generate_players_draft() {
  global $report;

  $players_bans_disable = true;

  $context_pickban = [];
  foreach($report['players_summary'] as $id => $el) {
    $context_pickban[ $id ] = [
      "matches_banned" => 0,
      "winrate_banned" => 0,
      "matches_picked" => $el['matches_s'],
      "matches_total" => $el['matches_s'],
      "winrate_picked" => $el['winrate_s']
    ];
  }

  if (!empty($report['draft']) && !empty($report['matches_additional'])) {
    include_once __DIR__."/../functions/players_bans_estimate.php";
    
    if (!empty($report['teams']) && !empty($report['match_participants_teams'])) {
      $players_bans_disable = false;

      estimate_players_draft_processor_tvt_report($context_pickban);
    } else {
      $players_bans_disable = false;

      estimate_players_draft_processor_pvp_report($context_pickban);
    }
  }

  $res = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_draft_explainer")."</div>".
      "<div class=\"line\">".locale_string("desc_draft_targeted_bans_estimate_explainer")."</div>".
    "</div>".
  "</details>".rg_generator_draft("players-draft", $context_pickban, $report['players_draft'], $report["random"]["matches_total"], false, $players_bans_disable, true);

  return $res;
}


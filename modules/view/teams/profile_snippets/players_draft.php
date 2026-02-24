<?php

$res["team".$tid]['players']['draft'] = "";

if (compare_release_ver($report['ana_version'], [ 2, 18, 0, 0, 0 ]) < 0) {
  foreach ($context[$tid]['players_draft_pb'] as $id => $el) {
    if (!in_array($id, $context[$tid]['active_roster'])) {
      unset($context[$tid]['players_draft_pb'][$id]);
    }
  }
}

// ??? Skip meta bans?

if(check_module($context_mod."team".$tid."-players-draft")) {
  $players_bans_disable = true;
  if (!empty($context[$tid]['draft_vs']) && !empty($report['match_participants_teams']) && !empty($context[$tid]['matches'])) {
    $players_bans_disable = false;

    include_once __DIR__."/../../functions/players_bans_estimate.php";

    estimate_players_draft_processor_tvt_single_team($context, $tid);
  }

  $res["team".$tid]['players']['draft'] = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_draft_explainer")."</div>".
      "<div class=\"line\">".locale_string("desc_draft_targeted_bans_estimate_explainer")."</div>".
    "</div>".
  "</details>".
  rg_generator_draft("team$tid-players-draft",
    $context[$tid]['players_draft_pb'],
    $context[$tid]['players_draft'],
    $context[$tid]['matches_total'],
    false,
    $players_bans_disable,
    true,
  );
}
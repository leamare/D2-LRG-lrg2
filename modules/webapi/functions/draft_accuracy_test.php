<?php 

function rgapi_draft_accuracy_test(&$context_pickban, &$context_draft) {
  $ratios = [];
  foreach ($context_draft[1][3] as $dr) {
    $total = $context_pickban[ $dr['heroid'] ]['matches_total'];
    $stage = $dr['matches'];
    $ratios[] = $stage/$total;
  }
  $res = array_sum($ratios) / sizeof($ratios);
  if ($res < 0.15) return false;
  return true;
}
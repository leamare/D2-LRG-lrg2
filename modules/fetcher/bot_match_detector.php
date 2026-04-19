<?php

const LRG_BOT_DENIES_MAX           = 5;
const LRG_BOT_LHPM_MAX             = 5.0;
const LRG_BOT_INV_MIN_ITEMS        = 3;
const LRG_BOT_INV_SYMDIFF_MAX      = 2;

function lrg_bot_player_inventory(array $p): array {
  $inv = [];
  for ($k = 0; $k < 6; $k++) {
    $iid = (int)($p['item_'.$k] ?? 0);
    if ($iid > 0) $inv[$iid] = true;
  }
  for ($k = 0; $k < 3; $k++) {
    $iid = (int)($p['backpack_'.$k] ?? 0);
    if ($iid > 0) $inv[$iid] = true;
  }
  return array_keys($inv);
}

function lrg_bot_team_inventory_cloned(array $teamInventories): bool {
  if (count($teamInventories) < 5) return false;
  foreach ($teamInventories as $inv) {
    if (count($inv) < LRG_BOT_INV_MIN_ITEMS) return false;
  }
  $n = count($teamInventories);
  for ($a = 0; $a < $n; $a++) {
    for ($b = $a + 1; $b < $n; $b++) {
      $A = $teamInventories[$a];
      $B = $teamInventories[$b];
      $diffAB = array_diff($A, $B);
      $diffBA = array_diff($B, $A);
      if (count($diffAB) > 1 || count($diffBA) > 1) return false;
      $sym = count($diffAB) + count($diffBA);
      if ($sym > LRG_BOT_INV_SYMDIFF_MAX) return false;
    }
  }
  return true;
}

function lrg_detect_bot_match(array $matchdata): array {
  $out = ['bot' => false, 'reason' => null];

  if (!isset($matchdata['players']) || !is_array($matchdata['players'])) return $out;
  if (count($matchdata['players']) < 10) return $out;

  $version  = $matchdata['matches']['version'] ?? null;
  $duration = (int)($matchdata['matches']['duration'] ?? 0);
  if ($version === null || $duration <= 0) return $out;

  $minutes = $duration / 60.0;

  $teamInv = [0 => [], 1 => []];
  $deniesOK = true;
  $lhpmOK   = true;

  foreach ($matchdata['players'] as $p) {
    $teamIdx = !empty($p['isRadiant']) ? 0 : 1;
    $teamInv[$teamIdx][] = lrg_bot_player_inventory($p);

    $denies = (int)($p['denies'] ?? 0);
    if ($denies >= LRG_BOT_DENIES_MAX) $deniesOK = false;

    $lh = (int)($p['last_hits'] ?? 0);
    if ($minutes > 0 && ($lh / $minutes) >= LRG_BOT_LHPM_MAX) $lhpmOK = false;
  }

  $invCloned =
       lrg_bot_team_inventory_cloned($teamInv[0])
    || lrg_bot_team_inventory_cloned($teamInv[1]);

  if ($invCloned) {
    $out['bot']    = true;
    $out['reason'] = 'inventory clones within a team';
    return $out;
  }

  if ($deniesOK && $lhpmOK) {
    $out['bot']    = true;
    $out['reason'] = 'all denies <'.LRG_BOT_DENIES_MAX.' and all LH/min <'.LRG_BOT_LHPM_MAX;
    return $out;
  }

  return $out;
}

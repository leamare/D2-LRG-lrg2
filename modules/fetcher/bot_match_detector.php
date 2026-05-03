<?php

const LRG_BOT_DENIES_MAX           = 5;
const LRG_BOT_LHPM_MAX             = 5.0;
const LRG_BOT_INV_MIN_ITEMS        = 3;
const LRG_BOT_INV_SYMDIFF_MAX      = 2;

/**
 * Build per-player normalized records: { team, last_hits, denies, items: int[] }.
 * Returns [] when data is unusable (missing duration, no players, etc.).
 */
function lrg_bot_normalize_players(array $matchdata): array {
  $players = $matchdata['players']    ?? null;
  $mlines  = $matchdata['matchlines'] ?? null;

  $out = [];

  if (is_array($players) && !empty($players) && isset($players[0]['last_hits'])) {
    foreach ($players as $p) {
      $items = [];
      for ($k = 0; $k < 6; $k++) {
        $iid = (int)($p['item_'.$k] ?? 0);
        if ($iid > 0) $items[$iid] = true;
      }
      for ($k = 0; $k < 3; $k++) {
        $iid = (int)($p['backpack_'.$k] ?? 0);
        if ($iid > 0) $items[$iid] = true;
      }
      $out[] = [
        'team'      => !empty($p['isRadiant']) ? 0 : 1,
        'last_hits' => (int)($p['last_hits'] ?? 0),
        'denies'    => (int)($p['denies']    ?? 0),
        'items'     => array_keys($items),
      ];
    }
    return $out;
  }

  if (is_array($mlines) && !empty($mlines)) {
    $items_by_pid = [];
    if (!empty($matchdata['items']) && is_array($matchdata['items'])) {
      foreach ($matchdata['items'] as $it) {
        $pid = (int)($it['playerid'] ?? 0);
        $iid = (int)($it['item_id']  ?? 0);
        if ($pid && $iid) $items_by_pid[$pid][$iid] = true;
      }
    }
    foreach ($mlines as $ml) {
      $pid = (int)($ml['playerid'] ?? 0);
      $out[] = [
        'team'      => !empty($ml['isRadiant']) ? 0 : 1,
        'last_hits' => (int)($ml['lastHits'] ?? 0),
        'denies'    => (int)($ml['denies']   ?? 0),
        'items'     => isset($items_by_pid[$pid]) ? array_keys($items_by_pid[$pid]) : [],
      ];
    }
    return $out;
  }

  return [];
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
      if (count($diffAB) + count($diffBA) > LRG_BOT_INV_SYMDIFF_MAX) return false;
    }
  }
  return true;
}

function lrg_detect_bot_match(array $matchdata): array {
  $out = ['bot' => false, 'reason' => null];

  if (!empty($matchdata['is_bot_match'])) {
    return ['bot' => true, 'reason' => 'cached bot-match flag'];
  }

  $duration = (int)($matchdata['matches']['duration'] ?? 0);
  if ($duration <= 0) return $out;

  $players = lrg_bot_normalize_players($matchdata);
  if (count($players) < 10) return $out;

  $minutes  = $duration / 60.0;
  $teamInv  = [0 => [], 1 => []];
  $deniesOK = true;
  $lhpmOK   = true;

  foreach ($players as $p) {
    $teamInv[$p['team']][] = $p['items'];
    if ($p['denies'] >= LRG_BOT_DENIES_MAX) $deniesOK = false;
    if ($minutes > 0 && ($p['last_hits'] / $minutes) >= LRG_BOT_LHPM_MAX) $lhpmOK = false;
  }

  $invCloned =
       lrg_bot_team_inventory_cloned($teamInv[0])
    || lrg_bot_team_inventory_cloned($teamInv[1]);

  $markers = (int)$invCloned + (int)$deniesOK + (int)$lhpmOK;
  if ($markers >= 2) {
    $reasons = [];
    if ($invCloned) $reasons[] = 'inventory clones within a team';
    if ($deniesOK)  $reasons[] = 'all denies <'.LRG_BOT_DENIES_MAX;
    if ($lhpmOK)    $reasons[] = 'all LH/min <'.LRG_BOT_LHPM_MAX;
    return ['bot' => true, 'reason' => implode(', ', $reasons)];
  }

  return $out;
}

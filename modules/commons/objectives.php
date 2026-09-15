<?php

/**
 * Numeric objective IDs
 *
 * Buildings (5 digits, first digit = tier / kind):
 *   TSLNI  where
 *     T = tower tier 1-4, or 5 melee rax, 6 range rax, 7 filler/healer/outpost
 *     S = side 1 radiant / 2 dire
 *     L = lane 1 top / 2 mid / 3 bot / 0 for T4 and non-lane buildings
 *     N = T4 instance 1|2, else 0
 *     I = reserved 0
 *   Examples: T1 top radiant = 11100, T4 dire #2 = 42020, melee rax bot dire = 52300
 *
 * Ancients (6 digits, first digit = side):
 *   1xxxxx radiant fort, 2xxxxx dire fort (100000 / 200000)
 *
 * Other events (6 digits, 8xxxxx) — stored keys match Guame / OpenDota-normalized
 * names (`unit_*`, `building_*`, `hero_first_blood`, `rune_wisdom_shrine`).
 *   800000 unit_roshan_kill
 *   801000 unit_roshan_aegis_pickup
 *   802000 unit_roshan_aegis_denied
 *   803000 unit_roshan_aegis_stolen
 *   810000 unit_tormentor_kill
 *   820000 unit_courier_kill
 *   830000 hero_first_blood
 *   850000 rune_wisdom_shrine (from runes_log / Stratz runes)
 */

function lrg_objective_id_from_npc(string $key, bool $target_is_radiant, int $tower4_index = 1): int {
  $side = $target_is_radiant ? 1 : 2;
  $key = strtolower($key);

  if (preg_match('/tower([1-4])(?:_(top|mid|bot))?/', $key, $m)) {
    $tier = (int)$m[1];
    $lane = 0;
    if (($m[2] ?? '') === 'top') $lane = 1;
    elseif (($m[2] ?? '') === 'mid') $lane = 2;
    elseif (($m[2] ?? '') === 'bot') $lane = 3;
    $inst = $tier === 4 ? max(1, $tower4_index) : 0;
    return $tier * 10000 + $side * 1000 + $lane * 100 + $inst * 10;
  }

  if (preg_match('/(melee|range)_rax_(top|mid|bot)/', $key, $m)) {
    $kind = $m[1] === 'melee' ? 5 : 6;
    $lane = $m[2] === 'top' ? 1 : ($m[2] === 'mid' ? 2 : 3);
    return $kind * 10000 + $side * 1000 + $lane * 100;
  }

  if (strpos($key, 'fort') !== false || strpos($key, 'ancient') !== false) {
    return $side * 100000;
  }

  if (strpos($key, 'outpost') !== false) {
    return 70000 + $side * 1000 + 1;
  }
  if (strpos($key, 'healer') !== false || strpos($key, 'filler') !== false) {
    return 70000 + $side * 1000;
  }

  return 0;
}

function lrg_objective_id_from_type(string $type, bool $target_is_radiant, int $tower4_index = 1): int {
  switch ($type) {
    case 'CHAT_MESSAGE_FIRSTBLOOD':
    case 'hero_first_blood':
      return 830000;
    case 'CHAT_MESSAGE_ROSHAN_KILL':
    case 'unit_roshan_kill':
      return 800000;
    case 'CHAT_MESSAGE_AEGIS':
    case 'unit_roshan_aegis_pickup':
      return 801000;
    case 'CHAT_MESSAGE_DENIED_AEGIS':
    case 'unit_roshan_aegis_denied':
      return 802000;
    case 'CHAT_MESSAGE_AEGIS_STOLEN':
    case 'unit_roshan_aegis_stolen':
      return 803000;
    case 'CHAT_MESSAGE_MINIBOSS_KILL':
    case 'unit_tormentor_kill':
      return 810000;
    case 'CHAT_MESSAGE_COURIER_LOST':
    case 'unit_courier_kill':
      return 820000;
    case 'rune_wisdom_shrine':
      return 850000;
  }
  if (strpos($type, 'building_') === 0) {
    return lrg_objective_id_from_npc('npc_dota_x_' . substr($type, 9), $target_is_radiant, $tower4_index);
  }
  return lrg_objective_id_from_npc($type, $target_is_radiant, $tower4_index);
}

function lrg_objective_row(int $match, string $key, int $timing, $killer, $target_is_radiant, int $t4 = 1): array {
  $target = !empty($target_is_radiant);
  return [
    'matchid' => $match,
    'objective_key' => $key,
    'objective_id' => lrg_objective_id_from_type($key, $target, $t4),
    'timing' => $timing,
    'killer_playerid' => ($killer === null || $killer === '') ? null : (int)$killer,
    'target_is_radiant' => $target ? 1 : 0,
  ];
}

function lrg_buildings_status_row(int $tower_r, int $tower_d, int $rax_r, int $rax_d): array {
  return [
    'tower_status_radiant' => $tower_r & 0x07FF,
    'tower_status_dire' => $tower_d & 0x07FF,
    'barracks_status_radiant' => $rax_r & 0x3F,
    'barracks_status_dire' => $rax_d & 0x3F,
  ];
}

function lrg_tower_bit_from_npc(string $key): ?array {
  $key = strtolower($key);
  $radiant = strpos($key, 'goodguys') !== false || strpos($key, 'radiant') !== false;
  $dire = strpos($key, 'badguys') !== false || strpos($key, 'dire') !== false;
  if (!$radiant && !$dire) {
    return null;
  }
  $side = $radiant ? 'r' : 'd';

  if (preg_match('/tower([1-3])_(top|mid|bot)/', $key, $m)) {
    $tier = (int)$m[1];
    $lane = $m[2] === 'top' ? 0 : ($m[2] === 'mid' ? 1 : 2);
    $bit = $lane * 3 + ($tier - 1);
    return [$side, 'tower', $bit];
  }
  if (preg_match('/tower4/', $key)) {
    return [$side, 'tower4', null];
  }
  if (preg_match('/(melee|range)_rax_(top|mid|bot)/', $key, $m)) {
    $lane = $m[2] === 'top' ? 0 : ($m[2] === 'mid' ? 1 : 2);
    $off = $m[1] === 'melee' ? 0 : 1;
    $bit = $lane * 2 + $off;
    return [$side, 'rax', $bit];
  }
  if (strpos($key, 'fort') !== false || strpos($key, 'ancient') !== false) {
    return [$side, 'ancient', 0];
  }
  return null;
}

function lrg_buildings_state_from_status(array $matchdata): ?array {
  $tr = $matchdata['tower_status_radiant'] ?? $matchdata['towerStatusRadiant'] ?? null;
  $td = $matchdata['tower_status_dire'] ?? $matchdata['towerStatusDire'] ?? null;
  $rr = $matchdata['barracks_status_radiant'] ?? $matchdata['barracksStatusRadiant'] ?? null;
  $rd = $matchdata['barracks_status_dire'] ?? $matchdata['barracksStatusDire'] ?? null;
  if ($tr === null && $td === null && $rr === null && $rd === null) {
    return null;
  }
  return lrg_buildings_status_row((int)($tr ?? 0x07FF), (int)($td ?? 0x07FF), (int)($rr ?? 0x3F), (int)($rd ?? 0x3F));
}

function lrg_buildings_state_from_objectives(array $objectives): array {
  $tower_r = 0x07FF;
  $tower_d = 0x07FF;
  $rax_r = 0x3F;
  $rax_d = 0x3F;
  $t4_r = 0;
  $t4_d = 0;

  foreach ($objectives as $obj) {
    $key = $obj['objective_key'] ?? $obj['key'] ?? '';
    $info = lrg_tower_bit_from_npc((string)$key);
    if ($info === null && !empty($obj['objective_id'])) {
      $id = (int)$obj['objective_id'];
      $t = intdiv($id, 10000);
      $s = intdiv($id % 10000, 1000);
      $l = intdiv($id % 1000, 100);
      $n = intdiv($id % 100, 10);
      $side = $s === 1 ? 'r' : 'd';
      if ($t >= 1 && $t <= 3 && $l >= 1 && $l <= 3) {
        $bit = ($l - 1) * 3 + ($t - 1);
        $info = [$side, 'tower', $bit];
      } elseif ($t === 4) {
        $info = [$side, 'tower4', $n > 0 ? $n - 1 : null];
      } elseif ($t === 5 || $t === 6) {
        $bit = ($l - 1) * 2 + ($t === 6 ? 1 : 0);
        $info = [$side, 'rax', $bit];
      } elseif ($id === 100000 || $id === 200000) {
        $info = [$id === 100000 ? 'r' : 'd', 'ancient', 0];
      }
    }
    if ($info === null) continue;
    [$side, $kind, $bit] = $info;
    if ($kind === 'tower' && $bit !== null) {
      if ($side === 'r') $tower_r &= ~(1 << $bit);
      else $tower_d &= ~(1 << $bit);
    } elseif ($kind === 'tower4') {
      $idx = $bit;
      if ($idx === null) {
        if ($side === 'r') { $idx = $t4_r; $t4_r++; }
        else { $idx = $t4_d; $t4_d++; }
      }
      $idx = min(1, max(0, (int)$idx));
      $b = 9 + $idx;
      if ($side === 'r') $tower_r &= ~(1 << $b);
      else $tower_d &= ~(1 << $b);
    } elseif ($kind === 'rax' && $bit !== null) {
      if ($side === 'r') $rax_r &= ~(1 << $bit);
      else $rax_d &= ~(1 << $bit);
    }
  }

  return lrg_buildings_status_row($tower_r, $tower_d, $rax_r, $rax_d);
}

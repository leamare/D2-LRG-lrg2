<?php

// Shared helper for the WRD@20 / STR@20 / WKS@20 / SPWK@20 stats.

const WARDS_AT20_CAP = 1200;

function wards_at20_match_counts($rows, $duration, $with_kills = true) {
  $duration = (int)$duration;
  if ($duration <= 0 || empty($rows)) return [];

  $cap = min(WARDS_AT20_CAP, $duration);
  $factor = $duration < WARDS_AT20_CAP ? WARDS_AT20_CAP / $duration : 1;

  $res = [];
  $reversed_kills = [];
  $any_destroyed_log = false;

  foreach ($rows as $row) {
    $pid = $row['playerid'];
    if (!isset($res[$pid])) $res[$pid] = [ 'w_raw' => 0, 's_raw' => 0, 'k_raw' => 0 ];

    $wards = json_decode($row['wards_log'] ?? '[]', true) ?: [];
    $sentries = json_decode($row['sentries_log'] ?? '[]', true) ?: [];

    foreach ($wards as $e) {
      if (($e['time'] ?? PHP_INT_MAX) <= $cap) $res[$pid]['w_raw']++;

      // collected regardless, but only used if no destroyed_log survived the fetch
      if (!$with_kills) continue;
      $killer = $e['destroyed_by'] ?? null;
      if ($killer !== null && ($e['destroyed_at'] ?? PHP_INT_MAX) <= $cap) {
        $reversed_kills[$killer] = ($reversed_kills[$killer] ?? 0) + 1;
      }
    }

    foreach ($sentries as $e) {
      if (($e['time'] ?? PHP_INT_MAX) <= $cap) $res[$pid]['s_raw']++;
    }

    if (!$with_kills) continue;

    $destroyed = json_decode($row['destroyed_log'] ?? '[]', true) ?: [];
    if (!empty($destroyed)) {
      $any_destroyed_log = true;
      foreach ($destroyed as $e) {
        if (($e['time'] ?? PHP_INT_MAX) <= $cap) $res[$pid]['k_raw']++;
      }
    }
  }

  // workaround for the fetcher leaving destroyed_log empty for the whole match
  if ($with_kills && !$any_destroyed_log) {
    foreach ($reversed_kills as $pid => $n) {
      if (!isset($res[$pid])) $res[$pid] = [ 'w_raw' => 0, 's_raw' => 0, 'k_raw' => 0 ];
      $res[$pid]['k_raw'] = $n;
    }
  }

  foreach ($res as $pid => $c) {
    $res[$pid]['factor'] = $factor;
    $res[$pid]['w'] = $c['w_raw'] * $factor;
    $res[$pid]['s'] = $c['s_raw'] * $factor;
    $res[$pid]['k'] = $c['k_raw'] * $factor;
  }

  return $res;
}

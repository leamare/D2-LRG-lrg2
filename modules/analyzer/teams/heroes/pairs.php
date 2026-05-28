<?php
/**
 * Included from modules/analyzer/teams/__main.php
 * @var int $id
 * @var mysqli $conn
 * @var float $limiter_lower
 * @var float $multiplier
 * @var array $result
 */

$result['teams'][$id]['hero_pairs'] = rg_query_hero_pairs(
  $conn,
  $result['teams'][$id]['pickban'],
  $result['teams'][$id]['matches_total'],
  (int)ceil($limiter_lower * $multiplier),
  null,
  (int)$id
);

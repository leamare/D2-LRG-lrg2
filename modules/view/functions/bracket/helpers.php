<?php

// Flatten a list of rounds (each {'series' => [...]}) into one series list.
function tb_rounds_series(array $rounds): array {
  return $rounds ? array_merge(...array_map(fn($r) => $r['series'], $rounds)) : [];
}

function tb_unique_teams(array $series): array {
  if (!$series) return [];

  return array_values(
    array_unique(
      array_merge(...array_map(fn($s) => $s['teams'], $series))
    )
  );
}

function tb_next_pow2(int $n): int {
  if ($n <= 1) return 1;
  $p = 1;
  while ($p < $n) $p <<= 1;

  return $p;
}

// Union-find over team ids, used to cluster series into connected groups.
function tb_uf_find(array &$p, int $x): int {
  if ($p[$x] !== $x) $p[$x] = tb_uf_find($p, $p[$x]);
  return $p[$x];
}

function tb_uf_union(array &$p, int $a, int $b): void {
  $ra = tb_uf_find($p, $a);
  $rb = tb_uf_find($p, $b);
  if ($ra !== $rb) $p[$ra] = $rb;
}

function tb_ub_round_name(int $i, int $n, int $cnt): string {
  $fe = $n - $i;

  if ($fe === 1 && $cnt === 1) return 'bracket_ub_final';
  if ($fe === 2 && $cnt <= 2)  return 'bracket_ub_sf';
  if ($fe === 3 && $cnt <= 4)  return 'bracket_ub_qf';
  
  return 'bracket_ub_round ' . ($i + 1);
}

function tb_lb_round_name(int $i, int $n, int $cnt): string {
  $fe = $n - $i;

  if ($fe === 1 && $cnt === 1) return 'bracket_lb_final';
  if ($fe === 2 && $cnt <= 2)  return 'bracket_lb_sf';

  return 'bracket_lb_round ' . ($i + 1);
}

function tb_se_round_name(int $i, int $n, int $cnt): string {
  $fe = $n - $i;

  if ($fe === 1 && $cnt === 1) return 'bracket_final';
  if ($fe === 2 && $cnt <= 2)  return 'bracket_sf';
  if ($fe === 3 && $cnt <= 4)  return 'bracket_qf';

  return 'bracket_round ' . ($i + 1);
}

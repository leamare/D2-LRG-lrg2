<?php 

const LEVELS_IDS = [
  1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18, 20, 25, 27, 28, 29, 30
];

// "730": "special_bonus_attributes",

function skillPriority($skillbuild, $noattr = false) {
  $nottalents = [];
  $skillNumbers = [];

  $skillbuild = array_values(
    array_filter($skillbuild, function($v) {
      return $v != 730;
    })
  );

  foreach ($skillbuild as $i => $sid) {
    if (!isset($skillNumbers[$sid])) {
      $skillNumbers[$sid] = 0;
    }
    $skillNumbers[$sid]++;

    if (!in_array($sid, $nottalents) && ($i < 10 || $skillNumbers[$sid] > 1)) {
      $nottalents[] = $sid;
    }
  }

  $skillNumbers = array_filter($nottalents, function($k) use (&$nottalents) {
    return !in_array($k, $nottalents);
  }, ARRAY_FILTER_USE_KEY);

  $ultimate = array_keys($skillNumbers, min($skillNumbers))[0];

  $maxedAt = [];
  $maxlevel = [];
  $firstPointAt = [];
  $talents = [];

  foreach ($skillbuild as $level => $skill) {
    if (!in_array($skill, $nottalents)) {
      if (($noattr ? $level+1 : LEVELS_IDS[$level]) < 26) {
        $talents[] = $skill;
      }
      continue;
    }
    if (!isset($maxlevel[$skill])) {
      $cnt = count($maxlevel)+1;
      if ($cnt > 4) continue;

      $firstPointAt[$skill] = $noattr ? $level+1 : LEVELS_IDS[$level];
      $maxlevel[$skill] = 1;
    } else {
      $maxlevel[$skill]++;
      $maxedAt[$skill] = $noattr ? $level+1 : LEVELS_IDS[$level];
    }
  }

  $skills = array_keys($maxedAt);

  usort($skills, function($a, $b) use ($firstPointAt, $maxedAt, $maxlevel, $ultimate) {
    if ($maxlevel[$a] != $maxlevel[$b]) {
      if ($maxlevel[$a] < $maxlevel[$b]) {
        if ($firstPointAt[$a] > $maxedAt[$b]) {
          return 1;
        }
        return -1;
      } else {
        if ($firstPointAt[$b] > $maxedAt[$a]) {
          return -1;
        }
        return 1;
      }
    }

    return $maxedAt[$a] <=> $maxedAt[$b];
  });

  $priority = [];
  $lastPriority = 1;

  for ($i = 0, $sz = count($skills); $i < $sz; $i++) {
    $priority[ $skills[$i] ] = $lastPriority;

    if ($skills[$i] == $ultimate) {
      $lastPriority++;
      continue;
    }

    if ($i < $sz-1 && abs($maxedAt[ $skills[$i] ] - $maxedAt[ $skills[$i+1] ]) < 3) {
      continue;
    }

    $lastPriority++;
  }

  return [
    'firstPointAt' => $firstPointAt,
    'maxedAt' => $maxedAt,
    'priority' => $priority,
    'talents' => $talents
  ];
}
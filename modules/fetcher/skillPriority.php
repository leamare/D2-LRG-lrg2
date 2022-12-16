<?php 

const LEVELS_IDS = [
  1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18, 20, 25, 27, 28, 29, 30
];

// "730": "special_bonus_attributes",
const WELL_KNOWN = [ 5433, 5375 ];

function indexToLevel($i, $noattr = false) {
  return $noattr ? $i+1 : (LEVELS_IDS[$i] ?? $i+1);
}

function levelToIndex($level) {
  return array_search($level, LEVELS_IDS);
}

function skillPriority($skillbuild, $hid, $noattr = false) {
  global $meta;
  $meta['heroes_spells'];
  $meta['spells_linked'];

  $ultLevel = (in_array(5375, $skillbuild) || in_array(5433, $skillbuild)) ? 3 : 6;

  $spell_ids = array_flip($meta['spells_tags']);

  $talents = [];
  $skillNumbers = [];

  $maxedAt = [];
  $firstPointAt = [];
  $skilledAt = [];

  $totalLevels = count($skillbuild);
  // $maxtotallevel = (LEVELS_IDS[$totalLevels-1] ?? $totalLevels);
  $maxtalents = $totalLevels > 9 ? (
    $totalLevels > 25 ? 4 : (int)floor(($totalLevels-10)/5)+1
  ) : 0;

  foreach ($skillbuild as $i => $sid) {
    $tag = $meta['spells_tags'][$sid] ?? "_unknown";
    if (isset($meta['spells_linked'][$tag])) {
      $skillbuild[$i] = $spell_ids[ $meta['spells_linked'][$tag] ];
    }
  }

  $attributes = [
    'count' => count(array_filter($skillbuild, function($v) {
      return $v == 730 || $v == 5002;
    })),
    'firstPointAt' => array_search(730, $skillbuild)
  ];

  // $skillbuild = array_values(
  //   array_filter($skillbuild, function($v) {
  //     return $v != 730;
  //   })
  // );

  if ($maxtalents) {
    $skills = array_unique($skillbuild);
    foreach ($skills as $sid) {
      if ($sid == 730 || $sid == 5002) continue;

      if (strpos($meta['spells_tags'][$sid], "special_bonus") !== false) {
        $i = array_search($sid, $skillbuild);
        $talents[ (LEVELS_IDS[$i] ?? $i+1) ] = $sid;
      }
    }
  }

  $sb_copy = $skillbuild;

  for ($i=0; ($sid = array_shift($sb_copy)); $i++) {
    if ($sid == 730 || $sid == 5002) continue;

    if ((count($skillNumbers) == 4 && !isset($skillNumbers[$sid])) || 
      (!in_array($sid, $sb_copy) && $i >= 9 && !isset($skillNumbers[$sid]) && ($maxtalents > count($talents))) || 
      strpos($meta['spells_tags'][$sid], "special_bonus") !== false
    ) {
      $talents[(LEVELS_IDS[$i] ?? $i+1)] = $sid;
      continue;
    }

    if (!isset($skillNumbers[$sid])) {
      $skillNumbers[$sid] = 0;
      $skilledAt[$sid] = [];
      $firstPointAt[$sid] = indexToLevel($i, $noattr);
    }
    $skilledAt[$sid][] = indexToLevel($i, $noattr);
    if (!in_array($sid, $sb_copy)) {
      $maxedAt[$sid] = indexToLevel($i, $noattr);
    }

    $skillNumbers[$sid]++;
  }

  $skills = array_keys($skillNumbers);
  $maxlevel = $skillNumbers;

  $rkeys = array_reverse(array_keys($firstPointAt));
  ksort($talents);

  $ultimate = null;

  $ultlevels = [ $ultLevel, 10, 16 ];

  // 1. if ability was skilled at 6/11/16+ => it's an ult
  // 2. if skilled more than 3 times => not ult
  // 3. if there is no such ability, then biggest diff

  if ($totalLevels >= $ultLevel) {
    $ults = [];
    foreach ($rkeys as $sid) {
      if ($maxlevel[$sid] > 3) continue;

      $criteria = 1;

      foreach ($skilledAt[$sid] as $i => $level) {
        if ($level < $ultlevels[$i]) continue 2;
      }
      $criteria++;

      if ($maxlevel[$sid] > 1) {
        $avgdiff = ($skilledAt[$sid][count($skilledAt[$sid])-1] - $skilledAt[$sid][0])/(count($skilledAt[$sid])-1);
        if ($avgdiff < 3) continue;
        $criteria++;
      }

      if ($firstPointAt[$sid] == $ultLevel) $criteria++;

      if (in_array($sid, WELL_KNOWN)) $criteria += 2;

      $ults[$sid] = $criteria;
    }

    if (!empty($ults)) {
      if (count($ults) > 1)
        arsort($ults);
      $ultimate = array_keys($ults)[0];
    }

    if (in_array($ultimate, $talents)) {
      unset($talents[ array_search($ultimate, $talents) ]);
    }
  }

  $level = count($skillbuild);

  foreach($maxlevel as $skill => $lvl) {
    if ($skill == $ultimate) {
      if ($lvl == 3) continue;
      $maxedAt[$skill] = LEVELS_IDS[ $level-1 ] < 19 ? 18 : $level + (3-$lvl) - 1;
      $maxlevel[$skill] = 3;
    } else {
      if ($lvl == 4) continue;
      $maxedAt[$skill] = $level + (4-$lvl) - 1;
      $maxlevel[$skill] = 4;
    }
  }

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
    'talents' => array_values($talents),
    'attributes' => $attributes,
    'ultimate' => $ultimate,
  ];
}
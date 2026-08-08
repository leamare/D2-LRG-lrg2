<?php

// Skill builds stats for heroes, packed with wrap_data() (head/data/keys columnar
// form, see modules/commons/wrap_data.php) to keep report size sane.
//
// Structure (per role bucket, 0 = total, 1-5 = ROLES_IDS_SIMPLE positions):
//   priority[role][hero]   -> list of { priority, matches, wins, winrate, ratio, max_level, featured_build, skill_stats }
//     skill_stats is a raw JSON map (skill_id -> {first_point_avg, maxed_at_avg})
//     scoped to that one priority variant, distinct from the hero-wide 'skills' below.
//   skills[role][hero]     -> list of { skill, first_point_avg, maxed_at_avg, matches }
//   talents[role][hero]    -> list of { tier, level, talent, matches, wins, winrate, pick_rate, skilled_at_avg }
//   attributes[role][hero] -> { matches, taken_rate, avg_count, first_point_avg, wins, winrate }
//   matches[role][hero]    -> { matches, winrate }
//   ultimate[hero]         -> most common ultimate ability id (role-agnostic)

include_once("modules/fetcher/skillPriority.php"); // LEVELS_IDS / indexToLevel()

const SB_TALENT_LEVELS = [ 10, 15, 20, 25 ];
const SB_TALENT_FINAL_LEVEL = 25; // pickrate/winrate ignore picks made after this
const SB_ATTR_TAGS = [ 730, 5002 ];
const SB_RATIO_THRESHOLD = 0.01;
const SB_NOATTR_HERO = 74; // Invoker: no LEVELS_IDS skip pattern, level = index+1

const SB_PRIORITY_DUMMY = [ 'm' => 0, 'w' => 0, 'max_level' => 0, 'builds' => [], 'skills' => [] ];
const SB_PRIORITY_SKILL_DUMMY = [ 'fp_sum' => 0, 'fp_cnt' => 0, 'mx_sum' => 0, 'mx_cnt' => 0 ];
const SB_SKILL_DUMMY = [ 'fp_sum' => 0, 'fp_cnt' => 0, 'mx_sum' => 0, 'mx_cnt' => 0, 'w' => 0 ];
const SB_TALENT_DUMMY = [ 'm' => 0, 'w' => 0, 'lvl_sum' => 0, 'lvl_cnt' => 0 ];
const SB_ATTR_DUMMY = [ 'matches' => 0, 'taken' => 0, 'cnt_sum' => 0, 'fp_sum' => 0, 'fp_cnt' => 0, 'taken_wins' => 0 ];
const SB_MATCHES_DUMMY = [ 'm' => 0, 'w' => 0 ];

// The largest talent tier threshold the level falls into, e.g. 13 -> 10, 25 -> 25.
// ability_upgrades_arr order doesn't always lock exactly to levels (points can be
// banked and spent late), so the computed level for an early talent can come out
// a notch under 10 - clamp those to the lowest tier instead of dropping them.
function sb_talent_tier_for_level($level) {
  foreach (SB_TALENT_LEVELS as $i => $threshold) {
    if ($level < $threshold) return max(0, $i - 1);
  }
  return count(SB_TALENT_LEVELS) - 1;
}

// Folds partial/incomplete priority observations into the closest matching
// full priority ordering, then buckets rare variants (< 1% of hero+role
// matches) into an "Others" row.
function sb_fold_priorities(&$priorities, $withLimit) {
  foreach ($priorities as $priority => $count) {
    $prio = json_decode($priority, true);
    if (count($prio) > 3) continue;

    foreach ($priorities as $p => $c) {
      if ($p === $priority) continue;
      $p_d = json_decode($p, true);
      $matches_found = 0;
      foreach ($prio as $skill => $pidx) {
        if (isset($p_d[$skill]) && $p_d[$skill] == $pidx) $matches_found++;
      }
      if ($matches_found == count($prio)) {
        $priorities[$p]['m'] += $count['m'];
        $priorities[$p]['w'] += $count['w'];
        $priorities[$p]['max_level'] = max($priorities[$p]['max_level'], $count['max_level']);
        foreach ($count['builds'] as $b => $bc) {
          $priorities[$p]['builds'][$b] = ($priorities[$p]['builds'][$b] ?? 0) + $bc;
        }
        foreach ($count['skills'] as $sid => $sv) {
          if (!isset($priorities[$p]['skills'][$sid])) $priorities[$p]['skills'][$sid] = SB_PRIORITY_SKILL_DUMMY;
          $priorities[$p]['skills'][$sid]['fp_sum'] += $sv['fp_sum'];
          $priorities[$p]['skills'][$sid]['fp_cnt'] += $sv['fp_cnt'];
          $priorities[$p]['skills'][$sid]['mx_sum'] += $sv['mx_sum'];
          $priorities[$p]['skills'][$sid]['mx_cnt'] += $sv['mx_cnt'];
        }
        unset($priorities[$priority]);
        break;
      }
    }
  }

  if (!$withLimit) return $priorities;

  $total = array_sum(array_column($priorities, 'm'));
  if (!$total) return $priorities;

  $out = [];
  $others = [ 'm' => 0, 'w' => 0, 'max_level' => 0 ];

  foreach ($priorities as $priority => $count) {
    if ($count['m'] / $total < SB_RATIO_THRESHOLD) {
      $others['m'] += $count['m'];
      $others['w'] += $count['w'];
      $others['max_level'] = max($others['max_level'], $count['max_level']);
      continue;
    }
    $out[$priority] = $count;
  }

  if ($others['m'] > 0) {
    $out['others'] = $others;
  }

  return $out;
}

function sb_query($withRoles, $withTalents, $withLimit) {
  global $conn, $schema;

  echo "[ ] SKILL BUILDS DATA - ";
  resetbltime();

  $roles_join = '';
  $roles_select = '0';
  if ($withRoles && $schema['adv_matchlines_roles']) {
    $roles_join = "JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid AND am.role < 6";
    $roles_select = 'am.role';
  }

  $sql = <<<SQL
    SELECT
      sb.hero_id, $roles_select AS role,
      sb.priority, sb.first_point_at, sb.maxed_at, sb.talents, sb.attributes, sb.ultimate, sb.skill_build,
      COUNT(*) cnt, SUM(m.radiantWin = ml.isRadiant) wins, MAX(ml.level) max_level
    FROM skill_builds sb
    JOIN matches m ON m.matchid = sb.matchid
    JOIN matchlines ml ON sb.matchid = ml.matchid AND sb.playerid = ml.playerid
    $roles_join
    WHERE ml.level > 0
    GROUP BY sb.hero_id, role, sb.priority, sb.first_point_at, sb.maxed_at, sb.talents, sb.attributes, sb.skill_build
  SQL;

  if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$sql."\n");

  $query_res = $conn->store_result();

  echo ' [ '.echobltime().' ] ';

  $priority_res = [];
  $skills_res = [];
  $talents_res = [];
  $attr_res = [];
  $matches_res = [];
  $ultimate_votes = [];

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    [ $hid, $role, $priority, $first_point_at, $maxed_at, $talents, $attributes, $ultimate, $skill_build, $cnt, $wins, $max_level ] = $row;

    $prio = json_decode($priority, true);
    if (!is_array($prio) || count($prio) < 3) continue;

    $roles = [ 0 ];
    if ($role) $roles[] = (int)$role;

    if ($ultimate) {
      $ultimate_votes[$hid][$ultimate] = ($ultimate_votes[$hid][$ultimate] ?? 0) + $cnt;
    }

    foreach ($roles as $rid) {
      // priority
      if (!isset($priority_res[$rid][$hid][$priority])) {
        $priority_res[$rid][$hid][$priority] = SB_PRIORITY_DUMMY;
      }
      $priority_res[$rid][$hid][$priority]['m'] += $cnt;
      $priority_res[$rid][$hid][$priority]['w'] += $wins;
      $priority_res[$rid][$hid][$priority]['max_level'] = max($priority_res[$rid][$hid][$priority]['max_level'], (int)$max_level);
      $priority_res[$rid][$hid][$priority]['builds'][$skill_build] = ($priority_res[$rid][$hid][$priority]['builds'][$skill_build] ?? 0) + $cnt;

      // matches/winrate totals
      if (!isset($matches_res[$rid][$hid])) $matches_res[$rid][$hid] = SB_MATCHES_DUMMY;
      $matches_res[$rid][$hid]['m'] += $cnt;
      $matches_res[$rid][$hid]['w'] += $wins;

      // per-skill first point / maxed at
      $fp = json_decode($first_point_at, true) ?: [];
      $mx = json_decode($maxed_at, true) ?: [];
      foreach (array_unique(array_merge(array_keys($fp), array_keys($mx))) as $sid) {
        if (!isset($skills_res[$rid][$hid][$sid])) $skills_res[$rid][$hid][$sid] = SB_SKILL_DUMMY;
        if (isset($fp[$sid])) {
          $skills_res[$rid][$hid][$sid]['fp_sum'] += $fp[$sid] * $cnt;
          $skills_res[$rid][$hid][$sid]['fp_cnt'] += $cnt;
        }
        if (isset($mx[$sid])) {
          $skills_res[$rid][$hid][$sid]['mx_sum'] += $mx[$sid] * $cnt;
          $skills_res[$rid][$hid][$sid]['mx_cnt'] += $cnt;
        }
        $skills_res[$rid][$hid][$sid]['w'] += $cnt;

        // same first point / maxed at, but scoped to this one priority variant
        $pskills = &$priority_res[$rid][$hid][$priority]['skills'];
        if (!isset($pskills[$sid])) $pskills[$sid] = SB_PRIORITY_SKILL_DUMMY;
        if (isset($fp[$sid])) {
          $pskills[$sid]['fp_sum'] += $fp[$sid] * $cnt;
          $pskills[$sid]['fp_cnt'] += $cnt;
        }
        if (isset($mx[$sid])) {
          $pskills[$sid]['mx_sum'] += $mx[$sid] * $cnt;
          $pskills[$sid]['mx_cnt'] += $cnt;
        }
        unset($pskills);
      }

      // talents: only the first 4 picks are real talent-tree choices (10/15/20/25),
      // anything past that is a hero that kept leveling in an overtime game.
      if ($withTalents) {
        $tl = array_slice(json_decode($talents, true) ?: [], 0, 4);
        $sb_raw = json_decode($skill_build, true) ?: [];
        $noattr = ((int)$hid === SB_NOATTR_HERO);

        foreach ($tl as $tid) {
          $idx = array_search($tid, $sb_raw);
          if ($idx === false) continue;
          $level = indexToLevel($idx, $noattr);

          // Bucketed by the actual level it was skilled at, not pick order - a
          // talent skilled at 13 (before the 15 threshold) counts under the 10 tier.
          $tier = sb_talent_tier_for_level($level);
          if ($tier === null) continue;

          if (!isset($talents_res[$rid][$hid][$tier][$tid])) $talents_res[$rid][$hid][$tier][$tid] = SB_TALENT_DUMMY;

          // skilled_at avg includes every occurrence, even ones past the final level
          $talents_res[$rid][$hid][$tier][$tid]['lvl_sum'] += $level * $cnt;
          $talents_res[$rid][$hid][$tier][$tid]['lvl_cnt'] += $cnt;

          // pickrate/winrate only count picks made at or before the last tier's level
          if ($level <= SB_TALENT_FINAL_LEVEL) {
            $talents_res[$rid][$hid][$tier][$tid]['m'] += $cnt;
            $talents_res[$rid][$hid][$tier][$tid]['w'] += $wins;
          }
        }
      }

      // attribute (stat) points
      $attr = json_decode($attributes, true) ?: [ 'count' => 0, 'firstPointAt' => false ];
      if (!isset($attr_res[$rid][$hid])) $attr_res[$rid][$hid] = SB_ATTR_DUMMY;
      $attr_res[$rid][$hid]['matches'] += $cnt;
      $attr_res[$rid][$hid]['cnt_sum'] += ($attr['count'] ?? 0) * $cnt;
      if (($attr['count'] ?? 0) > 0) {
        $attr_res[$rid][$hid]['taken'] += $cnt;
        $attr_res[$rid][$hid]['taken_wins'] += $wins;
        if (($attr['firstPointAt'] ?? false) !== false) {
          $attr_res[$rid][$hid]['fp_sum'] += $attr['firstPointAt'] * $cnt;
          $attr_res[$rid][$hid]['fp_cnt'] += $cnt;
        }
      }
    }
  }

  $query_res->free_result();

  echo ' [ '.echobltime().' ] ';

  // finalize priority rows (fold; ranking is computed at view/api render time)

  $priority_out = [];
  foreach ($priority_res as $rid => $heroes) {
    foreach ($heroes as $hid => $priorities) {
      $priorities = sb_fold_priorities($priorities, $withLimit);

      $total = array_sum(array_column($priorities, 'm'));
      if (!$total) continue;

      $rows = [];
      foreach ($priorities as $priority => $count) {
        $featured_build = null;
        if (!empty($count['builds'])) {
          $builds = $count['builds'];
          arsort($builds);
          $featured_build = array_key_first($builds);
        }

        $skill_stats = null;
        if (!empty($count['skills'])) {
          $ss = [];
          foreach ($count['skills'] as $sid => $sv) {
            $ss[$sid] = [
              'first_point_avg' => $sv['fp_cnt'] ? round($sv['fp_sum'] / $sv['fp_cnt'], 2) : null,
              'maxed_at_avg' => $sv['mx_cnt'] ? round($sv['mx_sum'] / $sv['mx_cnt'], 2) : null,
            ];
          }
          $skill_stats = json_encode($ss);
        }

        $rows[] = [
          // kept as raw JSON text rather than decoded, same as 'priority' below:
          // wrap_data()'s deep-nesting only understands a row's first field being
          // a flat list, not a variable-key map/nested list. Consumers just
          // JSON-decode these themselves.
          'priority' => $priority == 'others' ? null : $priority,
          'matches' => $count['m'],
          'wins' => $count['w'],
          'winrate' => round($count['w'] / $count['m'], 4),
          'ratio' => round($count['m'] / $total, 4),
          'max_level' => $count['max_level'],
          'featured_build' => $featured_build,
          'skill_stats' => $skill_stats,
        ];
      }

      usort($rows, function($a, $b) { return $b['matches'] <=> $a['matches']; });

      $priority_out[$rid][$hid] = $rows;
    }
  }

  // finalize skills rows

  $skills_out = [];
  foreach ($skills_res as $rid => $heroes) {
    foreach ($heroes as $hid => $skills) {
      $rows = [];
      foreach ($skills as $sid => $s) {
        $rows[] = [
          'skill' => (int)$sid,
          'first_point_avg' => $s['fp_cnt'] ? round($s['fp_sum'] / $s['fp_cnt'], 2) : null,
          'maxed_at_avg' => $s['mx_cnt'] ? round($s['mx_sum'] / $s['mx_cnt'], 2) : null,
          'matches' => $s['w'],
        ];
      }
      usort($rows, function($a, $b) { return $a['first_point_avg'] <=> $b['first_point_avg']; });
      $skills_out[$rid][$hid] = $rows;
    }
  }

  // finalize talents rows

  $talents_out = [];
  foreach ($talents_res as $rid => $heroes) {
    foreach ($heroes as $hid => $tiers) {
      $rows = [];
      foreach ($tiers as $tier => $options) {
        $tier_total = array_sum(array_column($options, 'm'));
        foreach ($options as $tid => $o) {
          $rows[] = [
            'tier' => (int)$tier,
            'level' => SB_TALENT_LEVELS[$tier],
            'talent' => (int)$tid,
            'matches' => $o['m'],
            'wins' => $o['w'],
            'winrate' => $o['m'] ? round($o['w'] / $o['m'], 4) : null,
            'pick_rate' => $tier_total ? round($o['m'] / $tier_total, 4) : null,
            'skilled_at_avg' => $o['lvl_cnt'] ? round($o['lvl_sum'] / $o['lvl_cnt'], 2) : null,
          ];
        }
      }
      usort($rows, function($a, $b) { return $a['tier'] <=> $b['tier'] ?: $b['matches'] <=> $a['matches']; });
      $talents_out[$rid][$hid] = $rows;
    }
  }

  // finalize attributes

  $attr_out = [];
  foreach ($attr_res as $rid => $heroes) {
    foreach ($heroes as $hid => $a) {
      $attr_out[$rid][$hid] = [
        'matches' => $a['matches'],
        'taken_rate' => $a['matches'] ? round($a['taken'] / $a['matches'], 4) : 0,
        'avg_count' => $a['matches'] ? round($a['cnt_sum'] / $a['matches'], 3) : 0,
        'first_point_avg' => $a['fp_cnt'] ? round($a['fp_sum'] / $a['fp_cnt'], 2) : null,
        'taken_matches' => $a['taken'],
        'taken_winrate' => $a['taken'] ? round($a['taken_wins'] / $a['taken'], 4) : null,
      ];
    }
  }

  // finalize matches totals

  $matches_out = [];
  foreach ($matches_res as $rid => $heroes) {
    foreach ($heroes as $hid => $m) {
      $matches_out[$rid][$hid] = [
        'matches' => $m['m'],
        'winrate' => $m['m'] ? round($m['w'] / $m['m'], 4) : 0,
      ];
    }
  }

  // ultimate: most common ultimate ability id per hero

  $ultimate_out = [];
  foreach ($ultimate_votes as $hid => $votes) {
    arsort($votes);
    $ultimate_out[$hid] = (int)array_key_first($votes);
  }

  echo " [ ".echobltime()." ] \n";

  $r = [
    'priority' => [],
    'skills' => [],
    'attributes' => [],
    'matches' => [],
    'ultimate' => $ultimate_out,
  ];

  foreach ($priority_out as $rid => $heroes) {
    $r['priority'][$rid] = wrap_data($heroes, true, true, true);
  }
  foreach ($skills_out as $rid => $heroes) {
    $r['skills'][$rid] = wrap_data($heroes, true, true, true);
  }
  foreach ($attr_out as $rid => $heroes) {
    $r['attributes'][$rid] = wrap_data($heroes, true, true, true);
  }
  foreach ($matches_out as $rid => $heroes) {
    $r['matches'][$rid] = wrap_data($heroes, true, true, true);
  }

  if ($withTalents) {
    $r['talents'] = [];
    foreach ($talents_out as $rid => $heroes) {
      $r['talents'][$rid] = wrap_data($heroes, true, true, true);
    }
  }

  return $r;
}

if (!isset($result['skill_builds'])) $result['skill_builds'] = [];

$data = sb_query(
  $lg_settings['ana']['skill_builds_roles'] ?? false,
  $lg_settings['ana']['skill_builds_talents'] ?? true,
  $lg_settings['ana']['skill_builds_limit'] ?? true
);

foreach ($data as $k => &$v) {
  $result['skill_builds'][$k] = $v;
}

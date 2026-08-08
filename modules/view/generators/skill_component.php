<?php

include_once($root."/modules/fetcher/skillPriority.php"); // LEVELS_IDS / indexToLevel()

const SB_VIEW_NOATTR_HERO = 74; // Invoker: no LEVELS_IDS skip pattern, level = index+1
const SB_VIEW_ATTR_TAGS = [ 730, 5002 ];
const SB_TALENT_LEVELS_VIEW = [ 10, 15, 20, 25 ];
const SB_TALENT_TIMELINE_ICON = 'talents_tree_full'; // generic talent icon, talents have no per-choice art
const SKILLBUILD_FEATURED_LIMIT = 3;

// Same .build-item-component card used for item tier lists (see
// modules/view/generators/item_component.php), reused for ability icons.
// Ability icons are square (unlike the item icon box), see the ".spell"
// modifier in res/reports.css. When $params['stats'] is given (first_point_avg/
// maxed_at_avg), attaches them as item-time labels same as item components do.
// $params['icon_tag'] swaps in a fixed icon (talents) while the tooltip still
// shows this ability/talent's real name.
function skillbuild_component($sid, $params = []) {
  $tags = [ "build-item-component", "spell" ];
  if ($params['is_ultimate'] ?? false) $tags[] = "ultimate";
  if ($params['inline'] ?? false) $tags[] = "inline";
  if (($params['size'] ?? 48) >= 64) $tags[] = "lg";

  $labels = "";
  $s = $params['stats'] ?? null;
  if ($s) {
    $lines = [];
    if (($s['first_point_avg'] ?? null) !== null) {
      $lines[] = "<span class=\"item-time item-stat-tooltip-line\">".
        locale_string("skillbuild_first_short", [ 'L' => round($s['first_point_avg']) ]).
      "</span>";
    }
    // Averages can be pulled from different game subsets (see analyzer comment),
    // so a rarely-picked skill can average a "maxed at" under its own "first
    // point at" - still true per game, just skip the contradictory pair.
    if (($s['maxed_at_avg'] ?? null) !== null && (($s['first_point_avg'] ?? null) === null || $s['maxed_at_avg'] >= $s['first_point_avg'])) {
      $lines[] = "<span class=\"item-time item-stat-tooltip-line\">".
        locale_string("skillbuild_maxed_short", [ 'L' => round($s['maxed_at_avg']) ]).
      "</span>";
    }
    if (!empty($lines)) $labels = "<div class=\"labels\">".implode("", $lines)."</div>";
  }

  return "<div class=\"".implode(" ", $tags)."\">".
    "<a class=\"item-image\" title=\"".addcslashes(spell_name($sid), '"')."\">".
      spell_icon($sid, '', $params['icon_tag'] ?? null).
    "</a>".
    $labels.
  "</div>";
}

// Renders one priority row as a flat chain of skill icons: consecutive
// abilities of equal priority get an "=" separator, a rank change gets the
// same right-arrow used to link item build stages. $skill_stats, if given, is
// a skill_id -> {first_point_avg, maxed_at_avg} map scoped to this variant.
function skillbuild_priority_component($priority_map, $ultimate = null, $skill_stats = null, $size = 48) {
  if ($priority_map === null) {
    return "<span class=\"skillbuild-others-label\">".locale_string("skillbuild_others")."</span>";
  }

  $skills = [];
  foreach ($priority_map as $sid => $rank) {
    $skills[] = [ (int)$sid, (int)$rank ];
  }
  usort($skills, function($a, $b) { return $a[1] <=> $b[1]; });

  $out = "<div class=\"items-list skillbuild-priority-row\">";
  foreach ($skills as $i => $s) {
    [ $sid, $rank ] = $s;
    if ($i > 0) {
      $tied = $rank == $skills[$i - 1][1];
      $out .= "<div class=\"build-item-arrow build-item-arrow-".($tied ? "equal" : "right")."\"></div>";
    }
    $out .= skillbuild_component($sid, [
      'is_ultimate' => $ultimate !== null && $sid == $ultimate,
      'size' => $size,
      'stats' => $skill_stats[$sid] ?? null,
    ]);
  }
  $out .= "</div>";

  return $out;
}

// Winrate is judged on an absolute scale, not relative to whatever else is in
// the row - a 60% option isn't "weak" just because its sibling happens to be
// 65%; it's simply a good pick.
const SB_TALENT_WR_HIGH = 0.54;
const SB_TALENT_WR_LOW = 0.47;

function skilltalent_winrate_tier($winrate) {
  if ($winrate === null) return null;
  if ($winrate >= SB_TALENT_WR_HIGH) return 'strong';
  if ($winrate <= SB_TALENT_WR_LOW) return 'weak';
  return null;
}

// A talent option is a name plus stats, no icon - doesn't fit the
// icon+label build-item-component shape, so this is its own small component.
// $is_popular highlights the most-picked option in its row with a solid
// content-header background; winrate gets its own absolute-threshold color.
function skilltalent_card($option, $is_popular = false, $compact = false) {
  if (empty($option)) {
    return "<div class=\"skilltalent-card tbc\"><div class=\"skilltalent-name\">-</div></div>";
  }

  $tags = [ "skilltalent-card" ];
  if ($is_popular) $tags[] = "popular";
  if ($wr_tier = skilltalent_winrate_tier($option['winrate'])) $tags[] = "winrate-".$wr_tier;

  $pr = $option['pick_rate'] !== null ? number_format($option['pick_rate'] * 100, 1)."%" : "-";
  $wr = $option['winrate'] !== null ? number_format($option['winrate'] * 100, 1)."%" : "-";

  $stats = "<span class=\"skilltalent-stat\">".locale_string("purchase_rate")." $pr</span>".
    "<span class=\"skilltalent-stat wr\">".locale_string("winrate")." $wr</span>";

  if (!$compact) {
    $sk = ($option['skilled_at_avg'] ?? null) !== null ? number_format($option['skilled_at_avg'], 1) : "-";
    $rk = ($option['rank'] ?? null) !== null ? number_format($option['rank'], 1) : "-";
    $stats .= "<span class=\"skilltalent-stat\">".locale_string("skillbuild_talent_skilled_avg")." $sk</span>".
      "<span class=\"skilltalent-stat\">".locale_string("rank")." $rk</span>";
  }

  return "<div class=\"".implode(" ", $tags)."\">".
    "<div class=\"skilltalent-name\">".spell_name($option['talent'])."</div>".
    "<div class=\"skilltalent-stats\">".$stats."</div>".
  "</div>";
}

// One <tr> for a talent tier: level in its own cell, then the shown options in
// a single equal-width row - no option is ever dropped into a separate "extra"
// block. Compact (overview) mode is always the strict top 2 by pickrate; full
// mode shows everything observed, metadata left/right first when matched.
function skilltalent_tier_row($hero, $tier, $level, $options, $compact) {
  sb_talent_ranking($options);

  if ($compact) {
    $shown = array_values(array_filter(sb_talent_top2($hero, $tier, $options)));
  } else {
    [ $left, $right, $rest ] = sb_talent_lr($hero, $tier, $options);
    $shown = array_values(array_filter(array_merge([ $left, $right ], $rest)));
  }

  // Highlight the most-picked option even when it's the only one recorded
  // (a sole 100%-pickrate choice is still "the popular pick").
  $pop_idx = null;
  if (!empty($shown)) {
    $max_m = -1;
    foreach ($shown as $i => $o) {
      if ($o['matches'] > $max_m) { $max_m = $o['matches']; $pop_idx = $i; }
    }
  }

  $cells = "";
  if (empty($shown)) {
    $cells = skilltalent_card(null);
  } else {
    foreach ($shown as $i => $o) {
      $cells .= skilltalent_card($o, $i === $pop_idx, $compact);
    }
  }

  return "<tr class=\"skilltalent-row\">".
    "<td class=\"skilltalent-level-cell\">".$level."</td>".
    "<td class=\"skilltalent-options-cell\"><div class=\"skilltalent-options-row\">".$cells."</div></td>".
  "</tr>";
}

// Full talent table: one row per tier (10/15/20/25), every observed option
// shown. $compact caps each row to the top 2 by pickrate, used for the
// overview embed - it also gets its own caption since (unlike the full
// table) there's no "Talents" content-header sitting right above it.
function skilltalent_tree_component($hero, $tiers_by_index, $compact = false) {
  $rows = "";
  // 25 at the top, 10 at the bottom - reverse of the tier/level array itself,
  // keys (tier index) preserved so lookups against $tiers_by_index still work.
  foreach (array_reverse(SB_TALENT_LEVELS_VIEW, true) as $tier => $level) {
    $rows .= skilltalent_tier_row($hero, $tier, $level, $tiers_by_index[$tier] ?? [], $compact);
  }

  $caption = $compact ? "<caption>".locale_string("skillbuild_talents")."</caption>" : "";

  return "<table class=\"list skilltalent-table".($compact ? " compact" : "")."\">".
    $caption.
    "<tbody>".$rows."</tbody>".
  "</table>";
}

// The recorded game behind a priority row often doesn't run to level 25 (or
// stops maxing a skill early because the match ended). Continues the build
// past wherever it was actually recorded, in the same priority order, until
// every skill is at its cap (4 points, 3 for the ultimate) and every talent
// tier has a pick - using each tier's most popular option as the filler. Only
// appends; never touches the real recorded prefix.
//
// Array index and in-game level are NOT the same number past index 15 -
// LEVELS_IDS skips levels (17, 19, 21-24, 26...), so this has to go through
// indexToLevel() for every candidate slot rather than counting 19, 20, 21...
// as if that were the level. Also can't stop the moment every skill is maxed:
// a later index can still land on a still-missing talent tier.
function skillbuild_extrapolate_build($featured_build, $priority_map, $ultimate, $tiers_by_index, $noattr) {
  $skills = array_map('intval', array_keys($priority_map));

  $invested = array_fill_keys($skills, 0);
  foreach ($featured_build as $sid) {
    if (isset($invested[$sid])) $invested[$sid]++;
  }

  $caps = [];
  foreach ($skills as $sid) {
    $caps[$sid] = ($ultimate !== null && $sid == $ultimate) ? 3 : 4;
  }

  $ordered = $skills;
  usort($ordered, function($a, $b) use ($priority_map) { return $priority_map[$a] <=> $priority_map[$b]; });

  $best_talent_by_tier = [];
  foreach ($tiers_by_index as $tier => $options) {
    if (!empty($options)) $best_talent_by_tier[$tier] = $options[0]['talent']; // pre-sorted by matches desc
  }

  $tiers_present = [];
  foreach ($featured_build as $i => $sid) {
    $t = array_search(indexToLevel($i, $noattr), SB_TALENT_LEVELS_VIEW);
    if ($t !== false) $tiers_present[$t] = true;
  }

  $sequence = $featured_build;
  $index = count($sequence);

  while (true) {
    $level = indexToLevel($index, $noattr);
    if ($level > 25) break;

    $tier = array_search($level, SB_TALENT_LEVELS_VIEW);

    if ($tier !== false) {
      if (!isset($tiers_present[$tier]) && isset($best_talent_by_tier[$tier])) {
        $sequence[] = $best_talent_by_tier[$tier];
        $tiers_present[$tier] = true;
      }
    } else {
      $picked = null;
      foreach ($ordered as $sid) {
        if ($invested[$sid] < $caps[$sid]) { $picked = $sid; break; }
      }
      if ($picked !== null) {
        $sequence[] = $picked;
        $invested[$picked]++;
      }
      // else every skill's already at cap at this index - fall through and
      // keep going, a talent tier further down might still need filling
    }

    $index++;
  }

  return $sequence;
}

// Reconstructed level-up sequence for the single most common exact build
// behind a priority row (analyzer's 'featured_build'), with level 6/10/full
// tabs (reuses the site-wide switchTab() js, no new JS needed). Attribute/stat
// points have no place here and are skipped entirely; talents have no icon so
// they render as a small text marker instead of a skill_icon(). The level each
// step shows is its true in-game level (computed from its original position
// in the sequence, not its position among the filtered-out attribute points).
// The "full" tab is extrapolated past wherever the real recorded game ended
// (see skillbuild_extrapolate_build()); level 6/10 stay strictly real data.
function skillbuild_timeline_component($hero, $uid_suffix, $featured_build, $ultimate = null, $priority_map = null, $tiers_by_index = []) {
  if (empty($featured_build)) return "";

  $noattr = ((int)$hero === SB_VIEW_NOATTR_HERO);

  $build_to_steps = function($build) use ($noattr) {
    $steps = [];
    foreach ($build as $i => $sid) {
      if (in_array($sid, SB_VIEW_ATTR_TAGS)) continue;
      $steps[] = [
        'sid' => $sid,
        'level' => indexToLevel($i, $noattr),
        'is_talent' => strpos(spell_tag($sid), 'special_bonus') !== false,
      ];
    }
    return $steps;
  };

  $steps = $build_to_steps($featured_build);
  if (empty($steps)) return "";

  $full_build = !empty($priority_map)
    ? skillbuild_extrapolate_build($featured_build, $priority_map, $ultimate, $tiers_by_index, $noattr)
    : $featured_build;
  $full_steps = $build_to_steps($full_build);

  $uid = "sbtl-".$hero."-".preg_replace('/[^a-z0-9]+/i', '-', (string)$uid_suffix);

  $panels = [
    '6' => [ locale_string("skillbuild_timeline_level6"), array_values(array_filter($steps, function($s) { return $s['level'] <= 6; })) ],
    '10' => [ locale_string("skillbuild_timeline_level10"), array_values(array_filter($steps, function($s) { return $s['level'] <= 10; })) ],
    'full' => [ locale_string("skillbuild_timeline_full"), $full_steps ],
  ];

  $out = "<div class=\"skillbuild-timeline-tabs\">";
  $first = true;
  foreach ($panels as $key => $p) {
    $out .= "<span class=\"skillbuild-timeline-tab $uid-selector".($first ? " active" : "")."\" ".
      "onclick=\"switchTab(event,'module-$uid-$key','$uid')\">".$p[0]."</span>";
    $first = false;
  }
  $out .= "</div>";

  $first = true;
  foreach ($panels as $key => $p) {
    $out .= "<div id=\"module-$uid-$key\" class=\"skillbuild-timeline-panel $uid".($first ? " active" : "")."\">";
    foreach ($p[1] as $step) {
      $out .= skillbuild_component($step['sid'], [
        'is_ultimate' => $ultimate !== null && $step['sid'] == $ultimate,
        'icon_tag' => $step['is_talent'] ? SB_TALENT_TIMELINE_ICON : null,
      ]);
    }
    $out .= "</div>";
    $first = false;
  }

  return $out;
}

// Gathers everything needed to render a hero+role's skill build data, decoded
// and ranked. Shared by the full heroes-skillbuilds page and the compact
// "Skill build" embed on the item builds page.
function skillbuild_collect($hero, $rid) {
  global $report;

  $priority = sb_decode_skill_stats(sb_decode_featured_build(
    sb_decode_priority(sb_unwrap_rows($report['skill_builds']['priority'], $rid)[$hero] ?? [])
  ));
  sb_priority_ranking($priority);

  $skills = sb_unwrap_rows($report['skill_builds']['skills'], $rid)[$hero] ?? [];
  $skills_by_id = [];
  foreach ($skills as $s) $skills_by_id[$s['skill']] = $s;

  $talents_flat = isset($report['skill_builds']['talents']) ? (sb_unwrap_rows($report['skill_builds']['talents'], $rid)[$hero] ?? []) : [];
  $tiers = [];
  foreach ($talents_flat as $t) {
    $tiers[$t['tier']][] = $t;
  }
  foreach ($tiers as &$opts) {
    usort($opts, function($a, $b) { return $b['matches'] <=> $a['matches']; });
  }
  unset($opts);

  return [
    'priority' => $priority,
    'skills' => $skills,
    'skills_by_id' => $skills_by_id,
    'tiers' => $tiers,
    'attributes' => sb_unwrap_role($report['skill_builds']['attributes'], $rid)[$hero] ?? null,
    'ultimate' => $report['skill_builds']['ultimate'][$hero] ?? null,
  ];
}

// The single most popular priority row plus the single best-ranked one (often
// the same row - if so, just the one card).
function skillbuild_featured_rows($priority) {
  if (empty($priority)) return [];

  $by_matches = $priority[0]; // analyzer output is pre-sorted by matches desc

  $by_rank = $priority[0];
  foreach ($priority as $row) {
    if (($row['rank'] ?? 0) > ($by_rank['rank'] ?? 0)) $by_rank = $row;
  }

  if ($by_rank === $by_matches) return [ $by_matches ];
  return [ $by_matches, $by_rank ];
}

// Overview block: featured priorities | compact talent tree, followed by the
// reconstructed timeline for the most popular priority row. Used both at the
// top of the full heroes-skillbuilds page and embedded (titled "Skill build")
// into the item builds page.
function skillbuild_render_overview($hero, $rid, $data) {
  $priority = $data['priority'];
  $ultimate = $data['ultimate'];

  $featured = skillbuild_featured_rows($priority);

  $left = "<div class=\"skillbuild-overview-column skillbuild-featured\">";
  $left .= "<div class=\"skillbuild-featured-caption\">".locale_string("skillbuild_featured_priorities")."</div>";
  foreach ($featured as $row) {
    // .skillbuild-featured-build shrink-wraps to the icon row's width, so the
    // summary line's space-between spreads only across that width, not the column.
    $left .= "<div class=\"skillbuild-featured-row\"><div class=\"skillbuild-featured-build\">".
      skillbuild_priority_component($row['priority'], $ultimate, $row['skill_stats'], 64).
      "<div class=\"skillbuild-priority-summary\">".
        "<span>".locale_string("ratio").": ".number_format($row['ratio'] * 100, 2)."%</span>".
        "<span>".locale_string("winrate").": ".number_format($row['winrate'] * 100, 2)."%</span>".
        "<span>".locale_string("rank").": ".number_format($row['rank'], 2)."</span>".
      "</div>".
    "</div></div>";
  }
  $left .= "</div>";

  $right = "<div class=\"skillbuild-overview-column skillbuild-talents-col\">".
    skilltalent_tree_component($hero, $data['tiers'], true).
  "</div>";

  $res = "<div class=\"skillbuild-overview-columns\">".$left.$right."</div>";

  $top = $featured[0] ?? null;
  if (!empty($top['featured_build'])) {
    // Caption + tabs + panels share one width/margin-constrained wrapper (same
    // treatment as table.list), so the tabs and icon rows actually line up
    // under the caption instead of spanning the full content width on their own.
    $res .= "<div class=\"skillbuild-timeline-section\">";
    $res .= "<div class=\"skillbuild-timeline-caption\">".locale_string("skillbuild_timeline_header")."</div>";
    $res .= skillbuild_timeline_component($hero, $rid, $top['featured_build'], $ultimate, $top['priority'], $data['tiers']);
    $res .= "</div>";
  }

  return $res;
}

function skillbuild_render_explainer() {
  return "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\"><div class=\"line\">".locale_string("skillbuilds_desc")."</div></div>".
  "</details>";
}

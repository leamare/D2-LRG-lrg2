<?php

/**
 * Portrait cards for heroes, players and teams
 */

include_once(__DIR__ . "/hero_name.php");
include_once(__DIR__ . "/player_name.php");
include_once(__DIR__ . "/team_name.php");

function lrg_card_winrate_class($wr) {
  if ($wr === null) return [ null, null ];
  if ($wr >= 0.53) return [ 'winrate-strong', 'item-winrate-increase' ];
  if ($wr < 0.47) return [ 'winrate-weak', 'item-winrate-increase' ];
  return [ 'winrate-neutral', 'item-winrate-neutral' ];
}

function lrg_card_rank($rank) {
  return $rank === null ? null : number_format($rank, 1);
}

function lrg_card_contest_tooltip($rate) {
  return $rate === null ? "" : ", ".locale_string('contest_rate').": ".number_format($rate*100,2)."%";
}

function lrg_card_matches_label($matches) {
  return $matches === null ? "" :
    "<span class=\"item-stat-tooltip item-stat-tooltip-line meta-hero-card-matches\">".
      number_format($matches, 0)." ".locale_string('matches').
    "</span>";
}

function lrg_card_markup($tags, $href, $tooltip, $portrait, $corner, $labels) {
  return "<div class=\"".implode(" ", $tags)."\">".
    "<a class=\"item-image\" href=\"".$href."\" title=\"".addcslashes($tooltip, "'\"")."\">".
      $portrait.
      ($corner !== null ? "<span class=\"item-prate\">".$corner."</span>" : "").
    "</a>".
    "<div class=\"labels\">".$labels."</div>".
  "</div>";
}

function lrg_hero_primary_role($hid) {
  global $report;

  if (empty($report['hero_positions'])) return null;
  if (is_wrapped($report['hero_positions'])) $report['hero_positions'] = unwrap_data($report['hero_positions']);

  $roles = [];
  for ($i=1; $i>=0; $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      if (empty($report['hero_positions'][$i][$j][$hid])) continue;
      $roles["$i.$j"] = $report['hero_positions'][$i][$j][$hid]['matches_s'];
    }
  }

  if (empty($roles)) return null;

  arsort($roles);
  return "position_".array_keys($roles)[0];
}

function lrg_hero_card($hid, $rank = null) {
  global $report, $leaguetag, $linkvars;

  $href = "?league=$leaguetag&mod=heroes-profiles-heroid$hid".(empty($linkvars) ? "" : "&".$linkvars);

  $pb = $report['pickban'][$hid] ?? null;
  $wr = $pb['winrate_picked'] ?? null;
  $matches_total = $report['random']['matches_total'] ?? 0;
  $pr = ($pb && $matches_total) ? ($pb['matches_picked'] / $matches_total) : null;
  $cr = ($pb && $matches_total) ? (($pb['matches_total'] ?? (($pb['matches_picked'] ?? 0) + ($pb['matches_banned'] ?? 0))) / $matches_total) : null;
  $role = lrg_hero_primary_role($hid);

  [ $wrtag, $wrclass ] = lrg_card_winrate_class($wr);
  $tags = [ "build-item-component", "meta-hero-card" ];
  if ($wrtag) $tags[] = $wrtag;

  $tooltip = hero_name($hid).
    ($wr !== null ? " - ".locale_string('winrate').": ".number_format($wr*100,2)."%" : "").
    ($pr !== null ? ", ".locale_string('picks').": ".number_format($pr*100,2)."%" : "").
    lrg_card_contest_tooltip($cr).
    ($role ? ", ".locale_string($role) : "").
    ($rank !== null ? ", ".locale_string('rank').": ".lrg_card_rank($rank) : "");

  $labels = "<span class=\"item-stat-tooltip-line meta-hero-card-name\">".hero_name($hid)."</span>".
    ($wr !== null ? "<span class=\"item-stat-tooltip item-stat-tooltip-line item-winrate $wrclass\">".number_format($wr*100,1)."%</span>" : "").
    ($role ? "<span class=\"item-stat-tooltip-line meta-hero-card-role\">".locale_string($role)."</span>" : "");

  return lrg_card_markup(
    $tags, $href, $tooltip, hero_portrait($hid),
    $rank !== null ? lrg_card_rank($rank) : ($pr !== null ? number_format($pr*100,1)."%" : null),
    $labels
  );
}

function lrg_hero_card_role($hid, $stats, $role_total = 0, $role = null, $rank = null) {
  global $report, $leaguetag, $linkvars;

  $href = "?league=$leaguetag&mod=heroes-profiles-heroid$hid".(empty($linkvars) ? "" : "&".$linkvars);

  $matches = $stats['matches_s'] ?? 0;
  $wr = isset($stats['winrate_s']) ? $stats['winrate_s'] : null;
  $matches_total = $report['random']['matches_total'] ?? 0;
  $pr = $matches_total ? $matches / $matches_total : null;
  $ratio = $role_total > 0 ? $matches / $role_total : null;
  // bans aren't tracked per role: split between the roles by the share of picks, same as for the tier itself
  $cr = $matches_total ? ($matches + ($report['pickban'][$hid]['matches_banned'] ?? 0) * ($ratio ?? 0)) / $matches_total : null;

  [ $wrtag, $wrclass ] = lrg_card_winrate_class($wr);
  $tags = [ "build-item-component", "meta-hero-card" ];
  if ($wrtag) $tags[] = $wrtag;

  $tooltip = hero_name($hid).
    ($role ? " - ".locale_string($role) : "").
    " - ".locale_string('matches').": ".number_format($matches, 0).
    ($wr !== null ? ", ".locale_string('winrate').": ".number_format($wr*100,2)."%" : "").
    ($pr !== null ? ", ".locale_string('pickrate').": ".number_format($pr*100,2)."%" : "").
    lrg_card_contest_tooltip($cr).
    ($ratio !== null ? ", ".locale_string('ratio_pos').": ".number_format($ratio*100,2)."%" : "").
    ($rank !== null ? ", ".locale_string('rank').": ".lrg_card_rank($rank) : "");

  $labels = "<span class=\"item-stat-tooltip-line meta-hero-card-name\">".hero_name($hid)."</span>".
    ($wr !== null ? "<span class=\"item-stat-tooltip item-stat-tooltip-line item-winrate $wrclass\">".number_format($wr*100,1)."%</span>" : "").
    ($ratio !== null ? "<span class=\"item-stat-tooltip item-stat-tooltip-line meta-hero-card-role\"".
        " title=\"".addcslashes(locale_string('ratio_pos'), "'\"")."\">".number_format($ratio*100,0)."%</span>" : "");

  return lrg_card_markup(
    $tags, $href, $tooltip, hero_portrait($hid),
    $rank !== null ? lrg_card_rank($rank) : ($pr !== null ? number_format($pr*100,1)."%" : null),
    $labels
  );
}

/**
 * Hero card with the record of a single player/team on that hero
 * $record: [ 'matches', 'wins', optional 'bans' ]
 * $games:  matches the player/team played, for the contest rate (picks plus bans over those)
 */
function lrg_hero_card_record($hid, $record = null, $rank = null, $games = null) {
  global $leaguetag, $linkvars;

  $href = "?league=$leaguetag&mod=heroes-profiles-heroid$hid".(empty($linkvars) ? "" : "&".$linkvars);

  $wr = ($record && $record['matches']) ? $record['wins'] / $record['matches'] : null;
  [ $wrtag, $wrclass ] = lrg_card_winrate_class($wr);
  $tags = [ "build-item-component", "meta-hero-card" ];
  if ($wrtag) $tags[] = $wrtag;

  $tooltip = hero_name($hid).
    ($record ? " - ".locale_string('matches').": ".$record['matches'] : "").
    ($wr !== null ? ", ".locale_string('winrate').": ".number_format($wr*100,2)."%" : "").
    (!empty($record['bans']) ? ", ".locale_string('bans').": ".$record['bans'] : "").
    lrg_card_contest_tooltip(($record && $games) ? ($record['matches'] + ($record['bans'] ?? 0)) / $games : null).
    ($rank !== null ? ", ".locale_string('rank').": ".lrg_card_rank($rank) : "");

  $labels = "<span class=\"item-stat-tooltip-line meta-hero-card-name\">".hero_name($hid)."</span>".
    ($wr !== null ? "<span class=\"item-stat-tooltip item-stat-tooltip-line item-winrate $wrclass\">".number_format($wr*100,1)."%</span>" : "").
    lrg_card_matches_label($record ? $record['matches'] : null);

  return lrg_card_markup(
    $tags, $href, $tooltip, hero_portrait($hid),
    $rank !== null ? lrg_card_rank($rank) : ($record ? $record['matches'] : null),
    $labels
  );
}

function lrg_player_card($pid, $record = null, $rank = null) {
  global $leaguetag, $linkvars, $player_photo_provider;

  $href = "?league=$leaguetag&mod=players-profiles-playerid$pid".(empty($linkvars) ? "" : "&".$linkvars);

  $wr = ($record && $record['matches']) ? $record['wins'] / $record['matches'] : null;
  [ $wrtag, $wrclass ] = lrg_card_winrate_class($wr);
  $tags = [ "build-item-component", "meta-hero-card", "entity-card-player" ];
  if ($wrtag) $tags[] = $wrtag;

  $portrait = "<img class=\"hero_portrait\" src=\"".
    str_replace("%HERO%", $pid, $player_photo_provider ?? "")."\" alt=\"".addcslashes(player_name($pid, false), "'\"")."\" />";

  $tooltip = player_name($pid).
    ($record ? " - ".locale_string('matches').": ".$record['matches'] : "").
    ($wr !== null ? ", ".locale_string('winrate').": ".number_format($wr*100,2)."%" : "").
    ($rank !== null ? ", ".locale_string('rank').": ".lrg_card_rank($rank) : "");

  $labels = "<span class=\"item-stat-tooltip-line meta-hero-card-name\">".player_name($pid, false)."</span>".
    ($wr !== null ? "<span class=\"item-stat-tooltip item-stat-tooltip-line item-winrate $wrclass\">".number_format($wr*100,1)."%</span>" : "").
    lrg_card_matches_label($record ? $record['matches'] : null);

  return lrg_card_markup(
    $tags, $href, $tooltip, $portrait,
    $rank !== null ? lrg_card_rank($rank) : ($record ? $record['matches'] : null),
    $labels
  );
}

function lrg_team_card_mini($tid, $record = null, $rank = null) {
  global $leaguetag, $linkvars, $team_logo_provider;

  $href = "?league=$leaguetag&mod=teams-profiles-team$tid".(empty($linkvars) ? "" : "&".$linkvars);

  $wr = ($record && $record['matches']) ? $record['wins'] / $record['matches'] : null;
  [ $wrtag, $wrclass ] = lrg_card_winrate_class($wr);
  $tags = [ "build-item-component", "meta-hero-card", "entity-card-team" ];
  if ($wrtag) $tags[] = $wrtag;

  $portrait = "<img class=\"hero_portrait\" src=\"".
    str_replace('%TEAM%', $tid, $team_logo_provider ?? "")."\" alt=\"".addcslashes(team_name($tid), "'\"")."\" />";

  $tooltip = team_name($tid).
    ($record ? " - ".locale_string('matches').": ".$record['matches'] : "").
    ($wr !== null ? ", ".locale_string('winrate').": ".number_format($wr*100,2)."%" : "").
    ($rank !== null ? ", ".locale_string('rank').": ".lrg_card_rank($rank) : "");

  $labels = "<span class=\"item-stat-tooltip-line meta-hero-card-name\">".team_tag($tid)."</span>".
    ($wr !== null ? "<span class=\"item-stat-tooltip item-stat-tooltip-line item-winrate $wrclass\">".number_format($wr*100,1)."%</span>" : "").
    lrg_card_matches_label($record ? $record['matches'] : null);

  return lrg_card_markup(
    $tags, $href, $tooltip, $portrait,
    $rank !== null ? lrg_card_rank($rank) : ($record ? $record['matches'] : null),
    $labels
  );
}

<?php

ini_set('memory_limit', '4000M');

/**
 * Rebuild descriptor (leagues/*.json) and optionally matchlists/*.list from a baked report JSON.
 *
 * Usage (from repo root):
 *   php tools/restore_league_from_report.php -l <league_tag> [-i path/to/report.json] [-f]
 *   (Glued -l<tag> is fine; the tool must re-parse -l in getopt so a letter "i" inside the tag is not treated as -i.)
 *
 *   -i,--report  Path to baked report (default: reports/report_<tag>.json)
 *   -f,--force   Overwrite existing descriptor / matchlist
 *
 * When the report includes first_match / last_match ({ mid, date }), the descriptor gets
 * match_limit_* and time_limit_* derived with ±1 slack so rg_fetcher range checks include
 * those boundary matches (see modules/fetcher/fetch.php).
 */

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/head.php';
require_once $root . '/modules/commons/merge_mods.php';

if (!isset($lrg_league_tag)) {
  die("[F] Pass -l <league_tag>.\n");
}

// Include l: so glued -l<tag> is one option; otherwise a literal "i" inside the tag (e.g. …_imm…) is parsed as -i.
$longopts = ['report:', 'input:', 'force'];
$tool_opts = getopt('i:fl:', $longopts);

$force = isset($tool_opts['f']) || isset($tool_opts['force']);
$report_path = $tool_opts['i'] ?? $tool_opts['report'] ?? $tool_opts['input'] ?? ($root . '/reports/report_' . $lrg_league_tag . '.json');
if (is_array($report_path)) {
  $report_path = end($report_path);
}
$report_path = (string)$report_path;

if (!is_readable($report_path)) {
  die("[F] Cannot read report: {$report_path}\n");
}

$raw_json = file_get_contents($report_path);
$report = json_decode($raw_json, true);
if (!is_array($report)) {
  die("[F] Report is not valid JSON or not an object/array root.\n");
}

$r_tag = $report['league_tag'] ?? null;
if ($r_tag !== null && (string)$r_tag !== (string)$lrg_league_tag) {
  echo "[W] Report league_tag (" . var_export($r_tag, true) . ") differs from -l ({$lrg_league_tag}). Continuing anyway.\n";
}

$default_path = $root . '/templates/default.json';
if (!is_readable($default_path)) {
  die("[F] Missing templates/default.json\n");
}

$desc = json_decode(file_get_contents($default_path), true);
if (!is_array($desc)) {
  die("[F] Could not parse templates/default.json\n");
}

// Analyzer version: required so rg_analyzer compare_ver() does not break on missing version.
$baked_ver = $report['ana_version'] ?? null;
if (is_array($baked_ver) && count($baked_ver) >= 3) {
  $desc['version'] = $baked_ver;
} elseif (isset($lrg_version) && is_array($lrg_version)) {
  $desc['version'] = $lrg_version;
} else {
  $desc['version'] = [2, 30, 0, 0, 0];
}

$desc['league_tag'] = $lrg_league_tag;
$desc['league_name'] = $report['league_name'] ?? '';
$desc['league_desc'] = $report['league_desc'] ?? '';
$desc['league_id'] = $report['league_id'] ?? null;

foreach (['sponsors', 'orgs', 'links', 'localized'] as $k) {
  if (array_key_exists($k, $report)) {
    $desc[$k] = $report[$k];
  }
}

// Interest lists (override inference).
if (!empty($report['teams_interest']) && is_array($report['teams_interest'])) {
  $desc['teams'] = array_values(array_map('intval', $report['teams_interest']));
  unset($desc['players']);
  $desc['main']['teams'] = true;
} elseif (!empty($report['players_interest']) && is_array($report['players_interest'])) {
  $desc['players'] = array_values(array_map('intval', $report['players_interest']));
  unset($desc['teams']);
  $desc['main']['teams'] = false;
} else {
  $has_tvt = !empty($report['match_participants_teams']) && is_array($report['match_participants_teams']);
  if ($has_tvt) {
    unset($desc['players']);
    $desc['main']['teams'] = true;
    $team_ids = [];
    foreach ($report['match_participants_teams'] as $m) {
      if (!is_array($m)) {
        continue;
      }
      if (!empty($m['radiant'])) {
        $team_ids[(int)$m['radiant']] = true;
      }
      if (!empty($m['dire'])) {
        $team_ids[(int)$m['dire']] = true;
      }
    }
    unset($team_ids[0]);
    if (!empty($team_ids)) {
      $desc['teams'] = array_keys($team_ids);
      sort($desc['teams'], SORT_NUMERIC);
    }
  } else {
    unset($desc['teams']);
    $desc['main']['teams'] = false;
  }
}

// Baked report stores UI/settings slice under "settings" (from lg_settings['web'] + analyzers extras).
$web_in = isset($report['settings']) && is_array($report['settings']) ? $report['settings'] : [];

$baked_only_keys = [
  'limiter',
  'limiter_middle',
  'limiter_triplets',
  'limiter_combograph',
  'limiter_players',
  'limiter_players_median',
  'heroes_snapshot',
];

$sti_map = [
  'sti_builds_players_limit' => 'starting_builds_players_limit',
  'sti_builds_roles_players_limit' => 'starting_builds_roles_players_limit',
  'sti_builds_limit' => 'starting_builds_limit',
  'sti_builds_roles_limit' => 'starting_builds_roles_limit',
];

$web_clean = $web_in;
foreach ($baked_only_keys as $k) {
  unset($web_clean[$k]);
}

if (isset($web_clean['sti_builds_players_limit'])) {
  // Not a web key in default template; do not merge into web.
  unset($web_clean['sti_builds_players_limit']);
}
if (isset($web_clean['sti_builds_roles_players_limit'])) {
  unset($web_clean['sti_builds_roles_players_limit']);
}
if (isset($web_clean['sti_builds_limit'])) {
  unset($web_clean['sti_builds_limit']);
}
if (isset($web_clean['sti_builds_roles_limit'])) {
  unset($web_clean['sti_builds_roles_limit']);
}

foreach ($sti_map as $from => $to) {
  if (isset($web_in[$from]) && isset($desc['ana'])) {
    $desc['ana'][$to] = $web_in[$from];
  }
}

if (array_key_exists('series_id_priority', $web_in)) {
  $desc['main']['series_id_priority'] = (bool)$web_in['series_id_priority'];
}
unset($web_clean['series_id_priority']);

if (!empty($web_in['incomplete_source'])) {
  $desc['incomplete_source'] = true;
}
unset($web_clean['incomplete_source']);

if (isset($web_in['sources']) && is_array($web_in['sources'])) {
  $desc['sources'] = $web_in['sources'];
}
unset($web_clean['sources']);

if (array_key_exists('heroes_exclude', $web_in)) {
  $desc['heroes_exclude'] = $web_in['heroes_exclude'];
}
unset($web_clean['heroes_exclude']);

merge_mods($desc['web'], $web_clean);

// Heuristic ana/main toggles from report contents (conservative: only disable when absent).
if (empty($report['fantasy'])) {
  $desc['main']['fantasy'] = false;
}
if (empty($report['tickets'])) {
  $desc['ana']['tickets'] = false;
}
if (empty($report['milestones'])) {
  $desc['ana']['milestones'] = false;
}
if (empty($report['records']) && empty($report['records_ext'])) {
  $desc['ana']['records'] = false;
}

$has_matchlist_data = !empty($report['matches']) && is_array($report['matches'])
  && !empty($report['matches_additional']) && is_array($report['matches_additional']);
$desc['ana']['matchlist'] = $has_matchlist_data;

if (empty($report['regions_data']) || !is_array($report['regions_data'])) {
  unset($desc['ana']['regions']);
} else {
  echo "[ ] regions_data present in report; keeping ana.regions from template. Customize cluster groups manually if needed.\n";
}

if (array_key_exists('items', $report)) {
  $has_items = !empty($report['items']) && is_array($report['items']);
  $desc['main']['items'] = $has_items;
  $desc['ana']['items'] = $has_items;
}

if (array_key_exists('wards', $report) && empty($report['wards'])) {
  $desc['main']['wards'] = false;
}

if (array_key_exists('starting_items', $report) && empty($report['starting_items'])) {
  $desc['main']['starting'] = false;
  $desc['ana']['starting_items'] = false;
  $desc['ana']['starting_builds'] = false;
}
if (array_key_exists('consumables', $report) && empty($report['consumables'])) {
  $desc['ana']['consumables'] = false;
}

// Match / time windows from rg_analyzer report: first_match & last_match are { "mid", "date" } (unix).
// fetch.php skips when match < match_limit_after, match > match_limit_before, start_date < time_limit_after,
// start_date > time_limit_before. Off-by-one slack so boundary ids/times still pass.
$fetch_limits_from_report = false;
$fm = $report['first_match'] ?? null;
if (is_array($fm)) {
  if (isset($fm['mid']) && is_numeric($fm['mid'])) {
    $desc['match_limit_after'] = (int)$fm['mid'] - 1;
    $fetch_limits_from_report = true;
  }
  if (isset($fm['date']) && is_numeric($fm['date'])) {
    $desc['time_limit_after'] = (int)$fm['date'] - 1;
    $fetch_limits_from_report = true;
  }
}
$lm = $report['last_match'] ?? null;
if (is_array($lm)) {
  if (isset($lm['mid']) && is_numeric($lm['mid'])) {
    $desc['match_limit_before'] = (int)$lm['mid'] + 1;
    $fetch_limits_from_report = true;
  }
  if (isset($lm['date']) && is_numeric($lm['date'])) {
    $desc['time_limit_before'] = (int)$lm['date'] + 1;
    $fetch_limits_from_report = true;
  }
}
if ($fetch_limits_from_report) {
  echo "[ ] Set fetch window from report first/last match: match_limit_after=" .
    json_encode($desc['match_limit_after']) . ", match_limit_before=" .
    json_encode($desc['match_limit_before']) . ", time_limit_after=" .
    json_encode($desc['time_limit_after']) . ", time_limit_before=" .
    json_encode($desc['time_limit_before']) . "\n";
}

$league_file = $root . '/leagues/' . $lrg_league_tag . '.json';
if (!$force && file_exists($league_file)) {
  die("[F] Descriptor already exists: {$league_file} (use -f to overwrite).\n");
}

$json_out = json_encode($desc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json_out === false) {
  die("[F] json_encode failed for descriptor.\n");
}

if (file_put_contents($league_file, $json_out . "\n") === false) {
  die("[F] Could not write {$league_file}\n");
}
echo "[S] Wrote descriptor {$league_file}\n";

$list_file = $root . '/matchlists/' . $lrg_league_tag . '.list';
if ($has_matchlist_data) {
  if (!$force && file_exists($list_file)) {
    echo "[W] Matchlist exists, skipping {$list_file} (use -f to overwrite).\n";
  } else {
    $ids = array_keys($report['matches']);
    $ids = array_filter($ids, fn($id) => is_numeric($id));
    $ids = array_map('intval', $ids);
    sort($ids, SORT_NUMERIC);
    $lines = array_merge(
      ['# Restored from ' . basename($report_path) . ' (' . count($ids) . ' matches)'],
      $ids
    );
    $list_body = implode("\n", $lines) . "\n";
    if (file_put_contents($list_file, $list_body) === false) {
      die("[F] Could not write {$list_file}\n");
    }
    echo "[S] Wrote matchlist {$list_file}\n";
  }
} else {
  echo "[ ] No matches/matches_additional in report; matchlist not created.\n";
}

echo "[ ] Baked analyzer version was " . json_encode($report['ana_version'] ?? null) . "\n";
echo "[ ] Done. Database was not modified; run rg_fetcher with the new matchlist, then rg_analyzer.\n";

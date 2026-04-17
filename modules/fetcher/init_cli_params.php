<?php

$listen = isset($options['L']);

$stratz_timeout_retries = 2;

$force_adding = isset($options['F']);
$cache_dir    = $options['c'] ?? "cache";
if ($cache_dir === "NULL") $cache_dir = "";

if (!empty($options['P'])) {
  $players_list = json_decode(file_get_contents($options['P']));
} elseif (!empty($lg_settings['players_allowlist'])) {
  $players_list = $lg_settings['players_allowlist'];
}
if (!empty($options['N'])) {
  $rank_limit = (int)$options['N'];
}

$min_duration_seconds = $lg_settings['min_duration']   ?? 600;
$min_score_side       = $lg_settings['min_score_side'] ?? 5;

if (!empty($options['d'])) {
  $api_cooldown         = ((float)$options['d']) * 1000;
  $api_cooldown_seconds = ((float)$options['d']);
} else {
  $api_cooldown_seconds = 2;
}

$rewrite_existing = isset($options['W']);
$addition_mode    = isset($options['a']);
$update_unparsed  = isset($options['u']) || isset($options['U']);

$use_stratz      = isset($options['S']) || isset($options['s']);
$require_stratz  = isset($options['S']);
$use_full_stratz = isset($options['Z']);
$stratz_graphql  = isset($options['G']);

$stratz_graphql_group         = isset($options['G']) ? (int)($options['G']) : 0;
$stratz_graphql_group_counter = 0;

$fetch_workers = max(1, (int)($options['j'] ?? 1));
if ($fetch_workers > 1) {
  if ($listen || $stratz_graphql_group) {
    echo "[W] Parallel fetch (-j) is not compatible with listen mode (-L) or grouped Stratz (-G N); using one worker.\n";
    $fetch_workers = 1;
  } elseif (!function_exists('pcntl_fork')) {
    echo "[W] pcntl_fork unavailable; parallel fetch (-j) disabled.\n";
    $fetch_workers = 1;
  }
}

$ignore_stratz = isset($options['Q']);

$update_names = isset($options['n']);
if ($update_names) $updated_names = [];

$request_unparsed         = isset($options['R']);
$request_unparsed_players = isset($options['p']);
$ignore_abandons          = isset($options['f']);

if (!empty($odapikey) && !isset($ignore_api_key))
  $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $api_cooldown ?? 0, $odapikey);
else
  $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $api_cooldown ?? 0);

if (!empty($options['d'])) {
  $opendota_effective_cooldown_s = (float)$options['d'];
} elseif (!empty($odapikey) && !isset($ignore_api_key)) {
  $opendota_effective_cooldown_s = 0.25;
} else {
  $opendota_effective_cooldown_s = 1.0;
}

if ($conn->connect_error) die("[F] Connection to SQL server failed: " . $conn->connect_error . "\n");

$lrg_input = "matchlists/" . $lrg_league_tag . ".list";

$rnum           = 1;
$matches        = [];
$failed_matches = [];

$scheduled        = [];
$first_scheduled  = [];
$scheduled_stratz = [];

$scheduled_wait_period = (int)($options['w'] ?? 60);
$force_await           = isset($options['A']);

$lp          = array_key_last($meta['patchdates']);
$lastversion = ((int)$lp) * 100 + count($meta['patchdates'][$lp]['dates']);

<?php

$visjs_settings = "physics:{
  barnesHut:{
    avoidOverlap:1,
    centralGravity:0.3,
    springLength:95,
    springConstant:0.005,
    gravitationalConstant:-900
  },
  timestep: 0.1,
}, 

nodes: {
   borderWidth:3,
   shape: 'dot',
   font: {color:'#ccc', background: 'rgba(0,0,0,0.5)',size:12},
   shadow: {
     enabled: true
   },
   scaling:{
     label: {
       min:8, max:20
     }
   }
 }";

$level_codes = array(
  # level => array( class-postfix, class-level )
  0 => array ( "", "higher-level" ),
  1 => array ( "sublevel", "lower-level" ),
  2 => array ( "level-3", "level-3" ),
  3 => array ( "level-4", "level-4" ),
  4 => array ( "level-5", "level-5" ),
  5 => array ( "level-6", "level-6" ),
  6 => array ( "level-7", "level-7" ),
);

$charts_colors = array( "#6af","#f66","#fa6","#66f","#62f","#a6f","#6ff","#6fa","#2f6","#6f2","#ff6","#f22","#f6f","#666", "#46f", "#4f6", "#aa3" );

const SUMMARY_GROUPS = [
  'matches' => '_index',
  'matches_s' => '_index',

  'winrate' => 'performance',
  'winrate_s' => 'performance',

  'rank' => 'performance',
  'antirank' => 'performance',
  'diversity' => 'performance',
  'hero_pool' => 'performance',

  'kills' => 'kda',
  'deaths' => 'kda',
  'assists' => 'kda',
  'kills_s' => 'kda',
  'deaths_s' => 'kda',
  'assists_s' => 'kda',
  'kda' => 'kda',

  'gpm' => 'farm',
  'xpm' => 'farm',
  'lh_at10' => 'farm',
  'lasthits_per_min_s' => 'farm',
  'stacks_s' => 'farm',

  'heal_per_min_s' => 'combat',
  'hero_damage_per_min_s' => 'combat',
  'taken_damage_per_min_s' => 'combat',
  'stuns' => 'combat',

  'wards_placed' => 'vision',
  'sentries_placed' => 'vision',
  'wards_destroyed' => 'vision',
  'wards_lost' => 'vision',

  'tower_damage_per_min_s' => 'objectives,combat',
  'roshan_kills_with_team' => 'objectives',
  'courier_kills' => 'objectives',
  'roshan_kills' => 'objectives',

  'duration' => 'duration',
  'avg_match_len' => 'duration',
  'matches_median_duration' => 'duration',
  'avg_win_len' => 'duration',

  'pings' => 'objectives,performance',

  'rad_ratio' => 'draft',
  'radiant_wr' => 'draft',
  'dire_wr' => 'draft',
  'opener_ratio' => 'draft',
  'opener_pick_winrate' => 'draft',

  'opener_pick_radiant_winrate' => 'fp_draft',
  'opener_pick_dire_winrate' => 'fp_draft',
  'follower_pick_radiant_winrate' => 'fp_draft',
  'follower_pick_dire_winrate' => 'fp_draft',
  'follower_pick_radiant_ratio' => 'fp_draft',
  'opener_pick_radiant_ratio' => 'fp_draft',

  'damage_to_gold_per_min_s' => 'performance,farm,combat',

  'matchlinks' => 'permagroup',
  'common_position' => 'permagroup',
];

const SUMMARY_GROUPS_PRIORITIES = [
  '_index' => 0,
  'performance' => 1,
  'kda' => 2,
  'farm' => 3,
  'combat' => 4,
  'draft' => 5,
  'objectives' => 7,
  'duration' => 7,
  'vision' => 6,
  'permagroup' => 0,
];

const SUMMARY_KEYS_REPLACEMENTS = [
  'diversity' => 'diversity_s',
  'roshan_kills_with_team' => 'roshan_kills_with_team_s',
  'winrate_s' => 'winrate',
  'matches_s' => 'matches',

  "wards_placed" => "wards_placed_s",
  "sentries_placed" => "sentries_placed_s",
  "wards_destroyed" => "wards_destroyed_s",
  "wards_lost" => "wards_lost_s",
  "radiant_wr" => "rad_wr_s",
  "dire_wr" => "dire_wr_s",
  "avg_match_len" => "duration_s",
  "avg_win_len" => "avg_win_len_s",
  "matches_median_duration" => "matches_median_duration_s",
  "opener_ratio" => "opener_ratio_s",
  "opener_pick_winrate" => "opener_pick_winrate_s",
  "duration" => "duration_s",

  "opener_pick_radiant_winrate" => "opener_pick_radiant_winrate_s",
  "opener_pick_dire_winrate" => "opener_pick_dire_winrate_s",
  "follower_pick_radiant_winrate" => "follower_pick_radiant_winrate_s",
  "follower_pick_dire_winrate" => "follower_pick_dire_winrate_s",
  "follower_pick_radiant_ratio" => "follower_pick_radiant_ratio_s",
  "opener_pick_radiant_ratio" => "opener_pick_radiant_ratio_s",
];

const VALUESORT_COLS_KEYS = [
  'duration',
  'avg_match_len',
  'matches_median_duration',
  'avg_win_len',
];

if (isset($__lrg_onerror) && !$isApi) {
  $projectName = $projectName ?? "LRG2";

  set_error_handler(
    function(int $errno, string $errmsg, string $errfile = null, int $errline = null, array $errcontext = []) use ($__lrg_onerror, &$projectName) {
      $__lrg_onerror([
        'type' => 'error',
        'project' => $projectName ?? "LRG2",
        'path' => $_SERVER['REQUEST_URI'] ?? null,
        'message' => $errmsg."::",
        'file' => str_replace(__DIR__, "", $errfile),
        'line' => $errline,
        'severity' => $errno
      ]);
    },
    E_ALL
  );

  set_exception_handler(function(Throwable $e) use ($__lrg_onerror, &$projectName) {
    $__lrg_onerror([
      'type' => 'error',
      'project' => $projectName ?? "LRG2",
      'path' => $_SERVER['REQUEST_URI'] ?? null,
      'message' => $e->getMessage()."::".json_encode($e->getTrace()),
      'file' => str_replace(__DIR__, "", $e->getFile()),
      'line' => $e->getLine(),
      'severity' => E_ERROR | $e->getCode(),
    ]);
  });
}

if (isset($__lrg_onfinish)) {
  register_shutdown_function($__lrg_onfinish);
}
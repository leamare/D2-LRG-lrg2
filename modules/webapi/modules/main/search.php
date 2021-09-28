<?php 

$endpoints['search'] = function($mods, $vars, &$report) use (&$endpoints, $cat, $cats_file, $hidden_cat) {
  $cache = $endpoints['getcache']($mods, $vars, $report);
  $reps = [];

  if (file_exists($cats_file)) {
    $cats = file_get_contents($cats_file);
    $cats = json_decode($cats, true);
  } else {
    $cats = [];
  }

  $searchfilter = create_search_filters($vars['search']);

  $reps = [];
  foreach($cache["reps"] as $tag => $rep) {
    if(check_filters($rep, $searchfilter))
      $reps[$tag] = $rep;
  }

  return [
    "query" => $vars['search'],
    "reports" => $reps
  ];
};

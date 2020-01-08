<?php 

$endpoints['list'] = function($mods, $vars, &$report) use (&$endpoints, $cat, $cats_file, $hidden_cat) {
  $cache = $endpoints['getcache'];
  $reps = [];

  if (file_exists($cats_file)) {
    $cats = file_get_contents($cats_file);
    $cats = json_decode($cats, true);
  } else {
    $cats = [];
  }
  
  if (empty($cat)) $cat = "main";

  if(!empty($cats)) {
    if (isset($cats[$cat])) {
      $reps = [];
      foreach($cache["reps"] as $tag => $rep) {
        if(check_filters($rep, $cats[$cat]['filters']))
          $reps[$tag] = $rep;
      }
    } else if($cat == "main") {
      if(isset($cats[$hidden_cat])) {
        foreach($cache["reps"] as $tag => $rep) {
          if(!check_filters($rep, $cats[$hidden_cat]['filters']))
            $reps[$tag] = $rep;
        }
      } else {
        $reps = $cache["reps"];
      }
    } else if ($cat == "all") {
      $reps = $cache["reps"];
    } else {
      throw new \Exception("No such category.");
    }
  } else {
    $reps = $cache["reps"];
  }

  $cats_list = array_keys($cats);
  $cats_list[] = "all";
  $cats_list[] = "main";

  return [
    "cat_selected" => $cat,
    "cat_list" => $cats_list,
    "reports" => $reps
  ];
};

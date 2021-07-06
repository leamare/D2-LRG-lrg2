<?php 

$endpoints['list'] = function($mods, $vars, &$report) use (&$endpoints, $cat, $cats_file, $hidden_cat) {
  $cache = $endpoints['getcache']($mods, $vars, $report);
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
    } else if ($cat == "recent") {
      $reps = $cache["reps"];
      usort($reps, function($a, $b) {
        return $b['last_update'] - $a['last_update'];
      });
      if ($recent_last_limit ?? false) {
        $limit = null;
        foreach($reps as $k => $v) {
          if ($v['last_update'] < $recent_last_limit) {
            $limit = $k;
            break;
          }
        }
        $reps = array_slice($reps, 0, $limit);
      }
    } else {
      throw new \Exception("No such category.");
    }
  } else {
    $reps = $cache["reps"];
  }

  if(isset($cats[$hidden_cat])) unset($cats[$hidden_cat]);
  $cats_list = array_keys($cats);
  $cats_list[] = "all";
  $cats_list[] = "main";
  $cats_list[] = "recent";

  return [
    "cat_selected" => $cat,
    "cat_list" => $cats_list,
    "reports" => $reps
  ];
};

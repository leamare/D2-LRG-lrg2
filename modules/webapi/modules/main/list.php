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
        if(check_filters($rep, $cats[$cat]['filters'])) {
          if ($cats[$cat]['exclude_hidden'] && isset($cats[$hidden_cat])) {
            if(check_filters($rep, $cats[$hidden_cat]['filters'])) {
              continue;
            }
          }
          $reps[$tag] = $rep;
        }
      }

      if (isset($cats[$cat]['orderby'])) {
        $orderby = $cats[$cat]['orderby'];
        uasort($reps, function($a, $b) use (&$orderby) {
          $res = 0;
          foreach ($orderby as $k => $dir) {
            $res = $dir ? (($b[$k] ?? 0) <=> ($a[$k] ?? 0)) : (($a[$k] ?? 0) <=> ($b[$k] ?? 0));
            if ($res) break;
          }
  
          return $res;
        });
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
      uasort($reps, function($a, $b) {
        $lu = ($b['last_update'] ?? 0) <=> ($a['last_update'] ?? 0);
  
        if ($lu) return $lu;
  
        return ($b['matches'] ?? 0) <=> ($a['matches'] ?? 0);
      });
      if ($recent_last_limit ?? false) {
        $limit = null;
        foreach($reps as $k => $v) {
          if ($v['last_update'] < ($recent_last_limit ?? 5)) {
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
    "count" => count($reps),
    "reports" => $reps
  ];
};

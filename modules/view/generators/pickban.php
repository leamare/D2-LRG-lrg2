<?php
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/ranking.php");

function rg_generator_pickban($table_id, &$context, &$context_main, $heroes_flag = true, $roles = false) {
  if(!sizeof($context)) return "";

  $context_total_matches = $context_main['matches'] ?? $context_main["matches_total"] ?? 0;
  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;

  if (!$mp) {
    uasort($context, function($a, $b) {
      return $a['matches_picked'] <=> $b['matches_picked'];
    });
    $mp = isset($context[ round(sizeof($context)*0.5) ]) ? $context[ round(sizeof($context)*0.5) ]['matches_picked'] : 1;
  }
  if (!$mp) $mp = 1;

  if (!$mb) {
    if ($mp > 1) {
      $mb = 1;
    } else {
      uasort($context, function($a, $b) {
        return $a['matches_banned'] <=> $b['matches_banned'];
      });
      $mb = isset($context[ round(sizeof($context)*0.5) ]) ? $context[ round(sizeof($context)*0.5) ]['matches_banned'] : 1;
    }
  }
  if (!$mb) $mb = 1;

  uasort($context, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $res = "<div class=\"content-text\">".locale_string("heroes_median_picks").
    " (mp): $mp - ".locale_string("heroes_median_bans").
    " (mb): $mb - ".locale_string("matches_total").": $context_total_matches</div>";

  $res .= "<div class=\"content-text\">".locale_string("desc_pickban_ranks")."</div>";

  if ($roles) $res .= "<div class=\"content-text\">".locale_string("desc_pickban_roles")."</div>";

  $res .= "<input name=\"filter\" class=\"search-filter\" data-table-filter-id=\"$table_id\" placeholder=\"".locale_string('filter_placeholder')."\" />";

  $res .=  "<table id=\"$table_id\" class=\"list sortable\"><thead><tr>".
            ($heroes_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
            "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? "hero" : "player")."</th>".
            ($roles ? "<th>".locale_string("position")."</th>" : "").
            "<th>".locale_string("matches_total")."</th>".
            "<th class=\"separator\">".locale_string("contest_rate")."</th>".
            "<th>".locale_string("rank")."</th>".
            "<th>".locale_string("antirank")."</th>".
            "<th class=\"separator\">".locale_string("matches_picked")."</th>".
            // "<th>".locale_string("pickrate")."</th>".
            "<th>".locale_string("winrate")."</th>".
            "<th>".locale_string("mp")."</th>".
            "<th class=\"separator\">".locale_string("matches_banned")."</th>".
            // "<th>".locale_string("banrate")."</th>".
            "<th>".locale_string("winrate")."</th>".
            "<th>".locale_string("mb")."</th>".
            "</tr></thead>";

  $ranks = [];
  $antiranks = [];
  $context_copy = $context;

  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);
  unset($context_copy);

  $context_copy = $context;
  foreach($context_copy as &$el)  {
    $el['winrate_picked'] = 1-$el['winrate_picked'];
    $el['winrate_banned'] = 1-$el['winrate_banned'];
  }

  uasort($context_copy, $compound_ranking_sort);

  $increment = 100 / sizeof($context_copy); $i = 0;

  foreach ($context_copy as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $antiranks[$id] = $last_rank;
    } else
      $antiranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $antiranks[$id];
  }
  unset($last);
  unset($context_copy);

  foreach($context as $id => $el) {
    $_id = $id;
    if ($roles) {
      [ $id, $role ] = explode('|', $id);
    }
    $res .=  "<tr>".
            ($heroes_flag ? "<td>".hero_portrait($id)."</td><td>".hero_name($id)."</td>" : "<td>".player_name($id)."</td>").
            ($roles ? "<td>".locale_string("position_$role")."</td>" : "").
            "<td>".$el['matches_total']."</td>".
            "<td class=\"separator\">".number_format($el['matches_total']/$context_total_matches*100,2)."%</td>".
            "<td>".number_format($ranks[$_id],2)."</td>".
            "<td>".number_format($antiranks[$_id],2)."</td>".
            "<td class=\"separator\">".$el['matches_picked']."</td>".
            // "<td>".number_format($el['matches_picked']/$context_total_matches*100,2)."%</td>".
            "<td>".number_format($el['winrate_picked']*100,2)."%</td>".
            "<td>".number_format($el['matches_picked']/$mp, 1)."</td>".
            "<td class=\"separator\">".$el['matches_banned']."</td>".
            // "<td>".number_format($el['matches_banned']/$context_total_matches*100,2)."%</td>".
            "<td>".number_format($el['winrate_banned']*100,2)."%</td>".
            "<td>".number_format($el['matches_banned']/$mb, 1)."</td>".
            "</tr>";
  }
  unset($oi);
  $res .= "</table>";

  return $res;
}

function rg_generator_uncontested($context, $contested, $small = false, $heroes_flag = true) {
  global $portraits_provider;
  if ($heroes_flag) {
    // uncontested
    // unpicked : matches_picked 0
    // least contested : uasort, get the last 10%
    if (sizeof($contested) == sizeof($context)) {
      $context_tmp = [];
      foreach ($contested as $tid => $v) {
        if ($v['matches_picked'] == 0)
          $context_tmp[$tid] = $context[$tid];
      }

      if (sizeof($context_tmp) == 0) {
        $loc = "least_contested";
        $sz = floor(sizeof($contested) * 0.1);
        uasort($contested, function($a, $b) {
          if ($a['matches_total'] == $b['matches_total']) return 0;
          return $a['matches_total'] < $b['matches_total'] ? -1 : 1;
        });
        $i = 0;
        foreach ($contested as $tid => $v) {
          $context_tmp[$tid] = $context[$tid];
          $i++;
          if ($i == $sz) break;
        }
      } else {
        $loc = "heroes_unpicked";
      }
      $context = $context_tmp;
    } else {
      foreach($contested as $id => $el) {
        unset($context[$id]);
      }
      $loc = "heroes_uncontested";
    }
  } else {
    foreach($contested as $id => $el) {
      unset($context[$id]);
    }
    $loc = "players_uncontested";
  }

  $res = "";
  if(sizeof($context)) {
    $res .= "<div class=\"content-text ".($small ? "small-heroes" : "")."\"><h1>".
      locale_string($loc).
      ": ".sizeof($context)."</h1><div class=\"hero-list\">";

    if ($heroes_flag)
      foreach($context as $el) {
        $res .= "<div class=\"hero\"><img src=\"".str_replace("%HERO%", $el['tag'], $portraits_provider)."\" alt=\"".$el['tag']."\" />".
                "<span class=\"hero_name\">".$el['name']."</span></div>";
      }
    else
      foreach($context as $el) {
        $res .= "<div class=\"hero\"><span class=\"hero_name\">".$el['name']."</span></div>";
      }
    $res .= "</div></div>";
  }

  return $res;
}

?>

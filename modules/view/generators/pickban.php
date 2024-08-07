<?php
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/ranking.php");
include_once($root."/modules/view/functions/teams_diversity_recalc.php");
include_once($root."/modules/view/generators/draft.php");

function rg_generator_balance($table_id, $context) {
  $vals = balance_rank($context);

  return "<table id=\"$table_id\" class=\"list\">".
    "<thead><tr>".
      "<th>".locale_string("balance_total")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("pickrate")."</th>".
      "<th>".locale_string("contest_rate")."</th>".
    "</tr></thead>".
    "<tbody><tr>".
      "<td>".number_format($vals[0]*100,1)."</td>".
      "<td>".number_format($vals[1]*100,1)."</td>".
      "<td>".number_format($vals[2]*100,1)."</td>".
      "<td>".number_format($vals[3]*100,1)."</td>".
    "</tr></tbody></table>";
}

function rg_generator_pickban($table_id, &$context, &$context_main, $heroes_flag = true, $roles = false, $facets = false) {
  if(!sizeof($context)) return "";

  $context_total_matches = $context_main['matches'] ?? $context_main["matches_total"] ?? 1;
  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;
  if (is_array($mb)) $mb = null;

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

  $keys = array_keys(reset($context));
  $is_ratio = in_array('ratio', $keys);

  $res = "<div class=\"content-text\">".locale_string("heroes_median_picks").
    " (mp): $mp - ".locale_string("heroes_median_bans").
    " (mb): $mb - ".locale_string("matches_total").": $context_total_matches</div>";

  $res .= rg_pbdraft_accuracy_test($context);

  $res .= filter_toggles_component($table_id, [
    'mp' => [
      'value' => '0.9',
      'label' => 'data_filter_low_values_mp'
    ],
    'mb' => [
      'value' => '0.9',
      'label' => 'data_filter_low_values_mb'
    ]
  ], $table_id);

  $res .= rg_generator_balance("$table_id-balance", $context);

  $res .= search_filter_component($table_id);

  $res .=  "<table id=\"$table_id\" class=\"list sortable\"><thead><tr>".
    ($heroes_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
    ($facets ? "<th></th>" : ""). // ".locale_string("facet")."
    "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? "hero" : "player")."</th>".
    ($roles ? "<th>".locale_string("position")."</th>" : "").
    "<th>".locale_string("matches_total")."</th>".
    "<th class=\"separator\">".locale_string("contest_rate")."</th>".
    "<th>".locale_string("rank")."</th>".
    "<th>".locale_string("antirank")."</th>".
    "<th class=\"separator\">".locale_string("matches_picked")."</th>".
    // "<th>".locale_string("pickrate")."</th>".
    ($is_ratio ? "<th>".locale_string("ratio")."</th>" : "").
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

  compound_ranking($context, $context_total_matches);

  $context_copy = $context;

  uasort($context, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context)['wrank'];
  $max = reset($context)['wrank'];

  foreach ($context as $id => $el) {
    $ranks[$id] = 100 * ($el['wrank']-$min) / ($max-$min);

    $context_copy[$id]['winrate_picked'] = 1-$el['winrate_picked'];
    $context_copy[$id]['winrate_banned'] = 1-$el['winrate_banned'];
  }

  compound_ranking($context_copy, $context_total_matches);

  uasort($context_copy, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context_copy)['wrank'];
  $max = reset($context_copy)['wrank'];

  foreach ($context_copy as $id => $el) {
    $antiranks[$id] = 100 * ($el['wrank']-$min) / ($max-$min);
  }

  unset($context_copy);

  foreach($context as $id => $el) {
    $_id = $id;
    if ($roles) {
      [ $id, $role ] = explode('|', $id);
    }
    if ($facets) {
      [ $id, $v ] = explode('-', $id);
    }
    $el_mp = number_format($el['matches_picked']/$mp, 1);
    $el_mb = number_format((int)$el['matches_banned']/(int)$mb, 1);
    $res .=  "<tr data-value-mp=\"$el_mp\" data-value-mb=\"$el_mb\">".
            ($heroes_flag ? 
              "<td>".hero_portrait($id)."</td>".
                ($facets ? "<td>".facet_micro_element($id, $v)."</td>" : "").
                "<td>".hero_link($id).($facets && $v ? " ".locale_string('facet_short').$v : "")."</td>" : 
              "<td>".player_link($id)."</td>"
            ).
            ($roles ? "<td>".locale_string("position_$role")."</td>" : "").
            "<td>".$el['matches_total']."</td>".
            "<td class=\"separator\">".number_format($el['matches_total']/$context_total_matches*100,2)."%</td>".
            "<td>".number_format($ranks[$_id],2)."</td>".
            "<td>".number_format($antiranks[$_id],2)."</td>".
            "<td class=\"separator\">".$el['matches_picked']."</td>".
            // "<td>".number_format($el['matches_picked']/$context_total_matches*100,2)."%</td>".
            ($is_ratio ? "<td>".number_format($el['ratio']*100, 2)."%</td>" : "").
            "<td>".number_format($el['winrate_picked']*100,2)."%</td>".
            "<td>".$el_mp."</td>".
            "<td class=\"separator\">".$el['matches_banned']."</td>".
            // "<td>".number_format($el['matches_banned']/$context_total_matches*100,2)."%</td>".
            "<td>".number_format($el['winrate_banned']*100,2)."%</td>".
            "<td>".$el_mb."</td>".
            "</tr>";
  }
  
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
          $context_tmp[$tid] = $context[$tid] ?? [];
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

    usort($context, function($a, $b) {
      return $a['name'] <=> $b['name'];
    });
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

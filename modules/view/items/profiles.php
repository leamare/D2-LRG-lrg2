<?php

$modules['items']['profiles'] = [];

function rg_view_generate_items_profiles() {
  global $report, $leaguetag, $parent, $root, $unset_module, $mod, $meta, $strings, $item_profile_icons_provider, $item_icons_provider;

  if($mod == $parent."profiles") $unset_module = true;
  $parent_module = $parent."profiles-";
  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  $item_ids = array_keys($report['items']['stats']['total']);
  $item_names = [];

  foreach ($item_ids as $item) {
    $item_names[ $item ] = [
      'name' => $meta['items_full'][$item]['localized_name'],
      'tag' => $meta['items_full'][$item]['name']
    ];
    $strings['en']["itemid".$item] = item_name($item);
  }

  uasort($item_names, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  foreach($item_names as $iid => $name) {
    $res["itemid".$iid] = "";

    if(check_module($parent_module."itemid".$iid)) {
      $item = $iid;
    }
  }

  // total aka reference table data

  // RANKING FOR REFERENCE TABLE

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($report['items']['stats']['total'], $ranking_sort);

  $increment = 100 / sizeof($report['items']['stats']['total']); $i = 0;

  foreach ($report['items']['stats']['total'] as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

  // REFERENCE TABLE

  $data = $report['items']['stats']['total'][$item];
  $data['rank'] = $ranks[$item];
  if ($data['grad'] < 0) {
    $data['critical'] = $data['q1'] - 60*($data['early_wr'] - 0.5)/$data['grad'];
  }

  // heroes with most purchases

  unset($report['items']['stats']['total']);
  $heroes = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    if (!empty($items[$item]))
      $heroes[$hero] = $items[$item];
  }

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($heroes, $ranking_sort);

  $increment = 100 / sizeof($heroes); $i = 0;

  foreach ($heroes as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

  foreach ($heroes as $id => $line) {
    $heroes[$id]['rank'] = $ranks[$id];
    $heroes[$id]['id'] = $id;
  }

  uasort($heroes, function($a, $b) {
    return $b['prate'] <=> $a['prate'];
  });

  $heroes_purchases = array_slice($heroes, 0, 10);

  $heroes = array_filter($heroes, function($a) {
    return $a['prate'] > 0.01;
  });

  // best ranked heroes

  uasort($heroes, function($a, $b) {
    return $b['rank'] <=> $a['rank'];
  });

  $heroes_rank_top = array_slice($heroes, 0, 10);

  // worst ranked purchases

  uasort($heroes, function($a, $b) {
    return $a['rank'] <=> $b['rank'];
  });

  $heroes_rank_bot = array_slice($heroes, 0, 10);

  // best records

  if (isset($report['items']['records'])) {
    $records = [];

    if (is_wrapped($report['items']['records'])) {
      $report['items']['records'] = unwrap_data($report['items']['records']);
    }

    if (!empty($report['items']['records'][$item])) {
      foreach ($report['items']['records'][$item] as $hero => $line) {
        if (empty($line) || empty($line['match'])) continue;
        $line['hero'] = $hero;
        $records[] = $line;
      }
    }

    uasort($records, function($a, $b) {
      return $b['diff'] <=> $a['diff'];
    });
  
    $records_best = array_slice($records, 0, 10);
  }

  // records link
  // 
  
  $res['itemid'.$item] .= "<div class=\"profile-header\">".
    "<div class=\"profile-image\"><img src=\"".str_replace("%HERO%", item_tag($item), $item_profile_icons_provider ?? $item_icons_provider ?? "")."\" /></div>".
    "<div class=\"profile-name\">".item_name($item)."</div>".
    "<div class=\"profile-content\">".
      "<div class=\"profile-stats\">".
        "<div class=\"profile-statline\"><label>".locale_string("rank")."</label>: ".number_format($data['rank'], 2)."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("purchases")."</label>: ".$data['purchases']."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("purchase_rate")."</label>: ".number_format($data['prate']*100, 2)."%</div>".
      "</div>".
      "<div class=\"profile-stats\">".
        "<div class=\"profile-statline\"><label>".locale_string("item_time_median_long")."</label>: ".convert_time_seconds($data['median'])."</div>".
        "<div class=\"profile-statline\"><label>".locale_string("items_wr_gradient")."</label>: ".number_format($data['grad']*100, 2)."%</div>".
        (
          $data['grad'] < -0.01 ? 
          "<div class=\"profile-statline\"><label>".locale_string("item_time_window")."</label>: ".convert_time_seconds($data['q1']).
            " - ".convert_time_seconds($data['critical'])." (".convert_time_seconds($data['critical']-$data['q1']).")</div>" : 
          "<div class=\"profile-statline\"><label>".locale_string("item_time_window_long")."</label>: ".convert_time_seconds($data['q1']).
            " - ".convert_time_seconds($data['q3'])."</div>"
        ).
      "</div>".
      "<div class=\"profile-stats\">".
        "<div class=\"profile-statline\"><label>".locale_string("winrate")."</label>: ".number_format($data['winrate']*100, 2)."%</div>".
        "<div class=\"profile-statline\"><label>".locale_string("items_winrate_increase")."</label>: ".
          ($data['wo_wr'] < $data['winrate'] ? '+' : '').number_format(($data['winrate'] - $data['wo_wr'])*100, 2)."%</div>".
        "<div class=\"profile-statline\"><label>".locale_string("items_early_wr_long")."</label>: ".
          number_format($data['early_wr']*100, 2)."% (".
          ($data['early_wr'] > $data['winrate'] ? '+' : '').number_format(($data['early_wr'] - $data['winrate'])*100, 2).
          "%)</div>".
        "<div class=\"profile-statline\"><label>".locale_string("items_late_wr_long")."</label>: ".
          number_format($data['late_wr']*100, 2)."% (".
          ($data['late_wr'] > $data['winrate'] ? '+' : '').number_format(($data['late_wr'] - $data['winrate'])*100, 2).
          "%)</div>".
      "</div>".
    "</div>".
    "<div class=\"profile-content\">";
  // "</div>";

  $res['itemid'.$item] .= "<div class=\"profile-stats\"><div class=\"profile-stats-header\">".locale_string('item_profile_heroes_purchases')."</div><div class=\"profile-stats-icons\">";
  foreach ($heroes_purchases as $line) {
    $res['itemid'.$item] .= hero_icon($line['id']);
  }
  $res['itemid'.$item] .= "</div></div>";

  $res['itemid'.$item] .= "<div class=\"profile-stats\"><div class=\"profile-stats-header\">".locale_string('item_profile_heroes_best_rank')."</div><div class=\"profile-stats-icons\">";
  foreach ($heroes_rank_top as $line) {
    $res['itemid'.$item] .= hero_icon($line['id']);
  }
  $res['itemid'.$item] .= "</div></div>";

  $res['itemid'.$item] .= "<div class=\"profile-stats\"><div class=\"profile-stats-header\">".locale_string('item_profile_heroes_worst_rank')."</div><div class=\"profile-stats-icons\">";
  foreach ($heroes_rank_bot as $line) {
    $res['itemid'.$item] .= hero_icon($line['id']);
  }
  $res['itemid'.$item] .= "</div></div>";

  $res['itemid'.$item] .= "</div></div>";

  $res['itemid'.$item] .= "<div class=\"content-text\">".
    "<a href=\"?league=$leaguetag&mod=items-heroes-itemid$item".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_heroes_full")."</a>".
  "</div>";

  if (!empty($records)) {
    $res['itemid'.$item] .= "<div class=\"content-text\"><h1>".locale_string("items_overview_timings_header")."</h1></div>";

    $res['itemid'.$item] .= "<table id=\"items-records-itemid$item\" class=\"list\"><thead>
      <tr>
        <th>".locale_string("match")."</th>
        <th colspan=\"2\">".locale_string("hero")."</th>
        <th>".locale_string("item_timing")."</th>
        <th>".locale_string("item_timing_diff")."</th>
      </tr></thead></tbody>";

    foreach ($records_best as $hero => $line) {
      $res['itemid'.$item] .=  "<tr><td>".match_link($line['match'])."</td>".
        "<td>".hero_portrait($line['hero'])."</td>".
        "<td>".hero_name($line['hero'])."</td>".
        "<td data-sorter=\"time\">".convert_time_seconds($line['time'])."</td>".
        "<td data-sorter=\"time\">".convert_time_seconds($line['diff'])."</td>".
      "</tr>";
    }

    $res['itemid'.$item] .= "</tbody></table>";

    $res['itemid'.$item] .= "<div class=\"content-text\">".
      "<a href=\"?league=$leaguetag&mod=items-irecords-itemid$iid".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_records_full")."</a>".
    "</div>";
  }

  return $res;
}


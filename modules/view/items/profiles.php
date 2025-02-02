<?php

$modules['items']['profiles'] = [];

function rg_view_generate_items_profiles() {
  global $report, $leaguetag, $parent, $root, $unset_module, $mod, $meta, $strings, $item_profile_icons_provider, $item_icons_provider;

  if($mod == $parent."profiles") $unset_module = true;
  $parent_module = $parent."profiles-";
  $res = [];
  $starting_iids = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  // Get starting items IDs
  if (isset($report['starting_items']['items'][0][0]['keys'])) {
    $starting_iids = array_unique(
      array_map(function($a) {
        return floor($a/100);
      }, $report['starting_items']['items'][0][0]['keys'])
    );
  }

  // Get consumables IDs
  $consumable_iids = [];
  if (isset($report['starting_items']['consumables'])) {
    $consumable_iids = $report['starting_items']['consumables']['all'][0][0]['keys'];
  }

  // Combine all item IDs
  $item_ids = array_unique(array_merge(
    array_keys($report['items']['stats']['total']), 
    $starting_iids,
    $consumable_iids
  ));

  $item_names = [];

  foreach ($item_ids as $item) {
    $item_names[$item] = [
      'name' => $meta['items_full'][$item]['localized_name'],
      'tag' => $meta['items_full'][$item]['name'],
      'category' => null
    ];

    // Determine item category
    foreach ($meta['item_categories'] as $cat => $items) {
      if (in_array($item, $items)) {
        $item_names[$item]['category'] = $cat;
        break;
      }
    }

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

  $is_regular = in_array($item, array_keys($report['items']['stats']['total']));
  $is_starting = in_array($item, $starting_iids);
  $is_consumable = isset($report['starting_items']['consumables'])
    && in_array($item, $report['starting_items']['consumables']['all'][0][0]['keys']);

  if ($is_regular) {
    $ranks = [];

    $ranking_sort = function($a, $b) {
      return items_ranking_sort($a, $b);
    };

    uasort($report['items']['stats']['total'], $ranking_sort);

    $increment = 100 / sizeof($report['items']['stats']['total']); $i = 0;

    foreach ($report['items']['stats']['total'] as $id => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $ranks[$id] = $last_rank ?? 0;
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
    $min_prate = $heroes[ array_keys($heroes)[floor(count($heroes)*0.75)] ]['prate'];

    $heroes_purchases = array_slice($heroes, 0, 8);

    $heroes = array_filter($heroes, function($a) use ($min_prate) {
      return $a['prate'] > $min_prate;
    });

    $group_size = min(floor(count($heroes)/2), 8);

    // best ranked heroes

    uasort($heroes, function($a, $b) {
      return $b['rank'] <=> $a['rank'];
    });

    $heroes_rank_top = array_slice($heroes, 0, $group_size);

    // worst ranked purchases

    uasort($heroes, function($a, $b) {
      return $a['rank'] <=> $b['rank'];
    });

    $heroes_rank_bot = array_slice($heroes, 0, $group_size);

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
    
      $records_best = array_slice($records, 0, 8);
    }
  }

  if ($is_starting) {
    $sti_data = $report['starting_items']['items'][0][0];
    $sti_data['head'] = $report['starting_items']['items_head'];
    $sti_data = unwrap_data($sti_data);
    $report['starting_items']['matches'][0] = unwrap_data($report['starting_items']['matches'][0]);

    $item_sti = $sti_data[$item * 100 + 1];

    $heroes_sti = [];

    foreach ($report['starting_items']['items'][0] as $hero => $line) {
      if (!$hero) continue;
      $id = array_search($item * 100 + 1, $line['keys']);
      if ($id === false) continue;
      if (isset($line['data'][$id])) {
        $heroes_sti[$hero] = array_combine($report['starting_items']['items_head'], $line['data'][$id]);
      }
    }

    uasort($heroes_sti, function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    $heroes_sti_most_matches = array_slice(array_keys($heroes_sti), 0, 8);

    uasort($heroes_sti, function($a, $b) {
      return ($b['wins'] * 100 / $b['matches']) <=> ($a['wins'] * 100 / $a['matches']);
    });

    $heroes_sti_winrate = array_slice(array_keys($heroes_sti), 0, 8);
  }

  if ($is_consumable) {
    $rid_ref = 0;
    $hid_ref = 0;

    $cons_data = [
      '5m' => null,
      '10m' => null,
      'all' => null,
    ];

    $cons_hero_uses = [];
    $cons_role_uses = [];
  
    foreach ($cons_data as $blk => $d) {
      $cons_hero_uses[$blk] = [];
      $cons_role_uses[$blk] = [];
      if (empty($report['starting_items']['consumables'][$blk][$rid_ref][$hid_ref])) {
        $cons_data[$blk] = array_combine($report['starting_items']['cons_head'], array_fill(0, count($report['starting_items']['cons_head']), 0));
        continue;
      }
      $report['starting_items']['consumables'][$blk][$rid_ref][$hid_ref]['head'] = $report['starting_items']['cons_head'];
      $cons_data[$blk] = unwrap_data($report['starting_items']['consumables'][$blk][$rid_ref][$hid_ref]);

      foreach ($report['starting_items']['consumables'][$blk][$rid_ref] as $hid => $line) {
        $index = array_search($item, $line['keys']);
        if ($index === false) continue;

        $cons_hero_uses[$blk][$hid] = array_combine($report['starting_items']['cons_head'], $line['data'][$index]);
        $cons_hero_uses[$blk][$hid]['mean'] = $cons_hero_uses[$blk][$hid]['total'] / $cons_hero_uses[$blk][$hid]['matches'];
      }

      foreach ($report['starting_items']['consumables'][$blk] as $rid => &$heroes) {
        $index = array_search($item, $heroes[0]['keys']);
        if ($index === false) continue;

        $cons_role_uses[$blk][$rid] = array_combine($report['starting_items']['cons_head'], $heroes[0]['data'][$index]);
        $cons_role_uses[$blk][$rid]['mean'] = $cons_role_uses[$blk][$rid]['total'] / $cons_role_uses[$blk][$rid]['matches'];
      }
    }

    $blk = '10m';

    uasort($cons_hero_uses[$blk], function($a, $b) {
      return $b['mean'] <=> $a['mean'];
    });

    uasort($cons_role_uses[$blk], function($a, $b) {
      return $b['mean'] <=> $a['mean'];
    });

    $cons_hero_uses_highest_mean = array_slice(array_keys($cons_hero_uses[$blk]), 0, 8);
    $cons_role_most_uses = array_keys($cons_role_uses[$blk])[0];

    uasort($cons_hero_uses[$blk], function($a, $b) {
      return $a['mean'] <=> $b['mean'];
    });

    $cons_hero_uses_lowest_mean = array_slice(array_keys($cons_hero_uses[$blk]), 0, 8);
  }

  $res['itemid'.$item] .= "<div class=\"profile-header\">".
    "<div class=\"profile-image\">".
      "<img src=\"".str_replace("%HERO%", item_tag($item), $item_profile_icons_provider ?? $item_icons_provider ?? "")."\" />".
    "</div>".
    "<div class=\"profile-name\">".item_name($item)."</div>".
    "<div class=\"profile-name subheader\">".locale_string("items_category_".$item_names[$item]['category'])."</div>";
 
  $content_lines = [];
  if ($is_regular) {
    $content_lines[] = [ 'rank', number_format($data['rank'], 2) ];
    $content_lines[] = [ 'purchases', $data['purchases'] ];
    $content_lines[] = [ 'purchase_rate', number_format($data['prate']*100, 2)."%" ];
    $content_lines[] = [ 'item_time_median_long', convert_time_seconds($data['median']) ];
    $content_lines[] = [ 'items_wr_gradient', number_format($data['grad']*100, 2)."%" ];
    if ($data['grad'] < -0.01) {
      $content_lines[] = [ 'item_time_window', convert_time_seconds($data['q1']).
        " - ".convert_time_seconds($data['critical'])." (".convert_time_seconds($data['critical']-$data['q1']).")" ];
    } else {
      $content_lines[] = [ 'item_time_window_long', convert_time_seconds($data['q1']).
        " - ".convert_time_seconds($data['q3'])." (".convert_time_seconds($data['q3']-$data['q1']).")" ];
    }
    $content_lines[] = [ 'winrate', number_format($data['winrate']*100, 2)."%" ];
    $content_lines[] = [ 'items_winrate_increase', ($data['wo_wr'] < $data['winrate'] ? '+' : '').number_format(($data['winrate'] - $data['wo_wr'])*100, 2)."%" ];
    $content_lines[] = [ 'items_early_wr_long', number_format($data['early_wr']*100, 2)."% (".
      ($data['early_wr'] > $data['winrate'] ? '+' : '').number_format(($data['early_wr'] - $data['winrate'])*100, 2).
      "%)" ];
    $content_lines[] = [ 'items_late_wr_long', number_format($data['late_wr']*100, 2)."% (".
      ($data['late_wr'] > $data['winrate'] ? '+' : '').number_format(($data['late_wr'] - $data['winrate'])*100, 2).
      "%)" ];
  }

  if ($is_starting) {
    $content_lines[] = [ 'matches_sti', $item_sti['matches'] ];
    $content_lines[] = [
      $is_regular ? 'purchase_rate_sti' : 'purchase_rate',
      number_format($item_sti['freq'] * 100, 2)."%"
    ];
    $content_lines[] = [
      $is_regular ? 'winrate_sti' : 'winrate',
      +$item_sti['matches'] ? number_format($item_sti['wins'] * 100 / $item_sti['matches'], 2)."%" : '-'
    ];
    $content_lines[] = [
      $is_regular ? 'lane_wr_sti' : 'lane_wr',
      +$item_sti['matches'] ? number_format($item_sti['lane_wins'] * 100 / $item_sti['matches'], 2)."%" : '-'
    ];
  }

  if ($is_consumable) {
    $content_lines[] = [ 'matches_cons_all', $cons_data['all'][$item]['matches'] ];
    $content_lines[] = [ 'median_cons_all', $cons_data['5m'][$item]['med'] ];
    $content_lines[] = [ 'q1_cons_all', $cons_data['5m'][$item]['q1'] ];
    $content_lines[] = [ 'q3_cons_all', $cons_data['5m'][$item]['q3'] ];
  }

  $res['itemid'.$item] .= "<div class=\"profile-content\">";

  $group_size = ceil(count($content_lines)/3);

  foreach ($content_lines as $i => $line) {
    if (!$i || $i % $group_size == 0) {
      $res['itemid'.$item] .= "<div class=\"profile-stats\">";
    }
    $res['itemid'.$item] .= "<div class=\"profile-statline\"><label>".locale_string($line[0])."</label>: ".$line[1]."</div>";
    $i++;
    if ($i % $group_size == 0) {
      $res['itemid'.$item] .= "</div>";
    }
  }
  if (!$group_size ||$i % $group_size != 0)
    $res['itemid'.$item] .= "</div>";

  $res['itemid'.$item] .= "</div>";

  if ($is_regular) {
    $res['itemid'.$item] .= "<div class=\"profile-content\">";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_purchases')."</div>".
      "<div class=\"profile-stats-icons\">";
    foreach ($heroes_purchases as $line) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-stats-heroid{$line['id']}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-heroid{$line['id']}=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($line['id']).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div>";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_best_rank')."</div>".
      "<div class=\"profile-stats-icons\">";
    foreach ($heroes_rank_top as $line) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-stats-heroid{$line['id']}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-heroid{$line['id']}=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($line['id']).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div>";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_worst_rank')."</div>".
      "<div class=\"profile-stats-icons\">";
    foreach ($heroes_rank_bot as $line) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-stats-heroid{$line['id']}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-heroid{$line['id']}=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($line['id']).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div></div>";

    $res['itemid'.$item] .= "<div class=\"content-text\">".
      "<a href=\"?league=$leaguetag&mod=items-heroes-itemid$item".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_heroes_full")."</a>".
    "</div>";
  }

  if ($is_starting) {
    $res['itemid'.$item] .= "<div class=\"profile-content\">";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_sti_matches')."</div>".
      "<div class=\"profile-stats-icons\">";
    foreach ($heroes_sti_most_matches as $hid) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-stitems-heroid{$hid}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-stitems-heroid{$hid}-reference=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($hid).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div>";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_sti_winrate')."</div>".
      "<div class=\"profile-stats-icons\">";
    foreach ($heroes_sti_winrate as $hid) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-stitems-heroid{$hid}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-stitems-heroid{$hid}-reference=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($hid).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div></div>";

    $res['itemid'.$item] .= "<div class=\"content-text\">".
      "<a href=\"?league=$leaguetag&mod=items-stitems".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_sti_full")."</a>".
    "</div>";
  }

  if ($is_consumable) {
    $res['itemid'.$item] .= "<div class=\"profile-content\">";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_consumable_mean')."</div>".
      "<div class=\"profile-stats-icons\">";

    foreach ($cons_hero_uses_highest_mean as $hid) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-sticonsumables-heroid{$hid}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-sticonsumables-heroid{$hid}=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($hid).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div>";

    $res['itemid'.$item] .= "<div class=\"profile-stats\">".
      "<div class=\"profile-stats-header\">".locale_string('item_profile_heroes_consumable_lowest_mean')."</div>".
      "<div class=\"profile-stats-icons\">";
    foreach ($cons_hero_uses_lowest_mean as $hid) {
      $res['itemid'.$item] .= "<a href=\"?league=$leaguetag&mod=items-sticonsumables-heroid{$hid}".
        (empty($linkvars) ? "" : "&".$linkvars).
        "#sf-items-sticonsumables-heroid{$hid}=".rawurlencode($meta['items_full'][$item]['localized_name']).
        "\">".
        hero_icon($hid).
      "</a>";
    }
    $res['itemid'.$item] .= "</div></div></div>";

    $res['itemid'.$item] .= "<div class=\"content-text\">".
      "<a href=\"?league=$leaguetag&mod=items-sticonsumables".(empty($linkvars) ? "" : "&".$linkvars)."\">".
      locale_string("items_consumables_full")."</a>".
    "</div>";
  }

  $res['itemid'.$item] .= "</div>";

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
        "<td>".hero_link($line['hero'])."</td>".
        "<td data-sorter=\"time\">".convert_time_seconds($line['time'])."</td>".
        "<td data-sorter=\"time\">".convert_time_seconds($line['diff'])."</td>".
      "</tr>";
    }

    $res['itemid'.$item] .= "</tbody></table>";

    $res['itemid'.$item] .= "<div class=\"content-text\">".
      "<a href=\"?league=$leaguetag&mod=items-irecords-itemid$iid".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("items_records_full")."</a>".
    "</div>";
  }

  if ($is_starting) {
    $variants = [];

    foreach ($report['starting_items']['items'][0][0]['keys'] as $i => $item_sti) {
      if (floor($item_sti / 100) != $item) continue;
      $variants[$item_sti] = array_combine($report['starting_items']['items_head'], $report['starting_items']['items'][0][0]['data'][$i]);
    }

    $res['itemid'.$item] .= "<div class=\"content-text\"><h1>".locale_string("items_overview_sti_variants_header")."</h1></div>";

    $res['itemid'.$item] .= "<table id=\"items-sti-variants-itemid$item\" class=\"list sortable\">
      <thead>
        <tr>
          <th>".locale_string("count")."</th>
          <th>".locale_string("matches")."</th>
          <th>".locale_string("purchase_rate")."</th>
          <th>".locale_string("winrate")."</th>
          <th>".locale_string("lane_wr")."</th>
        </tr>
      </thead>
      <tbody>";

    foreach ($variants as $i => $line) {
      $res['itemid'.$item] .= "<tr><td>".($i % 100)."</td>".implode('', array_map(function($el) {
        return "<td>".$el."</td>";
      }, $line))."</tr>";
    }

    $res['itemid'.$item] .= "</tbody></table>";

    if (isset($report['starting_items']['builds'])) {
      $builds = [];

      foreach ($report['starting_items']['builds'][0]['data'][0] as $build) {
        $build = array_combine($report['starting_items']['builds'][0]['head'][1], $build);
        if (!in_array($item, $build['build'])) continue;
        $builds[] = $build;
      }

      $res['itemid'.$item] .= "<div class=\"content-text\"><h1>".locale_string("items_overview_sti_builds_featured_header")."</h1></div>";

      if (!empty($builds)) {
        $res['itemid'.$item] .= "<table id=\"items-sti-builds-itemid$item\" class=\"list sortable\">
            <thead>
              <tr>
              <th>".locale_string("build")."</th>
              <th>".locale_string("matches")."</th>
              <th>".locale_string("ratio")."</th>
              <th>".locale_string("winrate")."</th>
              <th>".locale_string("lane_wr")."</th>
            </tr>
          </thead>
          <tbody>";

        foreach ($builds as $build) {
          $res['itemid'.$item] .= "<tr><td>".implode(" ", array_map(function($el) {
              return "<a title=\"".item_name($el)."\">".item_icon($el, "bigger")."</a>";
            }, $build['build']))."</td>".
            "<td>".$build['matches']."</td>".
            "<td>".$build['ratio']."</td>".
            "<td>".$build['winrate']."</td>".
            "<td>".$build['lane_wr']."</td>".
          "</tr>";
        }

        $res['itemid'.$item] .= "</tbody></table>";
      } else {
        $res['itemid'.$item] .= "<div class=\"content-text\">".
          locale_string("items_overview_sti_builds_no_builds").
        "</div>";
      }
    }

  }

  if ($is_consumable) {
    $res['itemid'.$item] .= "<div class=\"content-text\"><h1>".locale_string("items_overview_consumables_blocks_all_header")."</h1></div>";

    $res['itemid'.$item] .= "<table id=\"items-consumables-itemid$item\" class=\"list sortable\">
      <thead>
        <tr>
          <th>".locale_string("sti_cons_block")."</th>
          <th>".locale_string("matches")."</th>
          <th>".locale_string("item_time_mean")."</th>
          <th>".locale_string("min")."</th>
          <th>".locale_string("item_time_q1")."</th>
          <th>".locale_string("median")."</th>
          <th>".locale_string("item_time_q3")."</th>
          <th>".locale_string("max")."</th>
        </tr>
      </thead>
      <tbody>";
    
    foreach ($cons_data as $blk => $d) {
      $d = $d[$item];
      if (empty($d)) {
        $d = [
          'matches' => 0,
          'mean' => 0,
          'min' => 0,
          'q1' => 0,
          'med' => 0,
          'q3' => 0,
          'max' => 0,
        ];
      } else {
        $d['mean'] = round($d['total'] / $d['matches'], 1);
      }

      $res['itemid'.$item] .= "<tr><td>".locale_string("sti_consumables_$blk")."</td>".
        "<td>".$d['matches']."</td>".
        "<td>".$d['mean']."</td>".
        "<td>".$d['min']."</td>".
        "<td>".$d['q1']."</td>".
        "<td>".$d['med']."</td>".
        "<td>".$d['q3']."</td>".
        "<td>".$d['max']."</td>".
      "</tr>";
    }

    $res['itemid'.$item] .= "</tbody></table>";

    if (count($cons_role_uses) > 1) {
      generate_positions_strings();

      $res['itemid'.$item] .= "<div class=\"content-text\"><h1>".locale_string("items_overview_consumables_blocks_roles_header")."</h1></div>";

      $res['itemid'.$item] .= "<table id=\"items-consumables-roles-itemid$item\" class=\"list sortable\">
      <thead>
        <tr>
          <th>".locale_string("role")."</th>
          <th>".locale_string("matches")."</th>
          <th>".locale_string("item_time_mean")."</th>
          <th>".locale_string("min")."</th>
          <th>".locale_string("item_time_q1")."</th>
          <th>".locale_string("median")."</th>
          <th>".locale_string("item_time_q3")."</th>
          <th>".locale_string("max")."</th>
        </tr>
      </thead>
      <tbody>";

      foreach ($cons_role_uses['10m'] as $role => $d) {
        if (!$role) continue;
        $res['itemid'.$item] .= "<tr><td>".locale_string(ROLES_IDS[$role])."</td>".
          "<td>".$d['matches']."</td>".
          "<td>".round($d['mean'], 1)."</td>".
          "<td>".$d['min']."</td>".
          "<td>".$d['q1']."</td>".
          "<td>".$d['med']."</td>".
          "<td>".$d['q3']."</td>".
          "<td>".$d['max']."</td>".
        "</tr>";
      }

      $res['itemid'.$item] .= "</tbody></table>";
    }
  }

  return $res;
}

